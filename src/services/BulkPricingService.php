<?php
/**
 * Commerce Bulk Pricing plugin for Craft CMS 4.x/5.x Commerce 4.x/5.x
 *
 * Bulk pricing for products
 *
 * @link      https://webdna.co.uk
 * @copyright Copyright (c) 2022 webdna
 */

namespace webdna\commerce\bulkpricing\services;

use webdna\commerce\bulkpricing\events\ApplyBulkPricingEvent;
use webdna\commerce\bulkpricing\fields\BulkPricingField;
use webdna\commerce\bulkpricing\models\PriceRow;

use Craft;
use craft\elements\User;

use craft\commerce\Plugin as Commerce;
use craft\commerce\base\PurchasableInterface;
use craft\commerce\models\LineItem;
use craft\commerce\models\Sale;
use craft\commerce\records\Sale as SaleRecord;

use yii\base\Component;

/**
 * Bulk Pricing service.
 *
 * @author webdna
 * @since 2.0
 */
class BulkPricingService extends Component
{
    /**
     * @event ApplyBulkPricingEvent The event that is triggered after bulk pricing has been applied to a line item.
     * It fires for every populated line item; `applied` says whether a bulk price was set.
     */
    public const EVENT_APPLY_BULK_PRICING = 'applyBulkPricing';

    /**
     * Calculate appropriate bulk price for lineItem
     *
     * @param LineItem $lineItem The line item to to calculate bulk price for.
     * @return LineItem
     */
    public function applyBulkPricing(LineItem $lineItem, ?User $user, string $paymentCurrency): LineItem
    {
        $purchasable = $lineItem->getPurchasable();
        $match = $purchasable ? $this->matchRow($purchasable, (int)$lineItem->qty, $user, $paymentCurrency) : null;

        if ($match) {
            [$field, $row] = $match;
            $lineItem->price = $row->price;
            $this->setEffectivePrice($lineItem, $row->salePrice);
            $this->flagTaxIncluded($lineItem, (bool)$field->taxIncluded);
        }

        if ($this->hasEventHandlers(self::EVENT_APPLY_BULK_PRICING)) {
            $this->trigger(self::EVENT_APPLY_BULK_PRICING, new ApplyBulkPricingEvent([
                'lineItem' => $lineItem,
                'isNew' => false,
                'order' => $lineItem->getOrder(),
                'applied' => $match !== null,
                'row' => $match[1] ?? null,
                'field' => $match[0] ?? null,
            ]));
        }

        return $lineItem;
    }

    /**
     * The unit price the cart charges for a purchasable at a quantity: the
     * applying bulk row's price after sales, or the purchasable's own sale
     * price when no row applies.
     *
     * @param string|null $currency Payment currency ISO; defaults to the session cart's, else the primary
     * @param User|null|false $user Defaults to the signed-in user; null is a guest
     */
    public function getPrice(PurchasableInterface $purchasable, int $qty = 1, User|null|false $user = false, ?string $currency = null): float
    {
        $match = $this->matchRow($purchasable, $qty, $this->resolveUser($user), $currency ?? $this->defaultCurrency());

        if ($match) {
            $row = $match[1];
            // Commerce 5 only charges the promotional price when it is lower (see setEffectivePrice()).
            return method_exists(LineItem::class, 'setPromotionalPrice') ? min($row->price, $row->salePrice) : $row->salePrice;
        }

        return (float)$purchasable->getSalePrice();
    }

    /**
     * The price breaks of the bulk pricing field that governs a purchasable,
     * lowest quantity first.
     *
     * @param string|null $fieldHandle A particular bulk pricing field; defaults to the last eligible one with rows
     * @return PriceRow[]
     */
    public function getPriceRows(PurchasableInterface $purchasable, User|null|false $user = false, ?string $currency = null, ?string $fieldHandle = null): array
    {
        $fields = $this->eligibleFields($purchasable, $this->resolveUser($user), $currency ?? $this->defaultCurrency());

        if ($fieldHandle !== null) {
            foreach ($fields as [$field, $rows]) {
                if ($field->handle === $fieldHandle) {
                    return $rows;
                }
            }
            return [];
        }

        $rows = [];
        foreach ($fields as [, $fieldRows]) {
            if ($fieldRows) {
                $rows = $fieldRows;
            }
        }
        return $rows;
    }

