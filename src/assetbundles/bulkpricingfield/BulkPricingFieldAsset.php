<?php
/**
 * Commerce Bulk Pricing plugin for Craft CMS 3.x
 *
 * Bulk pricing for products
 *
 * @link      https://kurious.agency
 * @copyright Copyright (c) 2019 Kurious Agency
 */

namespace kuriousagency\commerce\bulkpricing\assetbundles\bulkpricingfield;

use Craft;
use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

/**
 * @author    Kurious Agency
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
        $this->sourcePath = "@kuriousagency/commerce-bulk-pricing/assetbundles/bulkpricingfield/dist";

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
