# MyCompany Google Feed — User Guide

## Table of Contents

1. [Installation & Setup](#1-installation--setup)
2. [Module Configuration](#2-module-configuration)
3. [Working with Google Product Categories](#3-working-with-google-product-categories)
4. [Assigning Categories to Magento Categories](#4-assigning-categories-to-magento-categories)
5. [Working with the Feed](#5-working-with-the-feed)
6. [Multi-Store Setup](#6-multi-store-setup)
7. [Automated Feed Generation (Cron)](#7-automated-feed-generation-cron)
8. [Attribute Mapping](#8-attribute-mapping)
9. [Troubleshooting](#9-troubleshooting)
10. [Feed XML Structure](#10-feed-xml-structure)

---

## 1. Installation & Setup

### Step 1 — Enable the module

Run the following commands inside your Magento container (or on the server):

```bash
php bin/magento module:enable MyCompany_GoogleFeed
php bin/magento setup:upgrade
php bin/magento setup:di:compile
php bin/magento cache:flush
```

### Step 2 — Import Google Product Category Taxonomy

This is **required** before you can assign Google Product Categories to categories or products.

```bash
php bin/magento mycompany:googlefeed:import-taxonomy
```

What this command does:
- Scans all store views in your Magento installation
- Identifies unique locales (e.g. `en_US`, `uk_UA`, `de_DE`)
- Downloads the official Google Product Category taxonomy for each language from Google servers
- Saves the taxonomy to the database

Expected output:

```
Starting Google Product Category Taxonomy import...

Found 2 store view(s) to analyze

[1/2] Store: English Store (ID: 1, Code: en)
        Locale: en_US
        Normalized locale: en-US
        Download URL: https://www.google.com/basepages/producttype/taxonomy-with-ids.en-US.txt
        Categories found: 5627
        Saving to database... Done

[2/2] Store: Ukrainian Store (ID: 2, Code: uk)
        Locale: uk_UA
        Normalized locale: uk-UA
        Download URL: https://www.google.com/basepages/producttype/taxonomy-with-ids.uk-UA.txt
        Categories found: 4832
        Saving to database... Done

Import completed!
Processed 2 unique locale(s): en-US, uk-UA
```

> **Re-run this command** whenever you add a new store view with a different language, or to update categories to the latest Google taxonomy.

---

## 2. Module Configuration

Navigate to: **Stores → Configuration → MyCompany → Google Feed**

### General Settings

| Setting | Description |
|---|---|
| Feed URL | Read-only field with direct live feed URLs for store views |
| Enable Google Feed | Turns feed generation on/off for this store view |
| Feed Title | Title shown in the XML feed channel |
| Feed Description | Description shown in the XML feed channel |
| Enable HTTP Basic Authentication | Protects the feed endpoint with username/password |
| Authentication Username | Username for feed access when authentication is enabled |
| Authentication Password | Encrypted password for feed access when authentication is enabled |

### Feed Settings

| Setting | Description | Default |
|---|---|---|
| Products Limit | Maximum number of products per feed | 1000 |
| Include Out of Stock | Whether to include out-of-stock products | No |
| Image Size | Product image size in pixels | 800 |
| Currency | Feed currency (empty = store default) | — |
| Default Product Condition | `new` / `refurbished` / `used` | new |

### Product Filters

| Setting | Description |
|---|---|
| Include Categories | Only export products from selected categories |
| Exclude Categories | Exclude products from selected categories |
| Minimum Price | Skip products cheaper than this |
| Maximum Price | Skip products more expensive than this |

> **Note:** If both Include and Exclude are set, Include is the main export filter, while Exclude removes products from the selected set.

### Saving Configuration

After making changes, click **Save Config** in the top-right corner. Then flush cache:

```bash
php bin/magento cache:flush
```

---

## 3. Working with Google Product Categories

Google Product Categories is an official Google taxonomy with thousands of hierarchical categories (e.g. *Electronics > Communications > Telephony > Mobile Phones*). Including them in your feed improves product visibility and ad targeting in Google Shopping.

### How the taxonomy is stored

After running `import-taxonomy`, categories are stored in the database table `mycompany_googlefeed_taxonomy` with:
- Numeric ID (e.g. `267`)
- Full path label (e.g. `Electronics > Communications > Telephony > Mobile Phones`)
- Locale (e.g. `en-US`, `uk-UA`)

### Resolution priority

The module automatically resolves which Google Product Category to use for each product using this priority:

```
1. Product attribute `mycompany_google_product_category`
        ↓ (if not set)
2. Product category Google Product Category
        ↓ (if not set)
3. Parent category → ... → Root category
        ↓ (if nothing found)
4. Field omitted from feed
```

**Example:**

```
Electronics [Google Category: 222 — Electronics]
  └─ Phones [Google Category: 267 — Mobile Phones]
      └─ Smartphones [No assignment → inherits 267]
          └─ Product A [No assignment → inherits 267]
          └─ Product B [Product attribute set → uses own value]
```

---

## 4. Assigning Categories to Magento Categories

This sets the Google Product Category at the category level. All products in this category (and child categories without their own assignment) will inherit it.

### Step-by-step

1. Go to **Catalog → Categories** in the admin panel
2. Select the category you want to configure in the left tree
3. Scroll down to the **Display Settings** section and expand it
4. Find the **Google Product Category** field
5. Click **Select Category** button — a panel will slide open with the full Google taxonomy tree
6. Use the **search box** at the top to find your category quickly (e.g. type `mobile`)
7. Click **Select** next to the desired category
8. The field will show the category name; the ID is saved automatically
9. Click **Save** to save the Magento category

### Using the Category Picker

- **Search**: Type any keyword in the search box — results are highlighted and filtered in real time
- **Browse**: Click the arrow next to a parent category to expand it and see children
- **Select**: Click the **Select** button next to any category
- **Clear**: Use the **Clear** button next to the field to remove the assignment

---

## 5. Working with the Feed

### Viewing the Feed (Direct URL)

Access the live feed for any store view:

```
https://yourstore.com/googlefeed/feed/index
https://yourstore.com/googlefeed/feed/index?store=en
https://yourstore.com/googlefeed/feed/index?store=uk
```

Replace `en` or `uk` with your store code.

### Generating & Saving Feed Files

1. Go to **Marketing → Google Feed → Feed Management**
2. You will see a list of all store views with their status
3. Click **Generate & Save Feed Files** — this creates XML files for all enabled stores
4. Files are saved to `pub/media/googlefeed/` with descriptive names:
   - `feed_english_store_en_en.xml`
   - `feed_ukrainian_store_uk_uk.xml`
   - Format: `feed_{store_name}_{store_code}_{language_code}.xml`

### Adding Feed to Google Merchant Center

1. Log in to [Google Merchant Center](https://merchants.google.com)
2. Go to **Products → Feeds**
3. Click **+** to add a new feed
4. Set country, language, and feed name
5. Choose **Scheduled fetch** or **Upload**
6. For Scheduled fetch, enter the saved feed file URL:
   ```
   https://yourstore.com/media/googlefeed/feed_english_store_en_en.xml
   ```
7. Set fetch frequency (daily recommended)
8. Save and wait for Google to process the feed

### Feed Authentication

If you enable **HTTP Basic Authentication** in module configuration, the live feed URL will require credentials.

- Use this only if your feed consumer supports basic auth
- If Google Merchant Center reports authentication errors, disable feed authentication and retest

---

## 6. Multi-Store Setup

Each store view gets its own feed with localized content (product names, descriptions, URLs, prices).

### Step-by-step for multi-store

1. **Import taxonomy** — run once, covers all locales automatically:
   ```bash
   php bin/magento mycompany:googlefeed:import-taxonomy
   ```

2. **Configure each store view separately:**
   - In admin top-left, switch to the store view
   - Go to **Stores → Configuration → MyCompany → Google Feed**
   - Uncheck `Use Website` / `Use Default` for settings you want to customize
   - Enable the feed, set filters specific to that store
   - Save

3. **Assign Google Product Categories** in the locale of each store:
   - When editing a category or product, switch to that store view first
   - The picker shows categories in the locale of that store view

4. **Generate feeds:**
   - Via cron (recommended): configure in **Automatic Generation** section
   - Via admin: **Marketing → Google Feed → Feed Management → Generate & Save Feed Files**

5. **Register each feed in Google Merchant Center** with the correct language/country:
   - English: `https://yourstore.com/media/googlefeed/feed_english_store_en_en.xml`
   - Ukrainian: `https://yourstore.com/media/googlefeed/feed_ukrainian_store_uk_uk.xml`

---

## 7. Automated Feed Generation (Cron)

Configure in: **Stores → Configuration → MyCompany → Google Feed → Automatic Generation**

| Setting | Description |
|---|---|
| Enable Automatic Generation | Turns scheduled generation on/off |
| Generation Frequency | `daily` / `twice daily` / `every 6h` / `hourly` / `weekly` |
| Generation Time | Time to run in `HH:MM` format (e.g. `03:00`) |
| Generate Feeds for Stores | Select specific stores or leave empty for all active stores |
| Generated Feed Files | Displays generated files saved in `pub/media/googlefeed/` |

### How to verify cron is working

```bash
php bin/magento cron:run
```

Then check `var/log/system.log` for entries related to `GoogleFeed`.

---

## 8. Attribute Mapping

Configure in: **Stores → Configuration → MyCompany → Google Feed → Product Attributes Mapping**

All fields use **dropdown selection** — you choose from existing Magento product attributes.

| Feed Field | Mapping Setting | Google Feed XML Tag |
|---|---|---|
| Brand | Brand Attribute | `g:brand` |
| GTIN/UPC/EAN | GTIN Attribute | `g:gtin` |
| MPN | MPN Attribute | `g:mpn` |
| Missing GTIN handling | Set Identifier Exists to No When GTIN Is Missing | `g:identifier_exists` |
| Condition | Condition Attribute | `g:condition` |
| Color | Color Attribute | `g:color` |
| Size | Size Attribute | `g:size` |
| Gender | Gender Attribute | `g:gender` |
| Age Group | Age Group Attribute | `g:age_group` |

### How to map an attribute

1. Go to **Stores → Configuration → MyCompany → Google Feed → Product Attributes Mapping**
2. For each field, open the dropdown and select the corresponding attribute from your catalog
3. If the attribute doesn't exist, create it first in **Stores → Attributes → Product**
4. Save config and flush cache

When **Set Identifier Exists to No When GTIN Is Missing** is enabled, products without GTIN are exported with `g:identifier_exists` set to `no`.

---

## 9. Troubleshooting

### Feed is empty
- Check that **Enable Google Feed** is set to **Yes** for the store view
- Verify products are **enabled** and **visible** in catalog
- Check **Include Categories** filter — if not configured, the module exports no products by default
- Verify **Minimum/Maximum Price** filters don't exclude all products

### Google Product Category not showing in category form
```bash
php bin/magento cache:flush
php bin/magento mycompany:googlefeed:import-taxonomy
```

### Category picker shows no results / is empty
- The taxonomy for this store's locale was not imported
- Run: `php bin/magento mycompany:googlefeed:import-taxonomy`
- Check the database: `SELECT COUNT(*) FROM mycompany_googlefeed_taxonomy;`

### Saved Google Product Category not showing on category form reload
- Flush cache: `php bin/magento cache:flush`

### Feed shows wrong language
- Make sure you're accessing the feed with the correct store code: `?store=uk`
- Products must have localized content saved for that specific store view
- Check `Use Default Value` is unchecked for product name/description in that store view

### Feed files not generated
- Check `pub/media/googlefeed/` directory exists and is writable:
  ```bash
  chmod 775 pub/media/googlefeed/
  ```
- Verify the feed is enabled for the target store view
- Verify **Enable Automatic Generation** is set to **Yes**
- Check cron is running: `php bin/magento cron:run`
- Review: `var/log/system.log`

### Feed returns authentication error
- Check whether **Enable HTTP Basic Authentication** is enabled in configuration
- Verify the username and password
- If Google Merchant Center cannot access the feed, disable authentication and test again

### Performance issues with large catalogs
- Reduce **Products Limit** in configuration
- Enable the feed cache: `php bin/magento cache:enable googlefeed`
- Use cron generation (runs during off-peak hours) instead of on-demand
- Use category filters to export only relevant products

---

## 10. Feed XML Structure

The module generates XML in Google Shopping format with all required and recommended fields:

### Required Fields

| XML Tag | Description | Source |
|---|---|---|
| `g:id` | Unique identifier | Product SKU |
| `g:title` | Product title | Store view product name |
| `g:description` | Product description | Short/full description |
| `g:link` | Product URL | Store view product URL |
| `g:image_link` | Main image URL | Product base image |
| `g:price` | Price with currency | Store view price |
| `g:availability` | Stock availability | Inventory status |
| `g:condition` | Product condition | Attribute or default config |

### Recommended Fields

| XML Tag | Description | Source |
|---|---|---|
| `g:brand` | Brand | Attribute mapping |
| `g:gtin` | GTIN/UPC/EAN | Attribute mapping |
| `g:mpn` | Manufacturer part number | Attribute mapping |
| `g:identifier_exists` | Identifier presence flag | GTIN handling setting |
| `g:google_product_category` | Google category | Product → Category → Parent category |
| `g:product_type` | Product type | Store category path |
| `g:additional_image_link` | Additional images | Product gallery |

### Apparel Fields

| XML Tag | Description |
|---|---|
| `g:color` | Color |
| `g:size` | Size |
| `g:gender` | Gender |
| `g:age_group` | Age group |
