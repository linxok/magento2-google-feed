<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace MyCompany\GoogleFeed\Controller\Adminhtml\Feed;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Framework\App\Response\Http;
use MyCompany\GoogleFeed\Model\FeedGenerator;

class Generate extends Action implements \Magento\Framework\App\Action\HttpGetActionInterface
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
     * @var Http
     */
    protected $response;

    /**
     * Generate constructor.
     * @param Context $context
     * @param RawFactory $resultRawFactory
     * @param FeedGenerator $feedGenerator
     * @param Http $response
     */
    public function __construct(
        Context $context,
        RawFactory $resultRawFactory,
        FeedGenerator $feedGenerator,
        Http $response
    ) {
        $this->resultRawFactory = $resultRawFactory;
        $this->feedGenerator = $feedGenerator;
        $this->response = $response;
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
                $store = $this->_objectManager->get(\Magento\Store\Model\StoreManagerInterface::class)->getStore($storeId);
                $currentStore = $this->_objectManager->get(\Magento\Store\Model\StoreManagerInterface::class)->getStore();
                
                // Switch to requested store
                $this->_objectManager->get(\Magento\Store\Model\StoreManagerInterface::class)->setCurrentStore($storeId);
                
                $feedContent = $this->feedGenerator->generateFeed();
                
                // Restore original store
                $this->_objectManager->get(\Magento\Store\Model\StoreManagerInterface::class)->setCurrentStore($currentStore->getId());
                
                $filename = 'google_feed_' . $store->getCode() . '.xml';
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
