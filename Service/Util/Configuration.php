<?php
namespace Boxalino\Exporter\Service\Util;

use Magento\Store\Api\Data\WebsiteInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Class Configuration
 *
 * @package Boxalino\Exporter\Service\Util
 */
class Configuration
{
    /**
     *
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var array
     */
    protected $indexConfig = [];

    /**
     * @var string
     */
    protected $account;

    public function __construct(StoreManagerInterface $storeManager)
    {
        $this->storeManager = $storeManager;
        $this->initialize();
    }

    /**
     * @throws \Exception
     */
    public function initialize() : void
    {
        $this->indexConfig = [];
        $websites = $this->storeManager->getWebsites();
        foreach($websites as $website)
        {
            foreach ($website->getGroups(true) as $group)
            {
                foreach ($group->getStores() as $store)
                {
                    $enabled = $store->getConfig('boxalino_exporter/general/status');
                    if(!$enabled){ continue; }

                    $account = $store->getConfig('boxalino_exporter/general/account');
                    if($account == "")
                    {
                        throw new \Exception(
                            "Configuration error detected: Boxalino Account Name cannot be null for any store where exporter is enabled."
                        );
                    }

                    $locale = $store->getConfig('general/locale/code');
                    $parts = explode('_', $locale);
                    $language = $parts[0];

                    if (!array_key_exists($account, $this->indexConfig))
                    {
                        $this->indexConfig[$account] = [];
                    }

                    if (array_key_exists($language, $this->indexConfig[$account]))
                    {
                        throw new \Exception(
                            "Configuration error detected: Language '$language' can only be pushed to account '$account' once. Please review the configurations per website & store-view: 1. There must be a Boxalino account per WEBSITE; 2. The store-view locale code must be unique per website. 3. If there are duplicate languages, disable the exporter on one of the store-views."
                        );
                    }

                    $this->indexConfig[$account][$language] = [
                        'website' => $website,
                        'group'   => $group,
                        'store'   => $store
                    ];
                }
            }
        }
    }

    /**
     * @return \Magento\Store\Api\Data\WebsiteInterface
     */
    public function getWebsite() : WebsiteInterface
    {
        return $this->getAccountFirstLanguageArray()['website'];
    }

    /**
     * @return int
     */
    public function getWebsiteId() : int
    {
        $website = $this->getWebsite();

        return $website->getId();
    }

    /**
     * @param string $account
     * @return $this
     */
    public function setAccount(string $account)
    {
        $this->account = $account;
        return $this;
    }

    /**
     * @return array
     */
    public function getAccounts() : array
    {
        return array_keys($this->indexConfig);
    }

    /**
     * @return array
     * @throws \Exception
     */
    public function getAccountLanguages() : array
    {
        return array_keys($this->getAccountArray());
    }

    /**
     * @param $language
     * @return mixed
     * @throws \Exception
     */
    public function getStore(string $language) : Store
    {
        $array = $this->getAccountLanguageArray($language);
        return $array['store'];
    }

    /**
     * @return mixed
     * @throws \Exception
     */
    protected function getAccountArray() : array
    {
        if(isset($this->indexConfig[$this->account])) {
            return $this->indexConfig[$this->account];
        }

        throw new \Exception("Account is not defined: " . $this->account);
    }

    /**
     * @return mixed
     * @throws \Exception
     */
    protected function getAccountFirstLanguageArray() : array
    {
        $accountArray = $this->getAccountArray();
        foreach($accountArray as $l => $val) {
            return $val;
        }
        throw new \Exception("Account " . $this->account . " does not contain any language");
    }

    /**
     * @param $language
     * @return mixed
     * @throws \Exception
     */
    protected function getAccountLanguageArray(string $language) : array
    {
        $accountArray = $this->getAccountArray();
        if(isset($accountArray[$language])) {
            return $accountArray[$language];
        }
        throw new \Exception("Account " . $this->account . " does not contain a language " . $language);
    }

    /**
     * @return mixed
     * @throws \Exception
     */
    public function getFirstAccountStore()
    {
        $array = $this->getAccountFirstLanguageArray();
        return $array['store'];
    }

