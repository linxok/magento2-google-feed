<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace MyCompany\GoogleFeed\Model;

use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Psr\Log\LoggerInterface;

class FeedGenerator
{
    const GOOGLE_CATEGORY_ATTRIBUTE_CODE = 'mycompany_google_product_category';

    /**
     * @var CollectionFactory
     */
    protected $productCollectionFactory;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var PriceCurrencyInterface
     */
    protected $priceCurrency;

    /**
     * @var StockRegistryInterface
     */
    protected $stockRegistry;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * In-memory category cache keyed by "storeId:categoryId" to avoid N+1 repository loads.
     *
     * @var array
     */
    private $categoryCache = [];

    /**
     * @var CategoryRepositoryInterface
     */
    protected $categoryRepository;

    /**
     * @var GoogleCategoryStorage
     */
    protected $googleCategoryStorage;

    /**
     * @var StoreUrlResolver
     */
    protected $storeUrlResolver;

    /**
     * FeedGenerator constructor.
     * @param CollectionFactory $productCollectionFactory
     * @param StoreManagerInterface $storeManager
     * @param ScopeConfigInterface $scopeConfig
     * @param PriceCurrencyInterface $priceCurrency
     * @param StockRegistryInterface $stockRegistry
     * @param CategoryRepositoryInterface $categoryRepository
     * @param GoogleCategoryStorage $googleCategoryStorage
     * @param StoreUrlResolver $storeUrlResolver
     * @param LoggerInterface $logger
     */
    public function __construct(
        CollectionFactory           $productCollectionFactory,
        StoreManagerInterface       $storeManager,
        ScopeConfigInterface        $scopeConfig,
        PriceCurrencyInterface      $priceCurrency,
        StockRegistryInterface      $stockRegistry,
        CategoryRepositoryInterface $categoryRepository,
        GoogleCategoryStorage       $googleCategoryStorage,
        StoreUrlResolver            $storeUrlResolver,
        LoggerInterface             $logger
    )
    {
        $this->productCollectionFactory = $productCollectionFactory;
        $this->storeManager = $storeManager;
        $this->scopeConfig = $scopeConfig;
        $this->priceCurrency = $priceCurrency;
        $this->stockRegistry = $stockRegistry;
        $this->categoryRepository = $categoryRepository;
        $this->googleCategoryStorage = $googleCategoryStorage;
        $this->storeUrlResolver = $storeUrlResolver;
        $this->logger = $logger;
    }

    /**
     * Generate Google Shopping feed
     * @return string
     */
    public function generateFeed()
    {
        $xml = new \XMLWriter();
        $xml->openMemory();
        $xml->setIndent(true);
        $xml->startDocument('1.0', 'UTF-8');

        $xml->startElement('rss');
        $xml->writeAttribute('version', '2.0');
        $xml->writeAttribute('xmlns:g', 'http://base.google.com/ns/1.0');

        $xml->startElement('channel');

        // Channel information
        $xml->writeElement('title', $this->getConfigValue('googlefeed/general/title'));
        $xml->writeElement('link', $this->storeUrlResolver->getStoreBaseUrl());
        $xml->writeElement('description', $this->getConfigValue('googlefeed/general/description'));

        // Add products
        $products = $this->getProductCollection();
        $includeOutOfStock = $this->getConfigValue('googlefeed/feed/include_out_of_stock');

        foreach ($products as $product) {
            // Fetch stock once per product and reuse it for both filtering and availability.
            $stockItem = $this->stockRegistry->getStockItem($product->getId());
            $isInStock = (bool)$stockItem->getIsInStock();

            // Skip out of stock products if configured
            if (!$includeOutOfStock && !$isInStock) {
                continue;
            }

            $this->addProductToFeed($xml, $product, $isInStock);
        }

        $xml->endElement(); // channel
        $xml->endElement(); // rss
        $xml->endDocument();

        return $xml->outputMemory();
    }

