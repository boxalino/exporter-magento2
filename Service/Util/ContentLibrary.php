<?php
namespace Boxalino\Exporter\Service\Util;

use Monolog\Logger;

/**
 * Class Library
 * copy of the SDK BxData lib
 * https://github.com/boxalino/boxalino-client-SDK-php/blob/master/lib/BxData.php
 *
 * @package Boxalino\Exporter\Service\Util
 */
class ContentLibrary
{
    const URL_VERIFY_CREDENTIALS = '/frontend/dbmind/en/dbmind/api/credentials/verify';
    const URL_XML = '/frontend/dbmind/en/dbmind/api/data/source/update';
    const URL_PUBLISH_CONFIGURATION_CHANGES = '/frontend/dbmind/en/dbmind/api/configuration/publish/owner';
    const URL_ZIP = '/frontend/dbmind/en/dbmind/api/data/push';

    const URL_EXECUTE_TASK = '/frontend/dbmind/en/dbmind/files/task/execute';

    private $account;
    private $password;
    private $languages = [];
    private $isDev;
    private $isDelta;

    private $delimiter = ',';
    private $sources = [];

    private $host = 'http://di1.bx-cloud.com';

    private $owner = 'bx_client_data_api';
    protected $logger;

    public function setAccount($value)
    {
        $this->account = $value;
        return $this;
    }

    public function getAccount()
    {
        return $this->account;
    }

    public function setPassword($value)
    {
        $this->password = $value;
        return $this;
    }

    public function getPassword()
    {
        return $this->password;
    }

    public function setUseDevIndex($value)
    {
        $this->isDev = $value;
        return $this;
    }

    public function setIsDelta($value)
    {
        $this->isDelta = $value;
        return $this;
    }

    public function setLanguages(array $languages)
    {
        $this->languages = $languages;
        return $this;
    }

    public function getLanguages() : array
    {
        return $this->languages;
    }

    public function setDelimiter($delimiter)
    {
        $this->delimiter = $delimiter;
    }

    public function getLogger()
    {
        if(is_null($this->logger)) {
            $this->logger = new Logger("BoxalinoContentLibrary");
        }
        return $this->logger;
    }

    public function addMainXmlItemFile($filePath, $itemIdColumn, $xPath='', $encoding = 'UTF-8', $sourceId = 'item_vals', $container = 'products', $validate=true) {
        $sourceKey = $this->addXMLItemFile($filePath, $itemIdColumn, $xPath, $encoding, $sourceId, $container, $validate);
        $this->addSourceIdField($sourceKey, $itemIdColumn, 'XML', null, $validate) ;
        $this->addSourceStringField($sourceKey, "bx_item_id", $itemIdColumn, null, $validate) ;
        return $sourceKey;
    }
    public function addMainCSVItemFile($filePath, $itemIdColumn, $encoding = 'UTF-8', $delimiter = ',', $enclosure = "\"", $escape = "\\\\", $lineSeparator = "\\n", $sourceId = 'item_vals', $container = 'products', $validate=true) {
        $sourceKey = $this->addCSVItemFile($filePath, $itemIdColumn, $encoding, $delimiter, $enclosure, $escape, $lineSeparator, $sourceId, $container, $validate);
        $this->addSourceIdField($sourceKey, $itemIdColumn, 'CSV', null, $validate) ;
        $this->addSourceStringField($sourceKey, "bx_item_id", $itemIdColumn, null, $validate) ;
        return $sourceKey;
    }

    public function addMainCSVCustomerFile($filePath, $itemIdColumn, $encoding = 'UTF-8', $delimiter = ',', $enclosure = "\&", $escape = "\\\\", $lineSeparator = "\\n", $sourceId = 'customers', $container = 'customers', $validate=true) {
        $sourceKey = $this->addCSVItemFile($filePath, $itemIdColumn, $encoding, $delimiter, $enclosure, $escape, $lineSeparator, $sourceId, $container, $validate);
        $this->addSourceIdField($sourceKey, $itemIdColumn, 'CSV', null, $validate) ;
        $this->addSourceStringField($sourceKey, "bx_customer_id", $itemIdColumn, null, $validate) ;
        return $sourceKey;
    }

    public function addCSVItemFile($filePath, $itemIdColumn, $encoding = 'UTF-8', $delimiter = ',', $enclosure = "\&", $escape = "\\\\", $lineSeparator = "\\n", $sourceId = null, $container = 'products', $validate=true, $maxLength=23) {
        $params = array('itemIdColumn'=>$itemIdColumn, 'encoding'=>$encoding, 'delimiter'=>$delimiter, 'enclosure'=>$enclosure, 'escape'=>$escape, 'lineSeparator'=>$lineSeparator);
        if($sourceId == null) {
            $sourceId = $this->getSourceIdFromFileNameFromPath($filePath, $container, $maxLength, true);
        }
        return $this->addSourceFile($filePath, $sourceId, $container, 'item_data_file', 'CSV', $params, $validate);
    }

