<?php
/**
 * Commerce Bulk Pricing plugin for Craft CMS 4.x Commerce 4.x
 *
 * Bulk pricing for products
 *
 * @link      https://webdna.co.uk
 * @copyright Copyright (c) 2022 webdna
 */

namespace webdna\commerce\bulkpricing;

use webdna\commerce\bulkpricing\fields\BulkPricingField;
use webdna\commerce\bulkpricing\services\BulkPricingService;
use webdna\commerce\bulkpricing\models\Settings;
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
use craft\feedme\events\RegisterFeedMeNestedFieldsEvent;
use craft\feedme\services\Fields as FeedMeFields;
use craft\feedme\elements\CommerceProduct as FeedMeCommerceProduct;

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
    public static function config(): array
    {
        return [
            'components' => [
                'service' => ['class' => BulkPricingService::class],
            ],
        ];
    }
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

        if (Craft::$app->getPlugins()->isPluginEnabled('feed-me')) {
            Event::on(FeedMeFields::class, FeedMeFields::EVENT_REGISTER_FEED_ME_FIELDS, function(RegisterFeedMeFieldsEvent $e) {
                $e->fields[] = FeedMeBulkPricing::class;
            });
            Event::on(FeedMeCommerceProduct::class, FeedMeCommerceProduct::EVENT_REGISTER_FEED_ME_NESTED_FIELDS, function(RegisterFeedMeNestedFieldsEvent $e) {
                $e->nestedFields[] = BulkPricingField::class;
            });
        }


        Event::on(LineItems::class, LineItems::EVENT_POPULATE_LINE_ITEM, function(LineItemEvent $event) {
            $order = $event->lineItem->getOrder();
            $paymentCurrency = $order->getPaymentCurrency();
            $user = $order->customer;
            $lineItem = $event->lineItem;

            $event->lineItem = $this->service->applyBulkPricing($lineItem, $user, $paymentCurrency);

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
