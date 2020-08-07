<?php
namespace Boxalino\Exporter\Model\Config\Source;

/**
 * Class TransactionMode
 * Defines transaction exports modes (full historical transactions or for the last 30 days)
 *
 * @package Boxalino\Exporter\Model\Config\Source
 */
class TransactionMode implements \Magento\Framework\Data\OptionSourceInterface
{
    public function toOptionArray()
    {
        return [
            ['value' => 1, 'label' => 'Full (historical)'],
            ['value' => 0, 'label' => 'Incremental (last 30 days)']
        ];
    }
}
