<?php
namespace Boxalino\Exporter\Service\Component;

use Boxalino\Exporter\Api\Resource\BaseExporterResourceInterface;
use Boxalino\Exporter\Api\Component\CustomerExporterInterface;
use Boxalino\Exporter\Api\Resource\CustomerExporterResourceInterface;
use Psr\Log\LoggerInterface;

/**
 * Class Exporter
 *
 * @package Boxalino\Exporter\Model
 */
class Customer extends Base
    implements CustomerExporterInterface
{
    CONST EXPORTER_COMPONENT_TYPE = "customers";

    /**
     * @var CustomerExporterResourceInterface
     */
    protected $exporterResource;

    /**
     * @var \Magento\Directory\Model\Country
     */
    protected $countryHelper;

    public function __construct(
        LoggerInterface $logger,
        BaseExporterResourceInterface $baseExporterResource,
        \Magento\Directory\Model\CountryFactory $countryFactory,
        CustomerExporterResourceInterface $customerExporterResource
    ){
        parent::__construct($logger, $baseExporterResource);
        $this->countryHelper = $countryFactory->create();
        $this->exporterResource = $customerExporterResource;
    }

    /**
     * @throws \Zend_Db_Select_Exception
     */
    public function export()
    {
        if(!$this->getConfig()->isCustomersExportEnabled())
        {
            $this->getLogger()->info("Boxalino Exporter: CUSTOMERS EXPORT is disabled for account: $this->account");
            return;
        }

        $this->getLogger()->info("Boxalino Exporter: CUSTOMERS EXPORT for account: $this->account");
        $page = 1; $header = true; $attrsFromDb = ['int'=>[], 'static'=>[], 'varchar'=>[], 'datetime'=>[]];

        $this->getLogger()->info("Boxalino Exporter: get final customer attributes for account: $this->account" );
        $customerAttributes = $this->getAttributes();

        $this->getLogger()->info("Boxalino Exporter: get customer attributes backend types for account: $this->account");
        $result = $this->exporterResource->getAttributesByCodes($customerAttributes);
        foreach ($result as $attr)
        {
            if (isset($attrsFromDb[$attr['backend_type']]))
            {
                $attrsFromDb[$attr['backend_type']][] = $attr['backend_type'] == 'static' ? $attr['attribute_code'] : $attr['aid'];
            }
        }
        $fieldsForCustomerSelect =  array_merge(['entity_id', 'confirmation'], $attrsFromDb['static']);
        do {
            $this->getLogger()->info("Boxalino Exporter: Customers - load page $page for account: $this->account");
            $this->getLogger()->info("Boxalino Exporter: Customers - get customer ids for page $page for account:  $this->account");

            $customers = $this->exporterResource->getAddressByFieldsAndLimit(CustomerExporterInterface::PAGINATION, $page, $fieldsForCustomerSelect);
            $customerIds = array_column($customers, 'entity_id');  $saveList = [];
            $customerAttributesValues = $this->exporterResource->getUnionAttributesByAttributesAndIds($attrsFromDb, $customerIds);
            if(!empty($customerAttributesValues))
            {
                $this->getLogger()->info("Boxalino Exporter: Customers - retrieve data for side queries page $page for account: $this->account");
                foreach ($customerAttributesValues as $r)
                {
                    $customers[array_search($r['entity_id'], $customerIds)][$r['attribute_code']] = $r['value'];
                }
            }
            $this->getLogger()->info("Boxalino Exporter: CUSTOMERS EXPORT - load data per customer for page #$page for account:  $this->account");
            foreach ($customers as $row)
            {
                $countryCode = $row['country_id'];
                if (array_key_exists('gender', $row)) {
                    $row['gender'] = is_null($row['gender']) ? null : ($row['gender'] % 2 == 0 ? 'female' : 'male');
                }

                $customer = [
                    'customer_id' => $row['entity_id'],
                    'country' => empty($countryCode) ? null : $this->countryHelper->loadByCode($countryCode)->getName(),
                    'zip' => $row['postcode']
                ];
                foreach($customerAttributes as $attr)
                {
                    $customer[$attr] = array_key_exists($attr, $row) ? $row[$attr] : '';
                }
                $saveList[] = $customer;
            }

            $data = $saveList;
            if (count($customers) == 0 && $header) { return; }
            if ($header)
            {
                $data = array_merge(array(array_keys(end($saveList))), $saveList);
                $header = false;
            }
            $this->getLogger()->info("Boxalino Exporter: CUSTOMERS EXPORT - save to file for page $page for account: $this->account");
            $this->getFiles()->savePartToCsv("customers.csv", $data);
            $data = null; $count = count($saveList); $page++;

        } while ($count >= CustomerExporterInterface::PAGINATION);

        $customers = null;
        $customerSourceKey = $this->getLibrary()->addMainCSVCustomerFile($this->getFiles()->getPath('customers.csv'), 'customer_id');
        foreach ($customerAttributes as $prop)
        {
            if($prop == 'id') {
                continue;
            }

            $this->getLibrary()->addSourceStringField($customerSourceKey, $prop, $prop);
        }

        $this->exportExtraTables();
        $this->getLogger()->info("Boxalino Exporter: CUSTOMER EXPORT - END of exporting for account: $this->account");
    }

    /**
     * @return array
     */
    public function getAttributes() : array
    {
        $this->getLogger()->info('Boxalino Exporter: get all customer attributes for account: ' . $this->account);
        $attributes = $this->exporterResource->getAttributes();

        $this->getLogger()->info('Boxalino Exporter: get configured customer attributes for account: ' . $this->account);
        $filteredAttributes = $this->getConfig()->getAccountCustomersProperties($attributes, array('dob', 'gender'));
        $attributes = array_intersect($attributes, $filteredAttributes);

        $this->getLogger()->info('Boxalino Exporter: returning configured customer attributes for account '
            . $this->account . ': ' . implode(',', array_values($attributes))
        );

        return $attributes;
    }

}
