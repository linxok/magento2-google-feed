<?php
namespace MyCompany\GoogleFeed\Setup\Patch\Schema;

use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\SchemaPatchInterface;

class AddHierarchyToTaxonomyTable implements SchemaPatchInterface
{
    /**
     * @var ModuleDataSetupInterface
     */
    private $moduleDataSetup;

    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     */
    public function __construct(ModuleDataSetupInterface $moduleDataSetup)
    {
        $this->moduleDataSetup = $moduleDataSetup;
    }

    /**
     * @return void
     */
    public function apply()
    {
        $setup = $this->moduleDataSetup;
        $setup->startSetup();

        $tableName = $setup->getTable('mycompany_googlefeed_taxonomy');
        $connection = $setup->getConnection();

        if ($connection->isTableExists($tableName)) {
            if (!$connection->tableColumnExists($tableName, 'parent_id')) {
                $connection->addColumn(
                    $tableName,
                    'parent_id',
                    [
                        'type' => Table::TYPE_INTEGER,
                        'unsigned' => true,
                        'nullable' => true,
                        'comment' => 'Parent Category ID',
                        'after' => 'google_category_id'
                    ]
                );
            }

            if (!$connection->tableColumnExists($tableName, 'level')) {
                $connection->addColumn(
                    $tableName,
                    'level',
                    [
                        'type' => Table::TYPE_SMALLINT,
                        'unsigned' => true,
                        'nullable' => false,
                        'default' => 0,
                        'comment' => 'Category Level',
                        'after' => 'parent_id'
                    ]
                );
            }

            if (!$connection->tableColumnExists($tableName, 'category_name')) {
                $connection->addColumn(
                    $tableName,
                    'category_name',
                    [
                        'type' => Table::TYPE_TEXT,
                        'length' => 255,
                        'nullable' => false,
                        'comment' => 'Category Name',
                        'after' => 'level'
                    ]
                );
            }

            $indexName = $connection->getIndexName(
                $tableName,
                ['locale_code', 'parent_id']
            );
            
            $indexes = $connection->getIndexList($tableName);
            $indexExists = false;
            
            foreach ($indexes as $index) {
                if ($index['KEY_NAME'] === $indexName) {
                    $indexExists = true;
                    break;
                }
            }
            
            if (!$indexExists) {
                $connection->addIndex(
                    $tableName,
                    $indexName,
                    ['locale_code', 'parent_id']
                );
            }
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
