<?php
namespace MyCompany\GoogleFeed\Controller\Adminhtml\Feed;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;

class Index extends Action implements \Magento\Framework\App\Action\HttpGetActionInterface
{
    /**
     * @var PageFactory
     */
    protected $resultPageFactory;

    /**
     * @param Context $context
     * @param PageFactory $resultPageFactory
     */
    public function __construct(
        Context $context,
        PageFactory $resultPageFactory
    ) {
        $this->resultPageFactory = $resultPageFactory;
        parent::__construct($context);
    }

    /**
     * Check permission
     * @return bool
     */
    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('MyCompany_GoogleFeed::feed_view');
    }

    /**
     * Index action
     * @return \Magento\Framework\View\Result\Page
     */
    public function execute()
    {
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('MyCompany_GoogleFeed::googlefeed');
        $resultPage->getConfig()->getTitle()->prepend(__('Google Feed Management'));
        
        return $resultPage;
    }
}
