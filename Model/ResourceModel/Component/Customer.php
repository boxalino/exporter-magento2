<?php
namespace Boxalino\Exporter\Model\ResourceModel\Component;

use Boxalino\Exporter\Api\Resource\CustomerExporterResourceInterface;

/**
 * Keeps most of db access for the exporter class
 *
 * Class Exporter
 * @package Boxalino\Exporter\Model\ResourceModel
 */
class Customer extends Base
    implements CustomerExporterResourceInterface
{

    /**
     * @return array
     */
    public function getAttributes() : array
    {
        $select = $this->adapter->select()
            ->from(
                ['a_t' => $this->adapter->getTableName('eav_attribute')],
                ['code' => 'attribute_code', 'attribute_code']
            )
            ->where('a_t.entity_type_id = ?', \Magento\Customer\Api\CustomerMetadataInterface::ATTRIBUTE_SET_ID_CUSTOMER);

        return $this->adapter->fetchPairs($select);
    }

    /**
     * @param array $codes
     * @return array
     */
    public function getAttributesByCodes(array $codes = []) : array
    {
        $select = $this->adapter->select()
            ->from(
                ['main_table' => $this->adapter->getTableName('eav_attribute')],
                [
                    'aid' => 'attribute_id',
                    'attribute_code',
                    'backend_type',
                ]
            )
            ->joinInner(
                ['additional_table' => $this->adapter->getTableName('customer_eav_attribute')],
                'additional_table.attribute_id = main_table.attribute_id',
                []
            )
            ->where('main_table.entity_type_id = ?', \Magento\Customer\Api\CustomerMetadataInterface::ATTRIBUTE_SET_ID_CUSTOMER)
            ->where('main_table.attribute_code IN (?)', $codes);

        return $this->adapter->fetchAll($select);
    }

    /**
     * @param $limit
     * @param $page
     * @param array $attributeGroups
     * @return array
     */
    public function getAddressByFieldsAndLimit(int $limit, int $page, array $attributeGroups = []) : array
    {
        $select = $this->adapter->select()
            ->from(
                $this->adapter->getTableName('customer_entity'),
                $attributeGroups
            )
            ->joinLeft(
                $this->adapter->getTableName('customer_address_entity'),
                'customer_entity.entity_id = customer_address_entity.parent_id',
                ['country_id', 'postcode']
            )
            ->limit($limit, ($page - 1) * $limit);

        return $this->adapter->fetchAll($select);
    }

    /**
     * @param $attributes
     * @param $ids
     * @return array
     * @throws \Zend_Db_Select_Exception
     */
    public function getUnionAttributesByAttributesAndIds(array $attributes, array $ids) : array
    {
        $columns = ['entity_id', 'attribute_id', 'value'];
        $attributeTypes = ['varchar', 'int', 'datetime'];

        $selects = [];
        foreach($attributeTypes as $type)
        {
            if (count($attributes[$type]) > 0)
            {
                $selects[] = $this->getSqlForAttributesUnion(
                    $this->adapter->getTableName('customer_entity_'. $type),
                    $columns, $attributes[$type], $ids
                );
            }
        }

        if(count($selects)) {
            $select = $this->adapter->select()
                ->union(
                    $selects,
                    \Magento\Framework\DB\Select::SQL_UNION_ALL
                );

            return $this->adapter->fetchAll($select);
        }

        return [];
    }

    /**
     * @param $table
     * @param $columns
     * @param $attributes
     * @param $ids
     * @return \Magento\Framework\DB\Select
     */
    protected function getSqlForAttributesUnion(string $table, array $columns, array $attributes, array $ids) : \Magento\Framework\DB\Select
    {
        return $this->adapter->select()
            ->from(['ce' => $table], $columns)
            ->joinLeft(
                ['ea' => $this->adapter->getTableName('eav_attribute')],
                'ce.attribute_id = ea.attribute_id',
                'ea.attribute_code'
            )
            ->where('ce.attribute_id IN(?)', $attributes)
            ->where('ea.entity_type_id = ?', \Magento\Customer\Api\CustomerMetadataInterface::ATTRIBUTE_SET_ID_CUSTOMER)
            ->where('ce.entity_id IN (?)', $ids);
    }

}
