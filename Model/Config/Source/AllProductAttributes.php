<?php
namespace MyCompany\GoogleFeed\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Magento\Catalog\Model\ResourceModel\Product\Attribute\CollectionFactory;

class AllProductAttributes implements OptionSourceInterface
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
     * Get options including system attributes
     *
     * @return array
     */
    public function toOptionArray()
    {
        $options = [
            ['value' => '', 'label' => __('-- Please Select --')]
        ];

        $collection = $this->attributeCollectionFactory->create();
        $collection->setOrder('frontend_label', 'ASC');

        // Group attributes
        $userDefined = [];
        $systemDefined = [];

        foreach ($collection as $attribute) {
            $label = $attribute->getFrontendLabel() 
                ? $attribute->getFrontendLabel() . ' (' . $attribute->getAttributeCode() . ')'
                : $attribute->getAttributeCode();

            $option = [
                'value' => $attribute->getAttributeCode(),
                'label' => $label
            ];

            if ($attribute->getIsUserDefined()) {
                $userDefined[] = $option;
            } else {
                $systemDefined[] = $option;
            }
        }

        // Add user-defined attributes first
        if (!empty($userDefined)) {
            $options[] = [
                'label' => __('Custom Attributes'),
                'value' => $userDefined
            ];
        }

        // Add system attributes
        if (!empty($systemDefined)) {
            $options[] = [
                'label' => __('System Attributes'),
                'value' => $systemDefined
            ];
        }

        return $options;
    }
}
