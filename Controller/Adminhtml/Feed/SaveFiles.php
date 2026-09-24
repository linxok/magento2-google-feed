<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace MyCompany\GoogleFeed\Controller\Adminhtml\Feed;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use MyCompany\GoogleFeed\Model\FeedFileManager;
use MyCompany\GoogleFeed\Model\FeedGenerator;

class SaveFiles extends Action implements HttpPostActionInterface
{
    /**
     * @var FeedGenerator
     */
    protected $feedGenerator;

    /**
     * @var FeedFileManager
     */
    protected $feedFileManager;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @param Context $context
     * @param FeedGenerator $feedGenerator
     * @param FeedFileManager $feedFileManager
     * @param StoreManagerInterface $storeManager
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        Context $context,
        FeedGenerator $feedGenerator,
        FeedFileManager $feedFileManager,
        StoreManagerInterface $storeManager,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->feedGenerator = $feedGenerator;
        $this->feedFileManager = $feedFileManager;
        $this->storeManager = $storeManager;
        $this->scopeConfig = $scopeConfig;
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
     * Generate and save feed files for configured stores
     * @return \Magento\Backend\Model\View\Result\Redirect
     */
    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();

        try {
            $storeIds = $this->feedFileManager->getStoreIdsForGeneration();
            $generatedFiles = [];
            $errors = [];

            foreach ($storeIds as $storeId) {
                $storeName = (string)$storeId;

                try {
                    $store = $this->storeManager->getStore($storeId);
                    $storeName = (string)$store->getName();

                    if (!$this->scopeConfig->isSetFlag(
                        'googlefeed/general/enabled',
                        ScopeInterface::SCOPE_STORE,
                        $storeId
                    )) {
                        continue;
                    }

                    $currentStoreId = (int)$this->storeManager->getStore()->getId();
                    $this->storeManager->setCurrentStore($storeId);

                    try {
                        $feedContent = $this->feedGenerator->generateFeed();
                        $finalPath = $this->feedFileManager->getStoreSpecificPath($store);
                        $this->feedFileManager->saveFeed($feedContent, $finalPath);

                        $generatedFiles[] = sprintf('%s (%s)', $store->getName(), $finalPath);
                    } finally {
                        $this->storeManager->setCurrentStore($currentStoreId);
                    }
                } catch (\Exception $e) {
                    $errors[] = sprintf('%s: %s', $storeName, $e->getMessage());
                }
            }

            if (!empty($generatedFiles)) {
                $this->messageManager->addSuccessMessage(
                    __(
                        'Successfully generated %1 feed file(s): %2',
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
}
