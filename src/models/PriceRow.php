<?php
/**
 * Commerce Bulk Pricing plugin for Craft CMS 4.x/5.x Commerce 4.x/5.x
 *
 * Bulk pricing for products
 *
 * @link      https://webdna.co.uk
 * @copyright Copyright (c) 2022 webdna
 */

namespace webdna\commerce\bulkpricing\models;

use craft\base\Model;

/**
 * One price break of a bulk pricing field, for one currency.
 *
 * @author webdna
 * @since 3.1.0
 */
class PriceRow extends Model
{
    /**
     * @var string The column heading, translated (e.g. "10+")
     */
    public string $heading = '';

    /**
     * @var int The quantity from which this row applies
     */
    public int $qty = 0;

    /**
     * @var float The row's unit price
     */
    public float $price = 0.0;

    /**
     * @var float The row's unit price after the purchasable's sales
     */
    public float $salePrice = 0.0;
}
