# Magento 2 ShipmentInvoice Extension

This extension allows Magento 2 customers to create and download invoices directly from their account page.

They can also create invoices as per shipments of their orders. For example if an order has two shipments, customer can go to his account page and create two invoices for each shipment, or create one invoice for both of them. Once the invoice is created, it can be printed by the customer.

## Architecture

Architecture of the extension is very minimalistic, simple to understand, easy to maintain the code and very low on resource usage.

### Template

Template `Aario_ShipmentInvoice::order/info/buttons.phtml` overides `Magento_Sales::order/info/buttons.phtml` and adds invoice creation widget to it.

### Block

Block `Aario\ShipmentInvoice\Block\Order\Info\Buttons` overides `Magento\Sales\Block\Order\Info\Buttons` and adds some extra functionality needed to render the invoice creation widget, including list of shipments which customer can select and include them in the invoice bing created.

### Widget
Widget `Aario_ShipmentInvoice/js/create-invoice` renders the invoice creation form. So the user can select shipments and click on Create Invoice button.

### Controller

Controller `Aario\ShipmentInvoice\Controller\Create` then responds to the post request with order ID and shipment IDs in its parameters and creates the invoice. For each created invoice, the ID of the shipments included in the invoice are saved.

### InstallSchema

The Install script `Aario\ShipmentInvoice\Setup\InstallSchema` adds a column named `invoice_id` to magento `sales_shipment` table, so in case customer has created an invoce with that shipment included in it, this will be stored in database. This allows the block to not provide those shipments to the widget, which are already included in an invoice.

## Screenshots

![Screenshot of create invoice dropdown menu](/docs/screenshot-1.png?raw=true "Customers can create invoices after shipments are created by the shop owner")

![Screenshot of created invoice](/docs/screenshot-2.png?raw=true "Customers can then view and print created invoice")
