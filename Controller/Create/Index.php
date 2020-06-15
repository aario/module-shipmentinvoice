<?php

/**
 * The controller for creating invoice
 *
 * PHP version 7.3
 *
 * @category Controller
 * @package  CreateInvoice
 * @author   Aario Shahbany <aario.shahbany@gmail.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.en.html GPLv3.0
 * @link     github.com/aario
 */
namespace Aario\ShipmentInvoice\Controller\Create;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Framework\UrlInterface;
use Magento\Sales\Model\Service\InvoiceService;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Invoice\Item as InvoiceItem;
use Magento\Sales\Model\Order\Shipment;
use Magento\Sales\Model\Order\Shipment\Item as ShipmentItem;
use Magento\Sales\Model\ResourceModel\Order\Shipment\Collection
    as ShipmentCollection;
use Magento\Framework\DB\Transaction;
use Magento\Framework\Controller\Result\Redirect;

class Index extends Action implements HttpPostActionInterface
{

    /* @var ResultFactory $resultFactory */
    protected $resultFactory;

    /* @var UrlInterface $urlBuilder */
    protected $urlBuilder;

    /* @var OrderRepositoryInterface $orderRepository */
    protected $orderRepository;

    /* @var Order $order */
    protected $order;

    /* @var array $shipments */
    protected $shipments;

    /* @var InvoiceService $invoiceService */
    protected $invoiceService;

    /* @var Transaction $transaction */
    protected $transaction;

    /**
     * Initialize
     *
     * @param Context                  $context         Action Context
     * @param ResultFactory            $resultFactory   Result used for redirect
     * @param OrderRepositoryInterface $orderRepository Orders repository object
     * @param UrlInterface             $urlBuilder      The URL builder
     * @param InvoiceService           $invoiceService  To create invoice objects
     * @param Transaction              $transaction     To save the invoice
     */
    public function __construct(
        Context $context,
        ResultFactory $resultFactory,
        OrderRepositoryInterface $orderRepository,
        UrlInterface $urlBuilder,
        InvoiceService $invoiceService,
        Transaction $transaction
    ) {
        parent::__construct($context);
        $this->resultFactory = $resultFactory;
        $this->orderRepository = $orderRepository;
        $this->urlBuilder = $urlBuilder;
        $this->invoiceService = $invoiceService;
        $this->transaction = $transaction;
    }

    /**
     * Find the shipment within collection by ID without querying
     * the table again
     *
     * @param ShipmentCollection $shipmentsCollection Collection of shipments
     * @param int                $shipmentId          The shipment ID to search
     *
     * @return null|Shipment
     */
    protected function getShipmentById(
        ShipmentCollection $shipmentsCollection,
        int $shipmentId
    ): ?Shipment {
        foreach ($shipmentsCollection as $shipment) {
            if ($shipment->getId() == $shipmentId) {
                return $shipment;
            }
        }
        return null;
    }

    /**
     * Find selected shipment Ids within all shipments of the order
     *
     * @param ShipmentCollection $shipmentsCollection Collection of shipments
     * @param array              $shipmentIds         Array of shipment IDs
     *
     * @return array
     */
    protected function getShipmentsFromCollection(
        ShipmentCollection $shipmentsCollection,
        array $shipmentIds
    ): ?array {
        $shipments = [];

        foreach ($shipmentIds as $shipmentId) {
            if (!is_numeric($shipmentId)) {
                $this->messageManager->addErrorMessage(__('Invalid shipment ID: '));
                return null;
            }

            $shipment = $this->getShipmentById($shipmentsCollection, $shipmentId);
            if (!$shipment) {
                $this->messageManager->addErrorMessage(__('Invalid shipment ID: ') . $shipmentId);
                return null;
            }

            $shipments[] = $shipment;
        }
        return $shipments;
    }

    /**
     * Process and validate http request
     *
     * @param HttpRequest $request The http request
     *
     * @return boolea
     */
    protected function processRequest(HttpRequest $request)
    {
        $orderId = $request->getPost('orderId');
        if (!is_numeric($orderId)) {
            $this->messageManager->addErrorMessage(__('Invalid order ID.'));
            return false;
        }
        $shipmentIds = $request->getPost('shipmentIds');
        if (!is_array($shipmentIds)) {
            $this->messageManager->addErrorMessage(__('Invalid request params.'));
            return false;
        }

        $this->order = $this->orderRepository->get($orderId);
        if (!$this->order) {
            $this->messageManager->addErrorMessage(__('Cannot load order.'));
            return false;
        }

        $shipmentsCollection = $this->order->getShipmentsCollection();
        $this->shipments = $this->getShipmentsFromCollection($shipmentsCollection, $shipmentIds);
        if (!$this->shipments) {
            return false;
        }

        return true;
    }