    /**
     * Get product collection for feed
     * @return \Magento\Catalog\Model\ResourceModel\Product\Collection
     */
    public function getProductCollection()
    {
        // Use Collection - it works better with EAV attributes
        $collection = $this->productCollectionFactory->create();

        // Set store context - CRITICAL for EAV attributes
        $storeId = $this->storeManager->getStore()->getId();
        $collection->addStoreFilter($storeId);

        // Add minimal required attributes
        $collection->addAttributeToSelect([
            'entity_id',
            'sku',
            'name',
            'price',
            'description',
            'short_description',
            'image',
            'url_key'
        ]);

        $configuredAttributeCodes = $this->getConfiguredProductAttributeCodes();
        if (!empty($configuredAttributeCodes)) {
            $collection->addAttributeToSelect($configuredAttributeCodes);
        }

        // Add media gallery to load additional images
        $collection->addMediaGalleryData();

        // Filter by status - enabled only
        $collection->addAttributeToFilter('status', 1);

        // Filter by visibility
        $collection->addAttributeToFilter('visibility', [
            'in' => [
                Visibility::VISIBILITY_BOTH,
                Visibility::VISIBILITY_IN_CATALOG,
                Visibility::VISIBILITY_IN_SEARCH
            ]
        ]);

        // Category filters - Include specific categories.
        // Empty selection intentionally exports no products to prevent accidental full catalog export.
        $includeCategoryIds = $this->parseCategoryIds($this->getConfigValue('googlefeed/filters/category_ids'));
        if (empty($includeCategoryIds)) {
            $collection->addCategoriesFilter(['in' => [0]]);
        } else {
            $collection->addCategoriesFilter(['in' => $includeCategoryIds]);
        }

        // Category filters - Exclude specific categories
        $excludeCategoryIds = $this->parseCategoryIds($this->getConfigValue('googlefeed/filters/exclude_categories'));
        if (!empty($excludeCategoryIds)) {
            $collection->addCategoriesFilter(['nin' => $excludeCategoryIds]);
        }

        // Price filters
        $minPrice = $this->getConfigValue('googlefeed/filters/min_price');
        if ($minPrice !== null && $minPrice !== '') {
            $collection->addAttributeToFilter('price', ['gteq' => (float)$minPrice]);
        }

        $maxPrice = $this->getConfigValue('googlefeed/filters/max_price');
        if ($maxPrice !== null && $maxPrice !== '') {
            $collection->addAttributeToFilter('price', ['lteq' => (float)$maxPrice]);
        }

        // Set limit
        $limit = $this->getConfigValue('googlefeed/feed/limit');
        if ($limit && $limit > 0) {
            $collection->setPageSize((int)$limit);
        } else {
            $collection->setPageSize(1000);
        }

        $collection->setCurPage(1);

        return $collection;
    }

    /**
     * Add product to feed
     * @param \XMLWriter $xml
     * @param \Magento\Catalog\Model\Product $product
     * @param bool $isInStock
     */
    protected function addProductToFeed(\XMLWriter $xml, $product, $isInStock)
    {
        $basePrice = $product->getPrice();
        if (($basePrice === null || $basePrice === '') && !$this->hasPositivePrice($product)) {
            $this->logger->warning('Product ' . $product->getSku() . ' has no price set, skipping');
            return;
        }

        $xml->startElement('item');

        try {
            $this->writeItemContent($xml, $product, (float)$basePrice, $isInStock);
        } catch (\Exception $e) {
            $this->logger->error(sprintf(
                'Google Feed: error building feed item for product "%s": %s',
                $product->getSku(),
                $e->getMessage()
            ));
        } finally {
            $xml->endElement(); // item
        }
    }

