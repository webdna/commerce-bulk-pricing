<?php
/**
 * Commerce Bulk Pricing plugin for Craft CMS 4.x/5.x Commerce 4.x/5.x
 *
 * Bulk pricing for products
 *
 * @link      https://webdna.co.uk
 * @copyright Copyright (c) 2022 webdna
 */

namespace webdna\commerce\bulkpricing\events;

use webdna\commerce\bulkpricing\fields\BulkPricingField;
use webdna\commerce\bulkpricing\models\PriceRow;

use craft\commerce\elements\Order;
use craft\commerce\events\LineItemEvent;

/**
 * Raised after bulk pricing has been worked out for a line item, inside
 * Commerce's populate-line-item step. The order's totals have not been
 * recalculated yet at this point, so read the line item, not the order.
 *
 * @author webdna
 * @since 3.1.0
 */
class ApplyBulkPricingEvent extends LineItemEvent
{
    /**
     * @var Order|null The order the line item belongs to
     */
    public ?Order $order = null;

    /**
     * @var bool Whether a bulk price was applied to the line item
     */
    public bool $applied = false;

    /**
     * @var PriceRow|null The row that was applied
     */
    public ?PriceRow $row = null;

    /**
     * @var BulkPricingField|null The field the applied row came from
     */
    public ?BulkPricingField $field = null;
}
