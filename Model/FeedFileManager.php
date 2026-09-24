<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace MyCompany\GoogleFeed\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Shared logic for choosing stores, building feed file names and saving feed files.
 */
class FeedFileManager
{
    const XML_PATH_STORE_IDS = 'googlefeed/cron/store_ids';
    const XML_PATH_FILE_PATH = 'googlefeed/cron/file_path';
    const DEFAULT_FILE_PATH = 'googlefeed/feed.xml';

    /**
     * @var Filesystem
     */
    private $filesystem;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @param Filesystem $filesystem
     * @param StoreManagerInterface $storeManager
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        Filesystem $filesystem,
        StoreManagerInterface $storeManager,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->filesystem = $filesystem;
        $this->storeManager = $storeManager;
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * Get store IDs configured for generation, or all active stores when not configured.
     *
     * @return int[]
     */
    public function getStoreIdsForGeneration()
    {
        $configuredStores = (string)$this->scopeConfig->getValue(self::XML_PATH_STORE_IDS);
        if (trim($configuredStores) !== '') {
            $storeIds = [];
            foreach (explode(',', $configuredStores) as $storeId) {
                $storeId = trim($storeId);
                if ($storeId !== '' && ctype_digit($storeId)) {
                    $storeIds[] = (int)$storeId;
                }
            }

            if (!empty($storeIds)) {
                return $storeIds;
            }
        }

        $storeIds = [];
        foreach ($this->storeManager->getStores() as $store) {
            if ($store->getIsActive()) {
                $storeIds[] = (int)$store->getId();
            }
        }

        return $storeIds;
    }

    /**
     * Build store specific file path like googlefeed/feed_storename_code_lang.xml.
     *
     * @param StoreInterface $store
     * @return string
     */
    public function getStoreSpecificPath(StoreInterface $store)
    {
        $basePath = $this->getBaseFilePath();
        $pathInfo = pathinfo($basePath);
        $directory = isset($pathInfo['dirname']) && $pathInfo['dirname'] !== '.' ? $pathInfo['dirname'] : '';
        $filename = $pathInfo['filename'] ?? 'feed';
        $extension = isset($pathInfo['extension']) ? '.' . $pathInfo['extension'] : '.xml';

        $storeCode = self::sanitizeFilePart($store->getCode());
        $storeName = self::sanitizeFilePart(strtolower((string)$store->getName()));
        $locale = (string)$this->scopeConfig->getValue(
            'general/locale/code',
            ScopeInterface::SCOPE_STORE,
            $store->getId()
        );
        $languageCode = $locale !== '' ? substr($locale, 0, 2) : 'en';

        $newFilename = sprintf('%s_%s_%s_%s%s', $filename, $storeName, $storeCode, $languageCode, $extension);

        return $directory !== '' ? $directory . '/' . $newFilename : $newFilename;
    }

    /**
     * Save feed content to a path inside pub/media.
     *
     * @param string $content
     * @param string $path
     * @return void
     */
    public function saveFeed($content, $path)
    {
        $mediaDirectory = $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA);
        $mediaDirectory->writeFile($path, $content);
    }

    /**
     * @return string
     */
    private function getBaseFilePath()
    {
        $basePath = (string)$this->scopeConfig->getValue(self::XML_PATH_FILE_PATH);
        if ($basePath === '') {
            $basePath = self::DEFAULT_FILE_PATH;
        }

        return $this->sanitizeRelativePath($basePath);
    }

    /**
     * Keep only safe relative path segments to prevent escaping the media directory.
     *
     * @param string $path
     * @return string
     */
    private function sanitizeRelativePath($path)
    {
        $path = str_replace('\\', '/', (string)$path);
        $segments = [];
        foreach (explode('/', $path) as $segment) {
            $segment = trim($segment);
            if ($segment === '' || $segment === '.' || $segment === '..') {
                continue;
            }

            $segments[] = preg_replace('/[^A-Za-z0-9._-]/', '_', $segment);
        }

        return empty($segments) ? self::DEFAULT_FILE_PATH : implode('/', $segments);
    }

    /**
     * Sanitize a single file name part (store code, store name, ...).
     *
     * @param string $value
     * @return string
     */
    public static function sanitizeFilePart($value)
    {
        $value = preg_replace('/[^a-z0-9_-]/i', '_', (string)$value);

        return $value === '' ? 'store' : $value;
    }
}