    /**
     * Write feed item content. The item element is already open.
     *
     * @param \XMLWriter $xml
     * @param \Magento\Catalog\Model\Product $product
     * @param float|string $basePrice
     * @param bool $isInStock
     * @return void
     */
    protected function writeItemContent(\XMLWriter $xml, $product, $basePrice, $isInStock)
    {
        // Basic product information - XMLWriter automatically escapes content
        $xml->writeElement('g:id', $this->sanitizeXmlValue($product->getSku()));
        $xml->writeElement('g:title', $this->sanitizeXmlValue($product->getName()));
        $description = $product->getDescription() ?: $product->getShortDescription() ?: $product->getName();
        $xml->writeElement('g:description', $this->sanitizeXmlValue(strip_tags($description ?? '')));
        $xml->writeElement('g:link', $this->sanitizeUrl($this->getProductFeedUrl($product)));

        // Image
        $imageUrl = $this->sanitizeUrl($this->getProductImageUrl($product));
        if ($imageUrl !== '') {
            $xml->writeElement('g:image_link', $imageUrl);
        }

        // Additional images
        $mediaGallery = $product->getMediaGalleryImages();
        if ($mediaGallery && $mediaGallery->getSize() > 1) {
            $count = 0;
            foreach ($mediaGallery as $image) {
                // Skip the main image and limit to 10 additional images (Google limit)
                if ($image->getFile() === $product->getImage() || $count >= 10) {
                    continue;
                }

                $additionalImageUrl = $this->sanitizeUrl(
                    $this->getProductImageUrl($product, (string)$image->getFile())
                );
                if ($additionalImageUrl !== '') {
                    $xml->writeElement('g:additional_image_link', $additionalImageUrl);
                    $count++;
                }
            }
        }

        // Price: g:price is the regular price, g:sale_price holds the discounted price when present.
        $regularPrice = (float)$basePrice;
        $finalPrice = $this->getProductFinalPrice($product);
        $salePrice = ($finalPrice > 0 && $finalPrice < $regularPrice) ? $finalPrice : null;
        $displayPrice = $regularPrice > 0 ? $regularPrice : $finalPrice;

        $configCurrency = $this->getConfigValue('googlefeed/feed/currency');
        $currency = $configCurrency ?: $this->storeManager->getStore()->getCurrentCurrency()->getCode();

        // Convert price to feed currency if different from base currency
        $baseCurrencyCode = $this->storeManager->getStore()->getBaseCurrencyCode();

        $xml->writeElement('g:price', $this->formatFeedPrice($displayPrice, $currency, $baseCurrencyCode));
        if ($salePrice !== null) {
            $xml->writeElement('g:sale_price', $this->formatFeedPrice($salePrice, $currency, $baseCurrencyCode));
        }

        // Availability
        $xml->writeElement('g:availability', $isInStock ? 'in_stock' : 'out_of_stock');

        // Brand (if attribute exists)
        $brandAttribute = $this->getConfigValue('googlefeed/attributes/brand_attribute');
        $brandValue = $this->getProductAttributeValue($product, $brandAttribute);
        if ($brandValue !== '') {
            $xml->writeElement('g:brand', $this->sanitizeXmlValue($brandValue));
        }

        // GTIN (if attribute exists). Google expects digits only.
        $gtinAttribute = $this->getConfigValue('googlefeed/attributes/gtin_attribute');
        $gtinValue = preg_replace('/\D/', '', $this->getProductAttributeValue($product, $gtinAttribute));
        if ($gtinValue !== '') {
            $xml->writeElement('g:gtin', $this->sanitizeXmlValue($gtinValue));
        }

        // MPN (if attribute exists)
        $mpnAttribute = $this->getConfigValue('googlefeed/attributes/mpn_attribute');
        $mpnValue = $this->getProductAttributeValue($product, $mpnAttribute);
        if ($mpnValue !== '') {
            $xml->writeElement('g:mpn', $this->sanitizeXmlValue($mpnValue));
        }

        // identifier_exists=no only when neither GTIN nor brand+MPN pair is available.
        $identifierExistsNoGtin = $this->getConfigValue('googlefeed/attributes/identifier_exists_no_gtin');
        $hasBrandAndMpn = $brandValue !== '' && $mpnValue !== '';
        if ($gtinValue === '' && !$hasBrandAndMpn && $identifierExistsNoGtin) {
            $xml->writeElement('g:identifier_exists', 'no');
        }

        // Condition mapped to the Google enum: new | refurbished | used.
        $conditionAttribute = $this->getConfigValue('googlefeed/attributes/condition_attribute');
        $condition = $this->mapConditionValue(
            $this->getProductAttributeValue($product, $conditionAttribute)
        );
        if ($condition === null) {
            $condition = $this->mapConditionValue($this->getConfigValue('googlefeed/feed/condition')) ?: 'new';
        }
        $xml->writeElement('g:condition', $condition);

        // Google Product Category
        $googleCategoryValue = $this->resolveGoogleCategoryValue($product);
        if ($googleCategoryValue !== null && $googleCategoryValue !== '') {
            $xml->writeElement(
                'g:google_product_category',
                $this->sanitizeXmlValue($this->mapGoogleCategoryValueForFeed($googleCategoryValue))
            );
        }

        // Product Type (Magento category)
        $categoryIds = $product->getCategoryIds();
        if (!empty($categoryIds)) {
            try {
                $storeId = $this->storeManager->getStore()->getId();
                $rootCategoryId = $this->storeManager->getStore()->getRootCategoryId();

                // Filter categories by current store's root category tree
                $validCategory = null;
                $maxLevel = 0;

                foreach ($categoryIds as $categoryId) {
                    $category = $this->getCategoryById($categoryId, $storeId);
                    if ($category === null) {
                        continue;
                    }

                    $pathIds = explode('/', (string)$category->getPath());

                    // Check if category belongs to current store's root category
                    if (in_array($rootCategoryId, $pathIds)) {
                        // Select the deepest category (most specific)
                        if ($category->getLevel() > $maxLevel) {
                            $maxLevel = $category->getLevel();
                            $validCategory = $category;
                        }
                    }
                }

                if ($validCategory && $validCategory->getName()) {
                    $categoryPath = $this->getCategoryPath($validCategory);
                    $xml->writeElement('g:product_type', $this->sanitizeXmlValue($categoryPath));
                }
            } catch (\Exception $e) {
                $this->logger->error('Error getting category for product ' . $product->getSku() . ': ' . $e->getMessage());
            }
        }

        // Color
        $colorAttribute = $this->getConfigValue('googlefeed/attributes/color_attribute');
        $colorValue = $this->getProductAttributeValue($product, $colorAttribute);
        if ($colorValue !== '') {
            $xml->writeElement('g:color', $this->sanitizeXmlValue($colorValue));
        }

        // Size
        $sizeAttribute = $this->getConfigValue('googlefeed/attributes/size_attribute');
        $sizeValue = $this->getProductAttributeValue($product, $sizeAttribute);
        if ($sizeValue !== '') {
            $xml->writeElement('g:size', $this->sanitizeXmlValue($sizeValue));
        }

        // Gender mapped to the Google enum: male | female | unisex.
        $genderAttribute = $this->getConfigValue('googlefeed/attributes/gender_attribute');
        $genderValue = $this->mapGenderValue(
            $this->getProductAttributeValue($product, $genderAttribute)
        );
        if ($genderValue !== null) {
            $xml->writeElement('g:gender', $genderValue);
        }

        // Age group mapped to the Google enum: newborn | infant | toddler | kids | adult.
        $ageGroupAttribute = $this->getConfigValue('googlefeed/attributes/age_group_attribute');
        $ageGroupValue = $this->mapAgeGroupValue(
            $this->getProductAttributeValue($product, $ageGroupAttribute)
        );
        if ($ageGroupValue !== null) {
            $xml->writeElement('g:age_group', $ageGroupValue);
        }
    }

