<?php
namespace MyCompany\GoogleFeed\Model\Category\Attribute\Source;

use Magento\Eav\Model\Entity\Attribute\Source\AbstractSource;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use MyCompany\GoogleFeed\Model\GoogleCategoryStorage;

class GoogleProductCategory extends AbstractSource
{
    /**
     * @var GoogleCategoryStorage
     */
    private $googleCategoryStorage;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var RequestInterface
     */
    private $request;

    /**
     * @param GoogleCategoryStorage $googleCategoryStorage
     * @param StoreManagerInterface $storeManager
     * @param ScopeConfigInterface $scopeConfig
     * @param RequestInterface $request
     */
    public function __construct(
        GoogleCategoryStorage $googleCategoryStorage,
        StoreManagerInterface $storeManager,
        ScopeConfigInterface $scopeConfig,
        RequestInterface $request
    ) {
        $this->googleCategoryStorage = $googleCategoryStorage;
        $this->storeManager = $storeManager;
        $this->scopeConfig = $scopeConfig;
        $this->request = $request;
    }

    /**
     * @return array
     */
    public function getAllOptions()
    {
        if ($this->_options !== null) {
            return $this->_options;
        }

        $locale = $this->getCurrentLocale();
        $this->_options = [
            ['value' => '', 'label' => __('-- Please Select --')],
        ];

        $this->_options = array_merge(
            $this->_options,
            $this->googleCategoryStorage->getOptionsByLocale($locale)
        );

        return $this->_options;
    }

    /**
     * @return string
     */
    private function getCurrentLocale()
    {
        $storeId = (int)$this->request->getParam('store', 0);
        if ($storeId <= 0) {
            $storeId = (int)$this->storeManager->getStore()->getId();
        }

        $locale = (string)$this->scopeConfig->getValue(
            'general/locale/code',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return $locale ?: 'en_US';
    }
}
