# Free Shipping Teaser Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a PrestaShop 9 module (`ps_freeshippingteaser`) that shows a real-time AJAX-updated free shipping progress teaser with progress bar in cart areas.

**Architecture:** Hook-only module (no core overrides). A pure `TeaserBuilder` service handles all math and text formatting. A thin `ThresholdFinder` reads from PS carrier rules or module config override. Hook handlers render a shared Smarty template; a front-end JS listener updates all teaser instances on `prestashop.on('updateCart')` via a single AJAX call.

**Tech Stack:** PHP 8.1+, PrestaShop 9, Smarty 4, Vanilla JS (ES5-compatible), PHPUnit 10 (dev), Composer

---

## File Map

| File | Purpose |
|---|---|
| `ps_freeshippingteaser.php` | Main module class — install, hooks, admin config |
| `src/TeaserBuilder.php` | Pure service: computes remaining amount, percent, qualified flag, formats text tokens |
| `src/ThresholdFinder.php` | PS-aware: reads manual config override or queries carrier range tables |
| `controllers/front/ajax.php` | AJAX endpoint — validates token, returns teaser JSON |
| `views/templates/hook/teaser.tpl` | Smarty template for all three hook positions |
| `views/css/freeshippingteaser.css` | Progress bar + teaser/success styles |
| `views/js/freeshippingteaser.js` | Listens to PS `updateCart` event, POSTs to AJAX endpoint, re-renders all teasers |
| `docs/free-shipping-setup.md` | End-user instructions for configuring free shipping in PS9 |
| `tests/TeaserBuilderTest.php` | PHPUnit unit tests for TeaserBuilder |
| `tests/bootstrap.php` | PHPUnit bootstrap (loads composer autoloader) |
| `phpunit.xml.dist` | PHPUnit configuration |
| `composer.json` | Autoloading + phpunit dev dependency |

---

## Task 1: Project Scaffold

**Files:**
- Create: `composer.json`
- Create: `phpunit.xml.dist`
- Create: `tests/bootstrap.php`
- Create: `logo.png` (placeholder)
- Create all source directories

- [ ] **Step 1: Create all directories**

```bash
mkdir -p src tests controllers/front views/templates/hook views/templates/admin views/js views/css docs
```

- [ ] **Step 2: Create `composer.json`**

```json
{
    "name": "prestashop/ps_freeshippingteaser",
    "description": "Free shipping progress teaser for PrestaShop 9",
    "type": "prestashop-module",
    "license": "AFL-3.0",
    "require": {
        "php": ">=8.1"
    },
    "require-dev": {
        "phpunit/phpunit": "^10"
    },
    "autoload": {
        "psr-4": {
            "PrestaShop\\Module\\FreeShippingTeaser\\": "src/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "PrestaShop\\Module\\FreeShippingTeaser\\Tests\\": "tests/"
        }
    }
}
```

- [ ] **Step 3: Create `phpunit.xml.dist`**

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="tests/bootstrap.php"
         colors="true">
    <testsuites>
        <testsuite name="Unit">
            <directory>tests/</directory>
        </testsuite>
    </testsuites>
    <source>
        <include>
            <directory suffix=".php">src/</directory>
        </include>
    </source>
</phpunit>
```

- [ ] **Step 4: Create `tests/bootstrap.php`**

```php
<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
```

- [ ] **Step 5: Create a placeholder `logo.png`**

Run this PHP one-liner to generate a minimal 64×64 PNG (requires PHP GD extension):

```bash
php -r "
\$img = imagecreatetruecolor(64, 64);
\$bg  = imagecolorallocate(\$img, 0, 148, 107);
imagefill(\$img, 0, 0, \$bg);
\$white = imagecolorallocate(\$img, 255, 255, 255);
imagestring(\$img, 5, 14, 24, 'FST', \$white);
imagepng(\$img, 'logo.png');
imagedestroy(\$img);
echo 'logo.png created' . PHP_EOL;
"
```

If GD is unavailable, copy any 64×64 PNG and name it `logo.png`.

- [ ] **Step 6: Install Composer dependencies**

```bash
composer install
```

Expected output ends with: `Generating autoload files`

- [ ] **Step 7: Commit scaffold**

```bash
git init
git add composer.json phpunit.xml.dist tests/bootstrap.php logo.png
git commit -m "chore: scaffold ps_freeshippingteaser module"
```

---

## Task 2: TeaserBuilder — Unit Tests First

**Files:**
- Create: `tests/TeaserBuilderTest.php`

- [ ] **Step 1: Write the failing tests**

Create `tests/TeaserBuilderTest.php`:

```php
<?php
declare(strict_types=1);

