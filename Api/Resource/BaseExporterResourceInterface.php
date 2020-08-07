<?php
namespace Boxalino\Exporter\Api\Resource;

/**
 * Interface BaseExporterResourceInterface
 * Used by the Boxalino indexers to store db logic
 *
 * @package Boxalino\Exporter\Api\Resource
 */
interface BaseExporterResourceInterface
{
    /**
     * @param string $table
     * @return mixed
     */
    public function getColumnsByTableName(string $table) : array;

    /**
     * @param $table
     * @return mixed
     */
    public function getTableContent(string $table) : array;

    /**
     * @param string $code
     * @param string $type
     * @return string
     */
    public function getAttributeIdByAttributeCodeAndEntityType(string $code, string $type) : string;

}