    /**
     * Get category path for product_type
     * @param \Magento\Catalog\Api\Data\CategoryInterface $category
     * @return string
     */
    protected function getCategoryPath($category)
    {
        $pathIds = explode('/', (string)$category->getPath());
        $categoryNames = [];
        $storeId = $this->storeManager->getStore()->getId();
        $rootCategoryId = $this->storeManager->getStore()->getRootCategoryId();

        foreach ($pathIds as $categoryId) {
            if ($categoryId <= 1 || $categoryId == $rootCategoryId) {
                continue;
            }

            $cat = $this->getCategoryById($categoryId, $storeId);
            if ($cat && $cat->getName()) {
                $categoryNames[] = $cat->getName();
            }
        }

        return implode(' > ', $categoryNames);
    }

    /**
     * Load a category once per store and memoize it to avoid repeated repository lookups.
     *
     * @param int|string $categoryId
     * @param int|string $storeId
     * @return \Magento\Catalog\Api\Data\CategoryInterface|null
     */
    protected function getCategoryById($categoryId, $storeId)
    {
        $categoryId = (int)$categoryId;
        if ($categoryId <= 0) {
            return null;
        }

        $cacheKey = (int)$storeId . ':' . $categoryId;
        if (!array_key_exists($cacheKey, $this->categoryCache)) {
            try {
                $this->categoryCache[$cacheKey] = $this->categoryRepository->get($categoryId, (int)$storeId);
            } catch (\Exception $e) {
                $this->categoryCache[$cacheKey] = null;
            }
        }

        return $this->categoryCache[$cacheKey];
    }

