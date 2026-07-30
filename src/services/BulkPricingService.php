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

use webdna\commerce\bulkpricing\fields\BulkPricingField;

use Craft;
use craft\elements\User;

use craft\commerce\models\LineItem;
use craft\commerce\records\Sale as SaleRecord;
use craft\commerce\events\LineItemEvent;

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
     * @event LineItemEvent The event that is triggered after bulk pricing has been applied to lineitem
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
        // The bulk pricing field lives on the variant when the product type has
        // variants, otherwise on the product itself. Commerce 5 deprecated
        // ProductType::hasVariants in favour of `maxVariants > 1`, so detect the
        // property to stay quiet (no deprecation log) on both major versions.
        $productType = $lineItem->purchasable->product->type ?? null;
        $hasVariants = $productType && (property_exists($productType, 'maxVariants')
            ? $productType->maxVariants > 1
            : !empty($productType->hasVariants));
        $element = $hasVariants ? $lineItem->purchasable : $lineItem->purchasable->product;
        if ($element) {
            foreach ($element->getFieldValues() as $key => $field)
            {
                if ( (get_class($f = Craft::$app->getFields()->getFieldByHandle($key)) == BulkPricingField::class) && (is_array($field)) ) {
                    $apply = false;

                    if($user || $f->guestUser){

                        if(is_array($f->userGroups) && count($f->userGroups) > 0) {
                            foreach ($f->userGroups as $group)
                            {
                                if ($user->isInGroup($group)) {
                                    $apply = true;
                                }
                            }
                        } else {
                            $apply = true;
                        }

                        if ($apply && (array_key_exists($paymentCurrency,$field))) {

                            foreach ($field[$paymentCurrency] as $qty => $value)
                            {
                                if ($qty != 'iso' && $lineItem->qty >= $qty && $value != '') {
                                    $lineItem->price = $value;
                                    if ($lineItem->purchasable->getSales()) {
                                        $originalPrice = $value;
                                        $takeOffAmount = 0;
                                        $newPrice = null;

                                        /** @var Sale $sale */
                                        foreach ($lineItem->purchasable->getSales() as $sale) {

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

                                        $this->setEffectivePrice($lineItem, $salePrice);
                                    } else {
                                        $this->setEffectivePrice($lineItem, $value);
                                    }

                                    $this->flagTaxIncluded($lineItem, (bool)$f->taxIncluded);
                                }
                            }

                            continue;
                        }
                    }
                }
            }

            if ($this->hasEventHandlers(self::EVENT_APPLY_BULK_PRICING)) {
                $this->trigger(self::EVENT_APPLY_BULK_PRICING, new LineItemEvent([
                    'lineItem' => $lineItem,
                    'isNew' => false,
                ]));
            }

            return $lineItem;
        }
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