namespace PrestaShop\Module\FreeShippingTeaser\Tests;

use PHPUnit\Framework\TestCase;
use PrestaShop\Module\FreeShippingTeaser\TeaserBuilder;

class TeaserBuilderTest extends TestCase
{
    private TeaserBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new TeaserBuilder();
    }

    public function testRemainingIsThresholdMinusCartTotal(): void
    {
        $result = $this->builder->build(100.0, 65.10, '$', 'Spend {amount} more', 'Ships FREE!');
        $this->assertEqualsWithDelta(34.90, $result['remaining'], 0.001);
    }

    public function testRemainingIsZeroWhenCartExceedsThreshold(): void
    {
        $result = $this->builder->build(100.0, 150.0, '$', 'Spend {amount} more', 'Ships FREE!');
        $this->assertSame(0.0, $result['remaining']);
    }

    public function testPercentReflectsCartProgress(): void
    {
        $result = $this->builder->build(100.0, 65.0, '$', 'Spend {amount} more', 'Ships FREE!');
        $this->assertSame(65, $result['percent']);
    }

    public function testPercentIsCappedAt100(): void
    {
        $result = $this->builder->build(100.0, 150.0, '$', 'Spend {amount} more', 'Ships FREE!');
        $this->assertSame(100, $result['percent']);
    }

    public function testQualifiedWhenCartTotalMeetsThreshold(): void
    {
        $result = $this->builder->build(100.0, 100.0, '$', 'Spend {amount} more', 'Ships FREE!');
        $this->assertTrue($result['qualified']);
    }

    public function testQualifiedWhenCartTotalExceedsThreshold(): void
    {
        $result = $this->builder->build(100.0, 120.0, '$', 'Spend {amount} more', 'Ships FREE!');
        $this->assertTrue($result['qualified']);
    }

    public function testNotQualifiedWhenBelowThreshold(): void
    {
        $result = $this->builder->build(100.0, 99.99, '$', 'Spend {amount} more', 'Ships FREE!');
        $this->assertFalse($result['qualified']);
    }

    public function testAmountTokenReplacedInTeaserText(): void
    {
        $result = $this->builder->build(100.0, 65.10, '$', 'Spend {amount} more', 'Ships FREE!');
        $this->assertStringContainsString('$34.90', $result['teaser_text']);
    }

    public function testThresholdTokenReplacedInTeaserText(): void
    {
        $result = $this->builder->build(100.0, 65.10, '$', 'Free at {threshold}', 'Ships FREE!');
        $this->assertStringContainsString('$100.00', $result['teaser_text']);
    }

    public function testThresholdTokenReplacedInSuccessText(): void
    {
        $result = $this->builder->build(100.0, 100.0, '$', 'Spend {amount} more', 'Reached {threshold}!');
        $this->assertStringContainsString('$100.00', $result['success_text']);
    }

    public function testCurrencyTokenReplaced(): void
    {
        $result = $this->builder->build(100.0, 50.0, '€', 'Currency: {currency}', 'Done');
        $this->assertStringContainsString('€', $result['teaser_text']);
    }

    public function testOutputContainsAllRequiredKeys(): void
    {
        $result = $this->builder->build(100.0, 50.0, '$', 'Spend {amount} more', 'Ships FREE!');
        foreach (['threshold', 'cart_total', 'remaining', 'currency_sign', 'percent', 'qualified', 'teaser_text', 'success_text'] as $key) {
            $this->assertArrayHasKey($key, $result);
        }
    }
}
```

- [ ] **Step 2: Run tests — verify they all fail**

```bash
vendor/bin/phpunit tests/TeaserBuilderTest.php --testdox
```

Expected output includes:
```
Error: Class "PrestaShop\Module\FreeShippingTeaser\TeaserBuilder" not found
```

- [ ] **Commit failing tests**

```bash
git add tests/TeaserBuilderTest.php
git commit -m "test: add TeaserBuilder unit tests (red)"
```

---

## Task 3: TeaserBuilder — Implementation

**Files:**
- Create: `src/TeaserBuilder.php`

- [ ] **Step 1: Create `src/TeaserBuilder.php`**

```php
<?php
declare(strict_types=1);

