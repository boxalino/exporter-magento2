<?php
namespace Boxalino\Exporter\Model\ResourceModel;

use \Magento\Framework\App\ResourceConnection;

/**
 * Class ProcessManager
 * Keeps most of db access for the exporter class
 *
 * @package Boxalino\Exporter\Model\ResourceModel
 */
class ProcessManager
{

    /**
     * @var \Magento\Framework\DB\Adapter\AdapterInterface
     */
    protected $adapter;

    /**
     * ProcessManager constructor.
     *
     * @param ResourceConnection $resource
     */
    public function __construct(
        ResourceConnection $resource
    ) {
        $this->adapter = $resource->getConnection();
    }

    /**
     * Check product IDs from last delta run
     *
     * @param null | array $date
     * @return array
     */
    public function getProductIdsByUpdatedAt(string $date) : array
    {
        $select = $this->adapter->select()
            ->from(
                ['c_p_e' => $this->adapter->getTableName('catalog_product_entity')],
                ['entity_id']
            )->where("DATE_FORMAT(c_p_e.updated_at, '%Y-%m-%d %H:%i:%s') >=  DATE_FORMAT(?, '%Y-%m-%d %H:%i:%s')", $date);

        return $this->adapter->fetchCol($select);
    }

    /**
     * Rollback indexer latest updated date in case of error
     *
     * @param $id
     * @param $updated
     * @return int
     */
    public function updateIndexerUpdatedAt(string $id, string $updated) : int
    {
        $dataBind = [
            "updated" => $updated,
            "indexer_id" => $id
        ];

        return $this->adapter->insertOnDuplicate(
            $this->adapter->getTableName("boxalino_exporter"),
            $dataBind, ["updated"]
        );
    }

    /**
     * @param $id
     * @return string
     */
    public function getLatestUpdatedAtByIndexerId(string $id) : string
    {
        $select = $this->adapter->select()
            ->from($this->adapter->getTableName("boxalino_exporter"), ["updated"])
            ->where("indexer_id = ?", $id);

        return $this->adapter->fetchOne($select);
    }

    /**
     * Getting a list of product IDs affected
     *
     * @param $id
     * @return string
     */
    public function getAffectedEntityIds(string $id) : string
    {
        $select = $this->adapter->select()
            ->from($this->adapter->getTableName("boxalino_exporter"), ["entity_id"])
            ->where("indexer_id = ?", $id);

        return $this->adapter->fetchOne($select);
    }

    /**
     * Updating the list of product IDs affected
     *
     * @param int $id
     * @param string $ids
     * @return int
     */
    public function updateAffectedEntityIds(string $id, string $ids) : int
    {
        $dataBind = [
            "entity_id" => $ids,
            "indexer_id" => $id
        ];

        return $this->adapter->insertOnDuplicate(
            $this->adapter->getTableName("boxalino_exporter"),
            $dataBind, ["entity_id"]
        );
    }
}
