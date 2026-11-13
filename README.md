# Ropstam Solutions – Senior CMS Developer Task

This repo has both parts of the task:

| Folder | Platform | What it is |
| --- | --- | --- |
| [`wordpress/rs-services-calculator`](wordpress/rs-services-calculator) | WordPress | Plugin with a **Services** post type and a dynamic block that shows the 3 latest services with a live add-on price calculator |
| [`shopify`](shopify) | Shopify (Online Store 2.0) | `bundle-builder` section where the customer picks one product from each group and adds the 3-pack to the cart in one request |

**Demo links**

- WordPress: https://lightyellow-goat-576244.hostingersite.com/
- Shopify: https://bundle-builder-demo-wvcbnxbs.myshopify.com/ (storefront password: `skewgi`)

---

## WordPress – RS Services Calculator

### Setup

1. Copy `wordpress/rs-services-calculator` into `wp-content/plugins/`, or zip the folder and upload it from **Plugins → Add New → Upload Plugin**.
2. Activate **RS Services Calculator**.
3. Go to **Services → Add New**, fill in **Base price** and **Delivery time (days)** in the *Service details* box, add a short excerpt and publish. Add at least 3.
4. Edit any page and insert the **Services Price Calculator** block.
   On classic themes or page builders use the shortcode instead:
   ```
   [rs_services_calculator heading="Our services" currency="$" excerpt="no"]
   ```

No build step is needed. The editor script uses the `wp.*` globals directly.

Requirements: WordPress 6.3+, PHP 7.4+.

### How it works

- **Post type** `rs_service` (archive at `/services/`) with REST support.
- **Meta** `base_price` (number) and `delivery_time_days` (integer) are registered with `register_post_meta()`, so they are typed, available over REST and run through a `sanitize_callback` plus an `auth_callback`. The meta box saves through `save_post_rs_service` with a nonce and a capability check. Negative or non-numeric prices are stored as `0`, and days are clamped to 1–365.
- **Query** uses `WP_Query` ordered by `modified DESC` with `no_found_rows` and the term cache turned off. There is no raw SQL anywhere.
- **Block** `rs-services/service-calculator` is a dynamic block (`block.json` + `render_callback`). The same renderer is used by the shortcode. Block settings:
  - heading, currency symbol, show/hide excerpt
  - add-ons list (label, price, delivery-day change), up to 6
- **Calculator** (`assets/js/view.js`) is plain JavaScript with no dependencies. Each root element gets one delegated click listener. Prices are handled in cents to avoid float rounding issues. Each add-on is a `<button aria-pressed>` toggle, and the estimate sits in an `aria-live="polite"` region so screen readers announce the new total.
- **Assets** are registered with `wp_register_script` / `wp_register_style` (dependencies and version set) and only enqueued by the renderer when the block or shortcode is on the page. The front-end script loads with `defer` in the footer.
- All output goes through `esc_html`, `esc_attr` or `esc_url`. Block attributes are sanitized again on render because they come from post content.

### Edge cases

- No published services: a "no services yet" message is shown.
- Service with no price: it shows "On request" and no calculator.
- JavaScript disabled: the add-on toggles stay hidden (they would do nothing), and the base price and delivery time are still shown.
- Delivery days can't drop below 1 day, even with several "faster" add-ons.

---

## Shopify – Bundle Builder section

### Setup

