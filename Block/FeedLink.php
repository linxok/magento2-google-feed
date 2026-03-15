<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace MyCompany\GoogleFeed\Block;

use Magento\Framework\View\Element\Template;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class FeedLink extends Template
{
    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * FeedLink constructor.
     * @param Template\Context $context
     * @param ScopeConfigInterface $scopeConfig
     * @param array $data
     */
    public function __construct(
        Template\Context $context,
        ScopeConfigInterface $scopeConfig,
        array $data = []
    ) {
        $this->scopeConfig = $scopeConfig;
        parent::__construct($context, $data);
    }

    /**
     * Check if feed is enabled
     * @return bool
     */
    public function isFeedEnabled()
    {
        return $this->scopeConfig->isSetFlag('googlefeed/general/enabled', ScopeInterface::SCOPE_STORE);
    }

    /**
     * Get feed URL
     * @return string
     */
    public function getFeedUrl()
    {
        return $this->getUrl('googlefeed/feed/index');
    }

    /**
     * Get feed title
     * @return string
     */
    public function getFeedTitle()
    {
        return $this->scopeConfig->getValue('googlefeed/general/title', ScopeInterface::SCOPE_STORE);
    }
}
