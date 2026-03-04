<?php
namespace MyCompany\GoogleFeed\Ui\Component\Form\Element;

use Magento\Framework\Data\OptionSourceInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Form\Element\Select;
use MyCompany\GoogleFeed\Model\GoogleCategoryStorage;

class GoogleCategoryTree extends Select
{
    /**
     * @var GoogleCategoryStorage
     */
    private $googleCategoryStorage;

    /**
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param GoogleCategoryStorage $googleCategoryStorage
     * @param OptionSourceInterface|null $options
     * @param array $components
     * @param array $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        GoogleCategoryStorage $googleCategoryStorage,
        OptionSourceInterface $options = null,
        array $components = [],
        array $data = []
    ) {
        $this->googleCategoryStorage = $googleCategoryStorage;
        parent::__construct($context, $uiComponentFactory, $options, $components, $data);
    }

    /**
     * @return void
     */
    public function prepare()
    {
        $config = $this->getData('config');
        
        $config['component'] = 'MyCompany_GoogleFeed/js/form/element/google-category-tree';
        $config['elementTmpl'] = 'MyCompany_GoogleFeed/form/element/google-category-tree';
        $config['treeUrl'] = $this->context->getUrl('googlefeed/category/tree');
        
        $this->setData('config', $config);
        
        parent::prepare();
    }
}
