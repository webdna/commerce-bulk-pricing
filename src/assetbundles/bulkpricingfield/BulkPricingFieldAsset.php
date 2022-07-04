<?php
/**
 * Commerce Bulk Pricing plugin for Craft CMS 3.x
 *
 * Bulk pricing for products
 *
 * @link      https://webdna.co.uk
 * @copyright Copyright (c) 2019 webdna
 */

namespace webdna\commerce\bulkpricing\assetbundles\bulkpricingfield;

use Craft;
use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

/**
 * @author    webdna
 * @package   CommerceBulkPricing
 * @since     1.0.0
 */
class BulkPricingFieldAsset extends AssetBundle
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function init()
    {
        $this->sourcePath = "@webdna/commerce-bulk-pricing/assetbundles/bulkpricingfield/dist";

        $this->depends = [
            CpAsset::class,
        ];

        $this->js = [
            'js/BulkPricingField.js',
        ];

        $this->css = [
            'css/BulkPricingField.css',
        ];

        parent::init();
    }
}
