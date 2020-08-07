<?php
namespace Boxalino\Exporter\Api\Component;

use Boxalino\Exporter\Api\ExporterInterface;
use Boxalino\Exporter\Service\Util\ContentLibrary;
use Boxalino\Exporter\Service\Util\FileHandler;

/**
 * Interface ProductExporterInterface
 *
 * @package Boxalino\Exporter\Api;
 */
interface ProductExporterInterface extends ExporterInterface
{

    CONST PAGINATION = 1000;

    /**
     * Store config for max_population
     */
    CONST LIMIT = 1000000;

    public function getFiles() : FileHandler;
    public function setFiles(FileHandler $files);
    public function setLibrary(ContentLibrary $library);
    public function getLibrary() : ContentLibrary;
}
