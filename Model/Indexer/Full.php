<?php
namespace Boxalino\Exporter\Model\Indexer;

use Boxalino\Exporter\Model\Process\Full as ProcessManager;

/**
 * Class Full
 * Full data exporter - exports to DI the products, customers, transactions as configured
 *
 * @package Boxalino\Exporter\Model\Indexer
 */
class Full implements \Magento\Framework\Indexer\ActionInterface,
    \Magento\Framework\Mview\ActionInterface
{

    /**
     * Exporter ID in configuration
     */
    const INDEXER_ID = 'boxalino_exporter';

    /**
     * Exporter type
     */
    const INDEXER_TYPE = "full";

    /**
     * @var ProcessManager
     */
    protected $processManager;

    /**
     * BxExporter constructor.
     */
    public function __construct(ProcessManager $processManager)
    {
        $this->processManager = $processManager;
    }

    /**
     * @param int $id
     */
    public function executeRow($id){}

    /**
     * @param array $ids
     */
    public function executeList(array $ids){}

    /**
     * @param \int[] $ids
     */
    public function execute($ids){}

    /**
     * @throws \Exception
     */
    public function executeFull()
    {
        if($this->processManager->processCanRun())
        {
            try{
                $startExportDate = $this->processManager->getUtcTime();
                $status = $this->processManager->run();
                if($status) {
                    $this->processManager->updateProcessRunDate($startExportDate);
                }
            } catch (\Exception $exception) {
                throw $exception;
            }
        }
    }

}
