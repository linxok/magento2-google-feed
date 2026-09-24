<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace MyCompany\GoogleFeed\Controller\Feed;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Response\Http;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use MyCompany\GoogleFeed\Model\FeedGenerator;
use Psr\Log\LoggerInterface;

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

        // Get store parameter from URL (e.g., ?store=en or ?store=uk)
        $storeCode = $this->getRequest()->getParam('store');
        if (is_scalar($storeCode) && (string)$storeCode !== '') {
            try {
                $store = $this->storeManager->getStore((string)$storeCode);
                $this->storeManager->setCurrentStore($store->getId());
            } catch (\Exception $e) {
                $this->logger->warning('Invalid store code in feed request: ' . (string)$storeCode);
            }
        }

        // Check HTTP Basic Authentication if enabled for the requested store
        if (!$this->authenticate()) {
            $result->setHttpResponseCode(401);
            $result->setHeader('WWW-Authenticate', 'Basic realm="Google Feed"');
            $result->setHeader('Content-Type', 'text/plain; charset=UTF-8');
            $result->setContents('Authentication required');
            return $result;
        }

        if (!$this->isFeedEnabled()) {
            $this->logger->warning(sprintf(
                'Google Feed request rejected: feed is disabled for store "%s"',
                $this->storeManager->getStore()->getCode()
            ));
            $result->setHttpResponseCode(404);
            $result->setHeader('Content-Type', 'text/plain; charset=UTF-8');
            $result->setContents('Feed is disabled');
            return $result;
        }

        try {
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
            $result->setContents(
                '<?xml version="1.0" encoding="UTF-8"?><error>Feed generation failed: '
                . htmlspecialchars($e->getMessage())
                . '</error>'
            );
        }

        return $result;
    }

    /**
     * @return bool
     */
    protected function isFeedEnabled()
    {
        return $this->scopeConfig->isSetFlag('googlefeed/general/enabled', ScopeInterface::SCOPE_STORE);
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
        $configUsername = (string)$this->scopeConfig->getValue(
            'googlefeed/general/auth_username',
            ScopeInterface::SCOPE_STORE
        );

        $encryptedPassword = (string)$this->scopeConfig->getValue(
            'googlefeed/general/auth_password',
            ScopeInterface::SCOPE_STORE
        );

        // If no credentials configured, deny access
        if ($configUsername === '' || $encryptedPassword === '') {
            $this->logger->warning('Google Feed authentication enabled but credentials not configured');
            return false;
        }

        // Decrypt password
        $configPassword = (string)$this->encryptor->decrypt($encryptedPassword);

        list($authUser, $authPass) = $this->getRequestCredentials();

        // Validate credentials
        if (hash_equals($configUsername, $authUser) && hash_equals($configPassword, $authPass)) {
            return true;
        }

        // Log failed authentication attempt
        $this->logger->warning('Google Feed authentication failed', [
            'provided_username' => $authUser,
            'ip_address' => $this->getRequest()->getServer('REMOTE_ADDR')
        ]);

        return false;
    }

    /**
     * Extract Basic Auth credentials from the request, including the Authorization header.
     *
     * @return array [username, password]
     */
    protected function getRequestCredentials()
    {
        $request = $this->getRequest();
        $authUser = $request->getServer('PHP_AUTH_USER');
        $authPass = $request->getServer('PHP_AUTH_PW');

        if ($authUser === null) {
            $authorization = $request->getServer('HTTP_AUTHORIZATION')
                ?: $request->getServer('REDIRECT_HTTP_AUTHORIZATION');

            if (is_string($authorization) && preg_match('/^Basic\s+(.+)$/i', trim($authorization), $matches)) {
                $decoded = base64_decode(trim($matches[1]), true);
                if (is_string($decoded) && strpos($decoded, ':') !== false) {
                    list($authUser, $authPass) = explode(':', $decoded, 2);
                }
            }
        }

        return [(string)$authUser, (string)$authPass];
    }
}
