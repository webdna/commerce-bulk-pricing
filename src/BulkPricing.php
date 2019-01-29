<?php
/**
 * Commerce Bulk Pricing plugin for Craft CMS 3.x
 *
 * Bulk pricing for products
 *
 * @link      https://kurious.agency
 * @copyright Copyright (c) 2019 Kurious Agency
 */

namespace kuriousagency\commerce\bulkpricing;

use kuriousagency\commerce\bulkpricing\fields\BulkPricingField;

use craft\commerce\events\LineItemEvent;
use craft\commerce\services\LineItems;

use Craft;
use craft\base\Plugin;
use craft\services\Plugins;
use craft\events\PluginEvent;
use craft\services\Fields;
use craft\events\RegisterComponentTypesEvent;

use yii\base\Event;

/**
 * Class CommerceBulkPricing
 *
 * @author    Kurious Agency
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
    public static $plugin;

    // Public Properties
    // =========================================================================

    /**
     * @var string
     */
    public $schemaVersion = '1.0.0';

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function init()
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
		
		Event::on(LineItems::class, LineItems::EVENT_POPULATE_LINE_ITEM, function(LineItemEvent $event) {
			$order = $event->lineItem->getOrder();
			$paymentCurrency = $order->getPaymentCurrency();

			$element = $event->lineItem->purchasable->product->type->hasVariants ? $event->lineItem->purchasable : $event->lineItem->purchasable->product;
			foreach ($element->getFieldValues() as $key => $field)
			{
				if (get_class(Craft::$app->getFields()->getFieldByHandle($key)) == 'kuriousagency\\commerce\\bulkpricing\\fields\\BulkPricingField') {
					foreach ($field[$paymentCurrency] as $qty => $value)
					{
						if ($event->lineItem->qty >= $qty && $value != '') {
							$event->lineItem->price = $value;
						}
					}

					continue;
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

    // Protected Methods
    // =========================================================================

}
