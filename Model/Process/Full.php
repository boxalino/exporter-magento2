<?php
namespace Boxalino\Exporter\Model\Exporter\Process;

use Boxalino\Exporter\Model\Exporter\ProcessManager;

/**
 * Class Full
 *
 * @package Boxalino\Exporter\Model\Exporter\Process
 */
class Full extends ProcessManager
{
    /**
     * Indexer ID in configuration
     */
    const INDEXER_ID = 'boxalino_indexer';

    /**
     * Indexer type
     */
    const INDEXER_TYPE = "full";

    /**
     * Default server timeout
     */
    const SERVER_TIMEOUT_DEFAULT = 3000;

    public function getType(): string
    {
        return self::INDEXER_TYPE;
    }

    public function getIndexerId(): string
    {
        return self::INDEXER_ID;
    }

    public function exportDeniedOnAccount($account)
    {
        return false;
    }

    /**
     * Get timeout for exporter
     * @return bool|int
     */
    public function getTimeout($account)
    {
        $customTimeout = $this->config->getExporterTimeout();
        if($customTimeout)
        {
            return $customTimeout;
        }

        return self::SERVER_TIMEOUT_DEFAULT;
    }

    /**
     * Latest run date is not checked for the full export
     *
     * @return null
     */
    public function getLatestRun()
    {
        return $this->getLatestUpdatedAt($this->getIndexerId());
    }

    /**
     * Full export does not care for ids -- everything is exported
     *
     * @return array
     */
    public function getIds(): array
    {
        return [];
    }

    /**
     * @return bool
     */
    public function isDelta()
    {
        return false;
    }

}
