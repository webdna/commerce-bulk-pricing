# Commerce Bulk Pricing Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/) and this project adheres to [Semantic Versioning](http://semver.org/).
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