    /**
     * @return null | int
     */
    public function getExporterTimeout() : int
    {
        return (int) $this->getFirstAccountStore()->getConfig('boxalino_exporter/advanced/timeout');
    }

    /**
     * @return null | string
     */
    public function getExporterTemporaryArchivePath() : ?string
    {
        $config = $this->getFirstAccountStore()->getConfig('boxalino_exporter/advanced/local_tmp');
        return empty($config) ? null : $config;
    }

    /**
     * @return bool
     */
    public function isExportSchedulerEnabled() : bool
    {
        return (bool) $this->getFirstAccountStore()->getConfig('boxalino_exporter/scheduler/status') == 1;
    }

    /**
     * @return bool
     */
    public function getExportSchedulerDeltaMinInterval() : int
    {
        return (int) $this->getFirstAccountStore()->getConfig('boxalino_exporter/scheduler/delta_min_interval');
    }

    /**
     * @return bool
     */
    public function getExportSchedulerDeltaStart() : int
    {
        return (int) $this->getFirstAccountStore()->getConfig('boxalino_exporter/scheduler/delta_start');
    }

    /**
     * @return bool
     */
    public function getExportSchedulerDeltaEnd() : int
    {
        return (int) $this->getFirstAccountStore()->getConfig('boxalino_exporter/scheduler/delta_end');
    }

    /**
     * @return bool
     */
    public function isCustomersExportEnabled() : bool
    {
        return (bool) $this->getFirstAccountStore()->getConfig('boxalino_exporter/customers/status') == 1;
    }

    /**
     * @return bool
     */
    public function isTransactionsExportEnabled() : bool
    {
        return (bool) $this->getFirstAccountStore()->getConfig('boxalino_exporter/transactions/status') == 1;
    }

    /**
     * @return int
     */
    public function getTransactionMode() : int
    {
        return (int) $this->getFirstAccountStore()->getConfig('boxalino_exporter/transactions/mode');
    }

    /**
     * @return string
     */
    public function toString() : string
    {
        $lines = [];
        foreach($this->indexConfig as $a => $vs) {
            $lines[] = $a . " - " . implode(',', array_keys($vs));
        }
        return implode('\n', $lines);
    }

    /**
     * @return mixed
     * @throws \Exception
     */
    public function getAccountPassword() : string
    {
        $password = $this->getFirstAccountStore()->getConfig('boxalino_exporter/general/password');
        if($password == '') {
            throw new \Exception("you must defined a password in Boxalino -> General configuration section");
        }

        return $password;
    }

    /**
     * @return mixed
     */
    public function isAccountDev() : bool
    {
        return (bool) $this->getFirstAccountStore()->getConfig('boxalino_exporter/general/index');
    }

    /**
     * @return bool
     */
    public function isExportEnabled() : bool
    {
        return (bool) $this->getFirstAccountStore()->getConfig('boxalino_exporter/general/status') == 1;
    }

    /**
     * @return bool
     */
    public function exportProductImages() : bool
    {
        return (bool) $this->getFirstAccountStore()->getConfig('boxalino_exporter/products/export_images') == 1;
    }

    /**
     * @return bool
     */
    public function exportProductUrl() : bool
    {
        return (bool) $this->getFirstAccountStore()->getConfig('boxalino_exporter/products/export_url') == 1;
    }

    /**
     * @return bool
     */
    public function exportRatingPerStoreViewOnly() : bool
    {
        return (bool) $this->getFirstAccountStore()->getConfig('boxalino_exporter/products/export_rating_storeview') == 1;
    }

    /**
     * @return bool
     */
    public function publishConfigurationChanges() : bool
    {
        return (bool) $this->getFirstAccountStore()->getConfig('boxalino_exporter/advanced/publish_configuration_changes') == 1;
    }

    /**
     * @return bool
     */
    public function exportFacetValueExtraInfo() : bool
    {
        return (bool) $this->getFirstAccountStore()->getConfig('boxalino_exporter/products/facetValueExtraInfo') == 1;
    }

