<?php
namespace MyCompany\GoogleFeed\Setup\Patch\Data;

use Magento\Catalog\Model\Category;
use Magento\Catalog\Setup\CategorySetupFactory;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class AddGoogleCategoryAttributes implements DataPatchInterface
{
    const ATTRIBUTE_CODE = 'mycompany_google_product_category';

    /**
     * @var ModuleDataSetupInterface
     */
    private $moduleDataSetup;

    /**
     * @var CategorySetupFactory
     */
    private $categorySetupFactory;

    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     * @param CategorySetupFactory $categorySetupFactory
     */
    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        CategorySetupFactory $categorySetupFactory
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->categorySetupFactory = $categorySetupFactory;
    }

    /**
     * @return void
     */
    public function apply()
    {
        $setup = $this->moduleDataSetup;
        $setup->startSetup();

        $categorySetup = $this->categorySetupFactory->create(['setup' => $setup]);

        if (!$categorySetup->getAttributeId(Category::ENTITY, self::ATTRIBUTE_CODE)) {
            $categorySetup->addAttribute(
                Category::ENTITY,
                self::ATTRIBUTE_CODE,
                [
                    'type' => 'varchar',
                    'label' => 'Google Product Category',
                    'input' => 'select',
                    'source' => 'MyCompany\\GoogleFeed\\Model\\Category\\Attribute\\Source\\GoogleProductCategory',
                    'required' => false,
                    'sort_order' => 210,
                    'global' => ScopedAttributeInterface::SCOPE_STORE,
                    'visible' => true,
                    'user_defined' => true,
                    'group' => 'Display Settings',
                ]
            );
        }

        $setup->endSetup();
    }

    /**
     * @return array
     */
    public static function getDependencies()
    {
        return [
            \MyCompany\GoogleFeed\Setup\Patch\Schema\CreateGoogleCategoryTaxonomyTable::class,
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
