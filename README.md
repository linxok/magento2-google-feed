# MyCompany Google Feed for Magento 2

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![Magento 2](https://img.shields.io/badge/Magento-2.4-orange.svg)](https://magento.com/)

Google Shopping XML feed generator for Magento 2 with multi-store support, localized taxonomy import, category-based Google Product Category mapping, and scheduled feed generation.

## Overview

`MyCompany_GoogleFeed` generates Google Merchant Center compatible XML feeds from Magento catalog data.

The module is designed for stores that need:

- Separate feeds per store view
- Localized product content in each feed
- Flexible product filtering
- Attribute mapping through admin configuration
- Google Product Category taxonomy import and assignment
- Manual and cron-based file generation

## Key Features

- **Multi-store feed generation** for all active or selected store views
- **Direct live feed URLs** per store view
- **Saved XML files** in `pub/media/googlefeed/`
- **CLI taxonomy import** for Google Product Categories
- **Category-level Google Product Category assignment** with inheritance
- **Attribute mapping** for brand, GTIN, MPN, condition, color, size, gender, age group
- **`g:identifier_exists` support** when GTIN is missing
- **Optional HTTP Basic Authentication** for the feed endpoint
- **Cron automation** with configurable frequency and store selection
- **Admin feed management page** under Marketing

## Requirements

- Magento 2.4.x
- PHP 7.4, 8.0, 8.1, or 8.2

## Package Information

- **Module name**: `MyCompany_GoogleFeed`
- **Composer package**: `mycompany/magento2-google-feed`
- **Current package version**: `1.0.3`
- **License**: `MIT`

## Installation

### Composer

```bash
composer require mycompany/magento2-google-feed
php bin/magento module:enable MyCompany_GoogleFeed
php bin/magento setup:upgrade
php bin/magento setup:di:compile
php bin/magento cache:flush
```

### Manual installation

1. Copy the module to `app/code/MyCompany/GoogleFeed`
2. Enable it and run Magento upgrade commands:

```bash
php bin/magento module:enable MyCompany_GoogleFeed
php bin/magento setup:upgrade
php bin/magento setup:di:compile
php bin/magento cache:flush
```

## Quick Start

### 1. Import Google taxonomy

Run the CLI command after installation:

```bash
php bin/magento mycompany:googlefeed:import-taxonomy
```

This command:

- Detects store view locales
- Downloads the Google Product Category taxonomy for each unique locale
- Stores categories in `mycompany_googlefeed_taxonomy`
- Avoids reprocessing duplicate locales in the same run

### 2. Configure the module

Open:

`Stores -> Configuration -> MyCompany -> Google Feed`

### 3. Enable the feed for a store view

At minimum configure:

- `Enable Google Feed`
- `Feed Title`
- `Feed Description`
- `Include Categories`

### 4. Test the live feed URL

Examples:

```text
https://yourstore.com/googlefeed/feed/index
https://yourstore.com/googlefeed/feed/index?store=en
https://yourstore.com/googlefeed/feed/index?store=uk
```

### 5. Generate XML files for Merchant Center

Use:

`Marketing -> Google Feed -> Feed Management`

Then click `Generate & Save Feed Files`.

Generated files are saved in:

```text
pub/media/googlefeed/
```

Example file names:

```text
feed_english_store_en_en.xml
feed_ukrainian_store_uk_uk.xml
```

## Configuration Reference

Configuration path:

`Stores -> Configuration -> MyCompany -> Google Feed`

### General Settings

- **Feed URL**
  - Read-only helper field with store-specific live feed URLs
- **Enable Google Feed**
  - Enables feed generation for the current scope
- **Feed Title**
  - XML channel title
- **Feed Description**
  - XML channel description
- **Enable HTTP Basic Authentication**
  - Protects feed access with basic auth
- **Authentication Username**
  - Used when auth is enabled
- **Authentication Password**
  - Stored encrypted in Magento config

### Feed Settings

- **Products Limit**
  - Maximum number of exported products
- **Include Out of Stock Products**
  - Includes out-of-stock items when enabled
- **Image Size**
  - Main product image size in pixels
- **Feed Currency**
  - Uses configured currency or store default when empty
- **Default Product Condition**
  - Default value for `g:condition`

### Product Filters

- **Include Categories**
  - Main export filter
  - If not configured, the module intentionally exports no products by default
- **Minimum Price**
  - Excludes products below the configured price
- **Maximum Price**
  - Excludes products above the configured price
- **Exclude Categories**
  - Removes products from selected categories

### Product Attributes Mapping

- **Brand Attribute** -> `g:brand`
- **GTIN Attribute** -> `g:gtin`
- **MPN Attribute** -> `g:mpn`
- **Set Identifier Exists to No When GTIN Is Missing** -> `g:identifier_exists=no`
- **Condition Attribute** -> overrides default `g:condition`
- **Color Attribute** -> `g:color`
- **Size Attribute** -> `g:size`
- **Gender Attribute** -> `g:gender`
- **Age Group Attribute** -> `g:age_group`

### Google Product Category Taxonomy

- **Taxonomy URL Pattern**
  - Uses `%s` as the locale placeholder
- **Custom Taxonomy URLs (Advanced)**
  - One row per locale in `locale_code=URL` format
- **Fallback Locale**
  - Used when no taxonomy is available for the store locale
- **Cache Lifetime (hours)**
  - Controls taxonomy download caching

### Automatic Generation (Cron)

- **Enable Automatic Generation**
- **Generation Frequency**
- **Generation Time**
- **Generate Feeds for Stores**
- **Generated Feed Files**

## Google Product Category Mapping

The module supports Google Product Category assignment at category level and resolves values with inheritance.

### Category assignment workflow

1. Go to `Catalog -> Categories`
2. Open a category
3. Expand `Display Settings`
4. Use the `Google Product Category` picker
5. Save the category

### Resolution priority

1. Product attribute `mycompany_google_product_category`
2. Direct category assignment
3. Parent category assignment
4. No value exported

### Taxonomy storage

Imported taxonomy data is stored in:

```text
mycompany_googlefeed_taxonomy
```

Each record stores locale, Google category ID, hierarchy level, parent ID, category name, and full category path.

## Admin Pages

### Marketing -> Google Feed -> Feed Management

Provides:

- Store view overview
- Feed status visibility
- Direct feed URLs
- Manual generation of saved XML files

### Stores -> Configuration -> MyCompany -> Google Feed

Provides:

- Per-scope configuration
- Generated files listing
- Attribute mapping
- Taxonomy source configuration
- Cron settings

## Console Commands

### Import taxonomy

```bash
php bin/magento mycompany:googlefeed:import-taxonomy
```

Run this command:

- After initial installation
- After adding a new locale/store view
- When refreshing taxonomy data from Google

## Feed Output

The XML feed includes standard Google Shopping fields such as:

- `g:id`
- `g:title`
- `g:description`
- `g:link`
- `g:image_link`
- `g:additional_image_link`
- `g:price`
- `g:availability`
- `g:condition`
- `g:brand`
- `g:gtin`
- `g:mpn`
- `g:identifier_exists`
- `g:google_product_category`
- `g:product_type`
- `g:color`
- `g:size`
- `g:gender`
- `g:age_group`

## Multi-Store Notes

- Each store view can have its own feed settings
- Feed URLs support the `?store={code}` parameter
- Saved files include store name, store code, and locale language prefix
- Product names, descriptions, URLs, prices, and category labels are store-aware

## Security Notes

The module includes:

- XML value sanitization
- URL validation
- ACL protection for admin actions
- Optional HTTP Basic Authentication for feed access

If you enable feed authentication, make sure your Google Merchant Center ingestion method supports it in your environment. If Google reports authentication issues, disable auth for the feed endpoint and retest.

## Troubleshooting

### Feed is empty

- Verify `Enable Google Feed` is enabled for the current store scope
- Verify products are enabled and visible
- Verify `Include Categories` contains at least one category
- Check price filters
- Check stock settings

### Feed opens with wrong store data

- Use the correct store code in `?store=`
- Verify localized product content exists for that store view
- Verify store-specific config values are not inherited unexpectedly

### Google Product Category picker is empty

- Run `php bin/magento mycompany:googlefeed:import-taxonomy`
- Verify taxonomy exists for the store locale or fallback locale
- Flush Magento cache

### Saved feed files are missing

- Verify the feed is enabled for the store
- Verify cron settings or run manual generation from admin
- Check write permissions for `pub/media/googlefeed/`

### Brand / GTIN / MPN fields are missing

- Verify the correct product attributes are selected in configuration
- Verify products actually contain values for those attributes

## Documentation

Detailed user guides are available here:

- English: `docs/en/USER_GUIDE.md`
- Ukrainian: `docs/uk/USER_GUIDE.md`

## Changelog Summary

### v1.0.3

- Added Google Product Category taxonomy import
- Added category-level assignment with inheritance
- Added localized taxonomy storage
- Added `additional_image_link` and `product_type`
- Added multi-language taxonomy fallback logic

### v1.0.2

- Added multi-store feed file generation
- Added Feed Management admin page
- Added store selection for cron generation

### v1.0.1

- Added security hardening
- Added product filters
- Added attribute dropdown mapping
- Added cron support

### v1.0.0

- Initial release