    public function addXMLItemFile($filePath, $itemIdColumn, $xPath, $encoding = 'UTF-8', $sourceId = null, $container = 'products', $validate=true, $maxLength=23){
        $params = array('itemIdColumn'=>$itemIdColumn, 'encoding'=>$encoding, 'baseXPath'=>$xPath);
        if($sourceId == null) {
            $sourceId = $this->getSourceIdFromFileNameFromPath($filePath, $container, $maxLength, true);
        }
        return $this->addSourceFile($filePath, $sourceId, $container, 'item_data_file', 'XML', $params, $validate);
    }
    public function addCSVCustomerFile($filePath, $itemIdColumn, $encoding = 'UTF-8', $delimiter = ',', $enclosure = "\&", $escape = "\\\\", $lineSeparator = "\\n", $sourceId = null, $container = 'customers', $validate=true, $maxLength=23) {
        $params = array('itemIdColumn'=>$itemIdColumn, 'encoding'=>$encoding, 'delimiter'=>$delimiter, 'enclosure'=>$enclosure, 'escape'=>$escape, 'lineSeparator'=>$lineSeparator);
        if($sourceId == null) {
            $sourceId = $this->getSourceIdFromFileNameFromPath($filePath, $container, $maxLength, true);
        }
        return $this->addSourceFile($filePath, $sourceId, $container, 'item_data_file', 'CSV', $params, $validate);
    }

    public function addCategoryFile($filePath, $categoryIdColumn, $parentIdColumn, $categoryLabelColumns, $encoding = 'UTF-8', $delimiter = ',', $enclosure = "\&", $escape = "\\\\", $lineSeparator = "\\n", $sourceId = 'resource_categories', $container = 'products', $validate=true) {
        $params = array('referenceIdColumn'=>$categoryIdColumn, 'parentIdColumn'=>$parentIdColumn, 'labelColumns'=>$categoryLabelColumns, 'encoding'=>$encoding, 'delimiter'=>$delimiter, 'enclosure'=>$enclosure, 'escape'=>$escape, 'lineSeparator'=>$lineSeparator);
        return $this->addSourceFile($filePath, $sourceId, $container, 'hierarchical', 'CSV', $params, $validate);
    }

    public function addResourceFile($filePath, $categoryIdColumn, $labelColumns, $encoding = 'UTF-8', $delimiter = ',', $enclosure = "\&", $escape = "\\\\", $lineSeparator = "\\n", $sourceId = null, $container = 'products', $validate=true, $maxLength=23) {
        $params = array('referenceIdColumn'=>$categoryIdColumn, 'labelColumns'=>$labelColumns, 'encoding'=>$encoding, 'delimiter'=>$delimiter, 'enclosure'=>$enclosure, 'escape'=>$escape, 'lineSeparator'=>$lineSeparator);
        if($sourceId == null) {
            $sourceId = 'resource_' . $this->getSourceIdFromFileNameFromPath($filePath, $container, $maxLength, true);
        }
        return $this->addSourceFile($filePath, $sourceId, $container, 'resource', 'CSV', $params, $validate);
    }

    public function setCSVTransactionFile($filePath, $orderIdColumn, $productIdColumn, $customerIdColumn, $orderDateIdColumn, $totalOrderValueColumn, $productListPriceColumn, $productDiscountedPriceColumn, $currencyColumn, $emailColumn, $productIdField='bx_item_id', $customerIdField='bx_customer_id', $productsContainer = 'products', $customersContainer = 'customers', $format = 'CSV', $encoding = 'UTF-8', $delimiter = ',', $enclosure = '"', $escape = "\\\\", $lineSeparator = "\\n",$container = 'transactions', $sourceId = 'transactions', $validate=true)
    {
        $params = array('encoding'=>$encoding, 'delimiter'=>$delimiter, 'enclosure'=>$enclosure, 'escape'=>$escape, 'lineSeparator'=>$lineSeparator);

        $params['file'] = $this->getFileNameFromPath($filePath);
        $params['orderIdColumn'] = $orderIdColumn;
        $params['productIdColumn'] = $productIdColumn;
        $params['product_property_id'] = $productIdField;
        $params['customerIdColumn'] = $customerIdColumn;
        $params['customer_property_id'] = $customerIdField;
        $params['productListPriceColumn'] = $productListPriceColumn;
        $params['productDiscountedPriceColumn'] = $productDiscountedPriceColumn;
        $params['totalOrderValueColumn'] = $totalOrderValueColumn;
        $params['orderReceptionDateColumn'] = $orderDateIdColumn;
        $params['currencyColumn'] = $currencyColumn;
        $params['emailColumn'] = $emailColumn;

        return $this->addSourceFile($filePath, $sourceId, $container, 'transactions', $format, $params, $validate);
    }

