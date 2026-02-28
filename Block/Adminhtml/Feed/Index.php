<?php
namespace MyCompany\GoogleFeed\Block\Adminhtml\Feed;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class Index extends Template
{
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
     * @param StoreManagerInterface $storeManager
     * @param ScopeConfigInterface $scopeConfig
     * @param array $data
     */
    public function __construct(
        Context $context,
        StoreManagerInterface $storeManager,
        ScopeConfigInterface $scopeConfig,
        array $data = []
    ) {
        $this->storeManager = $storeManager;
        $this->scopeConfig = $scopeConfig;
        parent::__construct($context, $data);
    }

    /**
     * Get active stores
     *
     * @return array
     */
    public function getActiveStores()
    {
        $stores = [];
        foreach ($this->storeManager->getStores() as $store) {
            if ($store->getIsActive()) {
                $stores[] = [
                    'id' => $store->getId(),
                    'name' => $store->getName(),
                    'code' => $store->getCode(),
                    'website' => $this->storeManager->getWebsite($store->getWebsiteId())->getName(),
                    'enabled' => $this->scopeConfig->isSetFlag('googlefeed/general/enabled', ScopeInterface::SCOPE_STORE, $store->getId()),
                    'url' => $store->getBaseUrl() . 'googlefeed/feed/index?store=' . $store->getCode(),
                    'download_url' => $this->getUrl('googlefeed/feed/generate', ['store_id' => $store->getId()])
                ];
            }
        }
        return $stores;
    }

    /**
     * Get generate URL
     *
     * @return string
     */
    public function getGenerateUrl()
    {
        return $this->getUrl('googlefeed/feed/generate');
    }
    
    /**
     * Get generate URL for specific store
     *
     * @param int $storeId
     * @return string
     */
    public function getGenerateUrlForStore($storeId)
    {
        return $this->getUrl('googlefeed/feed/generate', ['store_id' => $storeId]);
    }

    /**
     * Get save files URL
     *
     * @return string
     */
    public function getSaveFilesUrl()
    {
        return $this->getUrl('googlefeed/feed/savefiles');
    }

    /**
     * Get configuration URL
     *
     * @return string
     */
    public function getConfigUrl()
    {
        return $this->getUrl('adminhtml/system_config/edit', ['section' => 'googlefeed']);
    }
}
