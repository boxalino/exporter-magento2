<?php
namespace Boxalino\Exporter\Model\Indexer;

use Boxalino\Exporter\Model\Process\Delta as ProcessManager;
use Psr\Log\LoggerInterface;

/**
 * Class Delta
 * Delta indexer : exports to DI the products updated within a configurable time frame
 *
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
     * Run when the MVIEW is in use (Update by Schedule)
     *
     * @param int[] $ids
     * @return void
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function execute($ids)
    {
        if($this->processManager->processCanRun())
        {
            if(is_array($ids))
            {
                try{
                    $startExportDate = $this->processManager->getUtcTime();
                    $this->processManager->setIds($ids);
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
    }

    /**
     * Run via the command line or cron job (Update on Save mode)
     *
     * The delta IDs will be accessed by checking latest updated IDs
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
                    $this->processManager->updateAffectedProductIds();
                }
            } catch (\Exception $exception) {
                throw $exception;
            }
        }
    }


}
