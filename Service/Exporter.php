<?php
namespace Boxalino\Exporter\Service;

use Boxalino\Exporter\Api\Component\CustomerExporterInterface;
use Boxalino\Exporter\Api\Component\ProductExporterInterface;
use Boxalino\Exporter\Api\Component\TransactionExporterInterface;
use Boxalino\Exporter\Api\ExporterInterface;
use Boxalino\Exporter\Service\Util\Configuration;
use Boxalino\Exporter\Service\Util\ContentLibrary;
use Boxalino\Exporter\Service\Util\FileHandler;
use \Psr\Log\LoggerInterface;

/**
 * Class Exporter
 *
 * @package Boxalino\Exporter\Model
 */
class Exporter implements ExporterInterface
{
    use BaseTrait;

    /**
     * @var null
     */
    protected $serverTimeout = null;

    /**
     * @var ProductExporterInterface
     */
    protected $productExporter;

    /**
     * @var CustomerExporterInterface
     */
    protected $customerExporter;

    /**
     * @var TransactionExporterInterface
     */
    protected $transactionExporter;

    /**
     * Exporter constructor.
     *
     * @param LoggerInterface $logger
     * @param FileHandler $fileHandler
     * @param ContentLibrary $contentLibrary
     * @param Configuration $bxIndexConfig
     * @param ProductExporterInterface $productExporter
     * @param CustomerExporterInterface $customerExporter
     * @param TransactionExporterInterface $transactionExporter
     */
    public function __construct(
        LoggerInterface $logger,
        FileHandler $fileHandler,
        ContentLibrary $contentLibrary,
        \Boxalino\Exporter\Service\Util\Configuration $bxIndexConfig,
        ProductExporterInterface $productExporter,
        CustomerExporterInterface  $customerExporter,
        TransactionExporterInterface $transactionExporter
    ) {
        $this->fileHandler = $fileHandler;
        $this->logger = $logger;
        $this->config = $bxIndexConfig;
        $this->contentLibrary = $contentLibrary;
        $this->productExporter = $productExporter;
        $this->transactionExporter = $transactionExporter;
        $this->customerExporter = $customerExporter;
    }

    /**
     * @throws \Exception
     */
    public function export()
    {
        $this->initFiles();
        $this->initLibrary();

        $this->verifyCredentials();
        $this->exportProducts();

        if(!$this->isDelta())
        {
            $this->exportCustomers();
            $this->exportTransactions();
        }

        if($this->productExporter->isSuccess())
        {
            $this->prepareXmlConfigurations();
            $this->pushToDI();
        } else {
            $this->getLogger()->info('Boxalino Exporter: NO PRODUCTS FOUND for account: ' . $this->account);
        }

        $this->getLogger()->info('Boxalino Exporter: FINISHED ACCOUNT: ' . $this->account);
    }

    /**
     * Initializes export directory and files handler for the process
     */
    protected function initFiles()
    {
        $this->getLogger()->info("Boxalino Exporter: initialize files on account: " . $this->getAccount());
        $this->getConfig()->setAccount($this->account);
        $this->fileHandler->setAccount($this->account)->init();
    }

    /**
     * Initializes the xml/zip content library
     */
    protected function initLibrary()
    {
        $this->getLogger()->info("Boxalino Exporter: Initialize content library for account: {$this->getAccount()}");

        $this->contentLibrary->setAccount($this->account)
            ->setLanguages($this->getConfig()->getAccountLanguages())
            ->setPassword($this->getConfig()->getAccountPassword())
            ->setIsDelta($this->isDelta())
            ->setUseDevIndex($this->getConfig()->isAccountDev());
    }

    /**
     * Verifies credentials to the DI
     * If the server is too busy it will trigger a timeout but the export should not be stopped
     */
    protected function verifyCredentials()
    {
        $this->getLogger()->info("Boxalino Exporter: verify credentials for account: " . $this->account);
        try{
            $this->contentLibrary->verifyCredentials();
        } catch(\LogicException $e){
            $this->getLogger()->warning('Boxalino Exporter: verifyCredentials returned a timeout: ' . $e->getMessage());
        } catch (\Exception $e){
            $this->getLogger()->error("Boxalino Exporter: verifyCredentials failed with exception: {$e->getMessage()}");
            throw new \Exception("Boxalino Exporter: verifyCredentials on account {$this->account} failed with exception: {$e->getMessage()}");
        }
    }

