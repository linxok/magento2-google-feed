<?php
namespace MyCompany\GoogleFeed\Controller\Adminhtml\Feed;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use MyCompany\GoogleFeed\Model\FeedGenerator;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\Filesystem;
use Magento\Framework\App\Filesystem\DirectoryList;

class SaveFiles extends Action implements \Magento\Framework\App\Action\HttpPostActionInterface
{
    /**
     * @var FeedGenerator
     */
    protected $feedGenerator;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var Filesystem
     */
    protected $filesystem;

    /**
     * @param Context $context
     * @param FeedGenerator $feedGenerator
     * @param StoreManagerInterface $storeManager
     * @param ScopeConfigInterface $scopeConfig
     * @param Filesystem $filesystem
     */
    public function __construct(
        Context $context,
        FeedGenerator $feedGenerator,
        StoreManagerInterface $storeManager,
        ScopeConfigInterface $scopeConfig,
        Filesystem $filesystem
    ) {
        $this->feedGenerator = $feedGenerator;
        $this->storeManager = $storeManager;
        $this->scopeConfig = $scopeConfig;
        $this->filesystem = $filesystem;
        parent::__construct($context);
    }

    /**
     * Check permission
     * @return bool
     */
    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('MyCompany_GoogleFeed::feed_generate');
    }

    /**
     * Generate and save feed files for all stores
     * @return \Magento\Backend\Model\View\Result\Redirect
     */
    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        
        try {
            $storeIds = $this->getStoreIdsForGeneration();
            $generatedFiles = [];
            $errors = [];
            
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
                    
                    $generatedFiles[] = sprintf('%s (%s)', $store->getName(), $finalPath);
                    
                    $this->storeManager->setCurrentStore($currentStore->getId());
                } catch (\Exception $e) {
                    $errors[] = sprintf('%s: %s', $store->getName() ?? "Store ID $storeId", $e->getMessage());
                    if (isset($currentStore)) {
                        $this->storeManager->setCurrentStore($currentStore->getId());
                    }
                }
            }
            
            if (!empty($generatedFiles)) {
                $this->messageManager->addSuccessMessage(
                    __('Successfully generated %1 feed file(s): %2', 
                        count($generatedFiles), 
                        implode(', ', $generatedFiles)
                    )
                );
            }
            
            if (!empty($errors)) {
                foreach ($errors as $error) {
                    $this->messageManager->addErrorMessage(__('Error: %1', $error));
                }
            }
            
            if (empty($generatedFiles) && empty($errors)) {
                $this->messageManager->addWarningMessage(__('No feeds were generated. Please check your configuration.'));
            }
            
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('Error generating feed files: %1', $e->getMessage()));
        }
        
        $resultRedirect->setPath('*/*/index');
        return $resultRedirect;
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
        $locale = $this->scopeConfig->getValue('general/locale/code', \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $store->getId());
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
