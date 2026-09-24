<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace MyCompany\GoogleFeed\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Builds storefront URLs for feed output without corrupting host or duplicating store codes.
 */
class StoreUrlResolver
{
    const XML_PATH_USE_STORE_IN_URL = 'web/url/use_store';

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @param StoreManagerInterface $storeManager
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->storeManager = $storeManager;
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * Get storefront base URL including store code when store code in URL is enabled.
     *
     * @param StoreInterface|null $store
     * @return string
     */
    public function getStoreBaseUrl(StoreInterface $store = null)
    {
        $store = $store ?: $this->storeManager->getStore();

        // DIRECT_LINK honors "web/seo/use_rewrites" (index.php) but does not append the store code.
        $baseUrl = (string)$store->getBaseUrl(UrlInterface::URL_TYPE_DIRECT_LINK, true);
        if ($baseUrl === '') {
            $baseUrl = (string)$store->getBaseUrl(UrlInterface::URL_TYPE_DIRECT_LINK, false);
        }

        $storeCode = trim((string)$store->getCode(), '/');
        if ($storeCode === '' || !$this->isStoreCodeInUrl($store)) {
            return $this->ensureTrailingSlash($baseUrl);
        }

        return self::appendStoreCodeOnce($baseUrl, $storeCode);
    }

    /**
     * @param StoreInterface|null $store
     * @return string
     */
    public function getMediaBaseUrl(StoreInterface $store = null)
    {
        $store = $store ?: $this->storeManager->getStore();

        return (string)$store->getBaseUrl(UrlInterface::URL_TYPE_MEDIA);
    }

    /**
     * Build product URL for a feed item.
     *
     * @param \Magento\Catalog\Model\Product $product
     * @param StoreInterface|null $store
     * @return string
     */
    public function getProductUrl($product, StoreInterface $store = null)
    {
        $urlPath = trim((string)$product->getData('url_path'), '/');
        if ($urlPath === '') {
            $urlPath = ltrim((string)$product->getUrlKey(), '/');
            if ($urlPath !== '') {
                $urlPath .= '.html';
            }
        }

        if ($urlPath === '') {
            return (string)$product->getProductUrl();
        }

        return rtrim($this->getStoreBaseUrl($store), '/') . '/' . $urlPath;
    }

    /**
     * Build the public feed endpoint URL for a store.
     *
     * @param StoreInterface|null $store
     * @return string
     */
    public function getFeedUrl(StoreInterface $store = null)
    {
        $store = $store ?: $this->storeManager->getStore();

        return rtrim($this->getStoreBaseUrl($store), '/')
            . '/googlefeed/feed/index?store=' . rawurlencode((string)$store->getCode());
    }

    /**
     * Build public URL for a catalog image file.
     *
     * @param string $imageFile
     * @param StoreInterface|null $store
     * @return string
     */
    public function getImageUrl($imageFile, StoreInterface $store = null)
    {
        $imageFile = ltrim((string)$imageFile, '/');
        if ($imageFile === '') {
            return '';
        }

        return rtrim($this->getMediaBaseUrl($store), '/') . '/catalog/product/' . $this->encodePath($imageFile);
    }

    /**
     * Append store code to a base URL only when it is not present in the path already.
     *
     * @param string $baseUrl
     * @param string $storeCode
     * @return string
     */
    public static function appendStoreCodeOnce($baseUrl, $storeCode)
    {
        $baseUrl = (string)$baseUrl;
        $storeCode = trim((string)$storeCode, '/');

        if ($storeCode === '') {
            return self::ensureTrailingSlash($baseUrl);
        }

        $parts = parse_url($baseUrl);
        if ($parts === false || empty($parts['host'])) {
            return self::ensureTrailingSlash($baseUrl);
        }

        $path = isset($parts['path']) ? trim((string)$parts['path'], '/') : '';
        $segments = $path === '' ? [] : explode('/', $path);
        if (empty($segments) || end($segments) !== $storeCode) {
            $path = $path === '' ? $storeCode : $path . '/' . $storeCode;
        }

        $url = '';
        if (!empty($parts['scheme'])) {
            $url .= $parts['scheme'] . '://';
        }

        if (!empty($parts['user'])) {
            $url .= $parts['user'];
            if (!empty($parts['pass'])) {
                $url .= ':' . $parts['pass'];
            }
            $url .= '@';
        }

        $url .= $parts['host'];

        if (!empty($parts['port'])) {
            $url .= ':' . $parts['port'];
        }

        return rtrim($url, '/') . '/' . trim($path, '/') . '/';
    }

    /**
     * @param StoreInterface $store
     * @return bool
     */
    private function isStoreCodeInUrl(StoreInterface $store)
    {
        if (method_exists($store, 'isUseStoreInUrl')) {
            return (bool)$store->isUseStoreInUrl();
        }

        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_USE_STORE_IN_URL,
            ScopeInterface::SCOPE_STORE,
            $store->getId()
        );
    }

    /**
     * URL-encode every path segment, preserving slashes.
     *
     * @param string $path
     * @return string
     */
    private function encodePath($path)
    {
        return implode('/', array_map('rawurlencode', explode('/', (string)$path)));
    }

    /**
     * @param string $url
     * @return string
     */
    private static function ensureTrailingSlash($url)
    {
        $url = (string)$url;
        if ($url === '') {
            return '';
        }

        return rtrim($url, '/') . '/';
    }
}
