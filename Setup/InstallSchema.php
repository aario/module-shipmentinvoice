<?php

namespace Aario\ShipmentInvoice\Setup;

use Magento\Framework\Setup\InstallSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\DB\Ddl\Table;

class InstallSchema implements InstallSchemaInterface
{
    public function install(
        SchemaSetupInterface $setup,
        ModuleContextInterface $context
    ) {
        $installer = $setup;

        $installer->startSetup();

        /* While module install, creates columns in quote_address and sales_order_address table */

        $shipmentsTable = $installer->getTable('sales_shipment');

        $connection = $installer->getConnection();
        $connection->addColumn(
            $shipmentsTable,
            'invoice_id',
            [
                'type' => Table::TYPE_TEXT,
                'nullable' => false,
                'comment' => 'freightcollect',
            ]
        );

        $installer->endSetup();
    }
}
