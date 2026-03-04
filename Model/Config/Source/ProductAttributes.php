<?php
namespace MyCompany\GoogleFeed\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Magento\Catalog\Model\ResourceModel\Product\Attribute\CollectionFactory;

class ProductAttributes implements OptionSourceInterface
{
    /**
     * @var CollectionFactory
     */
    protected $attributeCollectionFactory;

    /**
     * @param CollectionFactory $attributeCollectionFactory
     */
    public function __construct(
        CollectionFactory $attributeCollectionFactory
    ) {
        $this->attributeCollectionFactory = $attributeCollectionFactory;
    }

    /**
     * Get options
     *
     * @return array
     */
    public function toOptionArray()
    {
        $options = [
            ['value' => '', 'label' => __('-- Please Select --')],
            ['value' => 'sku', 'label' => __('SKU (sku)')]
        ];

        $collection = $this->attributeCollectionFactory->create();
        $collection->addFieldToFilter('is_user_defined', 1)
            ->setOrder('frontend_label', 'ASC');

        foreach ($collection as $attribute) {
            $options[] = [
                'value' => $attribute->getAttributeCode(),
                'label' => $attribute->getFrontendLabel()
                    ? $attribute->getFrontendLabel() . ' (' . $attribute->getAttributeCode() . ')'
                    : $attribute->getAttributeCode()
            ];
        }

        return $options;
    }
}
