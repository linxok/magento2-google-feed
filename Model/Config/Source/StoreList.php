<?php
namespace MyCompany\GoogleFeed\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Magento\Store\Model\StoreManagerInterface;

class StoreList implements OptionSourceInterface
{
    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(StoreManagerInterface $storeManager)
    {
        $this->storeManager = $storeManager;
    }

    /**
     * Get options
     *
     * @return array
     */
    public function toOptionArray()
    {
        $options = [];
        
        foreach ($this->storeManager->getStores() as $store) {
            if ($store->getIsActive()) {
                $websiteName = $this->storeManager->getWebsite($store->getWebsiteId())->getName();
                $options[] = [
                    'value' => $store->getId(),
                    'label' => sprintf('%s - %s (%s)', $websiteName, $store->getName(), $store->getCode())
                ];
            }
        }
        
        return $options;
    }
}
