<?php
namespace Boxalino\Exporter\Model\Config\Source;

/**
 * Class DataIndex
 * Defines the available SOLR data index
 *
 * @package Boxalino\Exporter\Model\Config\Source
 */
class DataIndex implements \Magento\Framework\Data\OptionSourceInterface
{
    public function toOptionArray()
    {
        return [
            ['value' => 1, 'label' => 'Development'],
            ['value' => 0, 'label' => 'Production']
        ];
    }
}
