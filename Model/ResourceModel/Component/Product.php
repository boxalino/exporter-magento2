<?php
namespace Boxalino\Exporter\Model\ResourceModel\Component;

use Boxalino\Exporter\Api\Resource\ProductExporterResourceInterface;
use Magento\Framework\App\ProductMetadata;
use Magento\Framework\DB\Select;

/**
 * Keeps most of db access for the exporter class
 *
 * Class Exporter
 * @package Boxalino\Exporter\Model\ResourceModel
 */
class Product  extends Base
    implements ProductExporterResourceInterface
{

    /**
     * @var []
     */
    protected $exportIds = [];

    /**
     * @var bool
     */
    protected $isDelta = false;

    /**
     * @return array
     */
    public function getAttributes(): array
    {
        $select = $this->adapter->select()
            ->from(
                ['ca_t' => $this->adapter->getTableName('catalog_eav_attribute')],
                ['attribute_id']
            )
            ->joinInner(
                ['a_t' => $this->adapter->getTableName('eav_attribute')],
                'ca_t.attribute_id = a_t.attribute_id',
                ['attribute_code']
            );

        return $this->adapter->fetchPairs($select);
    }

    /**
     * @param string $id
     * @param string $attributeId
     * @param int $storeId
     * @return string
     */
    public function getAttributeValue(string $id, string $attributeId, int $storeId): string
    {
        $select = $this->adapter->select()
            ->from(
                ['c_p_e' => $this->adapter->getTableName('catalog_product_entity')],
                [new \Zend_Db_Expr("CASE WHEN c_p_e_b.value IS NULL THEN c_p_e_a.value ELSE c_p_e_b.value END as value")]
            )
            ->joinLeft(
                ['c_p_e_a' => $this->adapter->getTableName('catalog_product_entity_int')],
                'c_p_e_a.entity_id = c_p_e.entity_id AND c_p_e_a.store_id = 0 AND c_p_e_a.attribute_id = ' . $attributeId,
                []
            )
            ->joinLeft(
                ['c_p_e_b' => $this->adapter->getTableName('catalog_product_entity_int')],
                'c_p_e_b.entity_id = c_p_e.entity_id AND c_p_e_b.store_id = ' . $storeId . ' AND c_p_e_b.attribute_id = ' . $attributeId,
                []
            )
            ->where('c_p_e.entity_id = ?', $id);

        return $this->adapter->fetchOne($select);
    }

    /**
     * @param int $storeId
     * @param string $attributeId
     * @param string $condition
     * @return array
     */
    public function getDuplicateIds(int $storeId, string $attributeId, string $condition): array
    {
        $select = $this->adapter->select()
            ->from(
                ['c_p_r' => $this->adapter->getTableName('catalog_product_relation')],
                [
                    'child_id',
                    new \Zend_Db_Expr("CASE WHEN c_p_e_b.value IS NULL THEN c_p_e_a.value ELSE c_p_e_b.value END as value")
                ]
            )->joinLeft(
                ['c_p_e_a' => $this->adapter->getTableName('catalog_product_entity_int')],
                'c_p_e_a.entity_id = c_p_r.child_id AND c_p_e_a.store_id = 0 AND c_p_e_a.attribute_id = ' . $attributeId,
                ['default_store' => 'c_p_e_a.store_id']
            )->joinLeft(
                ['c_p_e_b' => $this->adapter->getTableName('catalog_product_entity_int')],
                'c_p_e_b.entity_id = c_p_r.child_id AND c_p_e_b.store_id = ' . $storeId . ' AND c_p_e_b.attribute_id = ' . $attributeId,
                ['c_p_e_b.store_id']
            );

        if (!empty($this->exportIds) && $this->isDelta) {
            $select->where('c_p_r.parent_id IN(?)', $this->exportIds);
        }

        $main = $this->adapter->select()
            ->from(
                ['main' => new \Zend_Db_Expr('( ' . $select->__toString() . ' )')],
                ['id' => 'child_id', 'child_id']
            )
            ->where('main.value <> ?', $condition);

        return $this->adapter->fetchPairs($main);

    }

    /**
     * @param int $storeId
     * @return array
     */
    public function getCategoriesByStoreId(int $storeId): array
    {
        $attributeId = $this->getAttributeIdByAttributeCodeAndEntityType('name', \Magento\Catalog\Setup\CategorySetup::CATEGORY_ENTITY_TYPE_ID);
        $select = $this->adapter->select()
            ->from(
                ['c_t' => $this->adapter->getTableName('catalog_category_entity')],
                ['entity_id', 'parent_id']
            )
            ->joinInner(
                ['c_v_i' => $this->adapter->getTableName('catalog_category_entity_varchar')],
                'c_v_i.entity_id = c_t.entity_id AND c_v_i.store_id = 0 AND c_v_i.attribute_id = ' . $attributeId,
                ['value_default' => 'c_v_i.value']
            )
            ->joinLeft(
                ['c_v_l' => $this->adapter->getTableName('catalog_category_entity_varchar')],
                'c_v_l.entity_id = c_t.entity_id AND c_v_l.attribute_id = ' . $attributeId . ' AND c_v_l.store_id = ' . $storeId,
                ['c_v_l.value', 'c_v_l.store_id']
            );

        $selectSql = $this->adapter->select()
            ->from(
                array('joins' => new \Zend_Db_Expr("( " . $select->__toString() . ")")),
                array(
                    'entity_id' => 'joins.entity_id',
                    'parent_id' => 'joins.parent_id',
                    new \Zend_Db_Expr("IF (joins.value IS NULL OR joins.value='', joins.value_default, joins.value ) AS value")
                )
            );

        return $this->adapter->fetchAll($selectSql);
    }

    /**
     * @param int $limit
     * @param int $page
     * @return array
     */
    public function getByLimitPage(int $limit, int $page): array
    {
        $select = $this->adapter->select()
            ->from(
                ['e' => $this->adapter->getTableName('catalog_product_entity')],
                ["*"]
            )
            ->limit($limit, ($page - 1) * $limit)
            ->joinLeft(
                ['p_t' => $this->adapter->getTableName('catalog_product_relation')],
                'e.entity_id = p_t.child_id', ['group_id' => 'parent_id']
            );

        if (!empty($this->exportIds) && $this->isDelta) {
            $select->where('e.entity_id IN(?)', $this->exportIds);
        }

        return $this->adapter->fetchAll($select);
    }

    /**
     * @param array $codes
     * @return array
     */
    public function getAttributesByCodes(array $codes = []): array
    {
        $select = $this->adapter->select()
            ->from(
                ['main_table' => $this->adapter->getTableName('eav_attribute')],
                ['attribute_id', 'attribute_code', 'backend_type', 'frontend_input']
            )
            ->joinInner(
                ['additional_table' => $this->adapter->getTableName('catalog_eav_attribute'), 'is_global'],
                'additional_table.attribute_id = main_table.attribute_id'
            )
            ->where('main_table.entity_type_id = ?', \Magento\Catalog\Setup\CategorySetup::CATALOG_PRODUCT_ENTITY_TYPE_ID)
            ->where('main_table.attribute_code IN(?)', $codes);

        return $this->adapter->fetchAll($select);
    }

    /**
     * @param $type
     * @param $key
     * @return array
     */
    public function getPriceByType(string $type, string $key): array
    {
        $select = $this->getPriceSqlByType($type, $key);
        if (!empty($this->exportIds) && $this->isDelta) {
            $select->where('c_p_r.parent_id IN(?)', $this->exportIds);
        }

        return $this->adapter->fetchAll($select);
    }

    /**
     * @param string $type
     * @param string $key
     * @return \Magento\Framework\DB\Select
     */
    public function getPriceSqlByType(string $type, string $key): \Magento\Framework\DB\Select
    {
        $statusId = $this->getAttributeIdByAttributeCodeAndEntityType('status', \Magento\Catalog\Setup\CategorySetup::CATALOG_PRODUCT_ENTITY_TYPE_ID);
        $select = $this->adapter->select()
            ->from(
                array('c_p_r' => $this->adapter->getTableName('catalog_product_relation')),
                array('parent_id')
            )
            ->join(
                array('t_d' => $this->adapter->getTableName('catalog_product_entity_' . $type)),
                't_d.entity_id = c_p_r.child_id',
                array(
                    'value' => 'MIN(t_d.value)'
                )
            )->join(
                array('t_s' => $this->adapter->getTableName('catalog_product_entity_int')),
                't_s.entity_id = c_p_r.child_id AND t_s.value = 1',
                array()
            )
            ->where('t_d.attribute_id = ?', $key)
            ->where('t_s.attribute_id = ?', $statusId)
            ->group(array('parent_id'));

        return $select;
    }

    /**
     * @param string $type
     * @param int $websiteId
     * @return array
     */
    public function getIndexedPrice(string $type, int $websiteId): array
    {
        $select = $this->adapter->select()
            ->from(
                array('c_p_i' => $this->adapter->getTableName('catalog_product_index_price')),
                ['entity_id', 'value' => $type . "_price"]
            )
            ->where('website_id=?', $websiteId)
            ->group(['entity_id']);

        if (!empty($this->exportIds) && $this->isDelta) {
            $select->where('c_p_i.entity_id IN(?)', $this->exportIds);
        }

        return $this->adapter->fetchAll($select);
    }

    /**
     * @param string $type
     * @param int $websiteId
     * @return array
     */
    public function getGroupedIndexedPrice(string $type, int $websiteId): array
    {
        $groupSelect = $this->adapter->select()
            ->from(
                array('c_p_i' => $this->adapter->getTableName('catalog_product_index_price')),
                ['entity_id', 'value' => $type . "_price"]
            )
            ->where('website_id=?', $websiteId)
            ->group(['entity_id']);

        $relationSelect = $this->adapter->select()
            ->from(
                ['e' => $this->adapter->getTableName('catalog_product_entity')],
                ["e.entity_id", "group_id" => new \Zend_Db_Expr("IF (p_r.parent_id IS NULL, e.entity_id, p_r.parent_id)")]
            )
            ->joinLeft(
                ['p_r' => $this->adapter->getTableName('catalog_product_relation')],
                'e.entity_id = p_r.child_id', []
            );

        $select = $this->adapter->select()
            ->from(
                ['e_r' => new \Zend_Db_Expr("( " . $relationSelect->__toString() . ")")],
                ['e_r.entity_id']
            )
            ->joinLeft(
                ['c_p_i_g' => new \Zend_Db_Expr("( " . $groupSelect->__toString() . ")")],
                "e_r.group_id = c_p_i_g.entity_id", ['c_p_i_g.value']
            );

        if (!empty($this->exportIds) && $this->isDelta) {
            $select->where('e_r.entity_id IN(?)', $this->exportIds);
        }

        return $this->adapter->fetchAll($select);
    }

    /**
     * @param int $websiteId
     * @return array
     */
    public function getDistinctCustomerGroupIdsForPriceByWebsiteId(int $websiteId) : array
    {
        $select = $this->adapter->select()
            ->from(
                $this->adapter->getTableName('catalog_product_index_price'),
                [new \Zend_Db_Expr("DISTINCT(customer_group_id) AS customer_group_id")]
            )
            ->where('website_id = ?', $websiteId);

        return $this->adapter->fetchCol($select);
    }

    /**
     * @param string $type
     * @param int $websiteId
     * @param string $customerGroupId
     * @return array
     */
    public function getIndexedPriceForCustomerGroup(string $type, int $websiteId, string $customerGroupId) : array
    {
        $select = $this->adapter->select()
            ->from(
                array('c_p_i' => $this->adapter->getTableName('catalog_product_index_price')),
                ['entity_id', 'value'=> $type . "_price"]
            )
            ->where('website_id = ?', $websiteId)
            ->where('customer_group_id = ?', $customerGroupId)
            ->group(['entity_id']);

        if(!empty($this->exportIds) && $this->isDelta)
        {
            $select->where('c_p_i.entity_id IN(?)', $this->exportIds);
        }

        return $this->adapter->fetchAll($select);
    }

    /**
     * @param string $type
     * @param int $websiteId
     * @param string $customerGroupId
     * @return array
     */
    public function getGroupedIndexedPriceForCustomerGroup(string $type, int $websiteId, string $customerGroupId) : array
    {
        $groupSelect = $this->adapter->select()
            ->from(
                array('c_p_i' => $this->adapter->getTableName('catalog_product_index_price')),
                ['entity_id', 'value'=> $type . "_price"]
            )
            ->where('website_id = ?', $websiteId)
            ->where('customer_group_id = ?', $customerGroupId)
            ->group(['entity_id']);

        $relationSelect = $this->adapter->select()
            ->from(
                ['e' => $this->adapter->getTableName('catalog_product_entity')],
                ["e.entity_id", "group_id" => new \Zend_Db_Expr("IF (p_r.parent_id IS NULL, e.entity_id, p_r.parent_id)")]
            )
            ->joinLeft(
                ['p_r' => $this->adapter->getTableName('catalog_product_relation')],
                'e.entity_id = p_r.child_id', []
            );

        $select = $this->adapter->select()
            ->from(
                ['e_r' => new \Zend_Db_Expr("( " . $relationSelect->__toString() . ")")],
                ['e_r.entity_id']
            )
            ->joinLeft(
                ['c_p_i_g' => new \Zend_Db_Expr("( " . $groupSelect->__toString() . ")")],
                "e_r.group_id = c_p_i_g.entity_id", ['c_p_i_g.value']
            );

        if(!empty($this->exportIds) && $this->isDelta)
        {
            $select->where('e_r.entity_id IN(?)', $this->exportIds);
        }

        return $this->adapter->fetchAll($select);
    }

    /**
     * Get child product attribute value based on the parent product attribute value
     *
     * @param string $attributeCode
     * @param string $type
     * @param int $storeId
     * @return \Zend_Db_Select
     * @throws \Zend_Db_Select_Exception
     */
    public function getAttributeParentUnionSqlByCodeTypeStore(string $attributeCode, string $type, int $storeId) : \Zend_Db_Select
    {
        $attributeId = $this->getAttributeIdByAttributeCodeAndEntityType($attributeCode, \Magento\Catalog\Setup\CategorySetup::CATALOG_PRODUCT_ENTITY_TYPE_ID);
        $select1 = $this->adapter->select()
            ->from(
                ['c_p_e' => $this->adapter->getTableName('catalog_product_entity')],
                ['c_p_e.entity_id']
            )
            ->joinLeft(
                ['c_p_r' => $this->adapter->getTableName('catalog_product_relation')],
                'c_p_e.entity_id = c_p_r.child_id',
                ['parent_id']
            );

        $select1->where('t_d.attribute_id = ?', $attributeId)->where('t_d.store_id = 0 OR t_d.store_id = ?',$storeId);
        if(!empty($this->exportIds) && $this->isDelta) $select1->where('c_p_e.entity_id IN(?)', $this->exportIds);

        $select2 = clone $select1;
        $select2->join(['t_d' => $this->adapter->getTableName('catalog_product_entity_' . $type)],
            't_d.entity_id = c_p_e.entity_id AND c_p_r.parent_id IS NULL',
            [
                't_d.attribute_id',
                't_d.value',
                't_d.store_id'
            ]
        );
        $select1->join(['t_d' => $this->adapter->getTableName('catalog_product_entity_' . $type)],
            't_d.entity_id = c_p_r.parent_id',
            [
                't_d.attribute_id',
                't_d.value',
                't_d.store_id'
            ]
        );

        return $this->adapter->select()->union(
            array($select1, $select2),
            \Zend_Db_Select::SQL_UNION
        );
    }

    /**
     * Query for setting the product status value based on the parent properties and product visibility
     * Fixes the issue when parent product is enabled but child product is disabled.
     *
     * @param int $storeId
     * @return \Magento\Framework\DB\Select
     */
    public function getStatusParentDependabilityByStore(int $storeId) : \Magento\Framework\DB\Select
    {
        $statusId = $this->getAttributeIdByAttributeCodeAndEntityType('status', \Magento\Catalog\Setup\CategorySetup::CATALOG_PRODUCT_ENTITY_TYPE_ID);
        $visibilityId = $this->getAttributeIdByAttributeCodeAndEntityType('visibility', \Magento\Catalog\Setup\CategorySetup::CATALOG_PRODUCT_ENTITY_TYPE_ID);

        $parentsCountSql = $this->getAttributeParentCountSqlByAttrIdValueStoreId($statusId,  \Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED, $storeId);
        $childCountSql = $this->getParentAttributeChildCountSqlByAttrIdValueStoreId($statusId,  \Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED, $storeId);

        $statusSql = $this->getEavJoinAttributeSQLByStoreAttrIdTable($statusId, $storeId, "catalog_product_entity_int");
        $visibilitySql = $this->getEavJoinAttributeSQLByStoreAttrIdTable($visibilityId, $storeId, "catalog_product_entity_int");
        $select = $this->adapter->select()
            ->from(
                ['c_p_e' => $this->adapter->getTableName('catalog_product_entity')],
                ['c_p_e.entity_id', 'c_p_e.type_id']
            )
            ->joinLeft(
                ['c_p_r' => $this->adapter->getTableName('catalog_product_relation')],
                'c_p_e.entity_id = c_p_r.child_id',
                ['parent_id']
            )
            ->join(
                ['c_p_e_s' => new \Zend_Db_Expr("( ". $statusSql->__toString() . ' )')],
                "c_p_e.entity_id = c_p_e_s.entity_id",
                ['c_p_e_s.attribute_id', 'c_p_e_s.store_id','entity_status'=>'c_p_e_s.value']
            )
            ->join(
                ['c_p_e_v' => new \Zend_Db_Expr("( ". $visibilitySql->__toString() . ' )')],
                "c_p_e.entity_id = c_p_e_v.entity_id",
                ['entity_visibility'=>'c_p_e_v.value']
            );

        if(!empty($this->exportIds) && $this->isDelta) $select->where('c_p_e.entity_id IN(?)', $this->exportIds);
        $configurableType = \Magento\ConfigurableProduct\Model\Product\Type\Configurable::TYPE_CODE;
        $groupedType = \Magento\GroupedProduct\Model\Product\Type\Grouped::TYPE_CODE;
        $visibilityOptions = implode(',', [\Magento\Catalog\Model\Product\Visibility::VISIBILITY_BOTH, \Magento\Catalog\Model\Product\Visibility::VISIBILITY_IN_CATALOG, \Magento\Catalog\Model\Product\Visibility::VISIBILITY_IN_SEARCH]);
        $finalSelect = $this->adapter->select()
            ->from(
                ["entity_select" => new \Zend_Db_Expr("( ". $select->__toString() . " )")],
                [
                    "entity_select.entity_id",
                    "entity_select.parent_id",
                    "entity_select.store_id",
                    "value" => new \Zend_Db_Expr("
                        (CASE
                            WHEN (entity_select.type_id = '{$configurableType}' OR entity_select.type_id = '{$groupedType}') AND entity_select.entity_status = '1' THEN IF(child_count.child_count > 0, 1, 2)
                            WHEN entity_select.parent_id IS NULL THEN entity_select.entity_status
                            WHEN entity_select.entity_status = '2' THEN 2
                            WHEN entity_select.entity_status = '1' AND entity_select.entity_visibility IN ({$visibilityOptions}) AND entity_select.parent_id IS NOT NULL AND parent_count.count IS NULL THEN 2
                            ELSE IF(entity_select.entity_status = '1' AND entity_select.entity_visibility IN ({$visibilityOptions}), 1, IF(entity_select.entity_status = '1' AND (parent_count.count > 0 OR parent_count.count IS NOT NULL), 1, 2))
                         END
                        )"
                    )
                ]
            )
            ->joinLeft(
                ["parent_count"=> new \Zend_Db_Expr("( ". $parentsCountSql->__toString() . " )")],
                "parent_count.entity_id = entity_select.entity_id",
                ["count"]
            )
            ->joinLeft(
                ["child_count"=> new \Zend_Db_Expr("( ". $childCountSql->__toString() . " )")],
                "child_count.entity_id = entity_select.entity_id",
                ["child_count"]
            );

        return $finalSelect;
    }

    /**
     * Getting count of parent products that have a certain value for an attribute
     * Used for validation of child values
     *
     * @param $attributeId
     * @param $value
     * @param $storeId
     * @return \Magento\Framework\DB\Select
     */
    protected function getAttributeParentCountSqlByAttrIdValueStoreId($attributeId, $value, $storeId) : \Magento\Framework\DB\Select
    {
        $storeAttributeValue = $this->getEavJoinAttributeSQLByStoreAttrIdTable($attributeId, $storeId, "catalog_product_entity_int");
        $select = $this->adapter->select()
            ->from(
                ['c_p_r' => $this->adapter->getTableName('catalog_product_relation')],
                ['c_p_r.parent_id']
            )
            ->joinLeft(
                ['c_p_e' => $this->adapter->getTableName('catalog_product_entity')],
                'c_p_e.entity_id = c_p_r.child_id',
                ['c_p_e.entity_id']
            )
            ->join(['t_d' => new \Zend_Db_Expr("( ". $storeAttributeValue->__toString() . ' )')],
                't_d.entity_id = c_p_r.parent_id',
                ['t_d.value']
            );

        return $this->adapter->select()
            ->from(
                ["parent_select"=> new \Zend_Db_Expr("( ". $select->__toString() . ' )')],
                ["count" => new \Zend_Db_Expr("COUNT(parent_select.parent_id)"), 'entity_id']
            )
            ->where("parent_select.value = ?", $value)
            ->group("parent_select.entity_id");
    }

    /**
     * Getting count of child products that have a certain value for an attribute
     * Used for validation of parent values
     *
     * @param $attributeId
     * @param $value
     * @param $storeId
     * @return \Magento\Framework\DB\Select
     */
    protected function getParentAttributeChildCountSqlByAttrIdValueStoreId($attributeId, $value, $storeId) : \Magento\Framework\DB\Select
    {
        $storeAttributeValue = $this->getEavJoinAttributeSQLByStoreAttrIdTable($attributeId, $storeId, "catalog_product_entity_int");
        $select = $this->adapter->select()
            ->from(
                ['c_p_r' => $this->adapter->getTableName('catalog_product_relation')],
                ['c_p_r.child_id']
            )
            ->joinLeft(
                ['c_p_e' => $this->adapter->getTableName('catalog_product_entity')],
                'c_p_e.entity_id = c_p_r.parent_id',
                ['c_p_e.entity_id']
            )
            ->join(['t_d' => new \Zend_Db_Expr("( ". $storeAttributeValue->__toString() . ' )')],
                't_d.entity_id = c_p_r.child_id',
                ['t_d.value']
            )
            ->where('t_d.value = ?', $value);

        return $this->adapter->select()
            ->from(
                ["child_select"=> new \Zend_Db_Expr("( ". $select->__toString() . ' )')],
                ["child_count" => new \Zend_Db_Expr("COUNT(child_select.child_id)"), 'entity_id']
            )
            ->group("child_select.entity_id");
    }

    /**
     * @return array
     */
    public function getStockInformation() : array
    {
        $select = $this->adapter->select()
            ->from(
                $this->adapter->getTableName('cataloginventory_stock_status'),
                ['entity_id' => 'product_id', 'stock_status', 'qty']
            )
            ->where('stock_id = ?', 1);
        if(!empty($this->exportIds) && $this->isDelta)
        {
            $select->where('product_id IN(?)', $this->exportIds);
        }

        return $this->adapter->fetchAll($select);
    }

    /**
     * Export the SEO URL link based on product visibility and parent
     *
     * @return Select
     */
    public function getSeoUrlInformationByStoreId(int $storeId) : Select
    {
        $urlKeyAttrId = $this->getAttributeIdByAttributeCodeAndEntityType("url_key", \Magento\Catalog\Setup\CategorySetup::CATALOG_PRODUCT_ENTITY_TYPE_ID);
        $urlKeySql = $this->getEavJoinAttributeSQLByStoreAttrIdTable($urlKeyAttrId, $storeId, "catalog_product_entity_varchar");

        $visibilityId = $this->getAttributeIdByAttributeCodeAndEntityType('visibility', \Magento\Catalog\Setup\CategorySetup::CATALOG_PRODUCT_ENTITY_TYPE_ID);
        $visibilitySql = $this->getEavJoinAttributeSQLByStoreAttrIdTable($visibilityId, $storeId, "catalog_product_entity_int");
        $visibilityOptions = implode(',', [\Magento\Catalog\Model\Product\Visibility::VISIBILITY_BOTH, \Magento\Catalog\Model\Product\Visibility::VISIBILITY_IN_SEARCH]);

        $select = $this->adapter->select()
            ->from(
                ['c_p_e' => $this->adapter->getTableName('catalog_product_entity')],
                ['c_p_e.entity_id']
            )
            ->joinLeft(
                ['c_p_r' => $this->adapter->getTableName('catalog_product_relation')],
                'c_p_e.entity_id = c_p_r.child_id',
                ['c_p_r.parent_id']
            )
            ->joinLeft(
                ['c_p_e_u' => new \Zend_Db_Expr("( ". $urlKeySql->__toString() . ' )')],
                "c_p_e.entity_id = c_p_e_u.entity_id",
                ['entity_value'=>'c_p_e_u.value', 'entity_store_id' => 'c_p_e_u.store_id']
            )
            ->joinLeft(
                ['c_p_e_u_p' => new \Zend_Db_Expr("( ". $urlKeySql->__toString() . ' )')],
                "c_p_r.parent_id = c_p_e_u_p.entity_id",
                ['parent_value'=>'c_p_e_u_p.value']
            )
            ->joinLeft(
                ['c_p_e_v' => new \Zend_Db_Expr("( ". $visibilitySql->__toString() . ' )')],
                "c_p_e.entity_id = c_p_e_v.entity_id",
                ['entity_visibility'=>'c_p_e_v.value']
            );

        if(!empty($this->exportIds) && $this->isDelta)
        {
            $select->where('c_p_e.entity_id IN(?)', $this->exportIds);
        }

        $finalSelect = $this->adapter->select()
            ->from(
                ["entity_select" => new \Zend_Db_Expr("( ". $select->__toString() . " )")],
                [
                    "entity_select.entity_id",
                    "store_id" => "entity_select.entity_store_id",
                    "value" => new \Zend_Db_Expr("
                        (CASE
                            WHEN entity_select.parent_id IS NULL THEN entity_select.entity_value
                            WHEN entity_select.entity_visibility IN ({$visibilityOptions}) THEN entity_select.entity_value
                            ELSE entity_select.parent_value
                         END
                        )"
                    )
                ]
            );

        return $finalSelect;
    }

    /**
     * @return Select
     */
    public function getParentSeoUrlInformationByStoreId(int $storeId) : Select
    {
        $urlKeyAttrId = $this->getAttributeIdByAttributeCodeAndEntityType("url_key", \Magento\Catalog\Setup\CategorySetup::CATALOG_PRODUCT_ENTITY_TYPE_ID);
        $urlKeySql = $this->getEavJoinAttributeSQLByStoreAttrIdTable($urlKeyAttrId, $storeId, "catalog_product_entity_varchar");

        $select = $this->adapter->select()
            ->from(
                ['c_p_e' => $this->adapter->getTableName('catalog_product_entity')],
                ['c_p_e.entity_id']
            )
            ->joinLeft(
                ['c_p_r' => $this->adapter->getTableName('catalog_product_relation')],
                'c_p_e.entity_id = c_p_r.child_id',
                ['c_p_r.parent_id']
            )
            ->joinLeft(
                ['c_p_e_u' => new \Zend_Db_Expr("( ". $urlKeySql->__toString() . ' )')],
                "c_p_e.entity_id = c_p_e_u.entity_id",
                ['entity_value'=>'c_p_e_u.value', 'entity_store_id' => 'c_p_e_u.store_id']
            )
            ->joinLeft(
                ['c_p_e_u_p' => new \Zend_Db_Expr("( ". $urlKeySql->__toString() . ' )')],
                "c_p_r.parent_id = c_p_e_u_p.entity_id",
                ['parent_value'=>'c_p_e_u_p.value']
            );

        if(!empty($this->exportIds) && $this->isDelta)
        {
            $select->where('c_p_e.entity_id IN(?)', $this->exportIds);
        }

        $finalSelect = $this->adapter->select()
            ->from(
                ["entity_select" => new \Zend_Db_Expr("( ". $select->__toString() . " )")],
                [
                    "entity_select.entity_id",
                    "value" => new \Zend_Db_Expr("
                        (CASE
                            WHEN entity_select.parent_id IS NULL THEN entity_select.entity_value
                            ELSE entity_select.parent_value
                         END
                        )"
                    )
                ]
            );

        return $finalSelect;
    }

    /**
     * @return array
     */
    public function getWebsiteInformation() : array
    {
        $select = $this->adapter->select()
            ->from(
                ['c_p_w' => $this->adapter->getTableName('catalog_product_website')],
                ['entity_id' => 'product_id', 'website_id']
            )->joinLeft(
                ['s_w' => $this->adapter->getTableName('store_website')],
                's_w.website_id = c_p_w.website_id',
                ['s_w.name']
            );
        if(!empty($this->exportIds) && $this->isDelta)
        {
            $select->where('product_id IN(?)', $this->exportIds);
        }

        return $this->adapter->fetchAll($select);
    }

    /**
     * @return array
     */
    public function getSuperLinkInformation() : array
    {
        $select = $this->adapter->select()
            ->from(
                $this->adapter->getTableName('catalog_product_super_link'),
                ['entity_id' => 'product_id', 'parent_id', 'link_id']
            );
        if(!empty($this->exportIds) && $this->isDelta)
        {
            $select->where('product_id IN(?)', $this->exportIds);
        }

        return $this->adapter->fetchAll($select);
    }

    /**
     * @return array
     */
    public function getLinksInformation() : array
    {
        $select = $this->adapter->select()
            ->from(
                ['pl'=> $this->adapter->getTableName('catalog_product_link')],
                ['entity_id' => 'product_id', 'linked_product_id', 'lt.code']
            )
            ->joinLeft(
                ['lt' => $this->adapter->getTableName('catalog_product_link_type')],
                'pl.link_type_id = lt.link_type_id', []
            )
            ->where('lt.link_type_id = pl.link_type_id');
        if(!empty($this->exportIds) && $this->isDelta)
        {
            $select->where('product_id IN(?)', $this->exportIds);
        }

        return $this->adapter->fetchAll($select);
    }

    /**
     * @return \Magento\Framework\DB\Select
     */
    protected function getParentCategoriesInformationSql() : \Magento\Framework\DB\Select
    {
        $select = $this->adapter->select()
            ->from(
                ['c_p_e' => $this->adapter->getTableName('catalog_product_entity')],
                ['c_p_e.entity_id']
            )
            ->joinLeft(
                ['c_p_r' => $this->adapter->getTableName('catalog_product_relation')],
                'c_p_e.entity_id = c_p_r.child_id',
                []
            );
        if(!empty($this->exportIds) && $this->isDelta)
        {
            $select->where('product_id IN(?)', $this->exportIds);
        }

        return $select;
    }

    /**
     * @return array
     * @throws \Zend_Db_Select_Exception
     */
    public function getParentCategoriesInformation() : array
    {
        $selectTwo = $this->getParentCategoriesInformationSql();
        $selectOne = clone $selectTwo;
        $selectOne->join(
            ['c_c_p' => $this->adapter->getTableName('catalog_category_product')],
            'c_c_p.product_id = c_p_r.parent_id',
            ['category_id']
        );
        $selectTwo->join(
            ['c_c_p' => $this->adapter->getTableName('catalog_category_product')],
            'c_c_p.product_id = c_p_e.entity_id AND c_p_r.parent_id IS NULL',
            ['category_id']
        );

        $select = $this->adapter->select()
            ->union(
                [$selectOne, $selectTwo],
                \Magento\Framework\DB\Select::SQL_UNION_ALL
            );

        return $this->adapter->fetchAll($select);
    }

    /**
     * @param array $duplicateIds
     * @return array
     */
    public function getParentCategoriesInformationByDuplicateIds(array $duplicateIds = []) : array
    {
        $select = $this->adapter->select()
            ->from(
                ['c_p_e' => $this->adapter->getTableName('catalog_product_entity')],
                ['entity_id']
            )->join(
                ['c_c_p' => $this->adapter->getTableName('catalog_category_product')],
                'c_c_p.product_id = c_p_e.entity_id',
                ['category_id']
            )->where('c_p_e.entity_id IN(?)', $duplicateIds);

        return $this->adapter->fetchAll($select);
    }

    /**
     * @param int $storeId
     * @return \Magento\Framework\DB\Select
     */
    protected function getParentTitleInformationSql(int $storeId) : \Magento\Framework\DB\Select
    {
        $attrId = $this->getAttributeIdByAttributeCodeAndEntityType("name", \Magento\Catalog\Setup\CategorySetup::CATALOG_PRODUCT_ENTITY_TYPE_ID);
        $select = $this->adapter->select()
            ->from(
                ['c_p_e' => $this->adapter->getTableName('catalog_product_entity')],
                ['c_p_e.entity_id']
            )
            ->joinLeft(
                ['c_p_r' => $this->adapter->getTableName('catalog_product_relation')],
                'c_p_e.entity_id = c_p_r.child_id',
                ['c_p_r.parent_id']
            );
        $select->where('t_d.attribute_id = ?', $attrId)->where('t_d.store_id = 0 OR t_d.store_id = ?', $storeId);
        if(!empty($this->exportIds) && $this->isDelta)
        {
            $select->where('c_p_e.entity_id IN(?)', $this->exportIds);
        }

        return $select;
    }

    /**
     * @param int $storeId
     * @return array
     * @throws \Zend_Db_Select_Exception
     */
    public function getParentTitleInformationByStore(int $storeId) : array
    {
        $selectOne = $this->getParentTitleInformationSql($storeId);
        $selectTwo = clone $selectOne;
        $selectTwo->join(
            ['t_d' => $this->adapter->getTableName('catalog_product_entity_varchar')],
            't_d.entity_id = c_p_e.entity_id AND c_p_r.parent_id IS NULL',
            [new \Zend_Db_Expr('t_d.value as value'), 't_d.store_id']
        );
        $selectOne->join(
            ['t_d' => $this->adapter->getTableName('catalog_product_entity_varchar')],
            't_d.entity_id = c_p_r.parent_id',
            [new \Zend_Db_Expr('t_d.value as value'), 't_d.store_id']
        );

        $select = $this->adapter->select()
            ->union(
                [$selectOne, $selectTwo],
                \Magento\Framework\DB\Select::SQL_UNION_ALL
            );

        return $this->adapter->fetchAll($select);
    }

    /**
     * @param int $storeId
     * @param array $duplicateIds
     * @return array
     */
    public function getParentTitleInformationByStoreAndDuplicateIds(int $storeId, array $duplicateIds = []) : array
    {
        $attrId = $this->getAttributeIdByAttributeCodeAndEntityType('name', \Magento\Catalog\Setup\CategorySetup::CATALOG_PRODUCT_ENTITY_TYPE_ID);
        $select = $this->adapter->select()
            ->from(
                ['c_p_e' => $this->adapter->getTableName('catalog_product_entity')],
                ['entity_id', new \Zend_Db_Expr("CASE WHEN c_p_e_v_b.value IS NULL THEN c_p_e_v_a.value ELSE c_p_e_v_b.value END as value")]
            )->joinLeft(
                ['c_p_e_v_a' => $this->adapter->getTableName('catalog_product_entity_varchar')],
                '(c_p_e_v_a.attribute_id = ' . $attrId . ' AND c_p_e_v_a.store_id = 0) AND (c_p_e_v_a.entity_id = c_p_e.entity_id)',
                []
            )->joinLeft(
                ['c_p_e_v_b' => $this->adapter->getTableName('catalog_product_entity_varchar')],
                '(c_p_e_v_b.attribute_id = ' . $attrId . ' AND c_p_e_v_b.store_id = ' . $storeId . ') AND (c_p_e_v_b.entity_id = c_p_e.entity_id)',
                []
            )->where('c_p_e.entity_id IN (?)', $duplicateIds);

        return $this->adapter->fetchAll($select);
    }

    /**
     * For child product - set the parent rating (if its own value does not exist)
     * @param int $storeId
     * @return array
     */
    public function getRatingPercentByRatingTypeStoreId(int $ratingId, int $storeId) : array
    {
        $ratingSelect = $this->_getRatingSelectByRatingTypeStoreId($ratingId, $storeId);
        $select = $this->adapter->select()
            ->from(
                ['c_p_e' => $this->adapter->getTableName('catalog_product_entity')],
                [
                    'c_p_e.entity_id',
                    'value' => new \Zend_Db_Expr("CASE WHEN r_o_v_a_e.value IS NULL THEN r_o_v_a_p.value ELSE r_o_v_a_e.value END")
                ]
            )
            ->joinLeft(
                ['c_p_r' => $this->adapter->getTableName('catalog_product_relation')],
                'c_p_e.entity_id = c_p_r.child_id',
                []
            )
            ->joinLeft(
                ["r_o_v_a_e"=>new \Zend_Db_Expr("( " . $ratingSelect->__toString() . " )")],
                "r_o_v_a_e.entity_id = c_p_e.entity_id",
                []
            )
            ->joinLeft(
                ["r_o_v_a_p"=>new \Zend_Db_Expr("( " . $ratingSelect->__toString() . " )")],
                "r_o_v_a_p.entity_id = c_p_r.parent_id",
                []
            );

        if(!empty($this->exportIds) && $this->isDelta)
        {
            $select->where('c_p_e.entity_id IN(?)', $this->exportIds);
        }

        return $this->adapter->fetchAll($select);
    }

    /**
     * @param int $ratingId
     * @param int $storeId
     * @return Select
     */
    protected function _getRatingSelectByRatingTypeStoreId(int $ratingId, int $storeId) : Select
    {
        return $this->adapter->select()
            ->from(
                ['r_o_v_a' => $this->adapter->getTableName('rating_option_vote_aggregated')],
                [
                    'value' => new \Zend_Db_Expr('IF(r_o_v_a_s.percent_approved IS NULL, r_o_v_a.percent_approved, r_o_v_a_s.percent_approved)'),
                    'entity_id'=>'r_o_v_a.entity_pk_value'
                ]
            )
            ->joinLeft(
                ['r_o_v_a_s' => $this->adapter->getTableName('rating_option_vote_aggregated')],
                "r_o_v_a_s.entity_pk_value = r_o_v_a.entity_pk_value AND r_o_v_a_s.store_id=$storeId",
                []
            )
            ->where('r_o_v_a.store_id = 0');
    }

    /**
     * @param array $storeIds
     * @return array
     */
    public function getEnabledRatingTitlesByStoreIds(array $storeIds) : array
    {
        $enabledRatingsStores = array_merge([0], $storeIds);
        $select = $this->adapter->select()
            ->from(
                ['r' => $this->adapter->getTableName('rating')],
                ['rating_id', 'rating_code']
            )
            ->join(
                ['r_s' => $this->adapter->getTableName('rating_store')],
                "r_s.rating_id=r.rating_id",
                []
            )
            ->where("r_s.store_id IN(?)", $enabledRatingsStores)
            ->group("rating_id");

        return $this->adapter->fetchPairs($select);
    }

    /**
     * Default function for accessing product attributes values
     * join them with default store
     * and make a selection on the store id
     *
     * @param $attributeId
     * @param $storeId
     * @param $table
     * @param string $main
     * @return mixed
     */
    protected function getEavJoinAttributeSQLByStoreAttrIdTable(string $attributeId, int $storeId, string $table, string $main = 'catalog_product_entity') : \Magento\Framework\DB\Select
    {
        $select = $this->adapter
            ->select()
            ->from(
                array('e' => $main),
                array('entity_id' => 'entity_id')
            );

        $innerCondition = array(
            $this->adapter->quoteInto("{$attributeId}_default.entity_id = e.entity_id", ''),
            $this->adapter->quoteInto("{$attributeId}_default.attribute_id = ?", $attributeId),
            $this->adapter->quoteInto("{$attributeId}_default.store_id = ?", 0)
        );

        $joinLeftConditions = array(
            $this->adapter->quoteInto("{$attributeId}_store.entity_id = e.entity_id", ''),
            $this->adapter->quoteInto("{$attributeId}_store.attribute_id = ?", $attributeId),
            $this->adapter->quoteInto("{$attributeId}_store.store_id IN(?)", $storeId)
        );

        $select
            ->joinInner(
                array($attributeId . '_default' => $table), implode(' AND ', $innerCondition),
                array('default_value' => 'value', 'attribute_id')
            )
            ->joinLeft(
                array("{$attributeId}_store" => $table), implode(' AND ', $joinLeftConditions),
                array("store_value" => 'value', 'store_id')
            );

        return $this->adapter->select()
            ->from(
                array('joins' => $select),
                array(
                    'attribute_id'=>'joins.attribute_id',
                    'entity_id' => 'joins.entity_id',
                    'store_id' => new \Zend_Db_Expr("IF (joins.store_value IS NULL OR joins.store_value = '', 0, joins.store_id)"),
                    'value' => new \Zend_Db_Expr("IF (joins.store_value IS NULL OR joins.store_value = '', joins.default_value, joins.store_value)")
                )
            );
    }

    /**
     * @param $storeId
     * @param $key
     * @return array
     */
    public function getAttributeOptionValuesByStoreAndKey(int $storeId, string $key) : array
    {
        $select = $this->adapter->select()
            ->from(
                array('a_o' => $this->adapter->getTableName('eav_attribute_option')),
                array(
                    'option_id',
                    new \Zend_Db_Expr("CASE WHEN c_o.value IS NULL THEN b_o.value ELSE c_o.value END as value")
                )
            )->joinLeft(array('b_o' => $this->adapter->getTableName('eav_attribute_option_value')),
                'b_o.option_id = a_o.option_id AND b_o.store_id = 0',
                array()
            )->joinLeft(array('c_o' => $this->adapter->getTableName('eav_attribute_option_value')),
                'c_o.option_id = a_o.option_id AND c_o.store_id = ' . $storeId,
                array()
            )->where('a_o.attribute_id = ?', $key);

        return $this->adapter->fetchAll($select);
    }

    /**
     * @param array $exportIds
     * @return $this
     */
    public function setExportIds(array $exportIds = [])
    {
        $this->exportIds = $exportIds;
        return $this;
    }

    /**
     * @return array
     */
    public function getExportIds() : array
    {
        return $this->exportIds;
    }

    /**
     * @param bool $isDelta
     * @return $this
     */
    public function isDelta(bool $isDelta) : self
    {
        $this->isDelta = $isDelta;
        return $this;
    }

}
