<?php
namespace Boxalino\Exporter\Api\Resource;

use Magento\Framework\DB\Select;

/**
 * Interface TransactionExporterResourceInterface
 * Used by the Boxalino indexers to store db logic
 *
 * @package Boxalino\Exporter\Api\Resource
 */
interface TransactionExporterResourceInterface extends BaseExporterResourceInterface
{
    /**
     * @return mixed
     */
    public function getAttributes() : array;

    /**
     * @param $account
     * @param array $billingColumns
     * @param array $shippingColumns
     * @param int $mode
     * @return mixed
     */
    public function prepareSelectByShippingBillingModeSql(string $account, array $billingColumns =[], array $shippingColumns = [], int $mode = 1) : array;

    /**
     * @param $limit
     * @param $page
     * @param $initialSelect
     * @return mixed
     */
    public function getByLimitPage(int $limit, int $page, Select $initialSelect) : array;

}
