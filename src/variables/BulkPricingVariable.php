<?php
/**
 * Commerce Bulk Pricing plugin for Craft CMS 4.x/5.x Commerce 4.x/5.x
 *
 * Bulk pricing for products
 *
 * @link      https://webdna.co.uk
 * @copyright Copyright (c) 2022 webdna
 */

namespace webdna\commerce\bulkpricing\variables;

use webdna\commerce\bulkpricing\BulkPricing;
use webdna\commerce\bulkpricing\models\PriceRow;

use craft\commerce\base\PurchasableInterface;

/**
 * `craft.bulkPricing` in Twig.
 *
 * Options for both methods: `currency` (payment currency ISO; defaults to the
 * cart's, else the primary) and `user` (defaults to the signed-in user; null
 * for a guest). `rows()` also takes `field`, a bulk pricing field handle.
 *
 * @author webdna
 * @since 3.1.0
 */
class BulkPricingVariable
{
    /**
     * The unit price the cart charges for this purchasable at this quantity,
     * sales included.
     *
     * {{ craft.bulkPricing.price(variant, 25)|commerceCurrency(cart.paymentCurrency) }}
     */
    public function price(?PurchasableInterface $purchasable, int $qty = 1, array $options = []): ?float
    {
        if (!$purchasable) {
            return null;
        }

        return BulkPricing::$plugin->service->getPrice(
            $purchasable,
            $qty,
            array_key_exists('user', $options) ? $options['user'] : false,
            $options['currency'] ?? null
        );
    }

    /**
     * The price breaks for this purchasable, lowest quantity first.
     *
     * {% for row in craft.bulkPricing.rows(variant) %}
     *     {{ row.heading }}: {{ row.salePrice|commerceCurrency(cart.paymentCurrency) }}
     * {% endfor %}
     *
     * @return PriceRow[]
     */
    public function rows(?PurchasableInterface $purchasable, array $options = []): array
    {
        if (!$purchasable) {
            return [];
        }

        return BulkPricing::$plugin->service->getPriceRows(
            $purchasable,
            array_key_exists('user', $options) ? $options['user'] : false,
            $options['currency'] ?? null,
            $options['field'] ?? null
        );
    }
}
