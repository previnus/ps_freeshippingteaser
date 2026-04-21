# Free Shipping Teaser Module — Design Spec
**Date:** 2026-04-21
**Module name:** `ps_freeshippingteaser`
**Platform:** PrestaShop 9
**Approach:** Hook-only module (no core overrides)

---

## Overview

A PrestaShop 9 module that displays a real-time free shipping teaser in cart-related locations. As the customer adds products to their cart, the teaser shows how much more they need to spend to qualify for free shipping, including a progress bar. When the threshold is reached, the teaser switches to a green success message. All updates happen via AJAX without a full page reload.

---

## Module Structure

```
ps_freeshippingteaser/
├── ps_freeshippingteaser.php
├── config.xml
├── logo.png
├── controllers/
│   └── front/
│       └── ajax.php
├── views/
│   ├── templates/
│   │   ├── hook/
│   │   │   └── teaser.tpl
│   │   └── admin/
│   │       └── configure.tpl
│   ├── js/
│   │   └── freeshippingteaser.js
│   └── css/
│       └── freeshippingteaser.css
└── docs/
    └── free-shipping-setup.md
```

---

## Hooks

| Hook | Purpose |
|---|---|
| `displayShoppingCartFooter` | Teaser below cart product table on full cart page |
| `displayBeforeCarrier` | Teaser above carrier selection in checkout |
| `displayNav2Column` | Teaser inside header mini-cart panel (primary hook; falls back to `displayTop` if not available) |
| `displayHeader` | Load front-end JS and CSS assets |
| `displayBackOfficeHeader` | Load admin assets |

---

## Threshold Logic

### Source priority
1. **Manual override** — `free_shipping_amount` set in module config (non-zero value wins)
2. **Carrier rules fallback** — queries active carriers for the lowest price range that results in free shipping (zero cost range), using PrestaShop's native carrier/range tables
3. **No threshold found** — teaser is hidden entirely (fail-silent)

### Remaining amount calculation
```
remaining = threshold - Cart::getOrderTotal(true, Cart::ONLY_PRODUCTS)
```
Tax-included, shipping excluded — matches how PrestaShop evaluates carrier rules.

### AJAX endpoint (`controllers/front/ajax.php`)
- Method: `POST`
- Parameters: `id_cart`, `token`
- Response:
```json
{
  "threshold": 100.00,
  "cart_total": 65.10,
  "remaining": 34.90,
  "currency_sign": "$",
  "percent": 65,
  "qualified": false,
  "teaser_text": "Spend $34.90 more and get FREE SHIPPING!",
  "success_text": "Your cart total has reached $100.00 and your order ships FREE!"
}
```

---

## Admin Configuration

Accessible via **Modules → Free Shipping Teaser → Configure**.

### Fields
| Field | Type | Default | Notes |
|---|---|---|---|
| Free Shipping Amount | Decimal | `0` | Leave 0 to auto-detect from carrier rules |
| Teaser Text | Multilang text | `"Spend {amount} more and get FREE SHIPPING!"` | Tokens: `{amount}`, `{threshold}`, `{currency}` |
| Success Text | Multilang text | `"Your cart total has reached {threshold} and your order ships FREE!"` | Same tokens |

Built with PrestaShop's native `HelperForm`.

---

## Front-End Behaviour

### Teaser states

| State | Condition | Display |
|---|---|---|
| Teaser | `remaining > 0` | Amber/orange bar with progress bar + "Spend X more..." text |
| Success | `remaining <= 0` | Green bar with success message, no progress bar |
| Hidden | No threshold configured | Nothing rendered |

### Progress bar
- Width = `(cart_total / threshold) * 100%`, capped at 100%
- CSS `transition: width 0.4s ease` for smooth animation on update
- Colour transition: amber → green as it fills toward 100%

### JavaScript (`freeshippingteaser.js`)
- On DOM ready: finds all `.freeshipping-teaser` containers on the page
- Listens for PrestaShop 9's native `prestashop.on('updateCart', ...)` event, fired after every add/remove/quantity change
- On event: POSTs to `ajax.php`, receives JSON, replaces inner HTML of all `.freeshipping-teaser` containers simultaneously
- No polling — purely event-driven

### Template (`teaser.tpl`)
```smarty
<div class="freeshipping-teaser" data-ajax-url="{$ajax_url|escape:'html':'UTF-8'}">
  {if $qualified}
    <div class="fst-success">
      <span>{$success_text|escape:'html':'UTF-8'}</span>
    </div>
  {else}
    <div class="fst-bar-wrap">
      <div class="fst-bar" style="width:{$percent}%"></div>
    </div>
    <p class="fst-message">{$teaser_text|escape:'html':'UTF-8'}</p>
  {/if}
</div>
```

### Render locations
All three hook handlers render the same `teaser.tpl` partial. The wrapping `data-ajax-url` attribute lets the JS find and refresh all instances from one AJAX response:
1. `displayShoppingCartFooter` → below cart product table
2. `displayBeforeCarrier` → above carrier list in checkout
3. `displayNav2Column` → inside header mini-cart panel

---

## Free Shipping Setup Instructions

Covered in `docs/free-shipping-setup.md`. Three methods:

1. **Carrier price ranges (recommended — auto-detected)**
   - Shipping → Carriers → edit carrier → Price ranges tab
   - Add range with cost `0.00` starting at the threshold amount
   - Module reads this automatically

2. **Cart rules (coupon-based)**
   - Catalog → Discounts → Cart Rules → Add new
   - Condition: minimum cart amount; Action: free shipping
   - Cannot be auto-detected — use the module's manual override field

3. **Module manual override**
   - Modules → Free Shipping Teaser → Configure
   - Enter threshold amount directly — overrides carrier rule detection

---

## Security

- AJAX endpoint validates a PrestaShop token (`Tools::getToken()`) on every request
- All template output escaped via `|escape:'html':'UTF-8'`
- Config form uses PS `HelperForm` with built-in CSRF protection
- `id_cart` in AJAX request validated against the current session's cart

---

## Out of Scope

- Product page display (cart locations only)
- Custom theme compatibility beyond hook placement
- Email or notification integration
