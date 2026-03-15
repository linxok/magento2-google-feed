<?php
namespace MyCompany\GoogleFeed\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class Condition implements OptionSourceInterface
{
    /**
     * Get options
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => 'new', 'label' => __('New')],
            ['value' => 'refurbished', 'label' => __('Refurbished')],
            ['value' => 'used', 'label' => __('Used')]
        ];
    }
}
