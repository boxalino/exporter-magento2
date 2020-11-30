<?php
namespace Boxalino\Exporter\Model\ResourceModel\Component;

use Boxalino\Exporter\Api\Resource\TransactionExporterResourceInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Config\ConfigOptionsListConstants;

/**
 * Class Transaction
 *
 * @package Boxalino\Exporter\Model\ResourceModel
 */
class Transaction extends Base
    implements TransactionExporterResourceInterface
{

    /**
     * We use the crypt key as salt when generating the guest user hash
     * this way we can still optimize on those users behaviour, whitout
     * exposing any personal data. The server salt is there to guarantee
     * that we can't connect guest user profiles across magento installs.
     *
     * @param $account
     * @param array $billingColumns
     * @param array $shippingColumns
     * @param int $mode
     * @return mixed
     */
    public function prepareSelectByShippingBillingModeSql(string $account, array $billingColumns =[], array $shippingColumns = [], int $mode = 1) : Select
    {
        $salt = $this->adapter->quote(
            ((string) $this->deploymentConfig->get(ConfigOptionsListConstants::CONFIG_PATH_CRYPT_KEY)) .
            $account
        );
        $sales_order_table = $this->adapter->getTableName('sales_order');
        $sales_order_item = $this->adapter->getTableName('sales_order_item');
        $sales_order_address =  $this->adapter->getTableName('sales_order_address');
        $sales_order_payment =  $this->adapter->getTableName('sales_order_payment');

        $select = $this->adapter
            ->select()
            ->from(
                array('order' => $sales_order_table),
                array(
                    'increment_id',
                    'entity_id',
                    'status',
                    'updated_at',
                    'created_at',
                    'customer_id',
                    'base_subtotal',
                    'shipping_amount',
                    'shipping_method',
                    'customer_is_guest',
                    'customer_email',
                    'order_currency_code',
                    'coupon_code',
                    'grand_total',
                    'shipping_description'
                )
            )
            ->joinLeft(
                array('item' => $sales_order_item),
                'order.entity_id = item.order_id',
                array(
                    'product_id',
                    'product_options',
                    'price',
                    'original_price',
                    'product_type',
                    'qty_ordered',
                    'qty_refunded',
                    'amount_refunded',
                    'discount_amount',
                    'discount_percent',
                    'tax_amount'
                )
            )
            ->joinLeft(
                array('guest' => $sales_order_address),
                'order.billing_address_id = guest.entity_id',
                array(
                    'guest_id' => 'IF(guest.email IS NOT NULL, SHA1(CONCAT(guest.email, ' . $salt . ')), NULL)'
                )
            )
            ->joinLeft(
                array('payment' => $sales_order_payment),
                'order.entity_id = payment.entity_id',
                array(
                    'payment_method' => 'method',
                    'payment_title' => 'additional_information'
                )
            );

        if (!$mode) {
            $select->where('DATE(order.created_at) >=  DATE(NOW() - INTERVAL 1 MONTH)');
        }

        if(!empty($billingColumns) && !empty($shippingColumns))
        {
            $select
                ->joinLeft(
                    array('billing_address' => $sales_order_address),
                    'order.billing_address_id = billing_address.entity_id',
                    $billingColumns
                )
                ->joinLeft(
                    array('shipping_address' => $sales_order_address),
                    'order.shipping_address_id = shipping_address.entity_id',
                    $shippingColumns
                );
        }

        return $select;
    }

    /**
     * @return array
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getAttributes() : array
    {
        return $this->getColumnsByTableName('sales_order_address');
    }

    /**
     * @param int $limit
     * @param int $page
     * @param Select $initialSelect
     * @return array
     */
    public function getByLimitPage(int $limit, int $page, Select $initialSelect) : array
    {
        $select = $this->adapter->select()
            ->from(['transactions_export' => new \Zend_Db_Expr("( " . $initialSelect->__toString() . ')')], ['*'])
            ->limit($limit, ($page - 1) * $limit);

        return $this->adapter->fetchAll($select);
    }

}
