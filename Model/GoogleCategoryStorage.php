<?php
namespace MyCompany\GoogleFeed\Model;

use Magento\Framework\App\ResourceConnection;

class GoogleCategoryStorage
{
    const TABLE_NAME = 'mycompany_googlefeed_taxonomy';

    /**
     * @var ResourceConnection
     */
    private $resourceConnection;

    /**
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(ResourceConnection $resourceConnection)
    {
        $this->resourceConnection = $resourceConnection;
    }

    /**
     * @param string $localeCode
     * @param array $rows
     * @return void
     */
    public function replaceLocaleCategories($localeCode, array $rows)
    {
        $localeCode = $this->normalizeLocaleCode($localeCode);
        $connection = $this->resourceConnection->getConnection();
        $tableName = $this->resourceConnection->getTableName(self::TABLE_NAME);

        $connection->delete($tableName, ['locale_code = ?' => $localeCode]);

        if (empty($rows)) {
            return;
        }

        $hierarchyData = $this->buildHierarchyData($rows, $localeCode);
        
        if (!empty($hierarchyData)) {
            $connection->insertMultiple($tableName, $hierarchyData);
        }
    }

    /**
     * Build hierarchy data with parent_id relationships
     *
     * @param array $rows
     * @param string $localeCode
     * @return array
     */
    private function buildHierarchyData(array $rows, $localeCode)
    {
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $pathToIdMap = [];
        $data = [];

        usort($rows, function ($a, $b) {
            return substr_count($a['category_path'], '>') <=> substr_count($b['category_path'], '>');
        });

        foreach ($rows as $row) {
            if (!isset($row['google_category_id'], $row['category_path'])) {
                continue;
            }

            $id = (int)$row['google_category_id'];
            $fullPath = (string)$row['category_path'];
            $parts = array_map('trim', explode('>', $fullPath));
            $level = count($parts) - 1;
            $categoryName = end($parts);
            
            $parentId = null;
            if ($level > 0) {
                $parentPath = implode(' > ', array_slice($parts, 0, -1));
                $parentId = $pathToIdMap[$parentPath] ?? null;
            }

            $data[] = [
                'locale_code' => $localeCode,
                'google_category_id' => $id,
                'parent_id' => $parentId,
                'level' => $level,
                'category_name' => $categoryName,
                'category_path' => $fullPath,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $pathToIdMap[$fullPath] = $id;
        }

        return $data;
    }

    /**
     * @param string $localeCode
     * @return array
     */
    public function getOptionsByLocale($localeCode)
    {
        $tableName = $this->resourceConnection->getTableName(self::TABLE_NAME);
        $connection = $this->resourceConnection->getConnection();
        $candidates = $this->getLocaleCandidates($localeCode);

        foreach ($candidates as $candidateLocale) {
            $select = $connection->select()
                ->from($tableName, ['google_category_id', 'parent_id', 'level', 'category_name'])
                ->where('locale_code = ?', $candidateLocale)
                ->order(['level ASC', 'category_name ASC']);

            $rows = $connection->fetchAll($select);
            if (empty($rows)) {
                continue;
            }

            return $this->buildHierarchicalOptions($rows);
        }

        return [];
    }

    /**
     * Build hierarchical options with visual tree structure
     *
     * @param array $rows
     * @return array
     */
    private function buildHierarchicalOptions(array $rows)
    {
        $categoriesById = [];
        foreach ($rows as $row) {
            $categoriesById[(int)$row['google_category_id']] = $row;
        }

        $tree = $this->buildTree($categoriesById, null);
        
        return $this->flattenTree($tree);
    }

    /**
     * Build tree structure from flat array
     *
     * @param array $categories
     * @param int|null $parentId
     * @return array
     */
    private function buildTree(array $categories, $parentId)
    {
        $branch = [];

        foreach ($categories as $category) {
            $categoryParentId = $category['parent_id'] !== null ? (int)$category['parent_id'] : null;
            
            if ($categoryParentId === $parentId) {
                $children = $this->buildTree($categories, (int)$category['google_category_id']);
                if ($children) {
                    $category['children'] = $children;
                }
                $branch[] = $category;
            }
        }

        return $branch;
    }

    /**
     * Flatten tree to options array with indentation
     *
     * @param array $tree
     * @param int $level
     * @param string $prefix
     * @return array
     */
    private function flattenTree(array $tree, $level = 0, $prefix = '')
    {
        $options = [];

        foreach ($tree as $index => $node) {
            $id = (int)$node['google_category_id'];
            $name = (string)$node['category_name'];
            
            $currentPrefix = '';
            if ($level > 0) {
                $currentPrefix = str_repeat('    ', $level - 1);
                $currentPrefix .= '└── ';
            }
            
            $options[] = [
                'value' => (string)$id,
                'label' => sprintf('%s%s [%d]', $currentPrefix, $name, $id),
            ];

            if (!empty($node['children'])) {
                $childOptions = $this->flattenTree($node['children'], $level + 1, $currentPrefix);
                $options = array_merge($options, $childOptions);
            }
        }

        return $options;
    }

    /**
     * @param int|string $googleCategoryId
     * @param string $localeCode
     * @return string|null
     */
    public function getPathById($googleCategoryId, $localeCode)
    {
        $googleCategoryId = (int)$googleCategoryId;
        if ($googleCategoryId <= 0) {
            return null;
        }

        $tableName = $this->resourceConnection->getTableName(self::TABLE_NAME);
        $connection = $this->resourceConnection->getConnection();
        $candidates = $this->getLocaleCandidates($localeCode);

        foreach ($candidates as $candidateLocale) {
            $select = $connection->select()
                ->from($tableName, ['category_path'])
                ->where('locale_code = ?', $candidateLocale)
                ->where('google_category_id = ?', $googleCategoryId)
                ->limit(1);

            $path = $connection->fetchOne($select);
            if ($path !== false) {
                return (string)$path;
            }
        }

        return null;
    }

    /**
     * @param string $localeCode
     * @return string
     */
    public function normalizeLocaleCode($localeCode)
    {
        $localeCode = trim(str_replace('_', '-', (string)$localeCode));
        if (preg_match('/^[a-z]{2}$/i', $localeCode)) {
            return strtolower($localeCode);
        }

        if (preg_match('/^([a-z]{2})-([a-z]{2})$/i', $localeCode, $matches)) {
            return strtolower($matches[1]) . '-' . strtoupper($matches[2]);
        }

        return 'en-US';
    }

    /**
     * @param string $localeCode
     * @param array $rows
     * @return void
     */
    public function saveTaxonomy($localeCode, array $rows)
    {
        $this->replaceLocaleCategories($localeCode, $rows);
    }

    /**
     * Get tree structure for UI component
     *
     * @param string $localeCode
     * @return array
     */
    public function getTreeByLocale($localeCode)
    {
        $tableName = $this->resourceConnection->getTableName(self::TABLE_NAME);
        $connection = $this->resourceConnection->getConnection();
        $candidates = $this->getLocaleCandidates($localeCode);

        foreach ($candidates as $candidateLocale) {
            $select = $connection->select()
                ->from($tableName, ['google_category_id', 'parent_id', 'level', 'category_name'])
                ->where('locale_code = ?', $candidateLocale)
                ->order(['level ASC', 'category_name ASC']);

            $rows = $connection->fetchAll($select);
            if (empty($rows)) {
                continue;
            }

            $categoriesById = [];
            foreach ($rows as $row) {
                $categoriesById[(int)$row['google_category_id']] = $row;
            }

            return $this->buildTreeStructure($categoriesById, null);
        }

        return [];
    }

    /**
     * Build tree structure for JSON response
     *
     * @param array $categories
     * @param int|null $parentId
     * @return array
     */
    private function buildTreeStructure(array $categories, $parentId)
    {
        $branch = [];

        foreach ($categories as $category) {
            $categoryParentId = $category['parent_id'] !== null ? (int)$category['parent_id'] : null;
            
            if ($categoryParentId === $parentId) {
                $node = [
                    'value' => (string)$category['google_category_id'],
                    'label' => $category['category_name'],
                    'is_active' => true,
                ];

                $children = $this->buildTreeStructure($categories, (int)$category['google_category_id']);
                if ($children) {
                    $node['optgroup'] = $children;
                }
                
                $branch[] = $node;
            }
        }

        return $branch;
    }

    /**
     * @param string $localeCode
     * @return array
     */
    private function getLocaleCandidates($localeCode)
    {
        $normalized = $this->normalizeLocaleCode($localeCode);
        $language = strtolower(substr($normalized, 0, 2));

        $candidates = [$normalized, $language, 'en-US'];

        return array_values(array_unique($candidates));
    }
}