Using Shopify CLI (built for Dawn and Dawn-based themes, but it doesn't depend on any theme JS):

```bash
# from your theme folder
cp path/to/repo/shopify/sections/bundle-builder.liquid sections/
cp path/to/repo/shopify/assets/bundle-builder.{js,css} assets/

shopify theme dev --store your-store.myshopify.com   # preview
shopify theme push                                   # or push to an unpublished theme
```

Then in the theme editor: **Add section → Bundle builder**. The section comes with 3 product groups. For each group, choose a **Collection** or pick specific products under **Or pick products** (this wins if both are set).

### Section settings

| Setting | Notes |
| --- | --- |
| Heading / Text | Shown above the builder |
| Products per group | 2–12 products shown from each collection/list |
| Bundle name | Saved on every cart line as the `Bundle` property |
| Displayed bundle discount | 0–50%. Only changes the price shown in the builder (see limitations) |
| Button label, padding | |
| Blocks: Product group (max 3) | Title, Collection, Or pick products |

### How it works

- The UI is a `<bundle-builder>` custom element. Each group is a `<fieldset>` with native radio inputs, so arrow keys, Tab and Space work like any radio group, and the legend gives the group its name.
- Products with variants get a `<select>`. Sold-out variants are disabled in it.
- Price, summary and status update in place. Only the text nodes that changed are touched.
- Money is formatted with the shop's `money_format`, so other currencies and formats work.
- **Add to cart** is one `POST /cart/add.js` request with an `items` array. Each line has:
  ```json
  { "Bundle": "3-Pack Bundle", "_bundle_id": "a1b2c3d4", "_bundle_size": "3" }
  ```
  `_bundle_id` links the three lines together. Properties starting with `_` are hidden at checkout by Shopify themes, so the customer only sees `Bundle`.
- If the theme has `#cart-icon-bubble` (Dawn and most Dawn-based themes), the request also asks for that section through the Section Rendering API and swaps it in, so the cart count updates without a reload. A `bundle-builder:added` event is dispatched on `document`, and Dawn's `publish(cartUpdate)` is called when it exists, for themes that want to open a drawer.

### States and edge cases

- **Sold out:** sold-out products are shown greyed out with a "Sold out" badge and can't be selected. If every product in a group is sold out, the group shows a notice and the button stays disabled with an explanation.
- **Incomplete bundle:** the button is disabled and the status reads "1 of 3 selected", etc.
- **Loading:** the button shows a spinner, gets `aria-busy="true"` and the groups are disabled so the selection can't change mid-request.
- **API errors:** a 422 from Shopify (e.g. "All 1 Wool Socks are in your cart.") is shown as-is in a `role="alert"` box. Network failures and a 15 s timeout show a friendly message. In every case the selection is kept, so the customer can retry.
- **Empty group in the editor:** a placeholder is shown only in the theme editor (`request.design_mode`), and the group is skipped on the live store.
- Groups adapt: if the merchant only sets up 2 groups, it becomes a 2-item bundle.

### Limitations / assumptions

- **The discount is display-only.** Changing the actual price needs a Shopify discount (an automatic discount or a Discount Function that targets lines sharing the same `_bundle_id`). Leave the setting at 0% unless a matching discount exists.
- The three products are added as separate cart lines (linked by properties), not as one bundle product. Cart Transform / the Bundles app would be the next step if a single merged line is needed.
- Product lists are limited to the first 12 products of a collection (`products_per_group`) to keep the page light.
- Stock is checked when the page renders and again by Shopify on add (422). Live inventory polling is not included.

---

## Key technical decisions

- **Dynamic block instead of a static one.** Prices and the "latest 3" list change often, so the block renders on the server. Saved post content never goes stale, and the block and shortcode share one renderer.
- **No build step for the editor script.** It uses the `wp.*` globals directly, so the plugin can be uploaded and used as-is. The trade-off is plain `createElement` calls instead of JSX.
- **Money in cents.** Both PHP and JS work with integers, so totals never show floating point errors like `0.30000000000000004`.
- **Assets only where they are used.** Scripts and styles are registered on `init` but enqueued from the renderer, so pages without the block load nothing extra.
- **Native form controls for accessibility.** WordPress add-ons are `<button aria-pressed>` toggles. Shopify groups are `<fieldset>` + radio inputs, so keyboard support and screen reader announcements come from the browser instead of custom code.
- **One `/cart/add.js` request for the whole bundle.** Sending all three items together means Shopify accepts or rejects the bundle as a unit, instead of leaving a half-added bundle in the cart.
- **`_bundle_id` line item property.** It links the three lines so a discount function, cart drawer or order export can group them later. The leading underscore keeps it hidden from customers.
- **Custom element for the Shopify section.** `<bundle-builder>` sets itself up in `connectedCallback`, so it also works when the theme editor re-renders the section, with no global init code.
- **Discount is display-only.** Changing the real price needs a Shopify discount or Function. Applying it only in the UI would show a price the checkout doesn't charge, so it defaults to 0%.

---


## Repo structure

```
wordpress/rs-services-calculator/
  rs-services-calculator.php   bootstrap + hooks
  includes/                    post type, meta, query, render, block/shortcode
  block/block.json
  assets/js/view.js            front-end calculator
  assets/js/editor.js          block editor UI (no build)
  assets/css/calculator.css
shopify/
  sections/bundle-builder.liquid
  assets/bundle-builder.js
  assets/bundle-builder.css
```
