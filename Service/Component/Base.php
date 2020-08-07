<?php
namespace Boxalino\Exporter\Service\Component;

use Boxalino\Exporter\Api\Resource\BaseExporterResourceInterface;
use Boxalino\Exporter\Service\BaseTrait;
use Doctrine\DBAL\Query\QueryBuilder;
use Magento\Framework\Exception\NoSuchEntityException;
use \Psr\Log\LoggerInterface;

/**
 * Class Exporter
 *
 * @package Boxalino\Exporter\Model
 */
class Base
{

    use BaseTrait;

    /**
     * @var BaseExporterResourceInterface
     */
    protected $baseResource;

    /**
     * @var bool
     */
    protected $success = true;

    /**
     * Base constructor.
     * @param LoggerInterface $logger
     */
    public function __construct(
        LoggerInterface $logger,
        BaseExporterResourceInterface $baseResource
    ) {
        $this->logger = $logger;
        $this->baseResource = $baseResource;
    }

    /**
     * Exporting additional tables that are related to entities
     * No logic on the connection is defined
     * To be added in the ETL
     *
     * @return $this
     */
    public function exportExtraTables() : self
    {
        $this->getLogger()->info("Boxalino Exporter: {$this->getComponent()} exporting additional tables for account: $this->account");
        $tables = $this->getConfig()->getAccountExtraTablesByEntityType($this->getComponent());
        if(empty($tables))
        {
            $this->getLogger()->info("Boxalino Exporter: {$this->getComponent()} no additional tables have been found.");
            return $this;
        }

        foreach($tables as $table)
        {
            try{
                $columns = $this->getColumnsByTableName($table);
                $tableContent = $this->getTableContent($table);
                $dataToSave = array_merge(array(array_keys(end($tableContent))), $tableContent);

                $fileName = "extra_". $table . ".csv";
                $this->getFiles()->savePartToCsv($fileName, $dataToSave);
                $this->getLibrary()->addExtraTableToEntity($this->getFiles()->getPath($fileName), $this->getComponent(), reset($columns), $columns);
                $this->getLogger()->info("Boxalino Exporter: {$this->getComponent()} - additional table {$table} exported.");
            } catch (NoSuchEntityException $exception)
            {
                $this->getLogger()->warning("Boxalino Exporter: {$this->getComponent()} additional table ". $exception->getMessage());
            } catch (\Exception $exception)
            {
                $this->getLogger()->error("Boxalino Exporter: {$this->getComponent()} additional table error: ". $exception->getMessage());
            }
        }

        return $this;
    }

    /**
     * @param string $table
     * @return array
     * @throws \Doctrine\DBAL\DBALException
     */
    protected function getColumnsByTableName(string $table) : array
    {
        $columns = $this->baseResource->getColumnsByTableName($table);
        if(empty($columns))
        {
            throw new \Exception("BxIndexLog: {$table} does not exist.");
        }

        return $columns;
    }

    /**
     * @param string $table
     * @return array
     */
    protected function getTableContent(string $table) : array
    {
        try {
            return $this->baseResource->getTableContent($table);
        } catch(\Exception $exc)
        {
            $this->getLogger()->warning("BxIndexLog: {$table} - additional table error: ". $exc->getMessage());
            return [];
        }
    }

    /**
     * Component name
     * Matches the entity name required by the library (customers, products, transactions)
     *
     * @return string
     */
    public function getComponent()
    {
        $callingClass = get_called_class();
        return $callingClass::EXPORTER_COMPONENT_TYPE;
    }

    /**
     * @param $query
     * @return \Generator
     */
    public function processExport(QueryBuilder $query)
    {
        foreach($query->execute()->fetchAll() as $row)
        {
            yield $row;
        }
    }

    /**
     * @param bool $value
     * @return $this
     */
    public function setSuccess(bool $value) : self
    {
        $this->success = $value;
        return $this;
    }

    /**
     * @return bool
     */
    public function isSuccess() : bool
    {
        return $this->success;
    }

}