    /**
     * Adding an additional table file with the content as it has it
     *
     * @param $filePath
     * @return string
     * @throws \Exception
     */
    public function addExtraTableToEntity($filePath, $container, $column, $columns, $maxLength = 23)
    {
        $params = ['referenceIdColumn'=>$column, 'labelColumns'=>$columns, 'encoding' => 'UTF-8', 'delimiter'=>',', 'enclosure'=>'"', 'escape'=>"\\\\", 'lineSeparator'=>"\\n"];
        $sourceId = $this->getSourceIdFromFileNameFromPath($filePath, $container, $maxLength, true);

        return $this->addSourceFile($filePath, $sourceId, $container, 'resource', 'CSV', $params);
    }

    public function addSourceFile($filePath, $sourceId, $container, $type, $format='CSV', $params=[], $validate=true) {
        if(sizeof($this->getLanguages())==0) {
            throw new \Exception("BoxalinoLibraryError: trying to add a source before having declared the languages with method setLanguages");
        }
        if(!isset($this->sources[$container])) {
            $this->sources[$container] = [];
        }
        $params['filePath'] = $filePath;
        $params['format'] = $format;
        $params['type'] = $type;
        $this->sources[$container][$sourceId] = $params;
        if($validate) {
            $this->validateSource($container, $sourceId);
        }
        $this->sourceIdContainers[$sourceId] = $container;
        return $this->encodesourceKey($container, $sourceId);
    }

    public function decodeSourceKey($sourceKey) {
        return explode('-', $sourceKey);
    }

    public function encodesourceKey($container, $sourceId) {
        return $container.'-'.$sourceId;
    }

    public function getSourceCSVRow($container, $sourceId, $row=0, $maxRow = 2) {
        if(!isset($this->sources[$container][$sourceId]['rows'])) {
            if (($handle = @fopen($this->sources[$container][$sourceId]['filePath'], "r")) !== FALSE) {
                $count = 1;
                $this->sources[$container][$sourceId]['rows'] = [];
                while (($data = fgetcsv($handle, 2000, $this->delimiter)) !== FALSE) {
                    $this->sources[$container][$sourceId]['rows'][] = $data;
                    if($count++>=$maxRow) {
                        break;
                    }
                }
                fclose($handle);
            }
        }
        if(isset($this->sources[$container][$sourceId]['rows'][$row])) {
            return $this->sources[$container][$sourceId]['rows'][$row];
        }
        return null;
    }

    private $globalValidate = true;
    public function setGlobalValidate($globalValidate) {
        $this->globalValidate = $globalValidate;
    }

    public function validateSource($container, $sourceId) {
        if(!$this->globalValidate) {
            return;
        }
        $source = $this->sources[$container][$sourceId];
        if($source['format'] == 'CSV') {
            if(isset($source['itemIdColumn'])) {
                $this->validateColumnExistance($container, $sourceId, $source['itemIdColumn']);
            }
        }
    }

    public function validateColumnExistance($container, $sourceId, $col) {
        if(!$this->globalValidate) {
            return;
        }
        $row = $this->getSourceCSVRow($container, $sourceId, 0);
        if($row !== null && !in_array($col, $row)) {
            throw new \Exception("BoxalinoLibraryError: the source '$sourceId' in the container '$container' declares an column '$col' which is not present in the header row of the provided CSV file: " . implode(',', $row));
        }
    }

    public function addSourceIdField($sourceKey, $col, $format, $referenceSourceKey=null, $validate=true) {
        $id_field = $format == 'CSV' ? 'bx_id' : 'id';
        $this->addSourceField($sourceKey, $id_field, "id", false, $col, $referenceSourceKey, $validate);
    }

    public function addSourceTitleField($sourceKey, $colMap, $referenceSourceKey=null, $validate=true) {
        $this->addSourceField($sourceKey, "bx_title", "title", true, $colMap, $referenceSourceKey, $validate);
    }

    public function addSourceDescriptionField($sourceKey, $colMap, $referenceSourceKey=null, $validate=true) {
        $this->addSourceField($sourceKey, "bx_description", "body", true, $colMap, $referenceSourceKey, $validate);
    }

    public function addSourceListPriceField($sourceKey, $col, $referenceSourceKey=null, $validate=true) {
        $this->addSourceField($sourceKey, "bx_listprice", "price", false, $col, $referenceSourceKey, $validate);
    }

