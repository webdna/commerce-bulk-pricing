<?php
/**
 * Bulk Pricing 3.1 web check — TS-7 of docs/specs/2026-10-20-bulk-pricing-3-1.md:
 * the Twig API's defaults (currency from the session cart, else the primary;
 * the signed-in user) in a web request. Craft reads cookies once per process,
 * so each case is its own run:
 *
 *     php plugins/commerce-bulk-pricing/scripts/check-web.php cart
 *     php plugins/commerce-bulk-pricing/scripts/check-web.php nocart
 *
 * Needs a store with two payment currencies. Like check.php, everything is
 * made inside one transaction that is rolled back.
 */

use webdna\commerce\bulkpricing\BulkPricing;

use craft\commerce\Plugin as Commerce;
use craft\commerce\elements\Order;
use craft\web\View;

$mode = $argv[1] ?? 'cart';
$_SERVER += [
    'HTTP_HOST' => 'localhost', 'SERVER_NAME' => 'localhost', 'REQUEST_METHOD' => 'GET',
    'REQUEST_URI' => '/', 'SCRIPT_NAME' => '/index.php', 'SCRIPT_FILENAME' => getcwd() . '/web/index.php',
];

// Craft's web bootstrap may print; keep it out of the report.
ob_start();
require getcwd() . '/bootstrap.php';
$app = require CRAFT_VENDOR_PATH . '/craftcms/cms/bootstrap/web.php';
require __DIR__ . '/fixtures.php';
$app->getRequest()->setIsConsoleRequest(false);
$app->getUser()->enableSession = false;

$failures = 0;
$check = function(string $name, bool $ok, string $detail = '') use (&$failures) {
    fwrite(STDOUT, ($ok ? 'PASS ' : 'FAIL ') . $name . ($detail !== '' ? " — $detail" : '') . "\n");
    $failures += $ok ? 0 : 1;
};

$transaction = $app->getDb()->beginTransaction();

try {
    $app->getPlugins()->enablePlugin('commerce-bulk-pricing');
    $commerce = Commerce::getInstance();
    ['variant' => $variant, 'member' => $member, 'iso' => $iso, 'otherIso' => $otherIso] = bulkPricingFixtures();
    if (!$otherIso) {
        throw new RuntimeException('Needs a second payment currency');
    }

    $carts = $commerce->getCarts();
    if ($mode === 'cart') {
        // A saved cart in the second currency, and its number in a signed cookie, as a browser sends it.
        $cart = new Order(['number' => $carts->generateCartNumber(), 'paymentCurrency' => $otherIso]);
        $app->getElements()->saveElement($cart, false) || throw new RuntimeException('cart: ' . json_encode($cart->getErrors()));
        $name = $carts->cartCookie['name'];
        $_COOKIE[$name] = $app->getSecurity()->hashData(serialize([$name, $cart->number]), $app->getRequest()->cookieValidationKey);
    }
    $ordersBefore = Order::find()->status(null)->isCompleted(false)->count();

    $view = $app->getView();
    $view->setTemplateMode(View::TEMPLATE_MODE_SITE);
    $twig = fn(string $template) => trim($view->renderString($template, ['v' => $variant]));

    [$label, $at25, $trade, $currency] = $mode === 'cart'
        ? ['the cart\'s currency', '25', '20', $otherIso]
        : ['the primary currency', '45', '40', $iso];

    $app->getUser()->setIdentity(null);
    $check("TS-7 $mode: guest, no options → $label", $twig('{{ craft.bulkPricing.price(v, 25) }}') === $at25,
        $twig('{{ craft.bulkPricing.price(v, 25) }}') . " (expected $at25 $currency)");
    $check("TS-7 $mode: guest rows() in $label", $twig('{% for r in craft.bulkPricing.rows(v) %}{{ r.price }};{% endfor %}') === ($mode === 'cart' ? '30;25;' : '50;45;'));
    $check("TS-7 $mode: guest gets no trade row", $twig('{{ craft.bulkPricing.price(v, 1) }}') === '60');

    $app->getUser()->setIdentity($member);
    $check("TS-7 $mode: signed-in member gets the trade row", $twig('{{ craft.bulkPricing.price(v, 1) }}') === $trade,
        $twig('{{ craft.bulkPricing.price(v, 1) }}') . " (expected $trade)");

    $check("TS-7 $mode: no cart started", Order::find()->status(null)->isCompleted(false)->count() === $ordersBefore
        && ($mode === 'cart' || !$carts->getHasSessionCartNumber()));
} catch (Throwable $e) {
    $check('fixtures', false, $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
} finally {
    $transaction->rollBack();
}

fwrite(STDOUT, $failures === 0 ? "all passed\n" : "$failures failed\n");
ob_end_clean();
exit($failures === 0 ? 0 : 1);
