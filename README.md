# Boxalino Exporter - Magento2

## Introduction
For the Magento2 integration, Boxalino comes with a divided approach: data exporter, framework layer and integration layer.
The current repository is used for the data exporter layer.

*It is an adaptation of the master branch to be used for PHP 7.0 setups.*

## About the data synchronization

With the use of the Boxalino account it is possible to create 2 data indexes: *production* (your live setup) and *development* (staging area).
The account name, password and the data index have to be configured in the plugin.

> In case you plan on using both data indexes, there is a _timing_ restriction: the development data index must be updated at least 2 hours after the production index.

## Data synchronization types

The export options have been designed as generic Magento 2 indexers. They can be identified in the Magento`s System >> Index Management view.

1. The *Boxalino Exporter (Full)* must be executed *once/day*. The time of the execution has to be after your store data has been updated by the other 3rd party events. 
> It requires to be configured with the Magento Indexer mode "Update on Save".

2. The *Boxalino Delta Exporter* can be executed as often as every 25 min.
*Beware*, there should be no delta exports for the 2 hours after a full data export.

> It requires to be configured with the Magento Indexer mode "Update by Schedule". When not, the products export logic will rely on the catalog_product_entity.updated_at field to identify latest changes in the products.

## Setting up exporter

The exporter can be executed with the use of the Magento cron jobs.

1. Edit & save the Boxalino Exporter configurations in your Magento Store Configurations.
2. Create a crontab.xml in which the crons can be defined. *Pay attention to the scheduler times*
```
<group id="default">
  <job name="boxalino_exporter_delta" instance="Boxalino\Exporter\Model\Indexer\Delta" method="execute">
      <schedule>*/30 7-23 * * *</schedule>
  </job>
  <job name="boxalino_exporter" instance="Boxalino\Exporter\Model\Indexer\Full" method="executeFull">
        <schedule>15 2 * * *</schedule>
    </job>
</group>
```

The data synchronization can also be triggered with the use of a command line:

```php bin/magento indexer:reindex boxalino_exporter```

```php bin/magento indexer:reindex boxalino_exporter_delta```

## Contact us!

If you have any question, you can reach us at support@boxalino.com