namespace PrestaShop\Module\FreeShippingTeaser;

class TeaserBuilder
{
    public function build(
        float $threshold,
        float $cartTotal,
        string $currencySign,
        string $teaserTemplate,
        string $successTemplate
    ): array {
        $qualified = $cartTotal >= $threshold;
        $remaining = $qualified ? 0.0 : round($threshold - $cartTotal, 2);
        $percent   = $threshold > 0 ? min(100, (int) (($cartTotal / $threshold) * 100)) : 0;

        return [
            'threshold'    => $threshold,
            'cart_total'   => $cartTotal,
            'remaining'    => $remaining,
            'currency_sign' => $currencySign,
            'percent'      => $percent,
            'qualified'    => $qualified,
            'teaser_text'  => $this->replaceTokens($teaserTemplate, $remaining, $threshold, $currencySign),
            'success_text' => $this->replaceTokens($successTemplate, $remaining, $threshold, $currencySign),
        ];
    }

    private function replaceTokens(
        string $template,
        float $amount,
        float $threshold,
        string $currencySign
    ): string {
        return str_replace(
            ['{amount}', '{threshold}', '{currency}'],
            [
                $currencySign . number_format($amount, 2),
                $currencySign . number_format($threshold, 2),
                $currencySign,
            ],
            $template
        );
    }
}
```

- [ ] **Step 2: Run tests — verify they all pass**

```bash
vendor/bin/phpunit tests/TeaserBuilderTest.php --testdox
```

Expected output:
```
PASS  PrestaShop\Module\FreeShippingTeaser\Tests\TeaserBuilderTest
 ✓ Remaining is threshold minus cart total
 ✓ Remaining is zero when cart exceeds threshold
 ✓ Percent reflects cart progress
 ✓ Percent is capped at 100
 ✓ Qualified when cart total meets threshold
 ✓ Qualified when cart total exceeds threshold
 ✓ Not qualified when below threshold
 ✓ Amount token replaced in teaser text
 ✓ Threshold token replaced in teaser text
 ✓ Threshold token replaced in success text
 ✓ Currency token replaced
 ✓ Output contains all required keys

OK (12 tests, 14 assertions)
```

- [ ] **Step 3: Commit**

```bash
git add src/TeaserBuilder.php
git commit -m "feat: add TeaserBuilder service (green)"
```

---

## Task 4: ThresholdFinder

**Files:**
- Create: `src/ThresholdFinder.php`

> Note: ThresholdFinder calls PrestaShop's `Configuration`, `Db`, and `DbQuery` classes directly. These are only available when running inside a PS instance, so no PHPUnit tests are written for this class — it is verified manually during installation.

- [ ] **Step 1: Create `src/ThresholdFinder.php`**

```php
<?php
declare(strict_types=1);

namespace PrestaShop\Module\FreeShippingTeaser;

class ThresholdFinder
{
    public function find(): ?float
    {
        $override = (float) \Configuration::get('FST_FREE_SHIPPING_AMOUNT');
        if ($override > 0.0) {
            return $override;
        }

        return $this->findFromCarrierRules();
    }

