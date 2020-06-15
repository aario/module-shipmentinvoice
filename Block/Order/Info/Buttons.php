<?php

/**
 * The block for action buttons
 *
 * PHP version 7.3
 *
 * @category Block
 * @package  CreateInvoice
 * @author   Aario Shahbany <aario.shahbany@gmail.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.en.html GPLv3.0
 * @link     github.com/aario
 */
namespace Aario\ShipmentInvoice\Block\Order\Info;

use Magento\Sales\Block\Order\Info\Buttons as BaseButtons;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\View\Element\Template\Context as TemplateContext;
use Magento\Framework\Registry;
use Magento\Framework\App\Http\Context as HttpContext;
use Magento\Sales\Model\Order;

class Buttons extends BaseButtons
{

    /**
     * Override path to template
     *
     * @var string
     */
    //phpcs:disable
    protected $_template = 'Aario_ShipmentInvoice::order/info/buttons.phtml';
    //phpcs:enable

    /**
     * Get a list of available shipment Ids within order
     *
     * @param Order $order Magento Order Object
     *
     * @return array array of shipment Ids without the InvoiceId
     */
    public function getShipmentIdsForCreatingInvoice(Order $order): array
    {
        $shipmentIds = [];
        $shipments = $order->getShipmentsCollection();
        foreach ($shipments as $shipment) {
            if ($shipment->getInvoiceId()) {
                continue;
            }
            $shipmentIds[$shipment->getId()] = $shipment->getIncrementId();
        }
        return $shipmentIds;
    }

    /**
     * Check if shipments tab is open
     *
     * @return bool
     */
    public function isShipmentsPage(): bool
    {
        return strpos($this->getUrl('*/*/*'), '/sales/order/shipment/') !== false;
    }
}