    public function addSourceDiscountedPriceField($sourceKey, $col, $referenceSourceKey=null, $validate=true) {
        $this->addSourceField($sourceKey, "bx_discountedprice", "discounted", false, $col, $referenceSourceKey, $validate);
    }

    public function addSourceLocalizedTextField($sourceKey, $fieldName, $colMap, $referenceSourceKey=null, $validate=true) {
        $this->addSourceField($sourceKey, $fieldName, "text", true, $colMap, $referenceSourceKey, $validate);
    }

    public function addSourceStringField($sourceKey, $fieldName, $col, $referenceSourceKey=null, $validate=true) {
        $this->addSourceField($sourceKey, $fieldName, "string", false, $col, $referenceSourceKey, $validate);
    }

    public function addSourceNumberField($sourceKey, $fieldName, $col, $referenceSourceKey=null, $validate=true) {
        $this->addSourceField($sourceKey, $fieldName, "number", false, $col, $referenceSourceKey, $validate);
    }

    public function setCategoryField($sourceKey, $col, $referenceSourceKey="resource_categories", $validate=true) {
        if($referenceSourceKey == "resource_categories") {
            list($container, $sourceId) = $this->decodeSourceKey($sourceKey);
            $referenceSourceKey = $this->encodesourceKey($container, $referenceSourceKey);
        }
        $this->addSourceField($sourceKey, "category", "hierarchical", false, $col, $referenceSourceKey, $validate);
    }

    public function addSourceField($sourceKey, $fieldName, $type, $localized, $colMap, $referenceSourceKey=null, $validate=true) {
        list($container, $sourceId) = $this->decodeSourceKey($sourceKey);
        if(!isset($this->sources[$container][$sourceId]['fields'])) {
            $this->sources[$container][$sourceId]['fields'] = [];
        }
        $this->sources[$container][$sourceId]['fields'][$fieldName] = array('type'=>$type, 'localized'=>$localized, 'map'=>$colMap, 'referenceSourceKey'=>$referenceSourceKey);
        if($this->sources[$container][$sourceId]['format'] == 'CSV') {
            if($localized && $referenceSourceKey == null) {
                if(!is_array($colMap)) {
                    throw new \Exception("BoxalinoLibraryError: '$fieldName': invalid column field name for a localized field (expect an array with a column name for each language array(lang=>colName)): " . serialize($colMap));
                }
                foreach($this->getLanguages() as $lang) {
                    if(!isset($colMap[$lang])) {
                        throw new \Exception("BoxalinoLibraryError: '$fieldName': no language column provided for language '$lang' in provided column map): " . serialize($colMap));
                    }
                    if(!is_string($colMap[$lang])) {
                        throw new \Exception("BoxalinoLibraryError: '$fieldName': invalid column field name for a non-localized field (expect a string): " . serialize($colMap));
                    }
                    if($validate) {
                        $this->validateColumnExistance($container, $sourceId, $colMap[$lang]);
                    }
                }
            } else {
                if(!is_string($colMap)) {
                    throw new \Exception("BoxalinoLibraryError: '$fieldName' invalid column field name for a non-localized field (expect a string): " . serialize($colMap));
                }
                if($validate) {
                    $this->validateColumnExistance($container, $sourceId, $colMap);
                }
            }
        }
    }

    public function setFieldIsMultiValued($sourceKey, $fieldName, $multiValued = true) {
        $this->addFieldParameter($sourceKey, $fieldName, 'multiValued', $multiValued ? 'true' : 'false');
    }

    public function addSourceCustomerGuestProperty($sourceKey, $parameterValue) {
        $this->addSourceParameter($sourceKey, "guest_property_id", $parameterValue);
    }

    public function addSourceEmailProperty($sourceKey, $parameterValue)
    {
        $this->addSourceParameter($sourceKey, 'emailColumn', $parameterValue);
    }

    public function addSourceCurrencyProperty($sourceKey, $parameterValue)
    {
        $this->addSourceParameter($sourceKey, 'currencyColumn', $parameterValue);
    }

    public function addSourceParameter($sourceKey, $parameterName, $parameterValue) {
        list($container, $sourceId) = $this->decodeSourceKey($sourceKey);
        if(!isset($this->sources[$container][$sourceId])) {
            throw new \Exception("BoxalinoLibraryError: trying to add a source parameter on sourceId '$sourceId', container '$container' while this source doesn't exist");
        }
        $this->sources[$container][$sourceId][$parameterName] = $parameterValue;
    }

