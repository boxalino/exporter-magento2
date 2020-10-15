<?php
namespace Boxalino\Exporter\Service\Component;

use Boxalino\Exporter\Api\Resource\BaseExporterResourceInterface;
use Boxalino\Exporter\Api\Component\TransactionExporterInterface;
use Boxalino\Exporter\Api\Resource\TransactionExporterResourceInterface;
use Psr\Log\LoggerInterface;
use Magento\Catalog\Model\ProductFactory;

/**
 * Class Transaction
 * Exporting transactions
 *
 * @package Boxalino\Exporter\Model
 */
class Transaction extends Base
    implements TransactionExporterInterface
{

    const EXPORTER_COMPONENT_TYPE = "transactions";

    /**
     * @var TransactionExporterResourceInterface
     */
    protected $exporterResource;

    /**
     * @var \Magento\Catalog\Model\ProductFactory
     */
    protected $productFactory;

    public function __construct(
        LoggerInterface $logger,
        BaseExporterResourceInterface $baseExporterResource,
        TransactionExporterResourceInterface $exporterResource,
        ProductFactory $productFactory
    ){
        parent::__construct($logger, $baseExporterResource);
        $this->exporterResource = $exporterResource;
        $this->productFactory = $productFactory;
    }

    public function export() : void
    {
        if(!$this->getConfig()->isTransactionsExportEnabled())
        {
            $this->getLogger()->info("Boxalino Exporter: TRANSACTION EXPORT is disabled for account $this->account");
            return;
        }

        $this->getLogger()->info("Boxalino Exporter: TRANSACTION EXPORT for account $this->account");
        $page = 1; $header = true; $transactions_to_save = [];
        $transactionAttributes = $this->getAttributes();
        $billingColumns = $shippingColumns = [];
        if (count($transactionAttributes))
        {
            foreach ($transactionAttributes as $attribute)
            {
                $billingColumns['billing_' . $attribute] = $attribute;
                $shippingColumns['shipping_' . $attribute] = $attribute;
            }
        }

        $tempSelect = $this->exporterResource->prepareSelectByShippingBillingModeSql($this->account, $billingColumns, $shippingColumns, $this->getConfig()->getTransactionMode());
        while (true)
        {
            $transactions = $this->exporterResource->getByLimitPage(TransactionExporterInterface::PAGINATION, $page, $tempSelect);
            if(sizeof($transactions) < 1 && $page == 1)
            {
                $this->getLogger()->info("Boxalino Exporter: TRANSACTIONS EXPORT - NO TRANSACTIONS FOUND for account $this->account");
                return;
            } elseif (sizeof($transactions) < 1 && $page > 1)
            {
                break;
            }

            $this->getLogger()->info("Boxalino Exporter: TRANSACTIONS EXPORT - loaded PAGE #$page for account $this->account");
            $configurable = [];
            foreach ($transactions as $transaction)
            {
                //is configurable
                if ($transaction['product_type'] == 'configurable')
                {
                    $configurable[$transaction['product_id']] = $transaction;
                }

                $productOptions = @unserialize($transaction['product_options']);
                if($productOptions === FALSE)
                {
                    $productOptions = @json_decode($transaction['product_options'], true);
                    if(is_null($productOptions))
                    {
                        $this->getLogger()->error("Boxalino Exporter: failed to unserialize and json decode product_options for order with entity_id: " . $transaction['entity_id']);
                        continue;
                    }
                }

                //is configurable - simple product
                if (intval($transaction['price']) == 0 && $transaction['product_type'] == 'simple' && isset($productOptions['info_buyRequest']['product']))
                {
                    if (isset($configurable[$productOptions['info_buyRequest']['product']]))
                    {
                        $pid = $configurable[$productOptions['info_buyRequest']['product']];
                        $transaction['original_price'] = $pid['original_price'];
                        $transaction['price'] = $pid['price'];
                    } else {
                        $product = $this->productFactory->create();
                        try {
                            $product->load($productOptions['info_buyRequest']['product']);

                            $transaction['original_price'] = ($product->getPrice());
                            $transaction['price'] = ($product->getPrice());

                            $tmp = [];
                            $tmp['original_price'] = $transaction['original_price'];
                            $tmp['price'] = $transaction['price'];

                            $configurable[$productOptions['info_buyRequest']['product']] = $tmp;
                            $tmp = null;
                        } catch (\Exception $e) {
                            $this->getLogger()->critical($e);
                        }
                        $product = null;
                    }
                }

                $status = 0; // 0 - pending, 1 - confirmed, 2 - shipping
                if ($transaction['updated_at'] != $transaction['created_at'])
                {
                    switch ($transaction['status'])
                    {
                        case 'canceled':
                            break;
                        case 'processing':
                            $status = 1;
                            break;
                        case 'complete':
                            $status = 2;
                            break;
                    }
                }

                $final_transaction = [
                    'order_id' => $transaction['entity_id'],
                    'increment_id' => $transaction['increment_id'],
                    'entity_id' => $transaction['product_id'],
                    'customer_id' => $transaction['customer_id'],
                    'email' => $transaction['customer_email'],
                    'guest_id' => $transaction['guest_id'],
                    'price' => $transaction['original_price'],
                    'discounted_price' => $transaction['price'],
                    'tax_amount'=> $transaction['tax_amount'],
                    'coupon_code' => $transaction['coupon_code'],
                    'currency' => $transaction['order_currency_code'],
                    'quantity' => $transaction['qty_ordered'],
                    'subtotal' => $transaction['base_subtotal'],
                    'total_order_value' => $transaction['grand_total'],
                    'discount_amount' => $transaction['discount_amount'],
                    'discount_percent' => $transaction['discount_percent'],
                    'shipping_costs' => $transaction['shipping_amount'],
                    'order_date' => $transaction['created_at'],
                    'confirmation_date' => $status == 1 ? $transaction['updated_at'] : null,
                    'shipping_date' => $status == 2 ? $transaction['updated_at'] : null,
                    'status' => $transaction['status'],
                    'shipping_method'=> $transaction['shipping_method'],
                    'shipping_description' => $transaction['shipping_description'],
                    'payment_method' => $transaction['payment_method'],
                    'payment_name' => $this->getMethodTitleFromAdditionalInformationJson($transaction['payment_title'])
                ];

                if (count($transactionAttributes))
                {
                    foreach ($transactionAttributes as $attribute)
                    {
                        $final_transaction['billing_' . $attribute] = $transaction['billing_' . $attribute];
                        $final_transaction['shipping_' . $attribute] = $transaction['shipping_' . $attribute];
                    }
                }

                $transactions_to_save[] = $final_transaction;
                $guest_id_transaction = null; $final_transaction = null;
            }

            $data = $transactions_to_save;
            $transactions_to_save = null; $configurable = null; $transactions = null;

            if ($header)
            {
                if(count($data) < 1) { return; }
                $data = array_merge(array(array_keys(end($data))), $data);
                $header = false;
            }

            $this->getLogger()->info("Boxalino Exporter: TRANSACTION EXPORT - save #$page to file for account $this->account");
            $this->getFiles()->savePartToCsv('transactions.csv', $data);
            $data = null; $page++;
        }

        $sourceKey = $this->getLibrary()->setCSVTransactionFile(
            $this->getFiles()->getPath('transactions.csv'),
            'order_id',
            'entity_id',
            'customer_id',
            'order_date',
            'total_order_value',
            'price',
            'discounted_price',
            'currency',
            'email'
        );

        $this->getLibrary()->addSourceCustomerGuestProperty($sourceKey,'guest_id');

        $this->exportExtraTables();
        $this->getLogger()->info("Boxalino Exporter: TRANSACTION EXPORT - END of export for account  $this->account");
    }


    /**
     * @return array
     * @throws \Exception
     */
    public function getAttributes() : array
    {
        $this->getLogger()->info("Boxalino Exporter: get all transaction attributes for account: $this->account");
        $attributes = $this->exporterResource->getAttributes();

        $this->getLogger()->info("Boxalino Exporter: get configured transaction attributes for account: $this->account");
        $filteredAttributes = $this->getConfig()->getAccountTransactionsProperties($attributes, []);
        $attributes = array_intersect($attributes, $filteredAttributes);

        $this->getLogger()->info('Boxalino Exporter: returning configured transaction attributes for account '
            . $this->account . ': ' . implode(',', array_values($attributes))
        );

        return $attributes;
    }

    /**
     * Reading payment method name from payment additional information
     *
     * @param string $additionalInformation
     * @return string
     */
    protected function getMethodTitleFromAdditionalInformationJson(string $additionalInformation) : ?string
    {
        $additionalInformation = json_decode($additionalInformation, true);
        if(isset($additionalInformation['method_title']))
        {
            return $additionalInformation['method_title'];
        }

        return '';
    }

}
