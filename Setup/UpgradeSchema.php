<?php
namespace Boxalino\Exporter\Setup;

use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\UpgradeSchemaInterface;
use \Magento\Framework\DB\Adapter\AdapterInterface;
use \Magento\Framework\DB\Ddl\Table;

/**
 * Create table for boxalino exports tracker
 *
 * @package     Boxalino\Exporter\
 * @author      Dana Negrescu <dana.negrescu@boxalino.com>
 */
class UpgradeSchema implements UpgradeSchemaInterface
{

    /**
     * Upgrades DB schema for a module
     *
     * @param SchemaSetupInterface   $setup
     * @param ModuleContextInterface $context
     *
     * @return void
     */
    public function upgrade(
        SchemaSetupInterface $setup,
        ModuleContextInterface $context
    ) {
        $installer = $setup;
        $installer->startSetup();

        if (!$installer->tableExists('boxalino_exporter')) {
            $this->addExporterTable($installer);
        }

        $installer->endSetup();
    }

    public function addExporterTable(SchemaSetupInterface $installer)
    {
        if (!$installer->tableExists('boxalino_export')) {
            $table = $installer->getConnection()->newTable(
                $installer->getTable('boxalino_exporter')
            )->addColumn(
                'id',
                \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
                null,
                [
                    'identity' => true,
                    'nullable' => false,
                    'primary' => true,
                    'unsigned' => true,
                ],
                'ID'
            )->addColumn(
                'indexer_id',
                \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                255,
                [],
                'Indexer Id'
            )->addColumn(
                'entity_id',
                \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                \Magento\Framework\Db\Ddl\Table::MAX_TEXT_SIZE,
                ['nullable' => true],
                'Entity IDs for delta exports: list of product IDs that have to be updated on next indexer run'
            )->addColumn(
                'updated',
                \Magento\Framework\DB\Ddl\Table::TYPE_TIMESTAMP,
                null,
                ['nullable' => true],
                'Updated at'
            )->addIndex(
                $installer->getIdxName('boxalino_export', ['indexer_id']),
                ['indexer_id'],
                ['type'=> AdapterInterface::INDEX_TYPE_UNIQUE]
            )->setComment('Boxalino Exports Time tracker');

            $installer->getConnection()->createTable($table);
        }
    }
}