    /**
     * Resolve Google category value from product categories (with parent inheritance)
     *
     * @param \Magento\Catalog\Model\Product $product
     * @return string|null
     */
    protected function resolveGoogleCategoryValue($product)
    {
        return $this->getGoogleCategoryFromCategories($product->getCategoryIds());
    }

    /**
     * Resolve Google category from assigned Magento categories
     *
     * @param array $categoryIds
     * @return string|null
     */
    protected function getGoogleCategoryFromCategories(array $categoryIds)
    {
        if (empty($categoryIds)) {
            return null;
        }

        $bestValue = null;
        $bestSourceLevel = -1;

        $storeId = $this->storeManager->getStore()->getId();

        foreach ($categoryIds as $categoryId) {
            $category = $this->getCategoryById($categoryId, $storeId);
            if ($category === null) {
                continue;
            }

            $resolved = $this->resolveCategoryGoogleCategoryValue($category);
            if ($resolved === null) {
                continue;
            }

            if ((int)$resolved['source_level'] >= $bestSourceLevel) {
                $bestSourceLevel = (int)$resolved['source_level'];
                $bestValue = (string)$resolved['value'];
            }
        }

        return $bestValue;
    }

    /**
     * Resolve Google category value for category with parent inheritance
     *
     * @param \Magento\Catalog\Api\Data\CategoryInterface $category
     * @return array|null
     */
    protected function resolveCategoryGoogleCategoryValue($category)
    {
        $pathIds = array_reverse(explode('/', (string)$category->getPath()));
        $storeId = $this->storeManager->getStore()->getId();

        foreach ($pathIds as $pathCategoryId) {
            $pathCategoryId = (int)$pathCategoryId;
            if ($pathCategoryId <= 1) {
                continue;
            }

            $pathCategory = $this->getCategoryById($pathCategoryId, $storeId);
            if ($pathCategory === null) {
                continue;
            }

            $value = $pathCategory->getData(self::GOOGLE_CATEGORY_ATTRIBUTE_CODE);
            if ($value !== null && $value !== '') {
                return [
                    'value' => (string)$value,
                    'source_level' => (int)$pathCategory->getLevel(),
                ];
            }
        }

        return null;
    }

    /**
     * Convert taxonomy ID to localized path if available
     *
     * @param string $value
     * @return string
     */
    protected function mapGoogleCategoryValueForFeed($value)
    {
        $value = trim((string)$value);
        if ($value === '') {
            return '';
        }

        if (ctype_digit($value)) {
            $path = $this->googleCategoryStorage->getPathById((int)$value, $this->getCurrentLocaleCode());
            if ($path !== null && $path !== '') {
                return $path;
            }
        }

        return $value;
    }

    /**
     * @return string
     */
    protected function getCurrentLocaleCode()
    {
        $locale = (string)$this->scopeConfig->getValue(
            'general/locale/code',
            ScopeInterface::SCOPE_STORE,
            $this->storeManager->getStore()->getId()
        );

        return $locale !== '' ? $locale : 'en_US';
    }

