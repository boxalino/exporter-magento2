<?php
namespace Boxalino\Exporter\Service\Component;

use Boxalino\Exporter\Api\Resource\BaseExporterResourceInterface;
use Boxalino\Exporter\Api\Component\ProductExporterInterface;
use Boxalino\Exporter\Api\Resource\ProductExporterResourceInterface;
use Magento\Framework\App\ProductMetadata;
use Magento\Store\Model\Store;
use \Psr\Log\LoggerInterface;

/**
 * Class Product
 *
 * @package Boxalino\Exporter\Model
 */
class Product extends Base
    implements ProductExporterInterface
{

    CONST EXPORTER_COMPONENT_TYPE = 'products';

    /**
     * @var \Magento\Framework\App\ResourceConnection
     */
    protected $rs;

    /**
     * @var []
     */
    protected $deltaIds = [];

    /**
     * @var ProductExporterResourceInterface
     */
    protected $exporterResource;

    /**
     * @var array | null
     */
    protected $duplicateIds = null;

    /**
     * @var array
     */
    protected $languages = [];

    /**
     * Product constructor.
     *
     * @param LoggerInterface $logger
     * @param BaseExporterResourceInterface $baseResource
     * @param \Magento\Framework\App\ResourceConnection $rs
     * @param ProductExporterResourceInterface $exporterResource
     */
    public function __construct(
        LoggerInterface $logger,
        BaseExporterResourceInterface $baseResource,
        \Magento\Framework\App\ResourceConnection $rs,
        ProductExporterResourceInterface $exporterResource
    ){
        parent::__construct($logger, $baseResource);
        $this->exporterResource = $exporterResource;
        $this->rs = $rs;
    }

    /**
     * @TODO SRP
     * @throws \Exception
     */
    public function export() : void
    {
        $this->setContextOnResource();
        $this->setLanguages($this->getConfig()->getAccountLanguages());
        $this->getLogger()->info('Boxalino Exporter: PRODUCT - START of export for account ' . $this->account);

        $totalCount = 0; $page = 1; $header = true;
        $attrs = $this->getAttributes();
        $this->getDuplicateIds();

        while (true) {
            if ($totalCount >= ProductExporterInterface::LIMIT) {
                break;
            }

            $data = [];
            $fetchedResult = $this->exporterResource->getByLimitPage(ProductExporterInterface::PAGINATION, $page);
            if(sizeof($fetchedResult)){
                foreach ($fetchedResult as $r) {
                    if($r['group_id'] == null) $r['group_id'] = $r['entity_id'];
                    $data[] = $r;
                    $totalCount++;
                    if(isset($this->duplicateIds[$r['entity_id']])){
                        $r['group_id'] = $r['entity_id'];
                        $r['entity_id'] = 'duplicate' . $r['entity_id'];
                        $data[] = $r;
                    }
                }
            } else {
                if($totalCount == 0){break;}
                break;
            }

            if ($header && count($data) > 0) {
                $data = array_merge(array(array_keys(end($data))), $data);
                $header = false;
            }

            $this->getFiles()->savePartToCsv('products.csv', $data);
            $data = null;
            $page++;
        }

        if($page==0)
        {
            $this->logger->info("Boxalino Exporter: NO PRODUCTS WERE FOUND FOR THE EXPORT.");
            $this->setSuccess(false);
            return;
        }

        $this->setComponentSourceKey($this->getLibrary()->addMainCSVItemFile($this->getFiles()->getPath('products.csv'), 'entity_id'));
        $this->getLibrary()->addSourceStringField($this->getComponentSourceKey(), 'group_id', 'group_id');
        $this->getLibrary()->addFieldParameter($this->getComponentSourceKey(), 'group_id', 'multiValued', 'false');

        $productAttributes = $this->exporterResource->getAttributesByCodes($attrs);
        $this->getLogger()->info('Boxalino Exporter: PRODUCT - connected to DB, built attribute info query for account ' . $this->account);

        $attrsFromDb = ['int'=>[], 'varchar'=>[], 'text'=>[], 'decimal'=>[], 'datetime'=>[]];
        foreach ($productAttributes as $r)
        {
            $type = $r['backend_type'];
            if (isset($attrsFromDb[$type]))
            {
                $attrsFromDb[$type][$r['attribute_id']] =[
                    'attribute_code' => $r['attribute_code'],
                    'is_global' => $r['is_global'],
                    'frontend_input' => $r['frontend_input']
                ];
            }
        }

        $this->exportAttributes($attrsFromDb);
        $this->exportInformation();
        $this->exportExtraTables();

        $this->setSuccess(true);
    }

    /**
     * @param array $attrs
     * @throws \Exception
     */
    protected function exportAttributes(array $attrs = []) : void
    {
        $this->getLogger()->info('Boxalino Exporter: PRODUCT - exportProductAttributes for account ' . $this->account);
        $paramPriceLabel = '';
        $paramSpecialPriceLabel = '';

        $db = $this->rs->getConnection();
        $columns = array(
            'entity_id',
            'attribute_id',
            'value',
            'store_id'
        );
        $this->getFiles()->prepareProductFiles($attrs);
        foreach($attrs as $attrKey => $types)
        {
            foreach ($types as $typeKey => $type)
            {
                $optionSelect = in_array($type['frontend_input'], ['multiselect','select']);
                $data = [];
                $additionalData = [];
                $exportAttribute = false;
                $global =  ($type['is_global'] == 1) ? true : false;
                $getValueForDuplicate = false;
                $d = [];
                $headerLangRow = [];
                $optionValues = [];

                foreach ($this->getLanguages() as $langIndex => $lang)
                {
                    $select = $db->select()->from(
                        array('t_d' => $this->rs->getTableName('catalog_product_entity_' . $attrKey)),
                        $columns
                    );
                    if($this->isDelta()) $select->where('t_d.entity_id IN(?)', $this->getDeltaIds());

                    $labelColumns[$lang] = 'value_' . $lang;
                    $storeObject = $this->getConfig()->getStore($lang);
                    $storeId = $storeObject->getId();

                    $storeBaseUrl = $storeObject->getBaseUrl();
                    $imageBaseUrl = $storeObject->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA) . "catalog/product";
                    $storeObject = null;

                    if ($type['attribute_code'] == 'price'|| $type['attribute_code'] == 'special_price') {
                        if($langIndex == 0) {
                            $priceData = $this->exporterResource->getPriceByType($attrKey, $typeKey);
                            if (sizeof($priceData)) {
                                $priceData = array_merge(array(array_keys(end($priceData))), $priceData);
                            } else {
                                $priceData = array(array('parent_id', 'value'));
                            }
                            $this->getFiles()->savePartToCsv($type['attribute_code'] . '.csv', $priceData);
                        }
                    }

                    if ($type['attribute_code'] == 'url_key') {
                        if (ProductMetadata::EDITION_NAME != "Community") {
                            $select1 = $db->select()
                                ->from(
                                    array('t_g' => $this->rs->getTableName('catalog_product_entity_url_key')),
                                    array('entity_id', 'attribute_id')
                                )
                                ->joinLeft(
                                    array('t_s' => $this->rs->getTableName('catalog_product_entity_url_key')),
                                    't_s.attribute_id = t_g.attribute_id AND t_s.entity_id = t_g.entity_id',
                                    array('value' => 'IF(t_s.store_id IS NULL, t_g.value, t_s.value)')
                                )
                                ->where('t_g.attribute_id = ?', $typeKey)->where('t_g.store_id = 0 OR t_g.store_id = ?', $storeId);
                            if($this->isDelta()) $select1->where('t_g.entity_id IN(?)', $this->getDeltaIds());
                            foreach ($db->fetchAll($select1) as $r) {
                                $data[] = $r;
                            }
                            continue;
                        }
                    }

                    if($optionSelect){
                        $fetchedOptionValues = $this->exporterResource->getAttributeOptionValuesByStoreAndKey($storeId, $typeKey);
                        if($fetchedOptionValues){
                            foreach($fetchedOptionValues as $v){
                                if(isset($optionValues[$v['option_id']])){
                                    $optionValues[$v['option_id']]['value_' . $lang] = $v['value'];
                                }else{
                                    $optionValues[$v['option_id']] = array($type['attribute_code'] . '_id' => $v['option_id'],
                                        'value_' . $lang => $v['value']);
                                }
                            }
                        }else{
                            $optionValues = [];
                            $exportAttribute = true;
                            $optionSelect = false;
                        }
                        $fetchedOptionValues = null;
                    }
                    $select->where('t_d.attribute_id = ?', $typeKey)->where('t_d.store_id = 0 OR t_d.store_id = ?',$storeId);

                    if ($type['attribute_code'] == 'visibility') {
                        $getValueForDuplicate = true;
                        $select = $this->exporterResource->getAttributeParentUnionSqlByCodeTypeStore($type['attribute_code'], $attrKey, $storeId);
                    }

                    if ($type['attribute_code'] == 'status') {
                        $getValueForDuplicate = true;
                        $select = $this->exporterResource->getStatusParentDependabilityByStore($storeId);
                    }

                    $fetchedResult = $db->fetchAll($select);
                    if (sizeof($fetchedResult))
                    {
                        foreach ($fetchedResult as $i => $row)
                        {
                            if (isset($data[$row['entity_id']]) && !$optionSelect)
                            {
                                if(isset($data[$row['entity_id']]['value_' . $lang]))
                                {
                                    if($row['store_id'] > 0){
                                        $data[$row['entity_id']]['value_' . $lang] = $row['value'];
                                        if(isset($this->duplicateIds[$row['entity_id']])){
                                            $data['duplicate'.$row['entity_id']]['value_' . $lang] = $getValueForDuplicate ?
                                                $this->exporterResource->getAttributeValue($row['entity_id'], $typeKey, $storeId) :
                                                $row['value'];
                                        }
                                        if(isset($additionalData[$row['entity_id']])){
                                            if ($type['attribute_code'] == 'url_key') {
                                                $url = $storeBaseUrl . $row['value'] . '.html';
                                            } else {
                                                $url = $imageBaseUrl . $row['value'];
                                            }
                                            $additionalData[$row['entity_id']]['value_' . $lang] = $url;
                                            if(isset($this->duplicateIds[$row['entity_id']])){
                                                $additionalData['duplicate'.$row['entity_id']]['value_' . $lang] = $url;
                                            }
                                        }
                                    }
                                } else {
                                    $data[$row['entity_id']]['value_' . $lang] = $row['value'];
                                    if(isset($this->duplicateIds[$row['entity_id']])){
                                        $data['duplicate'.$row['entity_id']]['value_' . $lang] = $getValueForDuplicate ?
                                            $this->exporterResource->getAttributeValue($row['entity_id'], $typeKey, $storeId) :
                                            $row['value'];
                                    }
                                    if (isset($additionalData[$row['entity_id']])) {
                                        if ($type['attribute_code'] == 'url_key') {
                                            $url = $storeBaseUrl . $row['value'] . '.html';

                                        } else {
                                            $url = $imageBaseUrl . $row['value'];
                                        }
                                        $additionalData[$row['entity_id']]['value_' . $lang] = $url;
                                        if(isset($this->duplicateIds[$row['entity_id']])){
                                            $additionalData['duplicate'.$row['entity_id']]['value_' . $lang] = $url;
                                        }
                                    }
                                }
                                continue;
                            } else {
                                if ($type['attribute_code'] == 'url_key') {
                                    if ($this->getConfig()->exportProductUrl())
                                    {
                                        $url = $storeBaseUrl . $row['value'] . '.html';
                                        $additionalData[$row['entity_id']] = array('entity_id' => $row['entity_id'],
                                            'store_id' => $row['store_id'],
                                            'value_' . $lang => $url);
                                        if(isset($this->duplicateIds[$row['entity_id']])){
                                            $additionalData['duplicate'.$row['entity_id']] = array(
                                                'entity_id' => 'duplicate'.$row['entity_id'],
                                                'value_' . $lang => $url);
                                        }
                                    }
                                }
                                if ($type['attribute_code'] == 'image') {
                                    if ($this->getConfig()->exportProductImages())
                                    {
                                        $url = $imageBaseUrl . $row['value'];
                                        $additionalData[$row['entity_id']] = array('entity_id' => $row['entity_id'],
                                            'value_' . $lang => $url);
                                        if(isset($this->duplicateIds[$row['entity_id']])){
                                            $additionalData['duplicate'.$row['entity_id']] = array(
                                                'entity_id' => 'duplicate'.$row['entity_id'],
                                                'value_' . $lang => $url);
                                        }
                                    }
                                }
                                if ($type['is_global'] != 1){
                                    if($optionSelect)
                                    {
                                        $values = explode(',',$row['value']);
                                        foreach($values as $v){
                                            $data[] = array('entity_id' => $row['entity_id'],
                                                $type['attribute_code'] . '_id' => $v);
                                            if(isset($this->duplicateIds[$row['entity_id']])){
                                                $data[] = array('entity_id' => 'duplicate'.$row['entity_id'],
                                                    $type['attribute_code'] . '_id' => $v);
                                            }
                                        }
                                    } else {
                                        if(!isset($data[$row['entity_id']])) {
                                            $data[$row['entity_id']] = array('entity_id' => $row['entity_id'],
                                                'store_id' => $row['store_id'],'value_' . $lang => $row['value']);
                                            if(isset($this->duplicateIds[$row['entity_id']])){
                                                $data['duplicate'.$row['entity_id']] = array(
                                                    'entity_id' => 'duplicate'.$row['entity_id'],
                                                    'store_id' => $row['store_id'],
                                                    'value_' . $lang => $getValueForDuplicate ?
                                                        $this->exporterResource->getAttributeValue($row['entity_id'], $typeKey, $storeId)
                                                        : $row['value']
                                                );
                                            }
                                        }
                                    }
                                    continue;
                                }else{
                                    if($optionSelect){
                                        $values = explode(',',$row['value']);
                                        foreach($values as $v){
                                            if(!isset($data[$row['entity_id'].$v])){
                                                $data[$row['entity_id'].$v] = array('entity_id' => $row['entity_id'],
                                                    $type['attribute_code'] . '_id' => $v);
                                                if(isset($this->duplicateIds[$row['entity_id']])){
                                                    $data[] = array('entity_id' => 'duplicate'.$row['entity_id'],
                                                        $type['attribute_code'] . '_id' => $v);
                                                }
                                            }
                                        }
                                    }else{
                                        $valueLabel = $type['attribute_code'] == 'visibility' ||
                                        $type['attribute_code'] == 'status' ||
                                        $type['attribute_code'] == 'special_from_date' ||
                                        $type['attribute_code'] == 'special_to_date' ? 'value_' . $lang : 'value';
                                        $data[$row['entity_id']] = array('entity_id' => $row['entity_id'],
                                            'store_id' => $row['store_id'],
                                            $valueLabel => $row['value']);
                                        if(isset($this->duplicateIds[$row['entity_id']])){
                                            $data['duplicate'.$row['entity_id']] = array(
                                                'entity_id' => 'duplicate'.$row['entity_id'],
                                                'store_id' => $row['store_id'],
                                                $valueLabel => $getValueForDuplicate ?
                                                    $this->exporterResource->getAttributeValue($row['entity_id'], $typeKey, $storeId)
                                                    : $row['value']
                                            );
                                        }
                                    }
                                }
                            }
                        }
                        if($type['is_global'] == 1 && !$optionSelect){
                            $global = true;
                            if($type['attribute_code'] != 'visibility'
                                && $type['attribute_code'] != 'status'
                                && $type['attribute_code'] != 'special_from_date'
                                && $type['attribute_code'] != 'special_to_date'
                            ) {
                                break;
                            }
                        }
                    }
                }

                if($optionSelect || $exportAttribute){
                    $optionHeader = array_merge(array($type['attribute_code'] . '_id'), $labelColumns);
                    $a = array_merge(array($optionHeader), $optionValues);
                    $this->getFiles()->savepartToCsv( $type['attribute_code'].'.csv', $a);
                    $optionValues = null;
                    $a = null;
                    $optionSourceKey = $this->getLibrary()->addResourceFile(
                        $this->getFiles()->getPath($type['attribute_code'] . '.csv'),
                        $type['attribute_code'] . '_id',
                        $labelColumns
                    );

                    if(sizeof($data) == 0 && !$this->isDelta())
                    {
                        $d = array(array('entity_id',$type['attribute_code'] . '_id'));
                        $this->getFiles()->savepartToCsv('product_' . $type['attribute_code'] . '.csv',$d);
                        $fieldId = $this->sanitizeFieldName($type['attribute_code']);
                        $attributeSourceKey = $this->getLibrary()->addCSVItemFile($this->getFiles()->getPath('product_' . $type['attribute_code'] . '.csv'), 'entity_id');
                        $this->getLibrary()->addSourceLocalizedTextField($attributeSourceKey,$type['attribute_code'], $type['attribute_code'] . '_id', $optionSourceKey);
                    }
                }

                if (sizeof($data) || in_array($type['attribute_code'], $this->getRequiredAttributes()))
                {
                    if(!$global || $type['attribute_code'] == 'visibility' ||
                        $type['attribute_code'] == 'status' ||
                        $type['attribute_code'] == 'special_from_date' ||
                        $type['attribute_code'] == 'special_to_date')
                    {
                        if(!$optionSelect)
                        {
                            $headerLangRow = array_merge(array('entity_id','store_id'), $labelColumns);
                            if(sizeof($additionalData))
                            {
                                $additionalHeader = array_merge(array('entity_id','store_id'), $labelColumns);
                                $d = array_merge(array($additionalHeader), $additionalData);
                                if ($type['attribute_code'] == 'url_key')
                                {
                                    $this->getFiles()->savepartToCsv('product_default_url.csv', $d);
                                    $sourceKey = $this->getLibrary()->addCSVItemFile($this->getFiles()->getPath('product_default_url.csv'), 'entity_id');
                                    $this->getLibrary()->addSourceLocalizedTextField($sourceKey, 'default_url', $labelColumns);
                                } else {
                                    $this->getFiles()->savepartToCsv('product_cache_image_url.csv', $d);
                                    $sourceKey = $this->getLibrary()->addCSVItemFile($this->getFiles()->getPath('product_cache_image_url.csv'), 'entity_id');
                                    $this->getLibrary()->addSourceLocalizedTextField($sourceKey, 'cache_image_url',$labelColumns);
                                }
                            }
                            $d = array_merge(array($headerLangRow), $data);
                        }else{
                            $d = array_merge(array(array('entity_id',$type['attribute_code'] . '_id')), $data);
                        }
                    } else {
                        if(empty($data)){
                            $d = array(array("entity_id", "store_id", "value"));
                        } else {
                            $d = array_merge(array(array_keys(end($data))), $data);
                        }
                    }

                    $this->getFiles()->savepartToCsv('product_' . $type['attribute_code'] . '.csv', $d);
                    $fieldId = $this->sanitizeFieldName($type['attribute_code']);
                    $attributeSourceKey = $this->getLibrary()->addCSVItemFile($this->getFiles()->getPath('product_' . $type['attribute_code'] . '.csv'), 'entity_id');
                    switch($type['attribute_code'])
                    {
                        case $optionSelect == true:
                            $this->getLibrary()->addSourceLocalizedTextField($attributeSourceKey,$type['attribute_code'],
                                $type['attribute_code'] . '_id', $optionSourceKey);
                            break;
                        case 'name':
                            $this->getLibrary()->addSourceTitleField($attributeSourceKey, $labelColumns);
                            break;
                        case 'description':
                            $this->getLibrary()->addSourceDescriptionField($attributeSourceKey, $labelColumns);
                            break;
                        case 'visibility':
                        case 'status':
                        case 'special_from_date':
                        case 'special_to_date':
                            $lc = [];
                            foreach ($this->getLanguages() as $lcl) {
                                $lc[$lcl] = 'value_' . $lcl;
                            }
                            $this->getLibrary()->addSourceLocalizedTextField($attributeSourceKey, $fieldId, $lc);
                            break;
                        case 'price':
                            $this->getLibrary()->addSourceListPriceField($this->getComponentSourceKey(), 'entity_id');
                            $paramPriceLabel = 'value';

                            if(!$global)
                            {
                                $paramPriceLabel = reset($labelColumns);
                                $this->getLibrary()->addSourceLocalizedTextField($attributeSourceKey, "price_localized", $labelColumns);
                            } else {
                                $this->getLibrary()->addSourceStringField($attributeSourceKey, "price_localized", $paramPriceLabel);
                            }

                            $this->getLibrary()->addFieldParameter($this->getComponentSourceKey(),'bx_listprice', 'pc_fields', 'CASE WHEN (price.'.$paramPriceLabel.' IS NULL OR price.'.$paramPriceLabel.' <= 0) AND ref.value IS NOT NULL then ref.value ELSE price.'.$paramPriceLabel.' END as price_value');
                            $this->getLibrary()->addFieldParameter($this->getComponentSourceKey(),'bx_listprice', 'pc_tables', 'LEFT JOIN `%%EXTRACT_PROCESS_TABLE_BASE%%_products_product_price` as price ON t.entity_id = price.entity_id, LEFT JOIN `%%EXTRACT_PROCESS_TABLE_BASE%%_products_resource_price` as ref ON t.entity_id = ref.parent_id');
                            $this->getLibrary()->addResourceFile($this->getFiles()->getPath($type['attribute_code'] . '.csv'), 'parent_id', "value");

                            break;
                        case 'special_price':
                            $this->getLibrary()->addSourceDiscountedPriceField($this->getComponentSourceKey(), 'entity_id');
                            $paramSpecialPriceLabel = "value";

                            if(!$global)
                            {
                                $paramSpecialPriceLabel = reset($labelColumns);
                                $this->getLibrary()->addSourceLocalizedTextField($attributeSourceKey, "special_price_localized", $labelColumns);
                            } else {
                                $this->getLibrary()->addSourceStringField($attributeSourceKey, "special_price_localized", $paramSpecialPriceLabel);
                            }

                            $this->getLibrary()->addFieldParameter($this->getComponentSourceKey(),'bx_discountedprice', 'pc_fields', 'CASE WHEN (price.'.$paramSpecialPriceLabel.' IS NULL OR price.'.$paramSpecialPriceLabel.' <= 0 OR min_price.'.$paramSpecialPriceLabel.' IS NULL) AND ref.value IS NOT NULL THEN ref.value WHEN (price.'.$paramSpecialPriceLabel.' IS NULL OR price.'.$paramSpecialPriceLabel.' <=0) THEN min_price.'.$paramSpecialPriceLabel.' ELSE LEAST(price.'.$paramSpecialPriceLabel.', min_price.'.$paramSpecialPriceLabel.') END as price_value');
                            $this->getLibrary()->addFieldParameter($this->getComponentSourceKey(),'bx_discountedprice', 'pc_tables', 'LEFT JOIN `%%EXTRACT_PROCESS_TABLE_BASE%%_products_product_special_price` as price ON t.entity_id = price.entity_id, LEFT JOIN `%%EXTRACT_PROCESS_TABLE_BASE%%_products_resource_special_price` as ref ON t.entity_id = ref.parent_id, LEFT JOIN `%%EXTRACT_PROCESS_TABLE_BASE%%_products_product_min_price_index` as min_price ON t.entity_id = min_price.entity_id');
                            $this->getLibrary()->addResourceFile($this->getFiles()->getPath($type['attribute_code'] . '.csv'), 'parent_id', "value");

                            break;
                        case ($attrKey === 'int' || $attrKey === 'decimal') && $type['is_global'] == 1:
                            $this->getLibrary()->addSourceNumberField($attributeSourceKey, $fieldId, 'value');
                            break;
                        default:
                            if(!$global)
                            {
                                $this->getLibrary()->addSourceLocalizedTextField($attributeSourceKey, $fieldId, $labelColumns);
                            } else {
                                $this->getLibrary()->addSourceStringField($attributeSourceKey, $fieldId, 'value');
                            }
                            break;
                    }
                }

                $data = null;
                $additionalData = null;
                $d = null;
                $labelColumns = null;
            }

        }

        $this->getLibrary()->addSourceNumberField($this->getComponentSourceKey(), 'bx_grouped_price', 'entity_id');
        $this->getLibrary()->addFieldParameter($this->getComponentSourceKey(),'bx_grouped_price', 'pc_fields', 'CASE WHEN sref.value IS NOT NULL AND sref.value > 0 AND (ref.value IS NULL OR sref.value < ref.value) THEN sref.value WHEN ref.value IS NOT NULL then ref.value WHEN sprice.'.$paramSpecialPriceLabel.' IS NOT NULL AND sprice.'.$paramSpecialPriceLabel.' > 0 AND price.'.$paramPriceLabel.' > sprice.'.$paramSpecialPriceLabel.' THEN sprice.'.$paramSpecialPriceLabel.' ELSE price.'.$paramPriceLabel.' END as price_value');
        $this->getLibrary()->addFieldParameter($this->getComponentSourceKey(),'bx_grouped_price', 'pc_tables', 'LEFT JOIN `%%EXTRACT_PROCESS_TABLE_BASE%%_products_product_price` as price ON t.entity_id = price.entity_id, LEFT JOIN `%%EXTRACT_PROCESS_TABLE_BASE%%_products_resource_price` as ref ON t.group_id = ref.parent_id, LEFT JOIN `%%EXTRACT_PROCESS_TABLE_BASE%%_products_product_special_price` as sprice ON t.entity_id = sprice.entity_id, LEFT JOIN `%%EXTRACT_PROCESS_TABLE_BASE%%_products_resource_special_price` as sref ON t.group_id = sref.parent_id');
        $this->getLibrary()->addFieldParameter($this->getComponentSourceKey(),'bx_grouped_price', 'multiValued', 'false');

        $this->exportIndexedPrices("final");
        $this->exportIndexedPrices("min");

        $this->getFiles()->clearEmptyFiles("product_");
    }

    /**
     * @throws \Exception
     */
    protected function exportInformation()
    {
        $this->getLogger()->info('Boxalino Exporter: PRODUCT INFORMATION START for account ' . $this->account);
        $this->exportStockInformation();
        $this->exportWebsiteInformation();
        $this->exportParentCategoriesInformation();
        $this->exportSuperLinkInformation();
        $this->exportLinkInformation();
        $this->exportParentTitleInformation();
        $this->exportCategoriesInformation();
        $this->getLogger()->info("Boxalino Exporter: PRODUCT INFORMATION FINISHED");
    }

    protected function exportStockInformation() : void
    {
        $information = $this->exporterResource->getStockInformation();
        $data = [];
        if(sizeof($information))
        {
            foreach ($information as $r)
            {
                $data[] = array('entity_id'=>$r['entity_id'], 'qty'=>$r['qty']);
                if(isset($this->duplicateIds[$r['entity_id']])){
                    $data[] = array('entity_id'=>'duplicate'.$r['entity_id'], 'qty'=>$r['qty']);
                }
            }
            $d = array_merge(array(array_keys(end($data))), $data);
            $this->getFiles()->savePartToCsv('product_stock.csv', $d);

            $attributeSourceKey = $this->getLibrary()->addCSVItemFile($this->getFiles()->getPath('product_stock.csv'), 'entity_id');
            $this->getLibrary()->addSourceNumberField($attributeSourceKey, 'qty', 'qty');
        }
    }

    protected function exportWebsiteInformation() : void
    {
        $information = $this->exporterResource->getWebsiteInformation();
        if(sizeof($information))
        {
            $data = $this->duplicate($information);
            $d = array_merge(array(array_keys(end($data))), $data);
            $this->getFiles()->savePartToCsv('product_website.csv', $d);

            $attributeSourceKey = $this->getLibrary()->addCSVItemFile($this->getFiles()->getPath('product_website.csv'), 'entity_id');
            $this->getLibrary()->addSourceStringField($attributeSourceKey, 'website_name', 'name');
            $this->getLibrary()->addSourceStringField($attributeSourceKey, 'website_id', 'website_id');
        }
    }

    protected function exportParentCategoriesInformation() : void
    {
        $productParentCategory = $this->exporterResource->getParentCategoriesInformation();
        $duplicateResult = $this->exporterResource->getParentCategoriesInformationByDuplicateIds($this->duplicateIds);
        foreach ($duplicateResult as $r)
        {
            $r['entity_id'] = 'duplicate'.$r['entity_id'];
            $productParentCategory[] = $r;
        }
        if (empty($productParentCategory))
        {
            $d = [['entity_id', 'category_id']];
        } else {
            $d = array_merge(array(array_keys(end($productParentCategory))), $productParentCategory);
        }

        $this->getFiles()->savePartToCsv('product_categories.csv', $d);
    }

    protected function exportSuperLinkInformation() : void
    {
        $information = $this->exporterResource->getSuperLinkInformation();
        if(sizeof($information))
        {
            $data = $this->duplicate($information);
            $d = array_merge(array(array_keys(end($data))), $data);
            $this->getFiles()->savePartToCsv('product_parent.csv', $d);

            $attributeSourceKey = $this->getLibrary()->addCSVItemFile($this->getFiles()->getPath('product_parent.csv'), 'entity_id');
            $this->getLibrary()->addSourceStringField($attributeSourceKey, 'parent_id', 'parent_id');
            $this->getLibrary()->addSourceStringField($attributeSourceKey, 'link_id', 'link_id');
        }
    }

    protected function exportLinkInformation() : void
    {
        $information = $this->exporterResource->getLinksInformation();
        if(sizeof($information))
        {
            $data = $this->duplicate($information);
            $d = array_merge(array(array_keys(end($data))), $data);
            $this->getFiles()->savePartToCsv('product_links.csv', $d);

            $attributeSourceKey = $this->getLibrary()->addCSVItemFile($this->getFiles()->getPath('product_links.csv'), 'entity_id');
            $this->getLibrary()->addSourceStringField($attributeSourceKey, 'code', 'code');
            $this->getLibrary()->addSourceStringField($attributeSourceKey, 'linked_product_id', 'linked_product_id');
        }
    }

    protected function exportParentTitleInformation() : void
    {
        foreach ($this->getLanguages() as $language)
        {
            $store = $this->getConfig()->getStore($language);
            $storeId = $store->getId(); $store = null;

            $fetchedResult = $this->exporterResource->getParentTitleInformationByStore($storeId);
            if (sizeof($fetchedResult))
            {
                foreach ($fetchedResult as $r)
                {
                    if (isset($data[$r['entity_id']]))
                    {
                        if(isset($data[$r['entity_id']]['value_' . $language]))
                        {
                            if($r['store_id'] > 0){
                                $data[$r['entity_id']]['value_' . $language] = $r['value'];
                            }
                        } else {
                            $data[$r['entity_id']]['value_' . $language] = $r['value'];
                        }
                        continue;
                    }
                    $data[$r['entity_id']] = array('entity_id' => $r['entity_id'], 'value_' . $language => $r['value']);
                }
                $fetchedResult = null;

                $duplicateResult = $this->exporterResource->getParentTitleInformationByStoreAndDuplicateIds($storeId, $this->duplicateIds);
                foreach ($duplicateResult as $r)
                {
                    $r['entity_id'] = 'duplicate'.$r['entity_id'];
                    if (isset($data[$r['entity_id']]))
                    {
                        $data[$r['entity_id']]['value_' . $language] = $r['value'];
                        continue;
                    }
                    $data[$r['entity_id']] = array('entity_id' => $r['entity_id'], 'value_' . $language => $r['value']);
                }
                $duplicateResult = null;
            }
        }
        $data = array_merge(array(array_keys(end($data))), $data);
        $this->getFiles()->savePartToCsv('product_bx_parent_title.csv', $data);

        $attributeSourceKey = $this->getLibrary()->addCSVItemFile($this->getFiles()->getPath('product_bx_parent_title.csv'), 'entity_id');
        $this->getLibrary()->addSourceLocalizedTextField($attributeSourceKey, 'bx_parent_title', $this->getLanguageHeaders());
        $this->getLibrary()->addFieldParameter($attributeSourceKey,'bx_parent_title', 'multiValued', 'false');
    }

    /**
     * @throws \Exception
     */
    public function exportCategoriesInformation() : void
    {
        $this->getLogger()->info("Boxalino Exporter: CATEGORIES prepare export for each language of the account: $this->account");
        $categories = [];
        foreach ($this->getLanguages() as $language)
        {
            $store = $this->getConfig()->getStore($language);
            $this->getLogger()->info("Boxalino Exporter: CATEGORIES START exportCategories for LANGUAGE $language on store:" . $store->getId());
            $categories = $this->exportCategoriesByStoreLanguage($store, $language, $categories);
        }
        $categories = array_merge(array(array_keys(end($categories))), $categories);
        $this->getFiles()->savePartToCsv('categories.csv', $categories);

        $this->getLibrary()->addCategoryFile($this->getFiles()->getPath('categories.csv'), 'category_id', 'parent_id', $this->getLanguageHeaders());
        $productToCategoriesSourceKey = $this->getLibrary()->addCSVItemFile($this->getFiles()->getPath('product_categories.csv'), 'entity_id');
        $this->getLibrary()->setCategoryField($productToCategoriesSourceKey, 'category_id');

        $this->getLogger()->info("Boxalino Exporter: CATEGORIES END.");
    }

    /**
     * Export content as is defined in the Magento2 price index event
     * This is to be used in case of
     * @param string $type
     */
    public function exportIndexedPrices(string $type) : void
    {
        $attributeCode = $type."_price_index";
        $filename = "product_{$attributeCode}.csv";

        $data = $this->exporterResource->getIndexedPrice($type);
        $data = array_merge([array_keys(end($data))], $data);
        $this->getFiles()->savepartToCsv($filename, $data);

        $attributeSourceKey = $this->getLibrary()->addCSVItemFile($this->getFiles()->getPath($filename), "entity_id");
        $this->getLibrary()->addSourceNumberField($attributeSourceKey, $attributeCode, "value");
        $this->getLibrary()->addFieldParameter($attributeSourceKey, $attributeCode, 'multiValued', 'false');
        $this->getLibrary()->addResourceFile($this->getFiles()->getPath($filename), "entity_id", "value");
    }

    /**
     * @param Store $store
     * @param string $language
     * @param array $transformedCategories
     * @return mixed
     * @throws \Exception
     */
    protected function exportCategoriesByStoreLanguage(Store $store, string $language, array $transformedCategories) : array
    {
        $categories = $this->exporterResource->getCategoriesByStoreId($store->getId());
        foreach($categories as $r)
        {
            if (!$r['parent_id'])  {
                continue;
            }
            if(isset($transformedCategories[$r['entity_id']])) {
                $transformedCategories[$r['entity_id']]['value_' .$language] = $r['value'];
                continue;
            }
            $transformedCategories[$r['entity_id']] =
                ['category_id' => $r['entity_id'], 'parent_id' => $r['parent_id'], 'value_' . $language => $r['value']];
        }

        return $transformedCategories;
    }

    /**
     * @return array
     */
    public function getAttributes() : array
    {
        $this->getLogger()->info('Boxalino Exporter: PRODUCT get all product attributes.');
        $attributes = $this->exporterResource->getAttributes();

        $this->getLogger()->info('Boxalino Exporter: PRODUCT get configured product attributes.');
        $attributes = $this->getConfig()->getAccountProductsProperties($attributes, $this->getRequiredAttributes());
        $this->getLogger()->info('Boxalino Exporter: PRODUCT ATTRIBUTES: ' . implode(',', array_values($attributes)));

        return $attributes;
    }

    /**
     * @return array
     * @throws \Exception
     */
    public function getDuplicateIds() : array
    {
        if(is_null($this->duplicateIds))
        {
            $ids = [];
            $attributeId = $this->exporterResource->getAttributeIdByAttributeCodeAndEntityType('visibility', \Magento\Catalog\Setup\CategorySetup::CATALOG_PRODUCT_ENTITY_TYPE_ID);
            foreach ($this->getLanguages() as $language)
            {
                $storeObject = $this->getConfig()->getStore($language);
                $ids = $this->exporterResource->getDuplicateIds(
                    $storeObject->getId(), $attributeId, \Magento\Catalog\Model\Product\Visibility::VISIBILITY_NOT_VISIBLE
                );
                $storeObject = null;
            }
            $this->duplicateIds = $ids;
        }

        return $this->duplicateIds;
    }

    public function duplicate(array $content) : array
    {
        $data = [];
        foreach ($content as $r)
        {
            $data[] = $r;
            if(isset($this->duplicateIds[$r['entity_id']]))
            {
                $r['entity_id'] = 'duplicate'.$r['entity_id'];
                $data[] = $r;
            }
        }

        return $data;
    }

    /**
     * set export context to the exporter resource
     *
     * @return $this
     */
    protected function setContextOnResource() : self
    {
        $this->exporterResource->setExportIds($this->getDeltaIds());
        if($this->isDelta())
        {
            $this->exporterResource->isDelta(true);
        }

        return $this;
    }

    /**
     * @return string[]
     */
    public function getRequiredAttributes() : array
    {
        return [
            'entity_id',
            'name',
            'description',
            'short_description',
            'sku',
            'price',
            'special_price',
            'special_from_date',
            'special_to_date',
            'category_ids',
            'visibility',
            'status'
        ];
    }

    /**
     * @return array
     * @throws \Exception
     */
    public function getLanguageHeaders() : array
    {
        $languages = $this->getLanguages();
        $fields = preg_filter('/^/', 'value_', array_values($languages));

        return array_combine($languages, $fields);
    }

    /**
     * @param array $languages
     * @return $this
     */
    public function setLanguages(array $languages)
    {
        $this->languages = $languages;
        return $this;
    }

    /**
     * @return array
     */
    public function getLanguages() : array
    {
        return $this->languages;
    }

    /**
     * Modifies a string to remove all non ASCII characters and spaces
     *
     * @param string $text
     * @return string|null
     */
    public function sanitizeFieldName(string $text) : ?string
    {
        $maxLength = 50;  $delimiter = "_";
        $text = preg_replace('~[^\\pL\d]+~u', $delimiter, $text);
        $text = trim($text, $delimiter);
        if (function_exists('iconv')) {
            $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
        }
        $text = strtolower($text);
        $text = preg_replace('~[^_\w]+~', '', $text);
        if (empty($text)) {
            return null;
        }
        $text = substr($text, 0, $maxLength);
        $text = trim($text, $delimiter);

        return $text;
    }

}