    /**
     * Find equivalent of the Invoice Item within the shipment
     *
     * @param Shipment    $shipment    The shipment to check
     * @param InvoiceItem $invoiceItem The item to search for
     *
     * @return ShipmentItem
     */
    protected function getItemInShipment(
        Shipment $shipment,
        InvoiceItem $invoiceItem
    ):? ShipmentItem {
        $invoiceItemSku = $invoiceItem->getSku();
        foreach ($shipment->getItemsCollection() as $shipmentItem) {
            if ($shipmentItem->getSku() == $invoiceItemSku) {
                return $shipmentItem;
            }
        }
        return null;
    }

    /**
     * Get quantity of the invoice item in all selected shipments
     *
     * @param InvoiceItem $invoiceItem The invoice item
     *
     * @return float
     */
    protected function getItemQuanityInShipments(InvoiceItem $invoiceItem): float
    {
        $qty = 0;
        foreach ($this->shipments as $shipment) {
            $shipmentItem = $this->getItemInShipment($shipment, $invoiceItem);
            if (!$shipmentItem) {
                continue;
            }
            $qty += $shipmentItem->getQty();
        }
        return $qty;
    }

    /**
     * Reset grand totals of the invoice before calculations
     *
     * @param Invoice $invoice The invoice object
     */
    protected function resetInvoiceGrandTotals(Invoice $invoice)
    {
        $invoice->setGrandTotal(0);
        $invoice->setBaseGrandTotal(0);
    }

    /**
     * Remove items in the default created invoice which does not exist in
     * selected shipments or have less quantity there
     *
     * @param Invoice $invoice The invoice object
     */
    protected function limitInvoiceItemsToSelectedShipments(Invoice $invoice)
    {
        $totalQty = 0;
        foreach ($invoice->getItemsCollection() as $key => $invoiceItem) {
            $qty = $this->getItemQuanityInShipments($invoiceItem);
            if (!$qty) {
                $invoiceItem->delete();
                $invoice->getItemsCollection()->removeItemByKey($key);
            } else {
                $invoiceItem->setQty($qty);
                $totalQty += $qty;
            }
        }
        $invoice->setTotalQty($totalQty);
        $this->resetInvoiceGrandTotals($invoice);
        $invoice->collectTotals();
    }

    /**
     * Save invoice to database
     *
     * @param Invoice $invoice The Invoice Object
     */
    protected function saveInvoice(Invoice $invoice)
    {
        $invoice->register();
        $invoice->save();
        $transactionSave = $this->transaction->addObject(
            $invoice
        )->addObject(
            $invoice->getOrder()
        );
        $transactionSave->save();
    }

    /**
     * Link Shipments to Invoices by ID
     *
     * @param Invoice $invoice The invoice object
     */
    protected function connectShipmentsToInvoice(Invoice $invoice)
    {
        foreach ($this->shipments as $shipment) {
            $shipment->setInvoiceId($invoice->getId());
            $shipment->save();
        }
    }

    /**
     * Create a new invoice based on selected shipments
     *
     * @return void
     */
    protected function createInvoice()
    {
        if (!$this->order->canInvoice()) {
            $this->messageManager->addErrorMessage(__('Cannot create invoice.'));
            return;
        }

        $invoice = $this->invoiceService->prepareInvoice($this->order);
        $this->limitInvoiceItemsToSelectedShipments($invoice);
        $this->saveInvoice($invoice);
        $this->connectShipmentsToInvoice($invoice);
        $this->messageManager->addSuccessMessage(__('Invoice created successfully'));
    }

    /**
     * Create invoice based on selected shipments
     *
     * @return Redirect
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function execute()
    {
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        if (!$this->processRequest($this->getRequest())) {
            $resultRedirect->setUrl($this->_redirect->getRefererUrl());
            return $resultRedirect;
        }

        $this->createInvoice();
        $resultRedirect->setUrl(
            $this->urlBuilder->getUrl(
                'sales/order/invoice/',
                [ 'order_id' => $this->order->getId() ]
            )
        );
        return $resultRedirect;
    }
}