    /**
     * @param \Magento\Catalog\Model\Product $product
     * @param string|null $attributeCode
     * @return string
     */
    protected function getProductAttributeValue($product, $attributeCode)
    {
        if (!$attributeCode) {
            return '';
        }

        $attribute = $product->getResource()->getAttribute($attributeCode);
        if ($attribute && $attribute->usesSource()) {
            $value = $product->getAttributeText($attributeCode);
            if (is_array($value)) {
                $value = implode(', ', array_filter($value));
            }

            return is_scalar($value) ? trim((string)$value) : '';
        }

        $value = $product->getData($attributeCode);

        return is_scalar($value) ? trim((string)$value) : '';
    }

    /**
     * Get the final price (special price / catalog rules) without throwing on bad product types.
     *
     * @param \Magento\Catalog\Model\Product $product
     * @return float
     */
    protected function getProductFinalPrice($product)
    {
        try {
            return max(0.0, (float)$product->getFinalPrice());
        } catch (\Exception $e) {
            return 0.0;
        }
    }

    /**
     * @param \Magento\Catalog\Model\Product $product
     * @return bool
     */
    protected function hasPositivePrice($product)
    {
        return (float)$product->getPrice() > 0 || $this->getProductFinalPrice($product) > 0;
    }

    /**
     * Format a price for the feed, converting to the feed currency when needed.
     *
     * @param float $amount
     * @param string $currency
     * @param string $baseCurrencyCode
     * @return string
     */
    protected function formatFeedPrice($amount, $currency, $baseCurrencyCode)
    {
        $value = $amount;
        if ($currency !== $baseCurrencyCode) {
            $value = $this->priceCurrency->convert($amount, null, $currency);
        }

        return number_format((float)$value, 2, '.', '') . ' ' . $currency;
    }

    /**
     * Map a configured condition value to the Google enum: new | refurbished | used.
     *
     * @param mixed $value
     * @return string|null Null when the value is empty or cannot be mapped.
     */
    protected function mapConditionValue($value)
    {
        $value = $this->normalizeEnumValue($value);
        if ($value === '') {
            return null;
        }

        if ($this->containsAny($value, ['refurb', 'відновл', 'восстанов'])) {
            return 'refurbished';
        }

        if ($this->containsAny($value, ['used', 'second', 'pre-owned', 'preowned', 'б/в', 'б/у', 'вживан'])) {
            return 'used';
        }

        if ($this->containsAny($value, ['new', 'нов'])) {
            return 'new';
        }

        return null;
    }

    /**
     * Map a configured gender value to the Google enum: male | female | unisex.
     *
     * @param mixed $value
     * @return string|null
     */
    protected function mapGenderValue($value)
    {
        $value = $this->normalizeEnumValue($value);
        if ($value === '') {
            return null;
        }

        if ($this->containsAny($value, ['unisex', 'унісекс', 'унисекс'])) {
            return 'unisex';
        }

        if ($this->containsAny($value, ['female', 'woman', 'women', 'girl', 'жін', 'жен', 'дівч'])) {
            return 'female';
        }

        if ($this->containsAny($value, ['male', 'man', 'men', 'boy', 'чолов', 'муж', 'хлоп'])) {
            return 'male';
        }

        return null;
    }

    /**
     * Map a configured age value to the Google enum: newborn | infant | toddler | kids | adult.
     *
     * @param mixed $value
     * @return string|null
     */
    protected function mapAgeGroupValue($value)
    {
        $value = $this->normalizeEnumValue($value);
        if ($value === '') {
            return null;
        }

        if ($this->containsAny($value, ['newborn', 'новонародж', 'немовл'])) {
            return 'newborn';
        }

        if ($this->containsAny($value, ['infant', 'грудн'])) {
            return 'infant';
        }

        if ($this->containsAny($value, ['toddler'])) {
            return 'toddler';
        }

        if ($this->containsAny($value, ['kid', 'child', 'дитя', 'діт', 'ребен', 'ребён'])) {
            return 'kids';
        }

        if ($this->containsAny($value, ['adult', 'доросл', 'взросл'])) {
            return 'adult';
        }

        return null;
    }