    /**
     * The row that applies at a quantity, and its field. Of several eligible
     * fields, the last with an applying row wins, as the line item has always done.
     *
     * @return array{0: BulkPricingField, 1: PriceRow}|null
     */
    private function matchRow(PurchasableInterface $purchasable, int $qty, ?User $user, string $currency): ?array
    {
        $match = null;

        foreach ($this->eligibleFields($purchasable, $user, $currency) as [$field, $rows]) {
            foreach ($rows as $row) {
                if ($qty >= $row->qty) {
                    $match = [$field, $row];
                }
            }
        }

        return $match;
    }

    /**
     * The bulk pricing fields this user may use for this purchasable in this
     * currency, in layout order, each with its rows.
     *
     * @return array<array{0: BulkPricingField, 1: PriceRow[]}>
     */
    private function eligibleFields(PurchasableInterface $purchasable, ?User $user, string $currency): array
    {
        // The bulk pricing fields live on the variant when its layout has one,
        // otherwise on the product. This is not read from the product type's
        // variant setting: Commerce 5's default, unlimited variants
        // (maxVariants null), counts as "no variants" there.
        $element = null;
        $bulkFields = [];
        foreach ([$purchasable, $purchasable->product ?? null] as $candidate) {
            $fieldLayout = $candidate ? $candidate->getFieldLayout() : null;
            $bulkFields = $fieldLayout
                ? array_filter($fieldLayout->getCustomFields(), fn($field) => $field instanceof BulkPricingField)
                : [];
            if ($bulkFields) {
                $element = $candidate;
                break;
            }
        }

        $fields = [];
        foreach ($bulkFields as $field) {
            if (!$this->userMayUse($field, $user)) {
                continue;
            }

            $value = $element->getFieldValue($field->handle);
            if (!is_array($value) || !isset($value[$currency]) || !is_array($value[$currency])) {
                continue;
            }

            $fields[] = [$field, $this->rowsFor($field, $value[$currency], $purchasable)];
        }

        return $fields;
    }

