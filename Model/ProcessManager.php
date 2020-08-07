<?php
namespace Boxalino\Exporter\Model;

use Boxalino\Exporter\Api\ExporterInterface;
use Boxalino\Exporter\Model\ResourceModel\ProcessManager as ProcessManagerResource;
use Boxalino\Exporter\Service\Util\Configuration;
use Boxalino\Exporter\Model\Indexer\Delta;
use Boxalino\Exporter\Model\Indexer\Full;
use \Psr\Log\LoggerInterface;

/**
 * Class ProcessManager
 *
 * @package Boxalino\Exporter\Model\Exporter
 */
abstract class ProcessManager
{

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * @var \Boxalino\Exporter\Service\Util\Configuration
     */
    protected $config = null;

    /**
     * @var []
     */
    protected $deltaIds = [];

    /**
     * @var \Magento\Indexer\Model\Indexer
     */
    protected $indexerModel;

    /**
     * @var ProcessManagerResource
     */
    protected $processResource;

    /**
     * @var null
     */
    protected $latestRun = null;

    /**
     * @var ExporterInterface
     */
    protected $exporterService;

    /**
     * @var \Magento\Framework\Stdlib\DateTime\TimezoneInterface
     */
    protected $timezone;


    /**
     * ProcessManager constructor.
     *
     * @param LoggerInterface $logger
     * @param \Magento\Indexer\Model\Indexer $indexer
     * @param Configuration $bxIndexConfig
     * @param ExporterInterface $exporterResource
     */
    public function __construct(
        LoggerInterface $logger,
        ExporterInterface $service,
        \Magento\Indexer\Model\Indexer $indexer,
        Configuration $bxIndexConfig,
        ProcessManagerResource $processResource,
        \Magento\Framework\Stdlib\DateTime\TimezoneInterface $timezone
    ) {
        $this->processResource = $processResource;
        $this->indexerModel = $indexer;
        $this->logger = $logger;
        $this->config = $bxIndexConfig;
        $this->exporterService = $service;
        $this->timezone = $timezone;
    }


    public function run()
    {
        $configurations = $this->config->toString();
        if(empty($configurations))
        {
            $this->logger->info("Boxalino Exporter: no active configurations found on either of the stores. Process cancelled.");
            return false;
        }

        $errorMessages = [];
        $latestRun = $this->getLatestRun();
        $this->logger->info("Boxalino Exporter: starting Boxalino {$this->getType()} export process. Latest update at {$latestRun} (UTC)  / {$this->getStoreTime($latestRun)} (store time)");
        $exporterHasRun = false;
        foreach($this->getAccounts() as $account)
        {
            $this->config->setAccount($account);
            try{
                if($this->exportAllowedByAccount($account))
                {
                    $exporterHasRun = true;
                    $this->exporterService
                        ->setAccount($account)
                        ->setDeltaIds($this->getIds())
                        ->setIsDelta($this->isDelta())
                        ->setTimeoutForExporter($this->getTimeout($account))
                        ->export();
                }
            } catch (\Exception $exception) {
                $errorMessages[] = $exception->getMessage();
                continue;
            }
        }

        if(!$exporterHasRun)
        {
            return false;
        }

        if(empty($errorMessages) && $exporterHasRun)
        {
            return true;
        }

        throw new \Exception(__("Boxalino Exporter: Boxalino Export failed with messages: " . implode(",", $errorMessages)));
    }

    /**
     * @return bool
     */
    public function processCanRun() : bool
    {
        if(($this->getType() == Delta::INDEXER_TYPE) &&  $this->indexerModel->load(Full::INDEXER_ID)->isWorking())
        {
            $this->logger->info("Boxalino Exporter: Delta exporter will not run. Full exporter process must finish first.");
            return false;
        }

        return true;
    }

    /**
     * @param string $account
     * @return bool
     */
    public function exportAllowedByAccount(string $account) : bool
    {
        if($this->exportDeniedOnAccount($account))
        {
            $this->logger->info("Boxalino Exporter: The {$this->getType()} export is denied permission to run. Check your exporter configurations.");
            return false;
        }

        return true;
    }

    /**
     * @return array
     */
    public function getAccounts() : array
    {
        return $this->config->getAccounts();
    }

    /**
     * Get indexer latest updated at
     *
     * @param $id
     * @return string
     */
    public function getLatestUpdatedAt(string $id) : string
    {
        return $this->processResource->getLatestUpdatedAtByIndexerId($id);
    }

    /**
     * @param $date
     */
    public function updateProcessRunDate(string $date)
    {
        $this->processResource->updateIndexerUpdatedAt($this->getIndexerId(), $date);
    }

    /**
     * @param string $format
     * @return string
     */
    public function getCurrentStoreTime(string $format = 'Y-m-d H:i:s') : string
    {
        return $this->timezone->date()->format($format);
    }

    /**
     * @param string $date
     * @return string
     */
    public function getStoreTime(string $date) : string
    {
        return $this->timezone->formatDate($date, 1, true);
    }

    /**
     * @param string|null $time
     * @return string
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getUtcTime(?string $time=null) : string
    {
        if(is_null($time)) {
            return $this->timezone->convertConfigTimeToUtc($this->getCurrentStoreTime());
        }

        return $this->timezone->convertConfigTimeToUtc($time);
    }

    abstract function getTimeout($account);
    abstract function getLatestRun();
    abstract function getIds();
    abstract function exportDeniedOnAccount($account);
    abstract function getType() : string;
    abstract function getIndexerId() : string;
    abstract function isDelta();

}
