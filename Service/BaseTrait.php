<?php
namespace Boxalino\Exporter\Service;

use Boxalino\Exporter\Service\Util\Configuration;
use Boxalino\Exporter\Service\Util\ContentLibrary;
use Boxalino\Exporter\Service\Util\FileHandler;
use Psr\Log\LoggerInterface;

trait BaseTrait
{

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * @var FileHandler
     */
    protected $fileHandler;

    /**
     * @var ContentLibrary
     */
    protected $contentLibrary;

    /**
     * @var string
     */
    protected $account;

    /**
     * @var bool
     */
    protected $delta = false;

    /**
     * @var \Boxalino\Exporter\Service\Util\Configuration
     */
    protected $config;

    /**
     * @var array
     */
    protected $deltaIds = [];


    /**
     * @return \Boxalino\Exporter\Service\Util\FileHandler
     */
    public function getFiles() : FileHandler
    {
        return $this->fileHandler;
    }

    /**
     * @param FileHandler $files
     */
    public function setFiles(FileHandler $files) : self
    {
        $this->fileHandler = $files;
        return $this;
    }

    /**
     * @param \Boxalino\Exporter\Service\Util\ContentLibrary $library
     */
    public function setLibrary(ContentLibrary $library) : self
    {
        $this->contentLibrary = $library;
        return $this;
    }

    /**
     * @return ContentLibrary
     */
    public function getLibrary() : ContentLibrary
    {
        return $this->contentLibrary;
    }

    /**
     * @param string $account
     * @return $this
     */
    public function setAccount(string $account) : self
    {
        $this->account = $account;
        return $this;
    }

    /**
     * @return string
     */
    public function getAccount() : string
    {
        return $this->account;
    }

    /**
     * @param Configuration $config
     */
    public function setConfig(Configuration $config)
    {
        $this->config = $config;
        return $this;
    }

    /**
     * @return Configuration
     */
    public function getConfig() : Configuration
    {
        return $this->config;
    }

    /**
     * @return LoggerInterface
     */
    public function getLogger() : LoggerInterface
    {
        return $this->logger;
    }

    /**
     * @return bool
     */
    public function isDelta() : bool
    {
        return $this->delta;
    }

    /**
     * @param bool $isDelta
     * @return $this
     */
    public function setIsDelta(bool $isDelta)
    {
        $this->delta = $isDelta;
        return $this;
    }

    /**
     * @return array
     */
    public function getDeltaIds() : array
    {
        return $this->deltaIds;
    }

    /**
     * @param array $ids
     */
    public function setDeltaIds(array $ids) : self
    {
        $this->deltaIds = $ids;
        return $this;
    }

}
