<?php
/**
 * Bulk Pricing 3.1 check — TS-1 to TS-6 of docs/specs/2026-10-20-bulk-pricing-3-1.md.
 *
 * Run from the root of a Craft 5 + Commerce 5 project with the plugin installed,
 * at least one user group with a member and one user outside it:
 *
 *     php plugins/commerce-bulk-pricing/scripts/check.php
 *
 * Everything it creates (fields, a product type, a product, a sale) is made
 * inside one database transaction that is rolled back at the end — enabling
 * the plugin too, when it is installed but disabled.
 */

use webdna\commerce\bulkpricing\BulkPricing;
use webdna\commerce\bulkpricing\events\ApplyBulkPricingEvent;
use webdna\commerce\bulkpricing\fields\BulkPricingField;
use webdna\commerce\bulkpricing\services\BulkPricingService;

use craft\commerce\Plugin as Commerce;
use craft\commerce\elements\Order;
use craft\commerce\elements\Product;
use craft\commerce\elements\Variant;
use craft\commerce\models\ProductType;
use craft\commerce\models\ProductTypeSite;
use craft\commerce\models\Sale;
use craft\elements\User;
use craft\fieldlayoutelements\CustomField;
use craft\models\FieldLayout;
use craft\models\FieldLayoutTab;
use craft\web\View;
use yii\base\Event;

require getcwd() . '/bootstrap.php';
$app = require CRAFT_VENDOR_PATH . '/craftcms/cms/bootstrap/console.php';

$failures = 0;
$check = function(string $name, bool $ok, string $detail = '') use (&$failures) {
    echo ($ok ? 'PASS ' : 'FAIL ') . $name . ($detail !== '' ? " — $detail" : '') . "\n";
    $failures += $ok ? 0 : 1;
};
$fail = function(string $what, $model) {
    throw new RuntimeException($what . ': ' . json_encode($model->getErrors()));
};

$transaction = Craft::$app->getDb()->beginTransaction();
Craft::$app->getPlugins()->enablePlugin('commerce-bulk-pricing');

$commerce = Commerce::getInstance();
$service = BulkPricing::$plugin->service;
$elements = Craft::$app->getElements();
$view = Craft::$app->getView();
$view->setTemplateMode(View::TEMPLATE_MODE_SITE);
$twig = fn(string $template, array $vars = []) => trim($view->renderString($template, $vars));

try {
    $iso = $commerce->getPaymentCurrencies()->getPrimaryPaymentCurrencyIso();

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
    $variant->setFieldValue($public->handle, [$iso => ['iso' => $iso, '20' => '45', '10' => '50', '30' => '']]);
    $variant->setFieldValue($trade->handle, [$iso => ['iso' => $iso, '1' => '40']]);
    $elements->saveElement($variant) || $fail('variant', $variant);
    $variant = Variant::find()->id($variant->id)->status(null)->one();

    // TS-1: rows sorted, the blank cell skipped, headings carried.
    $rows = $service->getPriceRows($variant, null, $iso);
    $check('TS-1 rows', array_map(fn($r) => "{$r->heading}={$r->qty}@{$r->price}", $rows) === ['10+=10@50', '20+=20@45'],
        json_encode(array_map(fn($r) => $r->toArray(), $rows)));
    $check('TS-1 rows via Twig',
        $twig('{% for r in craft.bulkPricing.rows(v, {user: null, currency: iso}) %}{{ r.heading }}:{{ r.qty }}:{{ r.price }};{% endfor %}', ['v' => $variant, 'iso' => $iso]) === '10+:10:50;20+:20:45;');

    // TS-2: price at quantity, as a guest (only the public field applies).
    $normal = (float)$variant->getSalePrice();
    $prices = array_map(fn($q) => $service->getPrice($variant, $q, null, $iso), [1, 10, 25]);
    $check('TS-2 price at 1/10/25', $prices === [$normal, 50.0, 45.0], json_encode($prices));
    $check('TS-2 price via Twig', $twig('{{ craft.bulkPricing.price(v, 25, {user: null, currency: iso}) }}', ['v' => $variant, 'iso' => $iso]) === '45');

    // TS-4: the trade field is the member's; a non-member and a guest never get it (BR-6).
    $check('TS-4 member gets the trade row at 1', $service->getPrice($variant, 1, $member, $iso) === 40.0);
    $check('TS-4 member: last field with an applying row wins at 25', $service->getPrice($variant, 25, $member, $iso) === 40.0);
    $check('TS-4 member rows() = trade field', array_map(fn($r) => $r->heading, $service->getPriceRows($variant, $member, $iso)) === ['Trade']);
    $check('TS-4 options.field picks a field', count($service->getPriceRows($variant, $member, $iso, $public->handle)) === 2);
    $check('TS-4 outsider gets no trade row', $service->getPrice($variant, 1, $outsider, $iso) === $normal);
    $check('TS-4 guest against a group field does not crash', $service->getPrice($variant, 1, null, $iso) === $normal);

    // TS-5: a currency the value lacks.
    $check('TS-5 unknown currency', $service->getPriceRows($variant, null, 'XXX') === [] && $service->getPrice($variant, 25, null, 'XXX') === $normal);

    // TS-3: a 10% sale on everything.
    $sale = new Sale([
        'name' => 'BP check 10%', 'apply' => 'byPercent', 'applyAmount' => -0.1,
        'allPurchasables' => true, 'allCategories' => true, 'allGroups' => true, 'enabled' => true,
    ]);
    $commerce->getSales()->saveSale($sale) || $fail('sale', $sale);
    $variant = Variant::find()->id($variant->id)->status(null)->one();
    $rows = $service->getPriceRows($variant, null, $iso);
    $check('TS-3 rows carry the sale price', array_map(fn($r) => $r->salePrice, $rows) === [45.0, 40.5], json_encode(array_map(fn($r) => $r->salePrice, $rows)));
    $check('TS-3 price at 25 with the sale', $service->getPrice($variant, 25, null, $iso) === 40.5);

    // TS-3 + TS-6: a real line item charges the same, and the event reports it.
    // Commerce populates a new line item twice (create, then refresh), so two events each.
    $events = [];
    Event::on(BulkPricingService::class, BulkPricingService::EVENT_APPLY_BULK_PRICING, function(ApplyBulkPricingEvent $e) use (&$events) {
        $events[] = $e;
    });
    foreach ([25 => null, 1 => $outsider] as $qty => $customer) {
        $order = new Order(['paymentCurrency' => $iso]);
        if ($customer) {
            $order->setCustomer($customer);
        }
        $lineItem = $commerce->getLineItems()->createLineItem($order, $variant->id, [], $qty);
        $expected = $service->getPrice($variant, $qty, $customer, $iso);
        $check("TS-3 line item at $qty charges price()", (float)$lineItem->getSalePrice() === $expected,
            "line item {$lineItem->getSalePrice()}, price() $expected");
    }
    $check('TS-6 one event per populate', count($events) === 4, (string)count($events));
    $applied = $events[0] ?? null;
    $check('TS-6 applied event carries row, field, order',
        $applied && $applied->applied && $applied->row?->qty === 20 && $applied->field?->handle === $public->handle && $applied->order instanceof Order && !$applied->isNew);
    $notApplied = $events[3] ?? null;
    $check('TS-6 no row: applied false', $notApplied && !$notApplied->applied && $notApplied->row === null && $notApplied->field === null);
} catch (Throwable $e) {
    $check('fixtures', false, $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
} finally {
    $transaction->rollBack();
}

echo $failures === 0 ? "all passed\n" : "$failures failed\n";
exit($failures === 0 ? 0 : 1);
