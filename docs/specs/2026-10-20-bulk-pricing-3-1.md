---
spec: Commerce Bulk Pricing 3.1
slug: bulk-pricing-3-1
status: draft
version: 0.2
date: 2026-10-20
author: Claude (with Sam Birch)
client: webdna (Craft Plugin Store product)
approver: Sam Birch
related: [webdna/commerce-bulk-pricing #11, #12, #13]
---

# Commerce Bulk Pricing 3.1

> **Status:** draft - **Version:** 0.2
> The issues decided with Sam on 20 Oct 2026: a template can show the price a customer will pay
> at a quantity, sales included, and list a product's price breaks as rows; a module can act on a
> line item after its bulk price is set. #4 (prices by category) stays in the backlog.

---

## 1. How it works

**One lookup, two readers.** 3.0 worked out a bulk price only inside the line item handler, so a
template had to re-implement it (the workaround in #11 does, and misses sales and user groups).
3.1 moves the lookup into the service: *which bulk field governs this purchasable for this user and
currency, which of its rows applies at this quantity, and what that row costs after sales*. The
line item handler and the Twig API both call it, so a template can never show a price the cart
does not charge.

**Twig (#11, #13).** `craft.bulkPricing.price(purchasable, qty)` is the unit price the cart would
charge for that quantity: the matching row's sale-aware price, or the purchasable's own sale price
when no row applies. `craft.bulkPricing.rows(purchasable)` is the governing field's rows, lowest
quantity first, each `{heading, qty, price, salePrice}`. Both take an optional
`{currency, user}`; by default the session cart's payment currency (else the store's primary) and
the signed-in user.

**After-apply event (#12).** 3.0 already fires `BulkPricingService::EVENT_APPLY_BULK_PRICING` with
a plain `LineItemEvent` after its `LineItems::EVENT_POPULATE_LINE_ITEM` handler, undocumented and
saying nothing of what happened. 3.1 keeps the name and fires `ApplyBulkPricingEvent`, a
`LineItemEvent` subclass (so existing handlers keep working) that also carries the order, whether a
bulk price was applied, the row and the field. The README documents it, and that the order's totals
are not recalculated yet at that point — read the line item.

## 2. Scope

**In:** #11, #12, #13; README and CHANGELOG; version 3.1.0. Three defects in the code being moved,
fixed because the shared lookup would otherwise inherit them (BR-6–8).

**Out:** #4 (categories); replying to or closing issues (Sam decides when); a test suite for the
plugin (verification is the craft5 check script, B5); Commerce 5 catalog pricing rules on top of a
bulk price (3.0's note stands: a bulk price is authoritative).

## 3. User journeys

1. A product page lists `10+ : £50.00 / 20+ : £45.00` from `rows()`, and a quantity selector shows
   `price(variant, qty)`; with a 10% sale running, the page shows £45.00 at 10 and the cart charges £45.00.
2. A trade customer sees their group's rows; a guest sees none (or the guest-allowed field's).
3. A module listens for `EVENT_APPLY_BULK_PRICING`, reads `$event->applied` and `$event->lineItem->getSalePrice()`.

**Negative:** a purchasable with no bulk field, or no row for the currency, gives `rows() == []` and
`price()` equal to its normal sale price; a blank cell is never a row.

## 4. Data model

Unchanged. A field value is `{ISO: {iso: ISO, "<qty>": "<price>", …}}`; headings are the field's
`columns` setting (`{colN: {heading, qty}}`). New value object `models/PriceRow`
(`heading` string, `qty` int, `price` float, `salePrice` float). No migration; schema stays 1.0.0.

## 5. Rules

- **BR-1** The line item handler and the Twig API use the same service lookup; for any purchasable,
  quantity, user and currency, `price()` equals the line item's `getSalePrice()`.
- **BR-2** A field governs when the user may use it (no groups set, or the user is in one; a guest
  only when `guestUser` is on) and its value has the currency. For a price, the last field in the
  layout with a row applying at the quantity governs — 3.0's loop order. For `rows()`, the last
  eligible field with any rows, unless `options.field` names one.
- **BR-3** A row is a non-`iso` key with a non-blank price; rows sort by quantity ascending, and the
  applying row is the highest quantity ≤ the requested quantity.
- **BR-4** `salePrice` is the row price after the purchasable's Sales, by 3.0's arithmetic unchanged,
  floored at 0. On Commerce 5 the charged price is the lower of price and salePrice (promotional
  price only when lower), as 3.0.
- **BR-5** Headings are translated with `Craft::t('site', …)`, as the CP input does; a column with
  no heading gives `''`.
- **BR-6** A guest against a group-restricted field with `guestUser` on is not eligible — 3.0 called
  `isInGroup()` on null.
- **BR-7** `applyBulkPricing()` always returns the line item — 3.0 returned nothing when the
  purchasable had no product, a `TypeError` against its return type.
- **BR-8** Rows apply by quantity regardless of column order — 3.0 depended on the columns being
  in ascending order.
- **BR-9** `EVENT_APPLY_BULK_PRICING` fires once per populate, whether or not a price applied, with
  `ApplyBulkPricingEvent {lineItem, isNew: false, order, applied, row, field}`. Commerce populates a
  new line item twice (`populate()`, then `refresh()`), so a new line item raises it twice.
- **BR-10** Code stays valid on PHP 8.0.2 and on Craft 4 / Commerce 4 (the composer floor): no
  enums, readonly, `new` in initialisers or Commerce-5-only calls without a guard.
- **BR-11** The bulk fields are read from the variant when its layout has one, else from the product
  — not from the product type's variant setting. 3.0 read `maxVariants > 1`, which on Commerce 5's
  default (unlimited, `maxVariants` null) said "no variants", so a variant's bulk field never applied.

## 6. Interfaces

- Twig: `craft.bulkPricing.price(purchasable, qty, options = {})` → float;
  `craft.bulkPricing.rows(purchasable, options = {})` → `PriceRow[]`. `options`: `currency` (ISO),
  `user` (User, or null for a guest), and for `rows()` `field` (a handle). A null purchasable gives
  `null` / `[]`.
- PHP: `BulkPricingService::getPrice()`, `getPriceRows()`, same arguments; the event class
  `webdna\commerce\bulkpricing\events\ApplyBulkPricingEvent`.

## 7. Test plan

- **TS-1** Rows: a field with columns ordered 20, 10 and a blank 30 gives two rows, 10 then 20, with headings.
- **TS-2** Price at qty 1, 10, 25 = normal sale price, row 10, row 20.
- **TS-3** A 10% Sale: rows' `salePrice` and `price()` drop 10%; a line item at the same qty has the same `getSalePrice()` (BR-1).
- **TS-4** A group-restricted field: a member of the group gets rows, a non-member and a guest get none; guest with `guestUser` on does not crash (BR-6).
- **TS-5** A currency the value lacks gives no rows and the normal price.
- **TS-6** The event fires once per populate (twice per new line item) with `applied`, `row`, `field`, `order` set; with no row, `applied` false.

---

## 8. Build order

One phase; the lookup comes first because the other two read it.

### Tasks

- [x] **1.1 Shared lookup** — `src/services/BulkPricingService.php`, `src/models/PriceRow.php`
      Rules: BR-1–8, BR-10, BR-11 · Verify: TS-1–5
- [x] **1.2 Twig API (#11, #13)** — `src/variables/BulkPricingVariable.php`, `src/BulkPricing.php` (CraftVariable init)
      Rules: BR-1, BR-5 · Verify: TS-1–5 through the variable
- [x] **1.3 After-apply event (#12)** — `src/events/ApplyBulkPricingEvent.php`, `src/services/BulkPricingService.php`
      Rules: BR-9 · Verify: TS-6
      *Built 20 Oct 2026.* `scripts/check.php` in craft5 (Commerce 5.7.5): 18 checks, all passed. Found, not
      fixed: a discount that **ignores promotions** makes Commerce drop the sale from a line item, but a bulk
      row then sets its own sale price on top (3.0 behaviour, kept); and `price()` cannot see discounts at all,
      being order context — the check switches the install's discounts off. Both noted in the README.
- [x] **1.4 Release notes** — `README.md` (Twig + event), `CHANGELOG.md` (3.1.0, unreleased), `composer.json` version 3.1.0
      Verify: B5

---

# Appendix B - build scaffolding

## B1. Before writing anything

Read the triage memory (`plugin-issue-triage-20-oct.md`, bulk-pricing) and issues #11–#13.

## B2. Context to load

1. `src/BulkPricing.php` (handler at 106), `src/services/BulkPricingService.php`, `src/fields/BulkPricingField.php` (`columns`, `getInputHtml()` for the value shape).
2. craft5 `vendor/craftcms/commerce/src/base/Purchasable.php` — `getSales()` 1279, `getSalePrice()` 652.

## B3. Guardrails

- **Keep 3.0's sale arithmetic and Commerce 4/5 branches byte-for-byte in behaviour** — only move them.
- **Do not create a cart to read its currency** — use the session cart only when one exists.
- **Never comment on or close the GitHub issues, never push without asking.**
- **craft5:** run ddev only from `~/projects/craft5` (lowercase), never commit there.

## B4. Definition of done

- [x] TS-1–TS-6 pass in craft5 (Commerce 5)
- [x] `php -l` clean on every changed file
- [x] CHANGELOG and README describe the change

## B5. Verification

```bash
# from ~/projects/craft5 — the script builds its fixtures in a transaction and rolls back
ddev exec php plugins/commerce-bulk-pricing/scripts/check.php
# expect: every TS line PASS, final line "all passed"
ddev exec "php -l plugins/commerce-bulk-pricing/src/<file>" for each changed file
```

Not runnable here: Commerce 4 (no Craft 4 sandbox). BR-10 is kept by reading, not by a run.

## B6. Out of bounds

- GitHub issues; pushing without asking. `~/Projects/lll`. Committing in craft5.

---

## Change log

| Date | Version | Change | By |
|---|---|---|---|
| 2026-10-20 | 0.1 | First draft, from the triage of 20 Oct 2026 | Claude (with Sam Birch) |
| 2026-10-20 | 0.2 | Built. BR-11 added (variant fields on unlimited-variant types, found building the fixtures); BR-9/TS-6: two populates per new line item | Claude |
