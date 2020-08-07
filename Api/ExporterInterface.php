<?php
namespace Boxalino\Exporter\Api;

/**
 * Interface ExporterInterface
 *
 * @package Boxalino\Exporter\Api;
 */
interface ExporterInterface
{
    public function export() : void;
    public function setAccount(string $account);
    public function getAccount() : string;
    public function setIsDelta(bool $isDelta);
    public function isDelta() : bool;
    public function setDeltaIds(array $ids);
    public function getDeltaIds() : array;
}
