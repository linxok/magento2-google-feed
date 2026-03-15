<?php
namespace MyCompany\GoogleFeed\Setup\Patch\Data;

use Magento\Catalog\Model\Product;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class RemoveGoogleCategoryProductAttribute implements DataPatchInterface
{
    const ATTRIBUTE_CODE = 'mycompany_google_product_category';

    /**
     * @var ModuleDataSetupInterface
     */
    private $moduleDataSetup;

    /**
     * @var EavSetupFactory
     */
    private $eavSetupFactory;

    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     * @param EavSetupFactory $eavSetupFactory
     */
    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        EavSetupFactory $eavSetupFactory
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->eavSetupFactory = $eavSetupFactory;
    }

    /**
     * @return void
     */
    public function apply()
    {
        $setup = $this->moduleDataSetup;
        $setup->startSetup();

        $eavSetup = $this->eavSetupFactory->create(['setup' => $setup]);

        if ($eavSetup->getAttributeId(Product::ENTITY, self::ATTRIBUTE_CODE)) {
            $eavSetup->removeAttribute(Product::ENTITY, self::ATTRIBUTE_CODE);
        }

        $setup->endSetup();
    }

    /**
     * @return array
     */
    public static function getDependencies()
    {
        return [
            AddGoogleCategoryAttributes::class,
        ];
    }

    /**
     * @return array
     */
    public function getAliases()
    {
        return [];
    }
}
