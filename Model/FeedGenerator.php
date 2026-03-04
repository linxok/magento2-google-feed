<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace MyCompany\GoogleFeed\Model;

use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Catalog\Helper\Image;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchCriteriaBuilderFactory;
use Psr\Log\LoggerInterface;

class FeedGenerator
{
    /**
     * @var CollectionFactory
     */
    protected $productCollectionFactory;

    /**
     * @var Image
     */
    protected $imageHelper;

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
     * @var ProductRepositoryInterface
     */
    protected $productRepository;

    /**
     * @var SearchCriteriaBuilderFactory
     */
    protected $searchCriteriaBuilderFactory;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * FeedGenerator constructor.
     * @param CollectionFactory $productCollectionFactory
     * @param Image $imageHelper
     * @param StoreManagerInterface $storeManager
     * @param ScopeConfigInterface $scopeConfig
     * @param PriceCurrencyInterface $priceCurrency
     * @param StockRegistryInterface $stockRegistry
     * @param ProductRepositoryInterface $productRepository
     * @param SearchCriteriaBuilderFactory $searchCriteriaBuilderFactory
     * @param LoggerInterface $logger
     */
    public function __construct(
        CollectionFactory $productCollectionFactory,
        Image $imageHelper,
        StoreManagerInterface $storeManager,
        ScopeConfigInterface $scopeConfig,
        PriceCurrencyInterface $priceCurrency,
        StockRegistryInterface $stockRegistry,
        ProductRepositoryInterface $productRepository,
        SearchCriteriaBuilderFactory $searchCriteriaBuilderFactory,
        LoggerInterface $logger
    ) {
        $this->productCollectionFactory = $productCollectionFactory;
        $this->imageHelper = $imageHelper;
        $this->storeManager = $storeManager;
        $this->scopeConfig = $scopeConfig;
        $this->priceCurrency = $priceCurrency;
        $this->stockRegistry = $stockRegistry;
        $this->productRepository = $productRepository;
        $this->searchCriteriaBuilderFactory = $searchCriteriaBuilderFactory;
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
        $xml->writeElement('link', $this->storeManager->getStore()->getBaseUrl());
        $xml->writeElement('description', $this->getConfigValue('googlefeed/general/description'));

        // Add products
        $products = $this->getProductCollection();
        $includeOutOfStock = $this->getConfigValue('googlefeed/feed/include_out_of_stock');
        
        foreach ($products as $product) {
            // Skip out of stock products if configured
            if (!$includeOutOfStock) {
                $stockItem = $this->stockRegistry->getStockItem($product->getId());
                if (!$stockItem->getIsInStock()) {
                    continue;
                }
            }
            
            $this->addProductToFeed($xml, $product);
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
        
        // Category filters - Include specific categories
        $includeCategories = $this->getConfigValue('googlefeed/filters/category_ids');
        
        // If category filter is not configured at all (null), don't show any products by default
        // This prevents accidentally exporting entire catalog before configuration
        if ($includeCategories === null) {
            $collection->addCategoriesFilter(['in' => [0]]);
        } else {
            $trimmedValue = trim((string)$includeCategories);
            
            if ($trimmedValue !== '') {
                // Parse category IDs
                $categoryIds = array_filter(array_map('trim', explode(',', $trimmedValue)));
                if (!empty($categoryIds)) {
                    $collection->addCategoriesFilter(['in' => $categoryIds]);
                } else {
                    // Field contains only commas/whitespace - no products
                    $collection->addCategoriesFilter(['in' => [0]]);
                }
            } else {
                // Field is explicitly empty (all categories unchecked) - no products
                $collection->addCategoriesFilter(['in' => [0]]);
            }
        }
        
        // Category filters - Exclude specific categories
        $excludeCategories = $this->getConfigValue('googlefeed/filters/exclude_categories');
        if ($excludeCategories && trim($excludeCategories) !== '') {
            $categoryIds = array_filter(array_map('trim', explode(',', $excludeCategories)));
            if (!empty($categoryIds)) {
                $collection->addCategoriesFilter(['nin' => $categoryIds]);
            }
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
     */
    protected function addProductToFeed(\XMLWriter $xml, $product)
    {
        $xml->startElement('item');
        
        // Basic product information - XMLWriter automatically escapes content
        $xml->writeElement('g:id', $this->sanitizeXmlValue($product->getSku()));
        $xml->writeElement('g:title', $this->sanitizeXmlValue($product->getName()));
        $description = $product->getDescription() ?: $product->getShortDescription() ?: $product->getName();
        $xml->writeElement('g:description', $this->sanitizeXmlValue(strip_tags($description ?? '')));
        $xml->writeElement('g:link', $this->sanitizeUrl($product->getProductUrl()));
        
        // Image
        $imageUrl = $this->imageHelper->init($product, 'product_base_image')
            ->constrainOnly(true)
            ->keepAspectRatio(true)
            ->keepFrame(false)
            ->resize($this->getConfigValue('googlefeed/feed/image_size'))
            ->getUrl();
        $xml->writeElement('g:image_link', $this->sanitizeUrl($imageUrl));
        
        // Additional images
        $mediaGallery = $product->getMediaGalleryImages();
        if ($mediaGallery && $mediaGallery->getSize() > 1) {
            $additionalImages = [];
            $imageSize = $this->getConfigValue('googlefeed/feed/image_size');
            $count = 0;
            foreach ($mediaGallery as $image) {
                // Skip the main image and limit to 10 additional images (Google limit)
                if ($image->getFile() !== $product->getImage() && $count < 10) {
                    $additionalImageUrl = $this->storeManager->getStore()
                        ->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA) 
                        . 'catalog/product' . $image->getFile();
                    $additionalImages[] = $this->sanitizeUrl($additionalImageUrl);
                    $count++;
                }
            }
            if (!empty($additionalImages)) {
                foreach ($additionalImages as $additionalImage) {
                    $xml->writeElement('g:additional_image_link', $additionalImage);
                }
            }
        }
        
        // Price
        $basePrice = $product->getPrice();
        $configCurrency = $this->getConfigValue('googlefeed/feed/currency');
        $currency = $configCurrency ?: $this->storeManager->getStore()->getCurrentCurrency()->getCode();
        
        // Convert price to feed currency if different from base currency
        $baseCurrencyCode = $this->storeManager->getStore()->getBaseCurrencyCode();
        if ($currency !== $baseCurrencyCode) {
            // Convert from base currency to feed currency
            $priceValue = $this->priceCurrency->convert($basePrice, null, $currency);
        } else {
            $priceValue = $basePrice;
        }
        
        $priceFormatted = number_format($priceValue, 2, '.', '');
        $xml->writeElement('g:price', $priceFormatted . ' ' . $currency);
        
        // Availability
        $stockItem = $this->stockRegistry->getStockItem($product->getId());
        $availability = $stockItem->getIsInStock() ? 'in stock' : 'out of stock';
        $xml->writeElement('g:availability', $availability);
        
        // Brand (if attribute exists)
        $brandAttribute = $this->getConfigValue('googlefeed/attributes/brand_attribute');
        if ($brandAttribute) {
            $attribute = $product->getResource()->getAttribute($brandAttribute);
            if ($attribute && $attribute->usesSource()) {
                $brandValue = $product->getAttributeText($brandAttribute);
                if ($brandValue) {
                    $xml->writeElement('g:brand', $this->sanitizeXmlValue($brandValue));
                }
            } elseif ($product->getData($brandAttribute)) {
                $xml->writeElement('g:brand', $this->sanitizeXmlValue($product->getData($brandAttribute)));
            }
        }
        
        // GTIN (if attribute exists)
        $gtinAttribute = $this->getConfigValue('googlefeed/attributes/gtin_attribute');
        if ($gtinAttribute && $product->getData($gtinAttribute)) {
            $xml->writeElement('g:gtin', $this->sanitizeXmlValue($product->getData($gtinAttribute)));
        }
        
        // MPN (if attribute exists)
        $mpnAttribute = $this->getConfigValue('googlefeed/attributes/mpn_attribute');
        if ($mpnAttribute && $product->getData($mpnAttribute)) {
            $xml->writeElement('g:mpn', $this->sanitizeXmlValue($product->getData($mpnAttribute)));
        }
        
        // Condition
        $conditionAttribute = $this->getConfigValue('googlefeed/attributes/condition_attribute');
        $condition = 'new';
        if ($conditionAttribute && $product->getData($conditionAttribute)) {
            $attribute = $product->getResource()->getAttribute($conditionAttribute);
            if ($attribute && $attribute->usesSource()) {
                $conditionValue = $product->getAttributeText($conditionAttribute);
                if ($conditionValue) {
                    $condition = strtolower($conditionValue);
                }
            } elseif ($product->getData($conditionAttribute)) {
                $condition = strtolower($product->getData($conditionAttribute));
            }
        } else {
            $condition = $this->getConfigValue('googlefeed/feed/condition') ?: 'new';
        }
        $xml->writeElement('g:condition', $this->sanitizeXmlValue($condition));
        
        // Google Product Category
        $googleCategoryAttribute = $this->getConfigValue('googlefeed/attributes/google_category_attribute');
        if ($googleCategoryAttribute && $product->getData($googleCategoryAttribute)) {
            $xml->writeElement('g:google_product_category', $this->sanitizeXmlValue($product->getData($googleCategoryAttribute)));
        }
        
        // Product Type (Magento category)
        if ($product->getCategory()) {
            $categoryName = $product->getCategory()->getName();
            $xml->writeElement('g:product_type', $this->sanitizeXmlValue($categoryName));
        }
        
        // Color
        $colorAttribute = $this->getConfigValue('googlefeed/attributes/color_attribute');
        if ($colorAttribute && $product->getData($colorAttribute)) {
            $attribute = $product->getResource()->getAttribute($colorAttribute);
            if ($attribute && $attribute->usesSource()) {
                $colorValue = $product->getAttributeText($colorAttribute);
                if ($colorValue) {
                    $xml->writeElement('g:color', $this->sanitizeXmlValue($colorValue));
                }
            } elseif ($product->getData($colorAttribute)) {
                $xml->writeElement('g:color', $this->sanitizeXmlValue($product->getData($colorAttribute)));
            }
        }
        
        // Size
        $sizeAttribute = $this->getConfigValue('googlefeed/attributes/size_attribute');
        if ($sizeAttribute && $product->getData($sizeAttribute)) {
            $attribute = $product->getResource()->getAttribute($sizeAttribute);
            if ($attribute && $attribute->usesSource()) {
                $sizeValue = $product->getAttributeText($sizeAttribute);
                if ($sizeValue) {
                    $xml->writeElement('g:size', $this->sanitizeXmlValue($sizeValue));
                }
            } elseif ($product->getData($sizeAttribute)) {
                $xml->writeElement('g:size', $this->sanitizeXmlValue($product->getData($sizeAttribute)));
            }
        }
        
        // Gender
        $genderAttribute = $this->getConfigValue('googlefeed/attributes/gender_attribute');
        if ($genderAttribute && $product->getData($genderAttribute)) {
            $attribute = $product->getResource()->getAttribute($genderAttribute);
            if ($attribute && $attribute->usesSource()) {
                $genderValue = $product->getAttributeText($genderAttribute);
                if ($genderValue) {
                    $xml->writeElement('g:gender', $this->sanitizeXmlValue(strtolower($genderValue)));
                }
            } elseif ($product->getData($genderAttribute)) {
                $xml->writeElement('g:gender', $this->sanitizeXmlValue(strtolower($product->getData($genderAttribute))));
            }
        }
        
        // Age Group
        $ageGroupAttribute = $this->getConfigValue('googlefeed/attributes/age_group_attribute');
        if ($ageGroupAttribute && $product->getData($ageGroupAttribute)) {
            $attribute = $product->getResource()->getAttribute($ageGroupAttribute);
            if ($attribute && $attribute->usesSource()) {
                $ageGroupValue = $product->getAttributeText($ageGroupAttribute);
                if ($ageGroupValue) {
                    $xml->writeElement('g:age_group', $this->sanitizeXmlValue(strtolower($ageGroupValue)));
                }
            } elseif ($product->getData($ageGroupAttribute)) {
                $xml->writeElement('g:age_group', $this->sanitizeXmlValue(strtolower($product->getData($ageGroupAttribute))));
            }
        }

        $xml->endElement(); // item
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
