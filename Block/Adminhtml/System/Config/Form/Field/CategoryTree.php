<?php
namespace MyCompany\GoogleFeed\Block\Adminhtml\System\Config\Form\Field;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Backend\Block\Template\Context;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;

class CategoryTree extends Field
{
    /**
     * @var CollectionFactory
     */
    protected $categoryCollectionFactory;

    /**
     * @var string
     */
    protected $_template = 'MyCompany_GoogleFeed::system/config/category_tree.phtml';

    /**
     * @param Context $context
     * @param CollectionFactory $categoryCollectionFactory
     * @param array $data
     */
    public function __construct(
        Context $context,
        CollectionFactory $categoryCollectionFactory,
        array $data = []
    ) {
        $this->categoryCollectionFactory = $categoryCollectionFactory;
        parent::__construct($context, $data);
    }

    /**
     * Get element HTML
     *
     * @param AbstractElement $element
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element)
    {
        $this->setElement($element);
        return $this->_toHtml();
    }

    /**
     * Get category tree as nested array
     *
     * @return array
     */
    public function getCategoryTree()
    {
        $collection = $this->categoryCollectionFactory->create();
        $collection->addAttributeToSelect('name')
            ->addAttributeToFilter('is_active', 1)
            ->setOrder('position', 'ASC');

        $categories = [];
        foreach ($collection as $category) {
            // Skip only the default root category (level 0)
            if ($category->getLevel() < 1) {
                continue;
            }
            $categories[$category->getId()] = [
                'id' => $category->getId(),
                'name' => $category->getName(),
                'level' => $category->getLevel(),
                'parent_id' => $category->getParentId(),
                'path' => $category->getPath()
            ];
        }

        return $this->buildTree($categories);
    }

    /**
     * Build hierarchical tree
     *
     * @param array $categories
     * @param int $parentId
     * @return array
     */
    protected function buildTree($categories, $parentId = null)
    {
        $tree = [];
        
        foreach ($categories as $category) {
            if ($parentId === null) {
                // Root level - show categories at level 1 (main root categories)
                if ($category['level'] == 1) {
                    $tree[$category['id']] = $category;
                    $tree[$category['id']]['children'] = $this->buildTree($categories, $category['id']);
                }
            } else {
                // Child level
                if ($category['parent_id'] == $parentId) {
                    $tree[$category['id']] = $category;
                    $tree[$category['id']]['children'] = $this->buildTree($categories, $category['id']);
                }
            }
        }
        
        return $tree;
    }

    /**
     * Get selected category IDs
     *
     * @return array
     */
    public function getSelectedCategories()
    {
        $value = $this->getElement()->getValue();
        if (empty($value)) {
            return [];
        }
        return explode(',', $value);
    }

    /**
     * Get element name
     *
     * @return string
     */
    public function getElementName()
    {
        return $this->getElement()->getName();
    }

    /**
     * Get element ID
     *
     * @return string
     */
    public function getElementId()
    {
        return $this->getElement()->getHtmlId();
    }

    /**
     * Render category tree recursively
     *
     * @param array $categories
     * @param array $selected
     * @param string $elementId
     * @param int $level
     * @return string
     */
    public function renderCategoryTree($categories, $selected, $elementId, $level = 0)
    {
        if (empty($categories)) {
            return '';
        }
        
        $html = '';
        foreach ($categories as $category) {
            $hasChildren = !empty($category['children']);
            $isChecked = in_array($category['id'], $selected);
            $categoryId = $category['id'];
            
            $html .= '<div class="category-item' . ($hasChildren ? ' has-children' : '') . '">';
            
            // Toggle button for parent categories
            if ($hasChildren) {
                $html .= '<span class="category-toggle" id="' . $this->escapeHtmlAttr($elementId) . '_toggle_' . $categoryId . '" onclick="categoryTreeToggle(\'' . $this->escapeJs($elementId) . '\', ' . $categoryId . ')">+</span>';
            } else {
                $html .= '<span class="category-toggle" style="visibility: hidden;">·</span>';
            }
            
            // Checkbox
            $html .= '<input type="checkbox" class="category-checkbox" value="' . $categoryId . '" id="' . $this->escapeHtmlAttr($elementId) . '_cat_' . $categoryId . '"';
            if ($isChecked) {
                $html .= ' checked="checked"';
            }
            if ($hasChildren) {
                $html .= ' onchange="categoryTreeCheckboxChange(\'' . $this->escapeJs($elementId) . '\', ' . $categoryId . ', this.checked)"';
            }
            $html .= ' />';
            
            // Label
            $html .= '<label class="category-label" for="' . $this->escapeHtmlAttr($elementId) . '_cat_' . $categoryId . '">';
            $html .= $this->escapeHtml($category['name']);
            $html .= '</label>';
            
            // Children
            if ($hasChildren) {
                $html .= '<div class="category-children" id="' . $this->escapeHtmlAttr($elementId) . '_children_' . $categoryId . '">';
                $html .= $this->renderCategoryTree($category['children'], $selected, $elementId, $level + 1);
                $html .= '</div>';
            }
            
            $html .= '</div>';
        }
        
        return $html;
    }
}
