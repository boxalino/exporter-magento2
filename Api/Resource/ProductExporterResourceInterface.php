<?php
namespace Boxalino\Exporter\Api\Resource;

use Magento\Framework\DB\Select;

/**
 * Interface ProductExporterResourceInterface
 * Used by the Boxalino indexers to store db logic
 *
 * @package Boxalino\Exporter\Api\Resource
 */
interface ProductExporterResourceInterface extends BaseExporterResourceInterface
{

    /**
     * @return array
     */
    public function getAttributes() : array;

    /**
     * @param array $codes
     * @return array
     */
    public function getAttributesByCodes(array $codes = []) : array;

    /**
     * @param string $id
     * @param string $attributeId
     * @param int $storeId
     * @return string
     */
    public function getAttributeValue(string $id, string $attributeId, int $storeId) : string;

    /**
     * @param $storeId
     * @param $attributeId
     * @param $condition
     * @return mixed
     */
    public function getDuplicateIds(int $storeId, string $attributeId, string $condition) : array;

    /**
     * @param int $limit
     * @param int $page
     * @return array
     */
    public function getByLimitPage(int $limit, int $page) : array;

    /**
     * Get child product attribute value based on the parent product attribute value
     *
     * @param string $attributeCode
     * @param string $type
     * @param int $storeId
     * @return \Zend_Db_Select
     */
    public function getAttributeParentUnionSqlByCodeTypeStore(string $attributeCode, string $type, int $storeId) : \Zend_Db_Select;

    /**
     * Query for setting the product status value based on the parent properties and product visibility
     * Fixes the issue when parent product is enabled but child product is disabled.
     *
     * @param int $storeId
     * @return Select
     */
    public function getStatusParentDependabilityByStore(int $storeId) : Select;

    /**
     * Information from catalog_product_website
     *
     * @return array
     */
    public function getWebsiteInformation() : array;

    /**
     * Information from catalog_product_super_link
     *
     * @return array
     */
    public function getSuperLinkInformation() : array;

    /**
     * Information from catalog_product_link & catalog_product_link_type
     *
     * @return array
     */
    public function getLinksInformation() : array;

    /**
     * Information from catalog_category_product
     *
     * @return array
     */
    public function getParentCategoriesInformation() : array;

    /**
     * @param int $storeId
     * @return array
     */
    public function getParentTitleInformationByStore(int $storeId) : array;

    /**
     * @param int $storeId
     * @param string $attrId
     * @param array $duplicateIds
     * @return array
     */
    public function getParentTitleInformationByStoreAttrDuplicateIds(int $storeId, string $attrId, array $duplicateIds = []) : array;

    /**
     * @param array $duplicateIds
     * @return array
     */
    public function getParentCategoriesInformationByDuplicateIds(array $duplicateIds = []) : array;

    /**
     * @param $storeId
     * @return array
     */
    public function getCategoriesByStoreId(int $storeId): array;

    /**
     * @return array
     */
    public function getStockInformation() : array;

    /**
     * @param string $type
     * @param string $key
     * @return array
     */
    public function getPriceByType(string $type, string $key) : array;

    /**
     * @param string $type
     * @param string $key
     * @return Select
     */
    public function getPriceSqlByType(string $type, string $key) : Select;

    /**
     * @param $storeId
     * @param $key
     * @return mixed
     */
    public function getAttributeOptionValuesByStoreAndKey(int $storeId, string $key) : array;

}