    /**
     * Prepares the XML configuration based on the existing properties and content
     *
     * @return bool|string
     * @throws \Exception
     */
    protected function prepareXmlConfigurations()
    {
        if ($this->isDelta())
        {
            return;
        }

        $this->getLogger()->info('Boxalino Exporter: Prepare XML configuration file: ' . $this->getAccount());

        try {
            $this->getLogger()->info('Boxalino Exporter: Push the XML configuration file to the Data Indexing server for account: ' . $this->getAccount());
            $this->contentLibrary->pushDataSpecifications();
        } catch(\LogicException $e){
            $this->getLogger()->warning('Boxalino Exporter: publishing XML configurations returned a timeout: ' . $e->getMessage());
        } catch (\Throwable $e) {
            $value = @json_decode($e->getMessage(), true);
            if (isset($value['error_type_number']) && $value['error_type_number'] == 3) {
                $this->getLogger()->info('Boxalino Exporter: Try to push the XML file a second time, error 3 happens always at the very first time but not after: ' . $this->getAccount());
                $this->contentLibrary->pushDataSpecifications();
            } else {
                $this->getLogger()->error("Boxalino Exporter: pushDataSpecifications failed with exception: " . $e->getMessage() . " If you have attribute changes, please check with Boxalino.");
                throw new \Exception("Boxalino Exporter: pushDataSpecifications failed with exception: " . $e->getMessage());
            }
        }

        $this->getLogger()->info('Boxalino Exporter: Publish the configuration changes from the magento2 owner for account: ' . $this->account);
        $publish = $this->getConfig()->publishConfigurationChanges();
        $changes = $this->contentLibrary->publishOwnerChanges($publish);
        if(sizeof($changes['changes']) > 0 && !$publish)
        {
            $this->getLogger()->warning("Boxalino Exporter: changes in configuration detected but not published as publish configuration automatically option has not been activated for account: " . $this->account);
        }

        $this->getLogger()->info('Boxalino Exporter: NORMAL - stop waiting for Data Intelligence processing for account: ' . $this->getAccount());
    }

    /**
     * Push created archive to Data Intelligence
     *
     * @return array|string
     */
    protected function pushToDI()
    {
        $this->getLogger()->info('Boxalino Exporter: Push the Zip data file to the Data Indexing server for account: ' . $this->account);
        $this->getLogger()->info('Boxalino Exporter: pushing to DI for account: ' . $this->getAccount());
        try {
            $this->contentLibrary->pushData($this->getConfig()->getExporterTemporaryArchivePath() , $this->getTimeoutForExporter());
        } catch(\LogicException $e){
            $this->getLogger()->warning($e->getMessage());
        } catch(\Exception $e){
            $this->getLogger()->error($e);
            throw $e;
        }
    }

    /**
     * Exporting products and product elements (tags, manufacturers, category, prices, reviews, etc)
     */
    public function exportProducts() : bool
    {
        try{
            $this->productExporter->setAccount($this->getAccount())
                ->setConfig($this->config)
                ->setFiles($this->getFiles())
                ->setLibrary($this->getLibrary())
                ->setIsDelta($this->isDelta())
                ->setDeltaIds($this->getDeltaIds())
                ->export();

            return true;
        } catch(\Exception $exc)
        {
            $this->getLogger()->error($exc->getMessage());
            throw $exc;
        }
    }

    /**
     * Export customer data
     */
    public function exportCustomers()
    {
        if($this->isDelta()) {
            return;
        }

        $this->customerExporter->setFiles($this->getFiles())
            ->setConfig($this->config)
            ->setAccount($this->getAccount())
            ->setLibrary($this->productExporter->getLibrary())
            ->export();
    }

    /**
     * Export order data
     */
    public function exportTransactions()
    {
        if($this->isDelta()) {
            return;
        }

        $this->transactionExporter->setFiles($this->getFiles())
            ->setConfig($this->config)
            ->setAccount($this->getAccount())
            ->setLibrary($this->customerExporter->getLibrary())
            ->export();
    }

    /**
     * Get timeout for exporter
     *
     * @return int
     */
    public function getTimeoutForExporter() : int
    {
        return $this->serverTimeout;
    }

    /**
     * Set timeout for exporter
     *
     * @param int $serverTimeout
     */
    public function setTimeoutForExporter(int $serverTimeout) : self
    {
        $this->serverTimeout = $serverTimeout;
        return $this;
    }

}