    /**
     * @param $allProperties
     * @param $includes
     * @param $excludes
     * @param array $requiredProperties
     * @return array
     * @throws \Exception
     */
    protected function getFinalProperties(array $allProperties, array $includes, array $excludes, $requiredProperties=array()) : array
    {
        foreach($includes as $k => $incl) {
            if($incl == "") {
                unset($includes[$k]);
            }
        }

        foreach($excludes as $k => $excl) {
            if($excl == "") {
                unset($excludes[$k]);
            }
        }

        if(sizeof($includes) > 0) {
            foreach($includes as $incl) {
                if(!in_array($incl, $allProperties)) {
                    throw new \Exception("requested include property $incl which is not part of all the properties provided");
                }

                if(!in_array($incl, $requiredProperties)) {
                    $requiredProperties[] = $incl;
                }
            }
            return $requiredProperties;
        }

        foreach($excludes as $excl) {
            if(!in_array($excl, $allProperties)) {
                throw new \Exception("requested exclude property $excl which is not part of all the properties provided");
            }
            if(in_array($excl, $requiredProperties)) {
                throw new \Exception("requested exclude property $excl which is part of the required properties and therefore cannot be excluded");
            }
        }

        $finalProperties = [];
        foreach($allProperties as $i => $p) {
            if(!in_array($p, $excludes)) {
                $finalProperties[$i] = $p;
            }
        }
        return $finalProperties;
    }

    /**
     * @param $allProperties
     * @param array $requiredProperties
     * @return array
     * @throws \Exception
     */
    public function getAccountProductsProperties(array $allProperties, array $requiredProperties=[]) : array
    {
        list($includes, $excludes) = $this->getAccountProperties("products");
        return $this->getFinalProperties($allProperties, $includes, $excludes, $requiredProperties);
    }

    /**
     * @param $allProperties
     * @param array $requiredProperties
     * @return array
     * @throws \Exception
     */
    public function getAccountCustomersProperties(array $allProperties, array $requiredProperties=[]) : array
    {
        list($includes, $excludes) = $this->getAccountProperties("customers");
        return $this->getFinalProperties($allProperties, $includes, $excludes, $requiredProperties);
    }

    /**
     * @param $allProperties
     * @param array $requiredProperties
     * @return array
     * @throws \Exception
     */
    public function getAccountTransactionsProperties(array $allProperties, array $requiredProperties=[]) : array
    {
        list($includes, $excludes) = $this->getAccountProperties("transactions");
        return $this->getFinalProperties($allProperties, $includes, $excludes, $requiredProperties);
    }

    /**
     * Getting additional tables for each entity to be exported (products, customers, transactions)
     *
     * @param string $type
     * @return array
     * @throws \Exception
     */
    public function getAccountExtraTablesByEntityType(string $type) : array
    {
        $configPath = "boxalino_exporter/" . $type . "/extra_tables";
        $additionalTablesList = $this->getFirstAccountStore()->getConfig($configPath);
        if($additionalTablesList)
        {
            return explode(',', $additionalTablesList);
        }

        return [];
    }

    /**
     * @return string
     */
    public function getAccountMediaGalleryAttributeCode() : string
    {
        return $this->getFirstAccountStore()->getConfig('boxalino_exporter/products/export_media_gallery') ?? "media_gallery";
    }

    /**
     * @param string $type
     * @return array|void
     * @throws \Exception
     */
    protected function getAccountProperties(string $type) : array
    {
        $includes = [];
        $excludes = [];
        $includeProperties = $this->getFirstAccountStore()->getConfig("boxalino_exporter/$type/include_properties");
        $excludeProperties = $this->getFirstAccountStore()->getConfig("boxalino_exporter/$type/exclude_properties");

        if(!is_null($includeProperties))
        {
            $includes = explode(",", $includeProperties);
        }
        if(!is_null($excludeProperties))
        {
            $excludes = explode(",", $excludeProperties);
        }

        return [$includes, $excludes];
    }


}
