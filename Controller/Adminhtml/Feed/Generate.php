<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace MyCompany\GoogleFeed\Controller\Adminhtml\Feed;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Store\Model\StoreManagerInterface;
use MyCompany\GoogleFeed\Model\FeedFileManager;
use MyCompany\GoogleFeed\Model\FeedGenerator;

class Generate extends Action implements HttpGetActionInterface
{
    /**
     * @var RawFactory
     */
    protected $resultRawFactory;

    /**
     * @var FeedGenerator
     */
    protected $feedGenerator;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * Generate constructor.
     * @param Context $context
     * @param RawFactory $resultRawFactory
     * @param FeedGenerator $feedGenerator
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        Context $context,
        RawFactory $resultRawFactory,
        FeedGenerator $feedGenerator,
        StoreManagerInterface $storeManager
    ) {
        $this->resultRawFactory = $resultRawFactory;
        $this->feedGenerator = $feedGenerator;
        $this->storeManager = $storeManager;
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
     * Generate Google Shopping feed in admin
     * @return \Magento\Framework\Controller\Result\Raw|\Magento\Backend\Model\View\Result\Redirect
     */
    public function execute()
    {
        $result = $this->resultRawFactory->create();

        try {
            $storeId = $this->getRequest()->getParam('store_id');

            if ($storeId) {
                // Generate feed for specific store
                $store = $this->storeManager->getStore($storeId);
                $currentStoreId = (int)$this->storeManager->getStore()->getId();

                // Switch to requested store
                $this->storeManager->setCurrentStore($store->getId());

                try {
                    $feedContent = $this->feedGenerator->generateFeed();
                } finally {
                    // Restore original store
                    $this->storeManager->setCurrentStore($currentStoreId);
                }

                $filename = 'google_feed_' . FeedFileManager::sanitizeFilePart($store->getCode()) . '.xml';
            } else {
                // Generate feed for current store
                $feedContent = $this->feedGenerator->generateFeed();
                $filename = 'google_feed.xml';
            }

            $result->setHeader('Content-Type', 'application/xml');
            $result->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"');
            $result->setContents($feedContent);
            return $result;
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('Error generating feed: %1', $e->getMessage()));
            $resultRedirect = $this->resultRedirectFactory->create();
            $resultRedirect->setRefererUrl();
            return $resultRedirect;
        }
    }
}
