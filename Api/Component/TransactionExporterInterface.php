<?php
namespace Boxalino\Exporter\Api\Component;

use Boxalino\Exporter\Api\ExporterInterface;
use Boxalino\Exporter\Service\Util\ContentLibrary;
use Boxalino\Exporter\Service\Util\FileHandler;

/**
 * Interface TransactionExporterInterface
 *
 * @package Boxalino\Exporter\Api;
 */
interface TransactionExporterInterface extends ExporterInterface
{
    CONST PAGINATION = 5000;

    public function getFiles() : FileHandler;
    public function setFiles(FileHandler $files);
    public function setLibrary(ContentLibrary $library);
    public function getLibrary() : ContentLibrary;
}
