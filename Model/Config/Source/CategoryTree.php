<?php
namespace MyCompany\GoogleFeed\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;

class CategoryTree implements OptionSourceInterface
{
    /**
     * @var CollectionFactory
     */
    protected $categoryCollectionFactory;

    /**
     * @param CollectionFactory $categoryCollectionFactory
     */
    public function __construct(
        CollectionFactory $categoryCollectionFactory
    ) {
        $this->categoryCollectionFactory = $categoryCollectionFactory;
    }

    /**
     * Get options with full category tree
     *
     * @return array
     */
    public function toOptionArray()
    {
        $collection = $this->categoryCollectionFactory->create();
        $collection->addAttributeToSelect('name')
            ->addAttributeToFilter('is_active', 1)
            ->setOrder('path', 'ASC');

        $options = [];
        
        foreach ($collection as $category) {
            // Skip root category (level 0) and default category (level 1)
            if ($category->getLevel() < 2) {
                continue;
            }
            
            // Calculate indentation based on level
            $level = $category->getLevel() - 2; // Adjust to start from 0
            $indent = str_repeat('— ', $level);
            
            $options[] = [
                'value' => $category->getId(),
                'label' => $indent . $category->getName()
            ];
        }

        return $options;
    }
}