    private function userMayUse(BulkPricingField $field, ?User $user): bool
    {
        if (!$user && !$field->guestUser) {
            return false;
        }

        if (!is_array($field->userGroups) || count($field->userGroups) === 0) {
            return true;
        }

        if (!$user) {
            return false;
        }

        foreach ($field->userGroups as $group) {
            if ($user->isInGroup($group)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array $prices One currency's cells, `qty => price` plus `iso`
     * @return PriceRow[]
     */
    private function rowsFor(BulkPricingField $field, array $prices, PurchasableInterface $purchasable): array
    {
        $headings = [];
        foreach ($field->columns as $column) {
            if (isset($column['qty'])) {
                $headings[(string)$column['qty']] = $column['heading'] ?? '';
            }
        }

        $rows = [];
        foreach ($prices as $qty => $price) {
            if ($qty === 'iso' || !is_numeric($qty) || $price === null || (string)$price === '') {
                continue;
            }

            $heading = $headings[(string)$qty] ?? '';
            $rows[] = new PriceRow([
                'heading' => $heading !== '' ? Craft::t('site', $heading) : '',
                'qty' => (int)$qty,
                'price' => (float)$price,
                'salePrice' => $this->applySales($purchasable, (float)$price),
            ]);
        }

        usort($rows, fn(PriceRow $a, PriceRow $b) => $a->qty <=> $b->qty);

        return $rows;
    }

    /**
     * A bulk price after the purchasable's sales, by the same arithmetic as
     * Commerce's own sale calculation.
     */
    private function applySales(PurchasableInterface $purchasable, float $originalPrice): float
    {
        $sales = $purchasable->getSales();
        if (!$sales) {
            return $originalPrice;
        }

        $takeOffAmount = 0;
        $newPrice = null;

        /** @var Sale $sale */
        foreach ($sales as $sale) {

            switch ($sale->apply) {
                case SaleRecord::APPLY_BY_PERCENT:
                    // applyAmount is stored as a negative already
                    $takeOffAmount += ($sale->applyAmount * $originalPrice);

                    if ($sale->ignorePrevious) {
                        $newPrice = $originalPrice + ($sale->applyAmount * $originalPrice);
                    }
                    break;
                case SaleRecord::APPLY_TO_PERCENT:
                    // applyAmount needs to be reversed since it is stored as negative
                    $newPrice = (-$sale->applyAmount * $originalPrice);
                    break;
                case SaleRecord::APPLY_BY_FLAT:
                    // applyAmount is stored as a negative already
                    $takeOffAmount += $sale->applyAmount;
                    if ($sale->ignorePrevious) {
                        // applyAmount is always negative so add the negative amount to the original price for the new price.
                        $newPrice = $originalPrice + $sale->applyAmount;
                    }
                    break;
                case SaleRecord::APPLY_TO_FLAT:
                    // applyAmount needs to be reversed since it is stored as negative
                    $newPrice = -$sale->applyAmount;
                    break;
            }

            // If the stop processing flag is true, it must been the last
            // since the sales for this purchasable would have returned it last.
            if ($sale->stopProcessing) {
                break;
            }
        }

        $salePrice = ($originalPrice + $takeOffAmount);

        // A newPrice has been set so use it.
        if (null !== $newPrice) {
            $salePrice = $newPrice;
        }

        if ($salePrice < 0) {
            $salePrice = 0;
        }

        return (float)$salePrice;
    }

    /**
     * `false` means "not given": the signed-in user. `null` is a guest.
     */
    private function resolveUser(User|null|false $user): ?User
    {
        if ($user !== false) {
            return $user;
        }

        $identity = Craft::$app->getUser()->getIdentity();
        return $identity instanceof User ? $identity : null;
    }

    /**
     * The session cart's payment currency when there is a session cart, else
     * the primary payment currency. Never starts a cart.
     */
    private function defaultCurrency(): string
    {
        $commerce = Commerce::getInstance();

        if (!Craft::$app->getRequest()->getIsConsoleRequest()) {
            $carts = $commerce->getCarts();
            if ($carts->getHasSessionCartNumber()) {
                return $carts->getCart()->getPaymentCurrency();
            }
        }

        return $commerce->getPaymentCurrencies()->getPrimaryPaymentCurrencyIso();
    }

    /**
     * Set the effective (discounted) unit price on a line item, working across
     * Commerce 4 and Commerce 5.
     *
     * Commerce 4 exposes a settable `salePrice`. Commerce 5 made `salePrice` a
     * read-only getter derived from `promotionalPrice`, so we set that instead —
     * but only when the effective price is actually lower than the (already
     * overridden) base price. That mirrors Commerce 4's on-sale semantics and
     * clears any catalog-pricing promotional price that would otherwise leak
     * through on top of the bulk price.
     *
     * @param float|int|string $effective The effective price to charge per unit.
     */
    private function setEffectivePrice(LineItem $lineItem, float|int|string $effective): void
    {
        if (method_exists($lineItem, 'setPromotionalPrice')) {
            // Commerce 5+: salePrice is read-only and derived from promotionalPrice.
            $lineItem->setPromotionalPrice((float)$effective < $lineItem->getPrice() ? (float)$effective : null);
        } else {
            // Commerce 4: salePrice is a settable property.
            $lineItem->salePrice = strval($effective);
        }
    }

    /**
     * Record the tax-included flag on the line item snapshot, working across
     * Commerce 4 and Commerce 5.
     *
     * Commerce 4 exposes `snapshot` as a public array. Commerce 5 made it a
     * private property behind getSnapshot()/setSnapshot(), so writing to an
     * array index directly no longer persists — merge and set the array back.
     */
    private function flagTaxIncluded(LineItem $lineItem, bool $taxIncluded): void
    {
        if (method_exists($lineItem, 'setSnapshot')) {
            // Commerce 5+
            $snapshot = $lineItem->getSnapshot() ?? [];
            $snapshot['taxIncluded'] = $taxIncluded;
            $lineItem->setSnapshot($snapshot);
        } else {
            // Commerce 4
            $lineItem->snapshot['taxIncluded'] = $taxIncluded;
        }
    }

}
