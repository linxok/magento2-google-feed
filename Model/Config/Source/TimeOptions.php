<?php
namespace MyCompany\GoogleFeed\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class TimeOptions implements OptionSourceInterface
{
    /**
     * Get time options with 30-minute intervals
     *
     * @return array
     */
    public function toOptionArray()
    {
        $options = [];
        
        for ($hour = 0; $hour < 24; $hour++) {
            for ($minute = 0; $minute < 60; $minute += 30) {
                $time = sprintf('%02d:%02d', $hour, $minute);
                $options[] = [
                    'value' => $time,
                    'label' => $time
                ];
            }
        }
        
        return $options;
    }
}
