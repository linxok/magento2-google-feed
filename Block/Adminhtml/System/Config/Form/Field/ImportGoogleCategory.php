<?php
namespace MyCompany\GoogleFeed\Block\Adminhtml\System\Config\Form\Field;

use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

class ImportGoogleCategory extends Field
{
    /**
     * @var string
     */
    protected $_template = 'MyCompany_GoogleFeed::system/config/import_google_category.phtml';

    /**
     * @param Context $context
     * @param array $data
     */
    public function __construct(Context $context, array $data = [])
    {
        parent::__construct($context, $data);
    }

    /**
     * @param AbstractElement $element
     * @return string
     */
    public function render(AbstractElement $element)
    {
        $element->unsScope()->unsCanUseWebsiteValue()->unsCanUseDefaultValue();
        return parent::render($element);
    }

    /**
     * @param AbstractElement $element
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element)
    {
        $this->addData([
            'button_label' => __('Import Google Product Categories'),
            'import_url' => $this->getUrl('googlefeed/taxonomy/import'),
            'store' => (int)$this->getRequest()->getParam('store', 0),
            'website' => (int)$this->getRequest()->getParam('website', 0),
        ]);

        return $this->_toHtml();
    }
}
