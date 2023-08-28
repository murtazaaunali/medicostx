<?php
/**
 * Created by PhpStorm.
 * User: joel
 * Date: 16/03/2017
 * Time: 01:21
 */

namespace Magenest\Stripe\Controller\Checkout;

use Magenest\Stripe\Helper\Constant;
use Magento\Framework\App\Action\Context;
use Magento\Checkout\Model\Session as CheckoutSession;

class UpdateSubscription extends \Magento\Framework\App\Action\Action
{
    protected $_checkoutSession;
    protected $_subscriptionFactory;
    protected $_chargeFactory;
    protected $invoiceSender;
    protected $transactionFactory;

    public function __construct(
        Context $context,
        CheckoutSession $session,
        \Magenest\Stripe\Model\SubscriptionFactory $subscriptionFactory,
        \Magenest\Stripe\Model\ChargeFactory $chargeFactory,
        \Magento\Sales\Model\Order\Email\Sender\InvoiceSender $invoiceSender,
        \Magento\Framework\DB\TransactionFactory $transactionFactory
    ) {
        parent::__construct($context);
        $this->_checkoutSession = $session;
        $this->_subscriptionFactory = $subscriptionFactory;
        $this->_chargeFactory = $chargeFactory;
        $this->invoiceSender = $invoiceSender;
        $this->transactionFactory = $transactionFactory;
    }

    public function execute()
    {
        $subscriptionId = $this->getRequest()->getParam('subscriptionId');
        $chargeId = $this->getRequest()->getParam('chargeId');
        $order = $this->_checkoutSession->getLastRealOrder();
        $orderId = $order->getIncrementId();

        if ($subscriptionId) {
            $subscription = $this->_subscriptionFactory->create()->getCollection()
                ->addFieldToFilter('subscription_id', $subscriptionId)->getFirstItem();

            $subscription->addData(['order_id' => $orderId])->save();
        }

        if ($chargeId) {
            $charge = $this->_chargeFactory->create()->getCollection()
                ->addFieldToFilter('charge_id', $chargeId)->getFirstItem();

            $charge->addData(['order_id' => $orderId])->save();
        }

        $order = $this->_checkoutSession->getLastRealOrder();
        /** @var \Magento\Sales\Model\Order\Payment $payment */
        $payment = $order->getPayment();
        $type = $payment->getAdditionalInformation(Constant::ADDITIONAL_TYPE);
        if ($type == Constant::IFRAME_PAYMENT_TYPE_BITCOIN) {
            if ($order->canInvoice()) {
                $invoice = $order->prepareInvoice();
                $invoice->setRequestedCaptureCase(\Magento\Sales\Model\Order\Invoice::CAPTURE_ONLINE);
                $invoice->register();

                $transaction = $this->transactionFactory->create();
                $transaction->addObject($invoice)
                    ->addObject($invoice->getOrder())
                    ->save();

                $this->invoiceSender->send($invoice);
                $order->addStatusHistoryComment(
                    __('Notified customer about invoice #%1.', $invoice->getId())
                )->setIsCustomerNotified(true)->save();
            }
        }
    }
}
