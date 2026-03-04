<?php
namespace MyCompany\GoogleFeed\Controller\Adminhtml\Taxonomy;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use MyCompany\GoogleFeed\Model\GoogleCategoryFetcher;
use MyCompany\GoogleFeed\Model\GoogleCategoryStorage;

class Import extends Action implements \Magento\Framework\App\Action\HttpPostActionInterface
{
    /**
     * @var GoogleCategoryFetcher
     */
    private $googleCategoryFetcher;

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
     * @param Context $context
     * @param GoogleCategoryFetcher $googleCategoryFetcher
     * @param GoogleCategoryStorage $googleCategoryStorage
     * @param StoreManagerInterface $storeManager
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        Context $context,
        GoogleCategoryFetcher $googleCategoryFetcher,
        GoogleCategoryStorage $googleCategoryStorage,
        StoreManagerInterface $storeManager,
        ScopeConfigInterface $scopeConfig
    ) {
        parent::__construct($context);
        $this->googleCategoryFetcher = $googleCategoryFetcher;
        $this->googleCategoryStorage = $googleCategoryStorage;
        $this->storeManager = $storeManager;
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * @return bool
     */
    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('MyCompany_GoogleFeed::taxonomy_import');
    }

    /**
     * @return ResponseInterface|\Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        $storeId = (int)$this->getRequest()->getParam('store', 0);
        $websiteId = (int)$this->getRequest()->getParam('website', 0);

        try {
            $localeCode = $this->resolveLocaleCode($storeId, $websiteId);
            $result = $this->googleCategoryFetcher->fetch($localeCode);

            $this->googleCategoryStorage->replaceLocaleCategories(
                $result['locale_code'],
                $result['rows']
            );

            $this->messageManager->addSuccessMessage(
                __('Imported %1 Google categories for locale %2.', count($result['rows']), $result['locale_code'])
            );
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('Import failed: %1', $e->getMessage()));
        }

        $params = ['section' => 'googlefeed'];
        if ($storeId > 0) {
            $params['store'] = $storeId;
        } elseif ($websiteId > 0) {
            $params['website'] = $websiteId;
        }

        return $this->resultRedirectFactory->create()->setPath('adminhtml/system_config/edit', $params);
    }

    /**
     * @param int $storeId
     * @param int $websiteId
     * @return string
     */
    private function resolveLocaleCode($storeId, $websiteId)
    {
        if ($storeId > 0) {
            $locale = (string)$this->scopeConfig->getValue(
                'general/locale/code',
                ScopeInterface::SCOPE_STORE,
                $storeId
            );

            if ($locale !== '') {
                return $locale;
            }
        }

        if ($websiteId > 0) {
            $website = $this->storeManager->getWebsite($websiteId);
            $defaultStore = $website->getDefaultStore();
            if ($defaultStore) {
                $locale = (string)$this->scopeConfig->getValue(
                    'general/locale/code',
                    ScopeInterface::SCOPE_STORE,
                    (int)$defaultStore->getId()
                );
                if ($locale !== '') {
                    return $locale;
                }
            }
        }

        $currentStoreId = (int)$this->storeManager->getStore()->getId();
        $locale = (string)$this->scopeConfig->getValue(
            'general/locale/code',
            ScopeInterface::SCOPE_STORE,
            $currentStoreId
        );

        return $locale !== '' ? $locale : 'en_US';
    }
}
