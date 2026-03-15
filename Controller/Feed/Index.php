<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace MyCompany\GoogleFeed\Controller\Feed;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Response\Http;
use Magento\Framework\Controller\Result\RawFactory;
use MyCompany\GoogleFeed\Model\FeedGenerator;
use Psr\Log\LoggerInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Model\StoreManagerInterface;

class Index extends Action implements HttpGetActionInterface
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
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var EncryptorInterface
     */
    protected $encryptor;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * Index constructor.
     * @param Context $context
     * @param RawFactory $resultRawFactory
     * @param FeedGenerator $feedGenerator
     * @param Http $response
     * @param LoggerInterface $logger
     * @param ScopeConfigInterface $scopeConfig
     * @param EncryptorInterface $encryptor
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        Context $context,
        RawFactory $resultRawFactory,
        FeedGenerator $feedGenerator,
        Http $response,
        LoggerInterface $logger,
        ScopeConfigInterface $scopeConfig,
        EncryptorInterface $encryptor,
        StoreManagerInterface $storeManager
    ) {
        $this->resultRawFactory = $resultRawFactory;
        $this->feedGenerator = $feedGenerator;
        $this->response = $response;
        $this->logger = $logger;
        $this->scopeConfig = $scopeConfig;
        $this->encryptor = $encryptor;
        $this->storeManager = $storeManager;
        parent::__construct($context);
    }

    /**
     * Generate Google Shopping feed
     * @return \Magento\Framework\Controller\Result\Raw
     */
    public function execute()
    {
        $result = $this->resultRawFactory->create();
        
        // Check HTTP Basic Authentication if enabled
        if (!$this->authenticate()) {
            $result->setHttpResponseCode(401);
            $result->setHeader('WWW-Authenticate', 'Basic realm="Google Feed"');
            $result->setHeader('Content-Type', 'text/plain; charset=UTF-8');
            $result->setContents('Authentication required');
            return $result;
        }
        
        try {
            // Get store parameter from URL (e.g., ?store=en or ?store=uk)
            $storeCode = $this->getRequest()->getParam('store');
            if ($storeCode) {
                try {
                    $store = $this->storeManager->getStore($storeCode);
                    $this->storeManager->setCurrentStore($store->getId());
                } catch (\Exception $e) {
                    $this->logger->warning('Invalid store code in feed request: ' . $storeCode);
                }
            }
            
            $feedContent = $this->feedGenerator->generateFeed();
            $result->setHeader('Content-Type', 'application/xml; charset=UTF-8');
            $result->setHeader('X-Content-Type-Options', 'nosniff');
            $result->setContents($feedContent);
        } catch (\Exception $e) {
            // Log detailed error for debugging
            $this->logger->critical('Google Feed generation failed: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString()
            ]);
            
            $result->setHttpResponseCode(500);
            $result->setHeader('Content-Type', 'application/xml; charset=UTF-8');
            $result->setContents('<?xml version="1.0" encoding="UTF-8"?><error>Feed generation failed: ' . htmlspecialchars($e->getMessage()) . '</error>');
        }
        
        return $result;
    }

    /**
     * Authenticate request using HTTP Basic Authentication
     * @return bool
     */
    protected function authenticate()
    {
        // Check if authentication is enabled
        $authEnabled = $this->scopeConfig->getValue(
            'googlefeed/general/enable_auth',
            ScopeInterface::SCOPE_STORE
        );
        
        // If authentication is disabled, allow access
        if (!$authEnabled) {
            return true;
        }
        
        // Get configured credentials
        $configUsername = $this->scopeConfig->getValue(
            'googlefeed/general/auth_username',
            ScopeInterface::SCOPE_STORE
        );
        
        $encryptedPassword = $this->scopeConfig->getValue(
            'googlefeed/general/auth_password',
            ScopeInterface::SCOPE_STORE
        );
        
        // If no credentials configured, deny access
        if (empty($configUsername) || empty($encryptedPassword)) {
            $this->logger->warning('Google Feed authentication enabled but credentials not configured');
            return false;
        }
        
        // Decrypt password
        $configPassword = $this->encryptor->decrypt($encryptedPassword);
        
        // Get HTTP Basic Auth credentials from request
        $request = $this->getRequest();
        $authUser = $request->getServer('PHP_AUTH_USER');
        $authPass = $request->getServer('PHP_AUTH_PW');
        
        // Validate credentials
        if ($authUser === $configUsername && $authPass === $configPassword) {
            return true;
        }
        
        // Log failed authentication attempt
        $this->logger->warning('Google Feed authentication failed', [
            'provided_username' => $authUser,
            'ip_address' => $request->getServer('REMOTE_ADDR')
        ]);
        
        return false;
    }
}
