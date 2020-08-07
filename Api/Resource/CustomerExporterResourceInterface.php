<?php
namespace Boxalino\Exporter\Api\Resource;

/**
 * Interface CustomerExporterResourceInterface
 * Used by the Boxalino indexers to store db logic
 *
 * @package Boxalino\Exporter\Api\Resource
 */
interface CustomerExporterResourceInterface extends BaseExporterResourceInterface
{

    /**
     * @return mixed
     */
    public function getAttributes();

    /**
     * @param array $codes
     * @return mixed
     */
    public function getAttributesByCodes(array $codes = []) : array;

    /**
     * @param $limit
     * @param $page
     * @param array $attributeGroups
     * @return mixed
     */
    public function getAddressByFieldsAndLimit(int $limit, int $page, array $attributeGroups = []) : array;

    /**
     * @param $attributes
     * @param $ids
     * @return mixed
     */
    public function getUnionAttributesByAttributesAndIds(array $attributes, array $ids) : array;

}
