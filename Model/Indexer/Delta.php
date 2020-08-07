<?php
namespace Boxalino\Exporter\Model\Indexer;

use Boxalino\Exporter\Model\Process\Delta as ProcessManager;
use Psr\Log\LoggerInterface;

/**
 * Class Delta
 * @package Boxalino\Exporter\Model\Indexer
 */
class Delta implements \Magento\Framework\Indexer\ActionInterface,
    \Magento\Framework\Mview\ActionInterface
{

    /**
     * Exporter ID in configuration
     */
    const INDEXER_ID = 'boxalino_exporter_delta';

    /**
     * Exporter type
     */
    const INDEXER_TYPE = 'delta';

    /**
     * @var ProcessManager
     */
    protected $processManager;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * BxDeltaExporter constructor.
     */
    public function __construct(
        ProcessManager $processManager,
        LoggerInterface  $logger
    ){
        $this->logger = $logger;
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
     * In case of a scheduled update, it will be run
     *
     * @param \int[] $ids
     * @throws \Exception
     */
    public function execute($ids){}

    /**
     * Run on execute full command
     * Run via the command line
     */
    public function executeFull()
    {
        $startExportDate = $this->processManager->getUtcTime();
        if(!$this->processManager->processCanRun())
        {
            return true;
        }

        try{
            $status = $this->processManager->run();
            if($status) {
                $this->processManager->updateProcessRunDate($startExportDate);
                $this->processManager->updateAffectedProductIds();
            }
        } catch (\Exception $exception) {
            throw $exception;
        }
    }
}
