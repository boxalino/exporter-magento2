<?php
namespace Boxalino\Exporter\Model\ResourceModel\Component;

use Boxalino\Exporter\Api\Resource\BaseExporterResourceInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use \Psr\Log\LoggerInterface;
use \Magento\Framework\App\ResourceConnection;
use Magento\Framework\Config\ConfigOptionsListConstants;

/**
 * Class Base
 * @package Boxalino\Exporter\Model\ResourceModel\Component
 */
class Base implements BaseExporterResourceInterface
{

    /**
     * @var \Magento\Framework\DB\Adapter\AdapterInterface
     */
    protected $adapter;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var \Magento\Framework\App\DeploymentConfig
     */
    protected $deploymentConfig;

    /**
     * Exporter constructor.
     *
     * @param LoggerInterface $logger
     * @param ResourceConnection $resource
     * @param \Magento\Framework\App\DeploymentConfig $deploymentConfig
     */
    public function __construct(
        LoggerInterface $logger,
        ResourceConnection $resource,
        \Magento\Framework\App\DeploymentConfig $deploymentConfig
    ) {
        $this->logger = $logger;
        $this->adapter = $resource->getConnection();
        $this->deploymentConfig = $deploymentConfig;
    }

    /**
     * @param $code
     * @param $type
     * @return string
     */
    public function getAttributeIdByAttributeCodeAndEntityType(string $code, string $type) : string
    {
        $whereConditions = [
            $this->adapter->quoteInto('attr.attribute_code = ?', $code),
            $this->adapter->quoteInto('attr.entity_type_id = ?', $type)
        ];

        $attributeIdSql = $this->adapter->select()
            ->from(['attr'=>'eav_attribute'], ['attribute_id'])
            ->where(implode(' AND ', $whereConditions));

        return $this->adapter->fetchOne($attributeIdSql);
    }

    /**
     * @param string $table
     * @return array
     * @throws NoSuchEntityException
     */
    public function getColumnsByTableName(string $table) : array
    {
        $dbConfig = $this->deploymentConfig->get(ConfigOptionsListConstants::CONFIG_PATH_DB);
        $select = $this->adapter->select()
            ->from(
                'INFORMATION_SCHEMA.COLUMNS',
                ['COLUMN_NAME', 'name'=>'COLUMN_NAME']
            )
            ->where('TABLE_SCHEMA=?', $dbConfig['connection']['default']['dbname'])
            ->where('TABLE_NAME=?', $this->adapter->getTableName($table));

        $columns =  $this->adapter->fetchPairs($select);
        if (empty($columns))
        {
            throw new NoSuchEntityException(__("{$table} does not exist."));
        }

        return $columns;
    }

    /**
     * @param $table
     * @return array
     */
    public function getTableContent(string $table) : array
    {
        $select = $this->adapter->select()
            ->from($table, ['*']);

        return $this->adapter->fetchAll($select);
    }

}