    public function addFieldParameter($sourceKey, $fieldName, $parameterName, $parameterValue) {
        list($container, $sourceId) = $this->decodeSourceKey($sourceKey);
        if(!isset($this->sources[$container][$sourceId]['fields'][$fieldName])) {
            throw new \Exception("BoxalinoLibraryError: trying to add a field parameter on sourceId '$sourceId', container '$container', fieldName '$fieldName' while this field doesn't exist");
        }
        if(!isset($this->sources[$container][$sourceId]['fields'][$fieldName]['fieldParameters'])) {
            $this->sources[$container][$sourceId]['fields'][$fieldName]['fieldParameters'] = [];
        }
        $this->sources[$container][$sourceId]['fields'][$fieldName]['fieldParameters'][$parameterName] = $parameterValue;
    }

    private $ftpSources = [];
    public function setFtpSource($sourceKey, $host="di1.bx-cloud.com", $port=21, $user=null, $password=null, $remoteDir = '/sources/production', $protocol=0, $type=0, $logontype=1,
                                 $timezoneoffset=0, $pasvMode='MODE_DEFAULT', $maximumMultipeConnections=0, $encodingType='Auto', $bypassProxy=0, $syncBrowsing=0)
    {
        if($user==null){
            $user = $this->getAccount();
        }

        if($password==null){
            $password = $this->getPassword();
        }

        $params = [];
        $params['Host'] = $host;
        $params['Port'] = $port;
        $params['User'] = $user;
        $params['Pass'] = $password;
        $params['Protocol'] = $protocol;
        $params['Type'] = $type;
        $params['Logontype'] = $logontype;
        $params['TimezoneOffset'] = $timezoneoffset;
        $params['PasvMode'] = $pasvMode;
        $params['MaximumMultipleConnections'] = $maximumMultipeConnections;
        $params['EncodingType'] = $encodingType;
        $params['BypassProxy'] = $bypassProxy;
        $params['Name'] = $user . " at " . $host;
        $params['RemoteDir'] = $remoteDir;
        $params['SyncBrowsing'] = $syncBrowsing;
        list($container, $sourceId) = $this->decodeSourceKey($sourceKey);
        $this->ftpSources[$sourceId] = $params;
    }

    private $httpSources = [];
    public function setHttpSource($sourceKey, $webDirectory, $user=null, $password=null, $header='User-Agent: Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:41.0) Gecko/20100101 Firefox/41.0') {

        if($user===null){
            $user = $this->getAccount();
        }

        if($password===null){
            $password = $this->getPassword();
        }

        $params = [];
        $params['WebDirectory'] = $webDirectory;
        $params['User'] = $user;
        $params['Pass'] = $password;
        $params['Header'] = $header;
        list($container, $sourceId) = $this->decodeSourceKey($sourceKey);
        $this->httpSources[$sourceId] = $params;
    }

