<?php
namespace Boxalino\Exporter\Model\Process;

use Boxalino\Exporter\Model\ProcessManager;

/**
 * Class Full
 * Full exporter process handler
 *
 * @package Boxalino\Exporter\Model\Process
 */
class Full extends ProcessManager
{

    /**
     * Default server timeout
     */
    const SERVER_TIMEOUT_DEFAULT = 300;

    /**
     * @return string
     */
    public function getType(): string
    {
        return \Boxalino\Exporter\Model\Indexer\Full::INDEXER_TYPE;
    }

    /**
     * @return string
     */
    public function getIndexerId(): string
    {
        return \Boxalino\Exporter\Model\Indexer\Full::INDEXER_ID;
    }

    /**
     * @param $account
     * @return bool
     */
    public function exportDeniedOnAccount($account) : bool
    {
        return false;
    }

    /**
     * Get timeout for exporter
     * @return bool|int
     */
    public function getTimeout() : int
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
    public function getLatestRun() : string
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
    public function isDelta() : bool
    {
        return false;
    }

}
