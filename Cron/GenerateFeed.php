<?php
namespace MyCompany\GoogleFeed\Cron;

use MyCompany\GoogleFeed\Model\FeedGenerator;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Filesystem;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Store\Model\ScopeInterface;
use Psr\Log\LoggerInterface;

class GenerateFeed
{
    /**
     * @var FeedGenerator
     */
    protected $feedGenerator;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var Filesystem
     */
    protected $filesystem;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @param FeedGenerator $feedGenerator
     * @param ScopeConfigInterface $scopeConfig
     * @param Filesystem $filesystem
     * @param StoreManagerInterface $storeManager
     * @param LoggerInterface $logger
     */
    public function __construct(
        FeedGenerator $feedGenerator,
        ScopeConfigInterface $scopeConfig,
        Filesystem $filesystem,
        StoreManagerInterface $storeManager,
        LoggerInterface $logger
    ) {
        $this->feedGenerator = $feedGenerator;
        $this->scopeConfig = $scopeConfig;
        $this->filesystem = $filesystem;
        $this->storeManager = $storeManager;
        $this->logger = $logger;
    }

    /**
     * Execute cron job
     *
     * @return void
     */
    public function execute()
    {
        if (!$this->scopeConfig->getValue('googlefeed/cron/enabled')) {
            return;
        }

        try {
            $storeIds = $this->getStoreIdsForGeneration();
            $generatedCount = 0;
            
            foreach ($storeIds as $storeId) {
                try {
                    $store = $this->storeManager->getStore($storeId);
                    
                    if (!$this->scopeConfig->isSetFlag('googlefeed/general/enabled', ScopeInterface::SCOPE_STORE, $storeId)) {
                        continue;
                    }
                    
                    $currentStore = $this->storeManager->getStore();
                    $this->storeManager->setCurrentStore($storeId);
                    
                    $feedContent = $this->feedGenerator->generateFeed();
                    
                    // Use hardcoded base path
                    $basePath = 'googlefeed/feed.xml';
                    $finalPath = $this->getStoreSpecificPath($basePath, $store);
                    $this->saveFeedToFile($feedContent, $finalPath);
                    $generatedCount++;
                    $this->logger->info(sprintf('Google Feed generated for store "%s" (%s): %s', 
                        $store->getName(), 
                        $store->getCode(), 
                        $finalPath
                    ));
                    
                    $this->storeManager->setCurrentStore($currentStore->getId());
                } catch (\Exception $e) {
                    $this->logger->error(sprintf('Error generating Google Feed for store ID %d: %s', $storeId, $e->getMessage()));
                    if (isset($currentStore)) {
                        $this->storeManager->setCurrentStore($currentStore->getId());
                    }
                }
            }
            
            if ($generatedCount > 0) {
                $this->logger->info(sprintf('Google Feed cron completed: %d feed(s) generated', $generatedCount));
            }
        } catch (\Exception $e) {
            $this->logger->error('Error in Google Feed cron execution: ' . $e->getMessage());
        }
    }

    /**
     * Get store IDs for feed generation
     *
     * @return array
     */
    protected function getStoreIdsForGeneration()
    {
        $configuredStores = $this->scopeConfig->getValue('googlefeed/cron/store_ids');
        
        if ($configuredStores && trim($configuredStores) !== '') {
            return array_filter(array_map('trim', explode(',', $configuredStores)));
        }
        
        $storeIds = [];
        foreach ($this->storeManager->getStores() as $store) {
            if ($store->getIsActive()) {
                $storeIds[] = $store->getId();
            }
        }
        
        return $storeIds;
    }

    /**
     * Get store-specific file path
     *
     * @param string $basePath
     * @param \Magento\Store\Api\Data\StoreInterface $store
     * @return string
     */
    protected function getStoreSpecificPath($basePath, $store)
    {
        $pathInfo = pathinfo($basePath);
        $directory = isset($pathInfo['dirname']) && $pathInfo['dirname'] !== '.' ? $pathInfo['dirname'] : '';
        $filename = $pathInfo['filename'] ?? 'feed';
        $extension = isset($pathInfo['extension']) ? '.' . $pathInfo['extension'] : '.xml';
        
        $storeCode = $store->getCode();
        $storeName = preg_replace('/[^a-z0-9_-]/i', '_', strtolower($store->getName()));
        $locale = $this->scopeConfig->getValue('general/locale/code', ScopeInterface::SCOPE_STORE, $store->getId());
        $languageCode = $locale ? substr($locale, 0, 2) : 'en';
        
        $newFilename = sprintf('%s_%s_%s_%s%s', $filename, $storeName, $storeCode, $languageCode, $extension);
        
        return $directory ? $directory . '/' . $newFilename : $newFilename;
    }

    /**
     * Save feed content to file
     *
     * @param string $content
     * @param string $path
     * @return void
     */
    protected function saveFeedToFile($content, $path)
    {
        $mediaDirectory = $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA);
        $mediaDirectory->writeFile($path, $content);
    }
}