    public function getXML()
    {
        $xml = new \SimpleXMLElement('<root/>');

        //languages
        $languagesXML = $xml->addChild('languages');
        foreach ($this->getLanguages() as $lang) {
            $language = $languagesXML->addChild('language');
            $language->addAttribute('id', $lang);
        }

        //containers
        $containers = $xml->addChild('containers');
        foreach($this->sources as $containerName => $containerSources)
        {
            $container = $containers->addChild('container');
            $container->addAttribute('id', $containerName);
            $container->addAttribute('type', $containerName);

            $sources = $container->addChild('sources');
            $properties = $container->addChild('properties');

            //foreach source
            foreach($containerSources as $sourceId => $sourceValues)
            {
                $source = $sources->addChild('source');
                $source->addAttribute('id', $sourceId);
                $source->addAttribute('type', $sourceValues['type']);
                if(isset($sourceValues['additional_item_source'])){
                    $source->addAttribute('additional_item_source', $sourceValues['additional_item_source']);
                }
                $sourceValues['file'] = $this->getFileNameFromPath($sourceValues['filePath']);
                if($sourceValues['format'] == 'CSV') {
                    $parameters = array(
                        'file'=>false,
                        'format'=>'CSV',
                        'encoding'=>'UTF-8',
                        'delimiter'=> $this->delimiter,
                        'enclosure'=>'"',
                        'escape'=>'\\\\',
                        'lineSeparator'=>"\\n"
                    );
                } else if($sourceValues['format'] == 'XML') {
                    $parameters = array(
                        'file'=>false,
                        'format'=> $sourceValues['format'],
                        'encoding'=>$sourceValues['encoding'],
                        'baseXPath'=>$sourceValues['baseXPath']
                    );
                }
                switch($sourceValues['type']) {
                    case 'item_data_file':
                        $parameters['itemIdColumn'] = false;
                        break;

                    case 'hierarchical':
                        $parameters['referenceIdColumn'] = false;
                        $parameters['parentIdColumn'] = false;
                        $parameters['labelColumns'] = false;
                        break;

                    case 'resource':
                        $parameters['referenceIdColumn'] = false;
                        $parameters['itemIdColumn'] = false;
                        $parameters['labelColumns'] = false;
                        $sourceValues['itemIdColumn'] = $sourceValues['referenceIdColumn'];
                        break;

                    case 'transactions':
                        $parameters = $sourceValues;
                        unset($parameters['filePath']);
                        unset($parameters['type']);
                        unset($parameters['product_property_id']);
                        unset($parameters['customer_property_id']);
                        break;
                }

                foreach($parameters as $parameter => $defaultValue) {
                    $value = isset($sourceValues[$parameter]) ? $sourceValues[$parameter] : $defaultValue;
                    if($value === false) {
                        throw new \Exception("BoxalinoLibraryError: source parameter '$parameter' required but not defined in source id '$sourceId' for container '$containerName'");
                    }
                    $param = $source->addChild($parameter);
                    if(is_array($value)) {
                        foreach($value as $language => $languageColumn) {
                            $languageParam = $param->addChild("language");
                            $languageParam->addAttribute('name', $language);
                            $languageParam->addAttribute('value', $languageColumn);
                        }
                    } else {
                        $param->addAttribute('value', $value);
                    }

                    if($sourceValues['type'] == 'transactions') {
                        switch($parameter) {
                            case 'productIdColumn':
                                $param->addAttribute('product_property_id', $sourceValues['product_property_id']);
                                break;

                            case 'customerIdColumn':
                                $param->addAttribute('customer_property_id', $sourceValues['customer_property_id']);

                                if(isset($sourceValues['guest_property_id'])) {
                                    $param->addAttribute('guest_property_id', $sourceValues['guest_property_id']);
                                }
                                break;
                        }
                    }
                }

                if(isset($this->ftpSources[$sourceId])) {
                    $param = $source->addChild('location');
                    $param->addAttribute('type', 'ftp');

                    $ftp = $source->addChild('ftp');
                    $ftp->addAttribute('name', 'ftp');

                    foreach($this->ftpSources[$sourceId] as $ftpPn => $ftpPv) {
                        $ftp->$ftpPn = $ftpPv;
                    }
                }

                if(isset($this->httpSources[$sourceId])) {
                    $param = $source->addChild('location');
                    $param->addAttribute('type', 'http');

                    $http = $source->addChild('http');

                    foreach($this->httpSources[$sourceId] as $httpPn => $httpPv) {
                        $http->$httpPn = $httpPv;
                    }
                }

                if(isset($sourceValues['fields'])) {
                    foreach($sourceValues['fields'] as $fieldId => $fieldValues)
                    {
                        $property = $properties->addChild('property');
                        $property->addAttribute('id', $fieldId);
                        $property->addAttribute('type', $fieldValues['type']);

                        $transform = $property->addChild('transform');
                        $logic = $transform->addChild('logic');
                        $logic->addAttribute('source', $sourceId);
                        $referenceSourceKey = isset($fieldValues['referenceSourceKey']) ? $fieldValues['referenceSourceKey'] : null;
                        $logicType = (($sourceValues['format'] == 'XML') ? "xpath" : ($referenceSourceKey == null ? 'direct' : 'reference'));
                        if($logicType == 'direct') {
                            if(isset($fieldValues['fieldParameters'])) {
                                foreach ($fieldValues['fieldParameters'] as $parameterName => $parameterValue) {
                                    switch ($parameterName) {
                                        case 'pc_fields':
                                        case 'pc_tables':
                                            $logicType = 'advanced';
                                    }
                                }
                            }
                        }
                        $logic->addAttribute('type', $logicType);
                        if(is_array($fieldValues['map'])) {
                            foreach($this->getLanguages() as $lang) {
                                $field = $logic->addChild('field');
                                $field->addAttribute('column', $fieldValues['map'][$lang]);
                                $field->addAttribute('language', $lang);
                            }
                        } else {
                            $field = $logic->addChild('field');
                            $field->addAttribute('column', $fieldValues['map']);
                        }

                        $params = $property->addChild('params');
                        if($referenceSourceKey) {
                            $referenceSource = $params->addChild('referenceSource');
                            list($referenceContainer, $referenceSourceId) = $this->decodeSourceKey($referenceSourceKey);
                            $referenceSource->addAttribute('value', $referenceSourceId);
                        }
                        if(isset($fieldValues['fieldParameters'])) {
                            foreach($fieldValues['fieldParameters'] as $parameterName => $parameterValue) {
                                $fieldParameter = $params->addChild('fieldParameter');
                                $fieldParameter->addAttribute('name', $parameterName);
                                $fieldParameter->addAttribute('value', $parameterValue);
                            }
                        }
                    }
                }
            }
        }

        $dom = dom_import_simplexml($xml)->ownerDocument;
        $dom->formatOutput = true;
        return $dom->saveXML();
    }