    private function findFromCarrierRules(): ?float
    {
        $sql = new \DbQuery();
        $sql->select('MIN(rp.delimiter1)');
        $sql->from('range_price', 'rp');
        $sql->innerJoin('carrier', 'c', 'c.id_carrier = rp.id_carrier');
        $sql->innerJoin(
            'delivery',
            'd',
            'd.id_range_price = rp.id_range_price AND d.id_carrier = rp.id_carrier'
        );
        $sql->where('c.active = 1');
        $sql->where('c.deleted = 0');
        $sql->where('d.price = 0');
        $sql->where('rp.delimiter1 > 0');

        $result = \Db::getInstance()->getValue($sql);

        return ($result !== false && $result > 0) ? (float) $result : null;
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add src/ThresholdFinder.php
git commit -m "feat: add ThresholdFinder service"
```

---

## Task 5: Main Module Class — Base Structure

**Files:**
- Create: `ps_freeshippingteaser.php`

- [ ] **Step 1: Create `ps_freeshippingteaser.php`**

```php
<?php
declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

use PrestaShop\Module\FreeShippingTeaser\TeaserBuilder;
use PrestaShop\Module\FreeShippingTeaser\ThresholdFinder;

class Ps_freeshippingteaser extends Module
{
    public function __construct()
    {
        $this->name          = 'ps_freeshippingteaser';
        $this->tab           = 'front_office_features';
        $this->version       = '1.0.0';
        $this->author        = 'Your Name';
        $this->need_instance = 0;
        $this->bootstrap     = true;

        parent::__construct();

        $this->displayName = $this->l('Free Shipping Teaser');
        $this->description = $this->l('Displays a real-time free shipping progress teaser in cart areas.');
        $this->ps_versions_compliancy = ['min' => '8.0.0', 'max' => _PS_VERSION_];
    }

    public function install(): bool
    {
        return parent::install()
            && $this->registerHook('displayShoppingCartFooter')
            && $this->registerHook('displayBeforeCarrier')
            && $this->registerHook('displayNav2Column')
            && $this->registerHook('displayTop')
            && $this->registerHook('displayHeader')
            && $this->registerHook('displayBackOfficeHeader')
            && Configuration::updateValue('FST_FREE_SHIPPING_AMOUNT', '0')
            && Configuration::updateValue(
                'FST_TEASER_TEXT',
                'Spend {amount} more and get FREE SHIPPING!'
            )
            && Configuration::updateValue(
                'FST_SUCCESS_TEXT',
                'Your cart total has reached {threshold} and your order ships FREE!'
            );
    }

    public function uninstall(): bool
    {
        return parent::uninstall()
            && Configuration::deleteByName('FST_FREE_SHIPPING_AMOUNT')
            && Configuration::deleteByName('FST_TEASER_TEXT')
            && Configuration::deleteByName('FST_SUCCESS_TEXT');
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add ps_freeshippingteaser.php
git commit -m "feat: add module skeleton with install/uninstall"
```

---

## Task 6: Admin Configuration Form

**Files:**
- Modify: `ps_freeshippingteaser.php` — add `getContent()` and `renderForm()` methods

- [ ] **Step 1: Add `getContent()` and `renderForm()` to the module class**

Append these two methods inside the `Ps_freeshippingteaser` class (before the closing `}`):

```php
    public function getContent(): string
    {
        $output = '';

        if (Tools::isSubmit('submitFSTModule')) {
            Configuration::updateValue(
                'FST_FREE_SHIPPING_AMOUNT',
                (float) Tools::getValue('FST_FREE_SHIPPING_AMOUNT')
            );
            Configuration::updateValue(
                'FST_TEASER_TEXT',
                pSQL(Tools::getValue('FST_TEASER_TEXT'))
            );
            Configuration::updateValue(
                'FST_SUCCESS_TEXT',
                pSQL(Tools::getValue('FST_SUCCESS_TEXT'))
            );
            $output .= $this->displayConfirmation($this->l('Settings updated.'));
        }

        return $output . $this->renderForm();
    }

    private function renderForm(): string
    {
        $helper                      = new HelperForm();
        $helper->table               = $this->table;
        $helper->name_controller     = $this->name;
        $helper->token               = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex        = AdminController::$currentIndex . '&configure=' . $this->name;
        $helper->submit_action       = 'submitFSTModule';
        $helper->default_form_language = (int) $this->context->language->id;
        $helper->fields_value        = [
            'FST_FREE_SHIPPING_AMOUNT' => Tools::getValue(
                'FST_FREE_SHIPPING_AMOUNT',
                Configuration::get('FST_FREE_SHIPPING_AMOUNT')
            ),
            'FST_TEASER_TEXT'  => Tools::getValue(
                'FST_TEASER_TEXT',
                Configuration::get('FST_TEASER_TEXT')
            ),
            'FST_SUCCESS_TEXT' => Tools::getValue(
                'FST_SUCCESS_TEXT',
                Configuration::get('FST_SUCCESS_TEXT')
            ),
        ];

        return $helper->generateForm([[
            'form' => [
                'legend' => [
                    'title' => $this->l('Settings'),
                    'icon'  => 'icon-cogs',
                ],
                'input' => [
                    [
                        'type'  => 'text',
                        'label' => $this->l('Free shipping amount'),
                        'name'  => 'FST_FREE_SHIPPING_AMOUNT',
                        'desc'  => $this->l('Set to 0 to auto-detect from carrier price ranges.'),
                        'size'  => 20,
                    ],
                    [
                        'type'  => 'text',
                        'label' => $this->l('Teaser text'),
                        'name'  => 'FST_TEASER_TEXT',
                        'desc'  => $this->l('Tokens: {amount}, {threshold}, {currency}'),
                        'size'  => 80,
                    ],
                    [
                        'type'  => 'text',
                        'label' => $this->l('Success text'),
                        'name'  => 'FST_SUCCESS_TEXT',
                        'desc'  => $this->l('Tokens: {amount}, {threshold}, {currency}'),
                        'size'  => 80,
                    ],
                ],
                'submit' => ['title' => $this->l('Save')],
            ],
        ]]);
    }
```

- [ ] **Step 2: Commit**

```bash
git add ps_freeshippingteaser.php
git commit -m "feat: add admin config form (getContent + HelperForm)"
```

---

## Task 7: AJAX Front Controller

**Files:**
- Create: `controllers/front/ajax.php`

- [ ] **Step 1: Create `controllers/front/ajax.php`**

```php
<?php
declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

if (file_exists(dirname(__DIR__, 2) . '/vendor/autoload.php')) {
    require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
}

use PrestaShop\Module\FreeShippingTeaser\TeaserBuilder;
use PrestaShop\Module\FreeShippingTeaser\ThresholdFinder;

class Ps_freeshippingteaserAjaxModuleFrontController extends ModuleFrontController
{
    public function initContent(): void
    {
        $this->ajax = true;
        parent::initContent();
    }

    public function displayAjax(): void
    {
        $token = Tools::getValue('token');

        if (!$token || $token !== Tools::getToken(false)) {
            http_response_code(403);
            $this->ajaxRender(json_encode(['error' => 'Invalid token']));

            return;
        }

        $finder    = new ThresholdFinder();
        $threshold = $finder->find();

        if ($threshold === null) {
            $this->ajaxRender(json_encode(['threshold' => null]));

            return;
        }

        $cart      = $this->context->cart;
        $currency  = $this->context->currency;
        $cartTotal = (float) $cart->getOrderTotal(true, Cart::ONLY_PRODUCTS);

        $builder = new TeaserBuilder();
        $data    = $builder->build(
            $threshold,
            $cartTotal,
            $currency->sign,
            (string) Configuration::get('FST_TEASER_TEXT'),
            (string) Configuration::get('FST_SUCCESS_TEXT')
        );

        $this->ajaxRender(json_encode($data));
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add controllers/front/ajax.php
git commit -m "feat: add AJAX front controller"
```

---

## Task 8: Hook Handlers

**Files:**
- Modify: `ps_freeshippingteaser.php` — add hook methods and `renderTeaser()`

- [ ] **Step 1: Add hook handler methods to the module class**

Append these methods inside `Ps_freeshippingteaser` (before the closing `}`):

```php
    /** Tracks whether the mini-cart teaser has already been output this request. */
    private bool $miniCartRendered = false;

    public function hookDisplayHeader(): string
    {
        $this->context->controller->addCSS($this->_path . 'views/css/freeshippingteaser.css');
        $this->context->controller->addJS($this->_path . 'views/js/freeshippingteaser.js');

        return '';
    }

    public function hookDisplayBackOfficeHeader(): void
    {
        // Reserved for future admin assets.
    }

    public function hookDisplayShoppingCartFooter(array $params): string
    {
        return $this->renderTeaser();
    }

    public function hookDisplayBeforeCarrier(array $params): string
    {
        return $this->renderTeaser();
    }

    /**
     * Primary hook for the header mini-cart area.
     * If this fires, suppress hookDisplayTop so we don't double-render.
     */
    public function hookDisplayNav2Column(array $params): string
    {
        $this->miniCartRendered = true;

        return $this->renderTeaser();
    }

    /** Fallback mini-cart hook for themes that don't wire displayNav2Column. */
    public function hookDisplayTop(array $params): string
    {
        if ($this->miniCartRendered) {
            return '';
        }

        return $this->renderTeaser();
    }

    private function renderTeaser(): string
    {
        $finder    = new ThresholdFinder();
        $threshold = $finder->find();

        if ($threshold === null) {
            return '';
        }

        $cart      = $this->context->cart;
        $currency  = $this->context->currency;
        $cartTotal = (float) $cart->getOrderTotal(true, Cart::ONLY_PRODUCTS);

        $builder = new TeaserBuilder();
        $data    = $builder->build(
            $threshold,
            $cartTotal,
            $currency->sign,
            (string) Configuration::get('FST_TEASER_TEXT'),
            (string) Configuration::get('FST_SUCCESS_TEXT')
        );

        $this->context->smarty->assign([
            'fst_qualified'    => $data['qualified'],
            'fst_percent'      => $data['percent'],
            'fst_teaser_text'  => $data['teaser_text'],
            'fst_success_text' => $data['success_text'],
            'fst_ajax_url'     => $this->context->link->getModuleLink($this->name, 'ajax'),
        ]);

        return $this->display(__FILE__, 'views/templates/hook/teaser.tpl');
    }
```

- [ ] **Step 2: Commit**

```bash
git add ps_freeshippingteaser.php
git commit -m "feat: add hook handlers and renderTeaser()"
```

---

## Task 9: Smarty Template

**Files:**
- Create: `views/templates/hook/teaser.tpl`

- [ ] **Step 1: Create `views/templates/hook/teaser.tpl`**

```smarty
{if isset($fst_ajax_url)}
<div class="freeshipping-teaser" data-ajax-url="{$fst_ajax_url|escape:'html':'UTF-8'}">
  {if $fst_qualified}
    <div class="fst-success">
      <span>{$fst_success_text|escape:'html':'UTF-8'}</span>
    </div>
  {else}
    <div class="fst-bar-wrap">
      <div class="fst-bar" style="width:{$fst_percent|intval}%"></div>
    </div>
    <p class="fst-message">{$fst_teaser_text|escape:'html':'UTF-8'}</p>
  {/if}
</div>
{/if}
```

- [ ] **Step 2: Commit**

```bash
git add views/templates/hook/teaser.tpl
git commit -m "feat: add teaser Smarty template"
```

---

## Task 10: CSS Styles

**Files:**
- Create: `views/css/freeshippingteaser.css`

- [ ] **Step 1: Create `views/css/freeshippingteaser.css`**

```css
.freeshipping-teaser {
    padding: 10px 15px;
    background: #fff8e1;
    border: 1px solid #ffcc02;
    border-radius: 4px;
    margin: 0 0 12px;
    font-size: 13px;
    box-sizing: border-box;
}

.fst-bar-wrap {
    background: #e0e0e0;
    border-radius: 10px;
    height: 8px;
    margin-bottom: 8px;
    overflow: hidden;
}

.fst-bar {
    height: 100%;
    background: linear-gradient(90deg, #ffcc02, #ff9800);
    border-radius: 10px;
    transition: width 0.4s ease;
    max-width: 100%;
}

.fst-bar.fst-bar--complete {
    background: linear-gradient(90deg, #66bb6a, #2e7d32);
}

.fst-message {
    margin: 0;
    color: #5a4a00;
    font-weight: 600;
    text-align: center;
    font-size: 13px;
}

.fst-success {
    background: #e8f5e9;
    border: 1px solid #4caf50;
    border-radius: 4px;
    padding: 8px 12px;
    color: #2e7d32;
    font-weight: 600;
    text-align: center;
    font-size: 13px;
}
```

- [ ] **Step 2: Commit**

```bash
git add views/css/freeshippingteaser.css
git commit -m "feat: add teaser CSS styles"
```

---

## Task 11: JavaScript

**Files:**
- Create: `views/js/freeshippingteaser.js`

- [ ] **Step 1: Create `views/js/freeshippingteaser.js`**

```javascript
(function () {
  'use strict';

  function escapeHtml(str) {
    var div = document.createElement('div');
    div.appendChild(document.createTextNode(String(str)));
    return div.innerHTML;
  }

  function renderTeaser(data) {
    if (!data || !data.threshold) {
      return '';
    }
    if (data.qualified) {
      return (
        '<div class="fst-success">' +
          '<span>' + escapeHtml(data.success_text) + '</span>' +
        '</div>'
      );
    }
    var barClass = data.percent >= 100 ? 'fst-bar fst-bar--complete' : 'fst-bar';
    return (
      '<div class="fst-bar-wrap">' +
        '<div class="' + barClass + '" style="width:' + data.percent + '%"></div>' +
      '</div>' +
      '<p class="fst-message">' + escapeHtml(data.teaser_text) + '</p>'
    );
  }

  function updateTeasers() {
    var containers = document.querySelectorAll('.freeshipping-teaser');
    if (!containers.length) { return; }

    var ajaxUrl = containers[0].getAttribute('data-ajax-url');
    if (!ajaxUrl) { return; }

    var formData = new FormData();
    formData.append('token', prestashop.static_token);

    fetch(ajaxUrl, { method: 'POST', body: formData })
      .then(function (response) { return response.json(); })
      .then(function (data) {
        containers.forEach(function (el) {
          el.innerHTML = renderTeaser(data);
        });
      })
      .catch(function () {
        /* teaser is non-critical — silent failure is acceptable */
      });
  }

  document.addEventListener('DOMContentLoaded', function () {
    if (typeof prestashop === 'undefined' || typeof prestashop.on !== 'function') {
      return;
    }
    prestashop.on('updateCart', function () {
      setTimeout(updateTeasers, 150);
    });
  });
}());
```

- [ ] **Step 2: Commit**

```bash
git add views/js/freeshippingteaser.js
git commit -m "feat: add AJAX update JavaScript"
```

---

## Task 12: Free Shipping Setup Documentation

**Files:**
- Create: `docs/free-shipping-setup.md`

- [ ] **Step 1: Create `docs/free-shipping-setup.md`**

```markdown
# How to Set Up Free Shipping in PrestaShop 9

The Free Shipping Teaser module shows customers how close they are to qualifying for free shipping.
Below are the three ways to configure it.

---

## Method 1: Carrier Price Ranges (Recommended — auto-detected)

This is the cleanest approach. The module reads your carrier rules automatically.

1. Go to **Back Office → Shipping → Carriers**
2. Click **Edit** on the carrier you want to offer free shipping for
3. Open the **Shipping costs** tab
4. Under **Price-based ranges**, add a new range:
   - **From:** your free shipping threshold (e.g. `100.00`)
   - **To:** leave blank or enter a very high number (e.g. `999999.00`)
   - Set the **cost to `0.00`** for every applicable shipping zone
5. Click **Save**

The module automatically finds the lowest threshold across all active carriers that have a zero-cost range.
No module configuration is needed.

---

## Method 2: Module Manual Override

Use this when:
- Your free shipping is configured via Cart Rules (Method 3 below), **or**
- Auto-detection returns the wrong value (e.g. multiple carriers with different thresholds)

1. Go to **Back Office → Modules → Module Manager**
2. Search for **Free Shipping Teaser** and click **Configure**
3. Enter the exact threshold amount in **Free shipping amount** (e.g. `100.00`)
4. Click **Save**

> This value takes priority over all carrier rule detection.

You can also customise the teaser and success text on the same page using these tokens:
- `{amount}` — the remaining amount the customer needs to spend
- `{threshold}` — the total free shipping threshold
- `{currency}` — the currency symbol (e.g. `$`, `€`)

---

## Method 3: Cart Rules (Coupon-Based Free Shipping)

PrestaShop supports free shipping via Cart Rules (discount codes). The module **cannot** auto-detect
this because it is applied at order calculation time rather than stored as a fixed carrier range.

**Setup:**

1. Go to **Catalog → Discounts → Cart Rules → Add new rule**
2. Under **Conditions**, set a **Minimum amount** (e.g. `100.00`, tax included)
3. Under **Actions**, tick **Free Shipping**
4. Save the cart rule

5. **Then set the module manual override** (Method 2 above) to the same threshold amount
   so the teaser displays the correct progress bar.

---

## Troubleshooting

| Issue | Solution |
|---|---|
| Teaser not visible on cart page | Ensure your theme supports `displayShoppingCartFooter`. Add the hook in your theme's `cart.tpl` if missing. |
| Teaser not visible in mini-cart | Ensure your theme supports `displayNav2Column` or `displayTop`. |
| Wrong threshold shown | Multiple carriers may have different thresholds. Use the manual override to pin the correct value. |
| Progress bar not updating | Check browser console for JS errors. Ensure `prestashop.static_token` is available (PS9 sets this globally). |
| Teaser shows after threshold is met | The AJAX endpoint may be returning stale data — hard-refresh the page and re-test. |
```

- [ ] **Step 2: Commit**

```bash
git add docs/free-shipping-setup.md
git commit -m "docs: add free shipping setup instructions"
```

---

## Task 13: Smoke Test Checklist

> Run these manually after installing the module on a PS9 instance.

- [ ] **Install the module**
  - Go to Back Office → Modules → Upload a module → zip the `ps_freeshippingteaser` folder
  - Install and confirm no errors

- [ ] **Check admin config page**
  - Modules → Free Shipping Teaser → Configure
  - Page loads, shows three fields with correct defaults
  - Save a test value (e.g. `50.00`) — confirmation message appears, value persists on reload

- [ ] **Check teaser on cart page (below threshold)**
  - Add a product with total < threshold to cart
  - Visit the cart page
  - `.freeshipping-teaser` div is present, shows progress bar and "Spend X more" text
  - Progress bar width reflects correct percentage

- [ ] **Check AJAX update on cart page**
  - With cart page open, increase product quantity
  - Without page reload, progress bar and text update to reflect new total
  - Open browser Network tab — confirm a POST to `.../module/ps_freeshippingteaser/ajax` fires

- [ ] **Check teaser in mini-cart (header)**
  - Navigate to any page
  - `.freeshipping-teaser` div visible in mini-cart / nav area

- [ ] **Check success state**
  - Add products until cart total meets or exceeds threshold
  - Teaser changes to green success message, progress bar disappears

- [ ] **Check auto-detection (carrier rules)**
  - Delete the manual override (set to `0`)
  - Ensure a carrier has a price range with cost `0.00` at your threshold
  - Reload cart page — correct threshold auto-detected

- [ ] **Check hidden state**
  - Set manual override to `0` and remove all carrier free-shipping ranges
  - Reload cart — teaser div is absent (no empty container rendered)
```
