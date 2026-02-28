# Google Shopping Feed for Magento 2

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![Magento 2](https://img.shields.io/badge/Magento-2.4-orange.svg)](https://magento.com/)

Google Shopping Feed Generator for Magento 2 with multi-store and multi-language support.

## Overview

This module generates Google Shopping XML feeds for Magento 2 stores with full multi-language support, comprehensive product filtering, and automated generation capabilities.

## Features

- ✅ **Multi-Store/Multi-Language Support** - Automatic feed generation for each store view with localized content
- ✅ **Automated Generation** - Cron-based scheduled feed generation with configurable frequency
- ✅ **Manual Generation** - Admin panel interface for on-demand feed file creation
- ✅ **Advanced Filtering** - Filter products by categories, price range, stock status
- ✅ **Attribute Mapping** - Dropdown selection for brand, GTIN, MPN, color, size, gender, age group
- ✅ **File Management** - View and download generated feeds directly from admin panel
- ✅ **Security** - XML injection prevention, URL validation, ACL permissions
- ✅ **Performance** - Cache support for optimized feed generation
- ✅ **Descriptive File Names** - Files include store name, code, and language (e.g., `feed_english_store_en_en.xml`)

## Requirements

- Magento 2.4.x or higher
- PHP 7.4, 8.0, 8.1, or 8.2

## Installation

### Method 1: Composer (Recommended)

```bash
composer require mycompany/magento2-google-feed
php bin/magento module:enable MyCompany_GoogleFeed
php bin/magento setup:upgrade
php bin/magento setup:di:compile
php bin/magento cache:flush
```

### Method 2: Manual Installation

1. Download the module from GitHub
2. Extract to `app/code/MyCompany/GoogleFeed`
3. Enable the module:

```bash
php bin/magento module:enable MyCompany_GoogleFeed
php bin/magento setup:upgrade
php bin/magento setup:di:compile
php bin/magento cache:flush
```

## Configuration

Navigate to: **Stores → Configuration → MyCompany → Google Feed**

### General Settings
- **Enable Google Feed**: Enable/disable feed generation
- **Feed Title**: Title for the feed channel
- **Feed Description**: Description for the feed channel

### Feed Settings
- **Products Limit**: Maximum number of products (default: 1000)
- **Include Out of Stock Products**: Include/exclude out of stock items
- **Image Size**: Product image size in pixels (default: 800)
- **Currency**: Currency for prices (empty = store default)
- **Default Product Condition**: new/refurbished/used

### Product Filters
- **Include Categories**: Export only selected categories
- **Exclude Categories**: Exclude specific categories
- **Minimum Price**: Filter products by minimum price
- **Maximum Price**: Filter products by maximum price

### Product Attributes Mapping
All fields use dropdown selection (no manual code entry):
- **Brand Attribute**: Product brand
- **GTIN Attribute**: GTIN/UPC/EAN code
- **MPN Attribute**: Manufacturer part number
- **Condition Attribute**: Product condition (overrides default)
- **Color Attribute**: Product color
- **Size Attribute**: Product size
- **Gender Attribute**: male/female/unisex
- **Age Group Attribute**: Target age group
- **Google Product Category Attribute**: Google category

### Automatic Generation (Cron)
- **Enable Automatic Generation**: Enable scheduled feed generation
- **Generation Frequency**: daily/twice_daily/every_6h/hourly/weekly
- **Generation Time**: Time to run (HH:MM format)
- **Generate Feeds for Stores**: Select specific stores or leave empty for all active stores
- **Save Feed to File**: Base path (e.g., googlefeed/feed.xml) - store name, code, and language will be added automatically
- **Generated Feed Files**: View and download all generated feed files directly from admin configuration

## Usage

### Frontend Access

Access the feed directly via URL:
```
https://yourstore.com/googlefeed/feed/index
```

### Multi-Language/Multi-Store Setup

**Automatic Multi-Store Feed Generation:**

The module automatically generates separate feeds for each store view:

1. **Via Cron (Recommended for production):**
   - Enable automatic generation in Configuration
   - Select stores in "Generate Feeds for Stores" (or leave empty for all)
   - Files are saved with descriptive names including store name, code, and language:
     - `pub/media/googlefeed/feed_english_store_en_en.xml` (English store)
     - `pub/media/googlefeed/feed_ukrainian_store_uk_uk.xml` (Ukrainian store)
     - `pub/media/googlefeed/feed_german_store_de_de.xml` (German store)
   - Format: `feed_{store_name}_{store_code}_{language_code}.xml`

2. **Manual File Generation:**
   - Go to **Marketing → Google Feed → Feed Management**
   - Click **"Generate & Save Feed Files"**
   - All enabled stores will have feeds generated automatically
   - Files saved to `pub/media/` with store-specific names

3. **Direct URL Access (for testing):**
   - English: `https://yourstore.com/googlefeed/feed/index?store=en`
   - Ukrainian: `https://yourstore.com/googlefeed/feed/index?store=uk`
   - German: `https://yourstore.com/googlefeed/feed/index?store=de`

**Configure in Google Merchant Center:**
1. Create separate feed for each language/country
2. Set appropriate country and language
3. Use store-specific feed file URL:
   - `https://yourstore.com/media/googlefeed/feed_english_store_en_en.xml`
   - `https://yourstore.com/media/googlefeed/feed_ukrainian_store_uk_uk.xml`
   - Format: `feed_{store_name}_{store_code}_{language_code}.xml`

**Per-Store Configuration:**
- Switch store view in admin (top-left corner)
- Configure store-specific settings
- Localize product names, descriptions, categories
- Each store can have different:
  - Product filters (categories, price ranges)
  - Attribute mappings
  - Currency settings
  - Feed title and description

### Admin Panel

**Feed Management Page:**

Navigate to: **Marketing → Google Feed → Feed Management**

This page provides:
- Overview of all store views and their feed status
- Direct links to feed URLs for each store
- **Generate & Save Feed Files** button - creates feed files for all configured stores
- Manual feed file generation with multi-store support

**Download Feed XML:**

Navigate to: **Marketing → Google Feed → Download Feed XML**

Downloads XML feed for current store view.

## Feed Format

The module generates XML in Google Shopping format with all required and recommended fields:

**Required Fields:**
- `g:id` (SKU)
- `g:title` (product name)
- `g:description` (product description)
- `g:link` (product URL)
- `g:image_link` (product image)
- `g:price` (price with currency)
- `g:availability` (in stock/out of stock)
- `g:condition` (new/refurbished/used)

**Recommended Fields:**
- `g:brand` (product brand)
- `g:gtin` (GTIN/UPC/EAN)
- `g:mpn` (manufacturer part number)
- `g:google_product_category` (Google category)
- `g:product_type` (store category)

**Additional Fields (apparel):**
- `g:color`, `g:size`, `g:gender`, `g:age_group`

## Security

**Implemented Protections:**
- ✅ XML injection prevention (sanitizes all user input)
- ✅ URL validation (only http/https protocols)
- ✅ Store ID validation
- ✅ Generic error messages (no stack traces exposed)
- ✅ Security headers (X-Content-Type-Options: nosniff)
- ✅ ACL permissions for admin functions

**Recommendations:**
- Configure rate limiting for feed endpoint
- Enable Magento cache for feed results
- Monitor access logs for abuse
- Regular security updates

## Performance

- Uses Magento cache system (cache type: `googlefeed`)
- Product collection optimized with proper filtering
- Configurable product limits to prevent memory issues
- Automatic generation via cron for large catalogs

## Troubleshooting

### Feed is empty
- Check if products are enabled and visible
- Verify stock status settings
- Check category filters configuration
- Verify price range filters

### Feed shows wrong language
- Verify store code in URL (`?store=uk`)
- Check products have localized content for store view
- Ensure "Use Default Value" is unchecked for product attributes

### Missing attributes (brand, GTIN, MPN)
- Use dropdown in admin to select correct attributes
- Ensure attributes exist in catalog
- Verify products have values for these attributes

### Products missing in localized feed
- Check product visibility in specific store view
- Verify category assignments for store view
- Check Base URL configuration for store view

### Feed files not generated for all stores
- Check that stores are enabled in "Generate Feeds for Stores" configuration
- Verify each store has "Enable Google Feed" set to Yes
- Check system.log for errors during generation
- Ensure pub/media/googlefeed/ directory is writable

### Different products in different language feeds
- This is expected - each store view can have different:
  - Product visibility settings
  - Category assignments
  - Price ranges (if configured per store)
- Verify product is enabled and visible in specific store view

### Cron not generating files
- Verify cron is running: `php bin/magento cron:run`
- Check cron configuration in System Configuration
- Review var/log/system.log for cron execution logs
- Ensure "Generate Feeds for Stores" includes desired stores

### Performance issues
- Reduce product limit in configuration
- Enable cache: `php bin/magento cache:enable googlefeed`
- Use cron for automatic generation (generates during off-peak hours)
- Generate feeds for stores separately if needed

## Version History

- **v1.0.2** (2026-02-28) - Multi-store enhancement
  - Added automatic multi-store/multi-language feed generation
  - Added store selection in cron configuration
  - Added manual file generation for all stores
  - Added Feed Management admin page
  - Descriptive file naming with store name, code, and language (feed_storename_code_lang.xml)
  - Removed REST API functionality (simplified module)
  - Each store generates separate feed with localized content

- **v1.0.1** (2026-02-28) - Security and feature update
  - Added XML injection prevention
  - Added URL validation
  - Fixed information disclosure
  - Added store ID validation
  - Added dropdown attribute selection
  - Added product filters (categories, price)
  - Added cron support
  - Added multi-language support

- **v1.0.0** - Initial release