    protected function callAPI($fields, $url, $temporaryFilePath=null, $timeout=60)
    {
        $s = curl_init();

        curl_setopt($s, CURLOPT_URL, $url);
        curl_setopt($s, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($s, CURLOPT_POST, true);
        curl_setopt($s, CURLOPT_ENCODING, '');
        curl_setopt($s, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($s, CURLOPT_POSTFIELDS, $fields);

        $responseBody = @curl_exec($s);

        if($responseBody === false)
        {
            if(strpos(curl_error($s), 'Operation timed out after') !== false)
            {
                throw new \LogicException("BoxalinoLibraryError: This is an expected scenario: the connection closed due to the timeout reach. Contact us at support@boxalino.com if you want updates on the exporter status. Original message:" . curl_error($s));
            }

            if(strpos(curl_error($s), "couldn't open file") !== false) {
                if($temporaryFilePath !== null) {
                    throw new \Exception('BoxalinoLibraryError: There seems to be a problem with the folder BxData uses to temporarily store a zip file with all your files before sending it. As you are currently provided a path, this is most likely the problem. Please make sure it is a valid path, or leave it to null (default value), then BxData will use sys_get_temp_dir() + "/bxclient" which typically works fine.');
                } else {
                    throw new \Exception('BoxalinoLibraryError: There seems to be a problem with the folder BxData uses to temporarily store a zip file with all your files before sending it. This means that the default path BxData uses sys_get_temp_dir() + "/bxclient" is not supported and you need to path a working path to the pushData function.');
                }
            }
            throw new \Exception('BoxalinoLibraryError: Curl error: ' . curl_error($s));
        }

        curl_close($s);
        if (strpos($responseBody, 'Internal Server Error') !== false) {
            throw new \Exception($this->getError($responseBody));
        }
        return $this->checkResponseBody($responseBody, $url);
    }

    public function getError($responseBody) {
        return $responseBody;
    }

    public function checkResponseBody($responseBody, $url) {
        if($responseBody == null) {
            throw new \Exception("BoxalinoLibraryError: API response of call to $url is empty string, this is an error!");
        }
        $value = json_decode($responseBody, true);
        if(sizeof($value) != 1 || !isset($value['token'])) {
            if(!isset($value['changes'])) {
                throw new \Exception($responseBody);
            }
        }
        return $value;
    }

    public function pushDataSpecifications($ignoreDeltaException=false)
    {
        if(!$ignoreDeltaException && $this->isDelta) {
            throw new \Exception("BoxalinoLibraryError: You should not push specifications when you are pushing a delta file. Only do it when you are preparing full files. Set method parameter ignoreDeltaException to true to ignore this exception and publish anyway.");
        }

        $fields = array(
            'username' => $this->getAccount(),
            'password' => $this->getPassword(),
            'account' => $this->getAccount(),
            'owner' => $this->owner,
            'xml' => $this->getXML()
        );

        $url = $this->host . self::URL_XML;
        return $this->callAPI($fields, $url);
    }

    public function checkChanges() {
        $this->publishOwnerChanges(false);
    }

    public function publishChanges() {
        $this->publishOwnerChanges(true);
    }

    public function publishOwnerChanges($publish=true) {
        if($this->isDev) {
            $publish = false;
        }
        $fields = array(
            'username' => $this->getAccount(),
            'password' => $this->getPassword(),
            'account' => $this->getAccount(),
            'owner' => $this->owner,
            'publish' => ($publish ? 'true' : 'false')
        );

        $url = $this->host . self::URL_PUBLISH_CONFIGURATION_CHANGES;
        return $this->callAPI($fields, $url);
    }

    public function verifyCredentials() {
        $fields = array(
            'username' => $this->getAccount(),
            'password' => $this->getPassword(),
            'account' => $this->getAccount(),
            'owner' => $this->owner
        );

        $url = $this->host . self::URL_VERIFY_CREDENTIALS;
        return $this->callAPI($fields, $url);
    }

    public function alreadyExistingSourceId($sourceId, $container) {
        return isset($this->sources[$container][$sourceId]);
    }

    public function getUnusedSourceIdPostFix($sourceId, $container) {
        $postFix = 2;
        foreach($this->sources[$container] as $sid => $values) {
            if(strpos($sid, $sourceId) === 0) {
                $count = str_replace($sourceId, '', $sid);
                if($count >= $postFix) {
                    $postFix = $count + 1;
                }
            }
        }
        return $postFix;
    }

    public function getSourceIdFromFileNameFromPath($filePath, $container, $maxLength=23, $withoutExtension=false) {
        $sourceId = $this->getFileNameFromPath($filePath, $withoutExtension);
        if(strlen($sourceId) > $maxLength) {
            $sourceId = substr($sourceId, 0, $maxLength);
        }

        if($this->alreadyExistingSourceId($sourceId, $container)) {
            $postFix = $this->getUnusedSourceIdPostFix($sourceId, $container);
            $sourceId .= $postFix;
        }
        return $sourceId;
    }

    public function getFileNameFromPath($filePath, $withoutExtension=false) {
        $parts = explode('/', $filePath);
        $file = $parts[sizeof($parts)-1];
        if($withoutExtension) {
            $parts = explode('.', $file);
            return $parts[0];
        }
        return $file;
    }

    public function getFiles() {
        $files = [];
        foreach($this->sources as $container => $containerSources) {
            foreach($containerSources as $sourceId => $sourceValues) {
                if(isset($this->ftpSources[$sourceId])) {
                    continue;
                }
                if(isset($this->httpSources[$sourceId])) {
                    continue;
                }
                if(!isset($sourceValues['file'])) {
                    $sourceValues['file'] = $this->getFileNameFromPath($sourceValues['filePath']);
                }
                $files[$sourceValues['file']] = $sourceValues['filePath'];
            }
        }
        return $files;
    }

    public function createZip($temporaryFilePath=null, $name='bxdata.zip', $clearFiles = true)
    {
        if($temporaryFilePath === null) {
            $temporaryFilePath = sys_get_temp_dir() . '/bxclient';
        }

        if ($temporaryFilePath != "" && !file_exists($temporaryFilePath)) {
            mkdir($temporaryFilePath);
        }

        $zipFilePath = $temporaryFilePath . '/' . $name;

        if (file_exists($zipFilePath)) {
            @unlink($zipFilePath);
        }

        $files = $this->getFiles();

        $zip = new \ZipArchive();
        if ($zip->open($zipFilePath, \ZipArchive::CREATE)) {

            foreach ($files as $f => $filePath) {
                if (!$zip->addFile($filePath, $f)) {
                    throw new \Exception(
                        'BoxalinoLibraryError: Synchronization failure: Failed to add file "' .
                        $filePath . '" to the zip "' .
                        $name . '". Please try again.'
                    );
                }
            }

            if (!$zip->addFromString ('properties.xml', $this->getXML())) {
                throw new \Exception(
                    'BoxalinoLibraryError: Synchronization failure: Failed to add xml string to the zip "' .
                    $name . '". Please try again.'
                );
            }

            if (!$zip->close()) {
                throw new \Exception(
                    'BoxalinoLibraryError: Synchronization failure: Failed to close the zip "' .
                    $name . '". Please try again.'
                );
            }

        } else {
            throw new \Exception(
                'BoxalinoLibraryError: Synchronization failure: Failed to open the zip "' .
                $name . '" for writing. Please check the permissions and try again.'
            );
        }
        if($clearFiles) {
            foreach ($files as $file) {
                unlink($file);
            }
        }

        return $zipFilePath;
    }

    public function pushData($temporaryFilePath=null, $timeout=60, $clearFiles=true) {

        $zipFile = $this->createZip($temporaryFilePath, 'bxdata.zip', $clearFiles);

        $fields = array(
            'username' => $this->getAccount(),
            'password' => $this->getPassword(),
            'account' => $this->getAccount(),
            'owner' => $this->owner,
            'dev' => ($this->isDev ? 'true' : 'false'),
            'delta' => ($this->isDelta ? 'true' : 'false'),
            'data' => $this->getCurlFile($zipFile, "application/zip")
        );

        $url = $this->host . self::URL_ZIP;
        return $this->callAPI($fields, $url, $temporaryFilePath, $timeout);
    }

    protected function getCurlFile($filename, $type)
    {
        try {
            if (class_exists('CURLFile')) {
                return new \CURLFile($filename, $type);
            }
        } catch(\Exception $e) {}
        return "@$filename;type=$type";
    }

    public function getTaskExecuteUrl($taskName) {
        return $this->host . self::URL_EXECUTE_TASK . '?iframeAccount=' . $this->getAccount() . '&task_process=' . $taskName;
    }

    public function publishChoices($isTest = false, $taskName="generate_optimization") {

        if($this->isDev) {
            $taskName .= '_dev';
        }
        if($isTest) {
            $taskName .= '_test';
        }
        $url = $this->getTaskExecuteUrl($taskName);
        file_get_contents($url);
    }

    public function prepareCorpusIndex($taskName="corpus") {
        $url = $this->getTaskExecuteUrl($taskName);
        file_get_contents($url);
    }

    public function prepareAutocompleteIndex($fields, $taskName="autocomplete") {
        $url = $this->getTaskExecuteUrl($taskName);
        file_get_contents($url);
    }

}
