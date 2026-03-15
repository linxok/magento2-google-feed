<?php
namespace MyCompany\GoogleFeed\Controller\Adminhtml\Category;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use MyCompany\GoogleFeed\Model\GoogleCategoryStorage;

class Tree extends Action implements HttpGetActionInterface
{
    const ADMIN_RESOURCE = 'MyCompany_GoogleFeed::config';

    /**
     * @var JsonFactory
     */
    private $resultJsonFactory;

    /**
     * @var GoogleCategoryStorage
     */
    private $googleCategoryStorage;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @param Context $context
     * @param JsonFactory $resultJsonFactory
     * @param GoogleCategoryStorage $googleCategoryStorage
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        GoogleCategoryStorage $googleCategoryStorage,
        ScopeConfigInterface $scopeConfig
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->googleCategoryStorage = $googleCategoryStorage;
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        $storeId = (int)$this->getRequest()->getParam('store', 0);
        
        $locale = (string)$this->scopeConfig->getValue(
            'general/locale/code',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        $tree = $this->googleCategoryStorage->getTreeByLocale($locale ?: 'en_US');

        $result = $this->resultJsonFactory->create();
        return $result->setData($tree);
    }
}
