<?php
/**
 * Commerce Bulk Pricing plugin for Craft CMS 3.x
 *
 * Bulk pricing for products
 *
 * @link      https://webdna.co.uk
 * @copyright Copyright (c) 2019 webdna
 */

namespace webdna\commerce\bulkpricing;

use webdna\commerce\bulkpricing\fields\BulkPricingField;
use webdna\commerce\bulkpricing\models\Settings;
use webdna\commerce\bulkpricing\adjusters\Tax;
use webdna\commerce\bulkpricing\integrations\feedme\BulkPricingField as FeedMeBulkPricing;

use craft\commerce\events\LineItemEvent;
use craft\commerce\services\LineItems;
use craft\commerce\services\OrderAdjustments;
use craft\commerce\events\DiscountAdjustmentsEvent;

use Craft;
use craft\base\Plugin;
use craft\services\Plugins;
use craft\events\PluginEvent;
use craft\services\Fields;
use craft\events\RegisterComponentTypesEvent;
use craft\commerce\records\Sale as SaleRecord;
// use craft\commerce\models\Sale;

use craft\feedme\events\RegisterFeedMeFieldsEvent;
use craft\feedme\services\Fields as FeedMeFields;

use yii\base\Event;

/**
 * Class CommerceBulkPricing
 *
 * @author    webdna
 * @package   CommerceBulkPricing
 * @since     1.0.0
 *
 */
class BulkPricing extends Plugin
{
    // Static Properties
    // =========================================================================

    /**
     * @var BulkPricing
     */
    public static Plugin $plugin;

    // Public Properties
    // =========================================================================

    /**
     * @var string
     */
    public string $schemaVersion = '1.0.0';

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function init(): void
    {
        parent::init();
        self::$plugin = $this;

        Event::on(
            Fields::class,
            Fields::EVENT_REGISTER_FIELD_TYPES,
            function (RegisterComponentTypesEvent $event) {
                $event->types[] = BulkPricingField::class;
            }
        );


        Event::on(
            Plugins::class,
            Plugins::EVENT_AFTER_INSTALL_PLUGIN,
            function (PluginEvent $event) {
                if ($event->plugin === $this) {
                }
            }
        );


        Event::on(OrderAdjustments::class, OrderAdjustments::EVENT_REGISTER_ORDER_ADJUSTERS, function(RegisterComponentTypesEvent $e) {

            foreach ($e->types as $key => $type)
            {
                if ($type == 'craft\\commerce\\adjusters\\Tax') {
                    array_splice($e->types, $key, 1, [
                        Tax::class,
                    ]);
                }
            }
        });


        Event::on(FeedMeFields::class, FeedMeFields::EVENT_REGISTER_FEED_ME_FIELDS, function(RegisterFeedMeFieldsEvent $e) {
            $e->fields[] = FeedMeBulkPricing::class;
        });


        Event::on(LineItems::class, LineItems::EVENT_POPULATE_LINE_ITEM, function(LineItemEvent $event) {
            $order = $event->lineItem->getOrder();
            $paymentCurrency = $order->getPaymentCurrency();
            $user = $order->customer;

            $element = (isset($event->lineItem->purchasable->product->type->hasVariants) && $event->lineItem->purchasable->product->type->hasVariants) ? $event->lineItem->purchasable : $event->lineItem->purchasable->product;
            if ($element) {
                foreach ($element->getFieldValues() as $key => $field)
                {
                    if ( (get_class($f = Craft::$app->getFields()->getFieldByHandle($key)) == 'webdna\\commerce\\bulkpricing\\fields\\BulkPricingField') && (is_array($field)) ) {
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
                                    if ($qty != 'iso' && $event->lineItem->qty >= $qty && $value != '') {
                                        $event->lineItem->price = $value;

                                        if ($event->lineItem->purchasable->getSales()) {
                                            $originalPrice = $value;
                                            $takeOffAmount = 0;
                                            $newPrice = null;

                                            /** @var Sale $sale */
                                            foreach ($event->lineItem->purchasable->getSales() as $sale) {

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

                                            $event->lineItem->salePrice = strval($salePrice);
                                        } else {
                                            $event->lineItem->salePrice = strval($value);
                                        }

                                        $event->lineItem->snapshot['taxIncluded'] = (bool)$f->taxIncluded;
                                    }
                                }

                                continue;
                            }
                        }
                    }
                }
            }

        });

        Craft::info(
            Craft::t(
                'commerce-bulk-pricing',
                '{name} plugin loaded',
                ['name' => $this->name]
            ),
            __METHOD__
        );
    }

}
