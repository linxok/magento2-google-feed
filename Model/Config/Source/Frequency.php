<?php
namespace MyCompany\GoogleFeed\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class Frequency implements OptionSourceInterface
{
    /**
     * Get options
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => 'daily', 'label' => __('Daily')],
            ['value' => 'twice_daily', 'label' => __('Twice Daily (every 12 hours)')],
            ['value' => 'every_6_hours', 'label' => __('Every 6 Hours')],
            ['value' => 'hourly', 'label' => __('Hourly')],
            ['value' => 'weekly', 'label' => __('Weekly')]
        ];
    }
}
