<?php
/**
 * Commerce Bulk Pricing plugin for Craft CMS 4.x Commerce 4.x
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
        $element = (isset($lineItem->purchasable->product->type->hasVariants) && $lineItem->purchasable->product->type->hasVariants) ? $lineItem->purchasable : $lineItem->purchasable->product;
        if ($element) {
            foreach ($element->getFieldValues() as $key => $field)
            {
                if ( (get_class($f = Craft::$app->getFields()->getFieldByHandle($key)) == BulkPricingField::class) && (is_array($field)) ) {
                    $apply = false;
                    
                    if($user || $f->guestUser){

                        if(is_array($f->userGroups)) {
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

                                        $lineItem->salePrice = strval($salePrice);
                                    } else {
                                        $lineItem->salePrice = strval($value);
                                    }

                                    $lineItem->snapshot['taxIncluded'] = (bool)$f->taxIncluded;
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

}
