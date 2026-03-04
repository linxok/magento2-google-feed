# MyCompany Google Feed — User Guide

## Table of Contents

1. [Installation & Setup](#1-installation--setup)
2. [Module Configuration](#2-module-configuration)
3. [Working with Google Product Categories](#3-working-with-google-product-categories)
4. [Assigning Categories to Magento Categories](#4-assigning-categories-to-magento-categories)
5. [Assigning Categories to Products](#5-assigning-categories-to-products)
6. [Working with the Feed](#6-working-with-the-feed)
7. [Multi-Store Setup](#7-multi-store-setup)
8. [Automated Feed Generation (Cron)](#8-automated-feed-generation-cron)
9. [Attribute Mapping](#9-attribute-mapping)
10. [Troubleshooting](#10-troubleshooting)

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
        Locale: en_US → en-US
        Fetching taxonomy... OK (5627 categories)
        Saving to database... Done

[2/2] Store: Ukrainian Store (ID: 2, Code: uk)
        Locale: uk_UA → uk-UA
        Fetching taxonomy... OK (4832 categories)
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
| Enable Google Feed | Turns feed generation on/off for this store view |
| Feed Title | Title shown in the XML feed channel |
| Feed Description | Description shown in the XML feed channel |

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

> **Note:** If both Include and Exclude are set, Include takes priority.

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

### Inheritance rules

The module automatically resolves which Google Product Category to use for each product using this priority chain (highest wins):

```
1. Product's own Google Product Category attribute
        ↓ (if not set)
2. Product's direct category Google Product Category
        ↓ (if not set)
3. Parent category's Google Product Category
        ↓ (if not set, traverses up the category tree)
4. Grandparent category → ... → Root category
        ↓ (if nothing found)
5. Field omitted from feed
```

**Example:**

```
Electronics [Google Category: 222 — Electronics]
  └─ Phones [Google Category: 267 — Mobile Phones]
      └─ Smartphones [No assignment → inherits 267]
          └─ Product A [No assignment → inherits 267]
          └─ Product B [Google Category: 268 — Smartphones → uses own]
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
6. Use the **search box** at the top to find your category quickly (e.g. type "mobile")
7. Click **Select** next to the desired category
8. The field will show the category name; the ID is saved automatically
9. Click **Save** to save the Magento category

![Category Assignment](../screenshots/category-assignment.png)

### Using the Category Picker

- **Search**: Type any keyword in the search box — results are highlighted and filtered in real time
- **Browse**: Click the ▶ arrow next to a parent category to expand it and see children
- **Select**: Click the blue **Select** button next to any category
- **Clear**: Use the **Clear** button next to the field to remove the assignment

---

## 5. Assigning Categories to Products

You can assign a Google Product Category directly to an individual product. This overrides any category-level assignment.

### Step-by-step

1. Go to **Catalog → Products**
2. Open any product for editing
3. Go to the **General** or **Search Engine Optimization** tab (depending on your Magento version and configuration)
4. Find the **Google Product Category** attribute
5. Click **Select Category** and use the picker (same as in categories)
6. Click **Save** to save the product

> **When to use product-level assignment:**
> - The product belongs to a category with a general assignment but needs a more specific one
> - The product doesn't fit neatly into any of its category's Google categories
> - You want to override inheritance for a specific product

---

## 6. Working with the Feed

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

### Downloading a Feed File

Navigate to: **Marketing → Google Feed → Download Feed XML**

This downloads the XML feed for the currently selected store view.

### Adding Feed to Google Merchant Center

1. Log in to [Google Merchant Center](https://merchants.google.com)
2. Go to **Products → Feeds**
3. Click **+** to add a new feed
4. Set country, language, and feed name
5. Choose **Scheduled fetch** or **Upload**
6. For Scheduled fetch, enter the feed URL:
   ```
   https://yourstore.com/media/googlefeed/feed_english_store_en_en.xml
   ```
7. Set fetch frequency (daily recommended)
8. Save and wait for Google to process the feed

---

## 7. Multi-Store Setup

Each store view gets its own feed with localized content (product names, descriptions, URLs, prices).

### Step-by-step for multi-store

1. **Import taxonomy** — run once, covers all locales automatically:
   ```bash
   php bin/magento mycompany:googlefeed:import-taxonomy
   ```

2. **Configure each store view separately:**
   - In admin top-left, switch to the store view
   - Go to **Stores → Configuration → MyCompany → Google Feed**
   - Uncheck "Use Website" / "Use Default" for settings you want to customize
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

## 8. Automated Feed Generation (Cron)

Configure in: **Stores → Configuration → MyCompany → Google Feed → Automatic Generation**

| Setting | Description |
|---|---|
| Enable Automatic Generation | Turns scheduled generation on/off |
| Generation Frequency | `daily` / `twice daily` / `every 6h` / `hourly` / `weekly` |
| Generation Time | Time to run in `HH:MM` format (e.g. `03:00`) |
| Generate Feeds for Stores | Select specific stores or leave empty for all active stores |
| Save Feed to File | Base file path (e.g. `googlefeed/feed.xml`) |

### How to verify cron is working

```bash
php bin/magento cron:run
```

Then check `var/log/system.log` for entries related to `GoogleFeed`.

---

## 9. Attribute Mapping

Configure in: **Stores → Configuration → MyCompany → Google Feed → Product Attributes Mapping**

All fields use **dropdown selection** — you choose from existing Magento product attributes.

| Feed Field | Mapping Setting | Google Feed XML Tag |
|---|---|---|
| Brand | Brand Attribute | `g:brand` |
| GTIN/UPC/EAN | GTIN Attribute | `g:gtin` |
| MPN | MPN Attribute | `g:mpn` |
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

---

## 10. Troubleshooting

### Feed is empty
- Check that **Enable Google Feed** is set to **Yes** for the store view
- Verify products are **enabled** and **visible** in catalog
- Check **Include Categories** filter — if set, only those categories are exported
- Verify **Minimum/Maximum Price** filters don't exclude all products

### Google Product Category not showing in category/product form
```bash
php bin/magento cache:flush
php bin/magento mycompany:googlefeed:import-taxonomy
```

### Category picker shows no results / is empty
- The taxonomy for this store's locale was not imported
- Run: `php bin/magento mycompany:googlefeed:import-taxonomy`
- Check the database: `SELECT COUNT(*) FROM mycompany_googlefeed_taxonomy;`

### Saved Google Product Category not showing on form reload
- This is resolved in the current version — the label is fetched automatically on form load
- If still occurring, flush cache: `php bin/magento cache:flush`

### Feed shows wrong language
- Make sure you're accessing the feed with the correct store code: `?store=uk`
- Products must have localized content saved for that specific store view
- Check **Use Default Value** is unchecked for product name/description in that store view

### Feed files not generated
- Check `pub/media/googlefeed/` directory exists and is writable:
  ```bash
  chmod 775 pub/media/googlefeed/
  ```
- Verify **Enable Automatic Generation** is set to **Yes**
- Check cron is running: `php bin/magento cron:run`
- Review: `var/log/system.log`

### Performance issues with large catalogs
- Reduce **Products Limit** in configuration
- Enable the feed cache: `php bin/magento cache:enable googlefeed`
- Use cron generation (runs during off-peak hours) instead of on-demand
- Use category filters to export only relevant products
