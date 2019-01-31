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
use kuriousagency\commerce\bulkpricing\models\Settings;
use kuriousagency\commerce\bulkpricing\adjusters\Tax;

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

		Event::on(OrderAdjustments::class, OrderAdjustments::EVENT_REGISTER_ORDER_ADJUSTERS, function(RegisterComponentTypesEvent $e) {
			
			/*$types = [
				Discount3for2::class, 
				Bundles::class, 
				Trade::class, 
			];*/
			
			foreach ($e->types as $key => $type)
			{
				if ($type == 'craft\\commerce\\adjusters\\Tax') {
					array_splice($e->types, $key, 1, [
						Tax::class,
					]);
				}
				//$types[] = $type;
			}
			//$e->types = $types;
		});

		
		Event::on(LineItems::class, LineItems::EVENT_POPULATE_LINE_ITEM, function(LineItemEvent $event) {
			$order = $event->lineItem->getOrder();
			$paymentCurrency = $order->getPaymentCurrency();
			$user = $order->user;
			

			// foreach ($this->getSettings()->userGroups as $group)
			// {
			// 	if ($user->isInGroup($group)) {
			// 		$apply = true;
			// 	}
			// }

			// if ($apply) {
				$element = $event->lineItem->purchasable->product->type->hasVariants ? $event->lineItem->purchasable : $event->lineItem->purchasable->product;
				foreach ($element->getFieldValues() as $key => $field)
				{
					if (get_class($f = Craft::$app->getFields()->getFieldByHandle($key)) == 'kuriousagency\\commerce\\bulkpricing\\fields\\BulkPricingField') {
						$apply = false;
						foreach ($f->userGroups as $group)
						{
							if ($user->isInGroup($group)) {
								$apply = true;
							}
						}
						if ($apply) {
							foreach ($field[$paymentCurrency] as $qty => $value)
							{
								if ($qty != 'iso' && $event->lineItem->qty >= $qty && $value != '') {
									$event->lineItem->price = $value;
									$event->lineItem->snapshot['taxIncluded'] = (bool)$f->taxIncluded;
									//Craft::dd($event->lineItem);
								}
							}

							continue;
						}
					}
				}
				//Craft::dd($event->lineItem);
			// }

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

    /**
     * @inheritdoc
     */
    /*protected function createSettingsModel()
    {
        return new Settings();
    }*/

    /**
     * @inheritdoc
     */
    /*protected function settingsHtml(): string
    {
        return Craft::$app->view->renderTemplate(
            'commerce-bulk-pricing/settings',
            [
                'settings' => $this->getSettings()
            ]
        );
    }*/

}
