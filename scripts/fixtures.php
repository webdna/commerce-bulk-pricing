<?php
/**
 * Fixtures shared by check.php and check-web.php. Call inside an open
 * transaction: everything made here is meant to be rolled back.
 */

use webdna\commerce\bulkpricing\fields\BulkPricingField;

use craft\commerce\Plugin as Commerce;
use craft\commerce\elements\Product;
use craft\commerce\elements\Variant;
use craft\commerce\models\ProductType;
use craft\commerce\models\ProductTypeSite;
use craft\elements\User;
use craft\fieldlayoutelements\CustomField;
use craft\models\FieldLayout;
use craft\models\FieldLayoutTab;

/**
 * A variant (60.00) on an unlimited-variant product type carrying two bulk
 * pricing fields: a public one (10+ 50, 20+ 45, blank 30+, columns out of
 * order) and a group-restricted trade one (1+ 40). A second payment currency,
 * when the store has one, is priced lower (10+ 30, 20+ 25, trade 20) so a
 * check can tell which currency was used.
 *
 * @return array{variant: Variant, public: BulkPricingField, trade: BulkPricingField, member: User, outsider: User, iso: string, otherIso: ?string}
 */
function bulkPricingFixtures(): array
{
    $fail = function(string $what, $model) {
        throw new RuntimeException($what . ': ' . json_encode($model->getErrors()));
    };
    $commerce = Commerce::getInstance();
    $elements = Craft::$app->getElements();

    $iso = $commerce->getPaymentCurrencies()->getPrimaryPaymentCurrencyIso();
    $otherIso = null;
    foreach ($commerce->getPaymentCurrencies()->getAllPaymentCurrencies() as $currency) {
        if ($currency->iso !== $iso) {
            $otherIso = $currency->iso;
            break;
        }
    }

    // Discounts are order context price() cannot see (one that ignores promotions drops a sale
    // from the line item), so the install's own discounts are switched off for the check.
    Craft::$app->getDb()->createCommand()->update('{{%commerce_discounts}}', ['enabled' => false])->execute();

    // A group with a member, and a user outside it.
    $member = $outsider = $group = null;
    foreach (Craft::$app->getUserGroups()->getAllGroups() as $candidate) {
        $member = User::find()->groupId($candidate->id)->one();
        $outsider = User::find()->admin(false)->groupId(['not', $candidate->id])->one();
        if ($member && $outsider && !$outsider->isInGroup($candidate)) {
            $group = $candidate;
            break;
        }
    }
    if (!$group) {
        throw new RuntimeException('Needs a user group with a member and a non-admin user outside it');
    }

    // Columns deliberately out of order, and a blank 30+ cell (TS-1).
    $suffix = substr(md5((string)microtime(true)), 0, 6);
    $public = new BulkPricingField([
        'name' => 'BP check public', 'handle' => 'bpCheckPublic' . $suffix, 'guestUser' => true,
        'columns' => [
            'col1' => ['heading' => '20+', 'qty' => '20'],
            'col2' => ['heading' => '10+', 'qty' => '10'],
            'col3' => ['heading' => '30+', 'qty' => '30'],
        ],
    ]);
    $trade = new BulkPricingField([
        'name' => 'BP check trade', 'handle' => 'bpCheckTrade' . $suffix, 'guestUser' => true,
        'userGroups' => [(string)$group->id],
        'columns' => ['col1' => ['heading' => 'Trade', 'qty' => '1']],
    ]);
    foreach ([$public, $trade] as $field) {
        Craft::$app->getFields()->saveField($field) || $fail('field', $field);
    }

    // Unlimited variants (maxVariants null), the field on the variant (BR-11).
    $productType = new ProductType(['name' => 'BP check', 'handle' => 'bpCheck' . $suffix]);
    $siteSettings = [];
    foreach (Craft::$app->getSites()->getAllSites() as $site) {
        $siteSettings[$site->id] = new ProductTypeSite(['siteId' => $site->id, 'hasUrls' => false]);
    }
    $productType->setSiteSettings($siteSettings);
    $layout = new FieldLayout(['type' => Variant::class]);
    $tab = new FieldLayoutTab(['name' => 'Content', 'layout' => $layout]);
    $tab->setElements([new CustomField($public), new CustomField($trade)]);
    $layout->setTabs([$tab]);
    $productType->getBehavior('variantFieldLayout')->setFieldLayout($layout);
    $commerce->getProductTypes()->saveProductType($productType) || $fail('product type', $productType);

    $product = new Product(['typeId' => $productType->id, 'title' => 'BP check']);
    $elements->saveElement($product) || $fail('product', $product);
    $variant = new Variant(['title' => 'BP check', 'sku' => 'BP-CHECK-' . $suffix, 'basePrice' => 60, 'promotable' => true]);
    $variant->setPrimaryOwner($product);
    $variant->setOwner($product);
    $publicValue = [$iso => ['iso' => $iso, '20' => '45', '10' => '50', '30' => '']];
    $tradeValue = [$iso => ['iso' => $iso, '1' => '40']];
    if ($otherIso) {
        $publicValue[$otherIso] = ['iso' => $otherIso, '20' => '25', '10' => '30', '30' => ''];
        $tradeValue[$otherIso] = ['iso' => $otherIso, '1' => '20'];
    }
    $variant->setFieldValue($public->handle, $publicValue);
    $variant->setFieldValue($trade->handle, $tradeValue);
    $elements->saveElement($variant) || $fail('variant', $variant);
    $variant = Variant::find()->id($variant->id)->status(null)->one();

    return compact('variant', 'public', 'trade', 'member', 'outsider', 'iso', 'otherIso');
}
