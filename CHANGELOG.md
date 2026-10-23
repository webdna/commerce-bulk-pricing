# Commerce Bulk Pricing Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/) and this project adheres to [Semantic Versioning](http://semver.org/).
## 3.1.1 - 2026-10-23

### Changed
- New plugin icon: a white glyph on a black circle, with a white icon mask for the control panel nav.

## 3.1.0 - 2026-10-20

> {warning} **Prices may change on Commerce 5 stores.** A bulk pricing field on a product type's *variant* layout was ignored when the product type allows unlimited variants (Commerce 5's default). From 3.1 it applies, so carts for those variants will start charging the bulk prices that were entered there. Before updating, check each product type with unlimited variants: if a bulk pricing field on its variant layout holds prices you do not want charged, clear them or remove the field.

### Added
- `craft.bulkPricing.price(purchasable, qty)` — the unit price the cart charges at a quantity, sales included ([#11](https://github.com/webdna/commerce-bulk-pricing/issues/11)).
- `craft.bulkPricing.rows(purchasable)` — the price breaks as `{heading, qty, price, salePrice}`, lowest quantity first ([#13](https://github.com/webdna/commerce-bulk-pricing/issues/13)).
- `BulkPricingService::getPrice()` and `getPriceRows()`, the same lookup in PHP.
- `EVENT_APPLY_BULK_PRICING` now carries an `ApplyBulkPricingEvent` with the order, whether a bulk price was applied, the row and the field, and is documented ([#12](https://github.com/webdna/commerce-bulk-pricing/issues/12)). It still extends `LineItemEvent`, so existing handlers keep working.

### Fixed
- A bulk pricing field on the variant is now read on product types with unlimited variants (Commerce 5's default), which were treated as having none — see the warning above.
- A guest no longer causes an error against a group-restricted field with guest pricing on.
- Price breaks apply by quantity whatever order the columns are in.
- A purchasable that is not a product variant no longer causes an error.

## 3.0.0 - 2026-07-30
### Added
- Support for Craft CMS 5 and Craft Commerce 5, alongside continued support for Craft 4 / Commerce 4.

### Changed
- The field now reports its content column via `dbType()` (JSON) on Craft 5, while keeping `getContentColumnType()` for Craft 4.
- On Commerce 5, the effective price is applied via the line item's `promotionalPrice` (Commerce 5 made `salePrice` a read-only, derived property). Behaviour on Commerce 4 is unchanged.
- Variant detection now uses `maxVariants` on Commerce 5 to avoid the deprecated `ProductType::hasVariants` warning.
- The `taxIncluded` snapshot flag is now written via `setSnapshot()` on Commerce 5 (where `snapshot` is no longer a public array).

### Note
- On Commerce 5, bulk pricing continues to stack with legacy **Sales** (as before). It does not stack with **catalog pricing rules** — when a bulk price applies, it is authoritative and any catalog promotional price is cleared for that line item.

## 2.1.1 - 2022-10-27
### Fixed
- Bug checking against user group list

## 2.1.0 - 2022-08-23
### Added
- Feed Me integration

## 2.0.0-beta.3 - 2022-07-15
### Fixed
- craft\element\Address issue: #16

## 2.0.0-beta.2 - 2022-07-14
### Fixed
- Depreciation notice: order->user

## 2.0.0-beta.1 - 2022-07-13
### Changed
- Craft 4 compatibility

## 1.1.8 - 2022-06-10
### Changed
- Change of ownership

## 1.1.7 - 2022-04-14

### Fixed

-   Fixed a bug in the tax adjuster where the tax wasn't being calcualted correctly for each line item

## 1.1.6 - 2021-01-18

### Fixed

-   Changelog Fix

## 1.1.5 - 2021-01-18

### Fixed

-   Changelog Fix

## 1.1.4 - 2021-01-18

> {warning} Sales are now applied to bulk prices whereas previosuly they weren't. Please check all your products and sales to avoid any unexpected sales being applied.

### Added

-   Now supports Commerce Sales

## 1.1.3 - 2020-12-16

### Fixed

-   Fixed a bug when trying to add a new column to a bulk pricing field

### Added

-   Now requires Craft 3.4+.
-   Missing requirement for Craft Commerce 2 or 3.

## 1.1.2 - 2020-03-06

### Added

-   Set lineitem->salePrice in addition to lineitem->price when calculating bulk price.

## 1.1.1 - 2020-03-06

### Changed

-   plugin icon.

## 1.1.0 - 2019-07-17

### Added

-   allowing guest users to see bulk prices

### Changed

-   if no user groups are selected then all users will see bulk pricing

## 1.0.12 - 2019-06-18

### Fixed

-   Check that element exists on populate line item handler

## 1.0.10

### Changed

-   Update tax adjuster to match commerce updates

## 1.0.0 - 2019-01-29

### Added

-   Initial release
