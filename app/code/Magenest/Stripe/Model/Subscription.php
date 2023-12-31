<?php
/**
 * Created by Magenest.
 * Author: Pham Quang Hau
 * Date: 25/05/2016
 * Time: 16:46
 */

namespace Magenest\Stripe\Model;

use Magenest\Stripe\Model\ResourceModel\Subscription as Resource;
use Magenest\Stripe\Model\ResourceModel\Subscription\Collection as Collection;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Model\Order\Address;
use Magento\Sales\Model\Order\Payment;
use Magento\Framework\App\ObjectManager;
use Magento\Sales\Model\Order;

class Subscription extends AbstractModel
{
    protected $_eventPrefix = 'subscription_';

    protected $orderFactory;

    protected $_orderManagement;

    public function __construct(
        Context $context,
        Registry $registry,
        Resource $resource,
        Collection $resourceCollection,
        OrderFactory $orderFactory,
        OrderManagementInterface $orderManagement,
        $data = []
    ) {
        $this->orderFactory = $orderFactory;
        $this->_orderManagement = $orderManagement;
        parent::__construct($context, $registry, $resource, $resourceCollection, $data);
    }

    public function placeOrder()
    {
        /** @var \Magento\Sales\Model\Order $newOrder */
        try {
            $newOrder = $this->generateOrder();
            $newOrder->save();
        } catch (\Exception $e) {
            return false;
        }


        return $newOrder;
    }

    /**
     * @param \Magento\Sales\Model\Order $order
     */
    public function sendInvoice($order)
    {
        $payment = $order->getPayment();
        $payment->setTransactionId($this->getData('subscription_id'))->setIsTransactionClosed(0);
        $payment->registerCaptureNotification($order->getGrandTotal());
        $invoice = $payment->getCreatedInvoice();

        $order->save();
    }

    public function generateOrder()
    {
        //the order id of first related order of the subscription
        $orderId = $this->getData('order_id');

        if ($orderId) {
            /** @var  \Magento\Sales\Model\Order $order */
            $order = $this->orderFactory->create()->loadByIncrementId($orderId);

            $newOrder = $this->orderFactory->create();
            $orderInfo = $order->getData();
            try {
                $objectManager = ObjectManager::getInstance();

                /** @var \Magenest\Stripe\Helper\Config $configModel */
                $configModel = $objectManager->create('\Magenest\Stripe\Helper\Config');

                $billingAdd = $objectManager->create('Magento\Sales\Model\Order\Address');
                $oriBA = $order->getBillingAddress()->getData();
                $billingAdd->setData($oriBA)->setId(null);

                if ($order->getShippingAddress()) {
                    $shippingAdd = $objectManager->create('Magento\Sales\Model\Order\Address');
                    $shippingInfo = $order->getBillingAddress()->getData();
                    $shippingAdd->setData($shippingInfo)->setId(null);
                } else {
                    $shippingAdd = null;
                }
                /** @var \Magento\Sales\Model\Order\Payment $payment */
                $payment = $objectManager->create('Magento\Sales\Model\Order\Payment');
                $paymentMethodCode = $order->getPayment()->getMethod();

                $payment->setMethod($paymentMethodCode);

                $transferDataKays = array(
                    'store_id',
                    'store_name',
                    'customer_id',
                    'customer_email',
                    'customer_firstname',
                    'customer_lastname',
                    'customer_middlename',
                    'customer_prefix',
                    'customer_suffix',
                    'customer_taxvat',
                    'customer_gender',
                    'customer_is_guest',
                    'customer_note_notify',
                    'customer_group_id',
                    'customer_note',
                    'shipping_method',
                    'shipping_description',
                    'base_currency_code',
                    'global_currency_code',
                    'order_currency_code',
                    'store_currency_code',
                    'base_to_global_rate',
                    'base_to_order_rate',
                    'store_to_base_rate',
                    'store_to_order_rate'
                );


                foreach ($transferDataKays as $key) {
                    if (isset($orderInfo[$key])) {
                        $newOrder->setData($key, $orderInfo[$key]);
                    } elseif (isset($shippingInfo[$key])) {
                        $newOrder->setData($key, $shippingInfo[$key]);
                    }
                }

                $storeId = $order->getStoreId();
                $newOrder->setStoreId($storeId)
                    ->setState(Order::STATE_NEW)
                    ->setStatus($configModel->getNewOrderStatus())
                    ->setBaseToOrderRate($order->getBaseToOrderRate())
                    ->setStoreToOrderRate($order->getStoreToOrderRate())
                    ->setOrderCurrencyCode($order->getOrderCurrencyCode())
                    ->setBaseSubtotal($order->getBaseSubtotal())
                    ->setSubtotal($order->getSubtotal())
                    ->setBaseShippingAmount($order->getBaseShippingAmount())
                    ->setShippingAmount($order->getShippingAmount())
                    ->setBaseTaxAmount($order->getBaseTaxAmount())
                    ->setTaxAmount($order->getTaxAmount())
                    ->setBaseGrandTotal($order->getBaseGrandTotal())
                    ->setGrandTotal($order->getGrandTotal())
                    ->setIsVirtual($order->getIsVirtual())
                    ->setWeight($order->getWeight())
                    //->setTotalQtyOrdered($this->getInfoValue('order_info', 'items_qty'))
                    ->setTotalQtyOrdered($order->getTotalQtyOrdered())
                    ->setBillingAddress($billingAdd)
                    ->setShippingAddress($shippingAdd)
                    ->setPayment($payment);

                //todo
                /** @var \Magento\Sales\Model\Order\Item[] $items */
                $items = $order->getAllItems();
                foreach ($items as $item) {
                    $newOrderItem = clone $item;
                    $newOrderItem->setId(null);
                    $newOrderItem->setQtyShipped(0);
                    $newOrder->addItem($newOrderItem);
                }
            } catch (\Exception $e) {
            }

            return $newOrder;
        }

        return null;
    }

    public function addSequenceOrder($orderId)
    {
        $sequenceOrderIds = '';
        $sequenceOrderIds = $this->getData('sequence_order_ids');

        if (!$sequenceOrderIds) {
            $this->addData([
                "sequence_order_ids" => serialize([$orderId])
            ])->save();
        } else {
            $sequenceOrderIds = unserialize($sequenceOrderIds);
            array_push($sequenceOrderIds, $orderId);

            $this->addData([
                "sequence_order_ids" => serialize($sequenceOrderIds)
            ])->save();
        }
    }
}
