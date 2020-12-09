<?php
namespace Boxalino\Exporter\Model\Indexer\Delta;

use Magento\Framework\Mview\ProcessorInterface;

/**
 * Mview
 * Mview manager for the cron jobs
 *
 * @package Boxalino\Exporter\Model\Indexer\Delta
 */
class Mview
{

    /**
     * @var ProcessorInterface
     */
    protected $mviewProcessor;

    /**
     * BxDeltaExporter constructor.
     */
    public function __construct(ProcessorInterface $mviewProcessor)
    {
        $this->mviewProcessor = $mviewProcessor;
    }

    /**
     * Run when the MVIEW is in use (Update by Schedule)
     */
    public function clearChangeLog()
    {
        $this->mviewProcessor->clearChangelog('boxalino');
    }

    /**
     * Run when the MVIEW is in use (Update by Schedule)
     * Exports the tagged products in "boxalino_exporter_delta_cl" table to Boxalino
     */
    public function update()
    {
        $this->mviewProcessor->update('boxalino');
    }

}
