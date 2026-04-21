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