    /**
     * @param mixed $value
     * @return string
     */
    private function normalizeEnumValue($value)
    {
        return is_scalar($value) ? mb_strtolower(trim((string)$value), 'UTF-8') : '';
    }

    /**
     * @param string $haystack
     * @param string[] $needles
     * @return bool
     */
    private function containsAny($haystack, array $needles)
    {
        foreach ($needles as $needle) {
            if ($needle !== '' && strpos($haystack, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array
     */
    protected function getConfiguredProductAttributeCodes()
    {
        $attributeCodes = [
            $this->getConfigValue('googlefeed/attributes/brand_attribute'),
            $this->getConfigValue('googlefeed/attributes/gtin_attribute'),
            $this->getConfigValue('googlefeed/attributes/mpn_attribute'),
            $this->getConfigValue('googlefeed/attributes/condition_attribute'),
            $this->getConfigValue('googlefeed/attributes/color_attribute'),
            $this->getConfigValue('googlefeed/attributes/size_attribute'),
            $this->getConfigValue('googlefeed/attributes/gender_attribute'),
            $this->getConfigValue('googlefeed/attributes/age_group_attribute')
        ];

        $attributeCodes = array_filter(array_map(function ($attributeCode) {
            if (!is_scalar($attributeCode)) {
                return '';
            }

            return trim((string)$attributeCode);
        }, $attributeCodes));

        return array_values(array_unique($attributeCodes));
    }

    /**
     * Parse a comma separated list of category IDs.
     *
     * @param mixed $value
     * @return array
     */
    protected function parseCategoryIds($value)
    {
        if ($value === null || !is_scalar($value)) {
            return [];
        }

        $categoryIds = [];
        foreach (explode(',', (string)$value) as $categoryId) {
            $categoryId = trim($categoryId);
            if ($categoryId !== '' && ctype_digit($categoryId)) {
                $categoryIds[] = (int)$categoryId;
            }
        }

        return array_values(array_unique($categoryIds));
    }

    /**
     * @param \Magento\Catalog\Model\Product $product
     * @return string
     */
    protected function getProductFeedUrl($product)
    {
        return $this->storeUrlResolver->getProductUrl($product);
    }

    /**
     * Get original image URL. Images are not resized during feed generation on purpose:
     * the catalog image helper would synchronously generate cache files for every image.
     *
     * @param \Magento\Catalog\Model\Product $product
     * @param string|null $imageFile
     * @return string
     */
    protected function getProductImageUrl($product, $imageFile = null)
    {
        $imageFile = $imageFile !== null ? (string)$imageFile : (string)$product->getImage();
        if ($imageFile === '' || $imageFile === 'no_selection') {
            return '';
        }

        return $this->storeUrlResolver->getImageUrl($imageFile);
    }

    /**
     * Sanitize XML value to prevent injection
     * @param string|null $value
     * @return string
     */
    protected function sanitizeXmlValue($value)
    {
        if ($value === null) {
            return '';
        }
        // Remove control characters and invalid XML characters
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', (string)$value);
        // Limit length to prevent DoS
        return mb_substr($value, 0, 5000);
    }

    /**
     * Sanitize and validate URL
     * @param string|null $url
     * @return string
     */
    protected function sanitizeUrl($url)
    {
        if ($url === null) {
            return '';
        }
        $url = filter_var($url, FILTER_SANITIZE_URL);
        // Validate URL format and allow only http/https
        if (filter_var($url, FILTER_VALIDATE_URL) && preg_match('/^https?:\/\//i', $url)) {
            return $url;
        }
        return '';
    }

    /**
     * Get configuration value
     * @param string $path
     * @return mixed
     */
    protected function getConfigValue($path)
    {
        return $this->scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE);
    }
}
