<?php
/**
 * Created by Magenest.
 * Author: Pham Quang Hau
 * Date: 02/06/2016
 * Time: 11:09
 */

namespace Magenest\Stripe\Model;

use Magenest\Stripe\Model\SubscriptionFactory;
use Magenest\Stripe\Helper\Data as DataHelper;

class Cron
{
    protected $_subscriptionFactory;

    protected $_helper;

    protected $_config;

    protected $_orderSender;

    protected $logger;

    public function __construct(
        SubscriptionFactory $subscriptionFactory,
        DataHelper $dataHelper,
        \Magenest\Stripe\Helper\Config $config,
        \Magento\Sales\Model\Order\Email\Sender\OrderSender $orderSender,
        \Magenest\Stripe\Helper\Logger $logger
    ) {
        $this->_helper = $dataHelper;
        $this->_config = $config;
        $this->_subscriptionFactory = $subscriptionFactory;
        $this->_orderSender = $orderSender;
        $this->logger = $logger;
    }

    public function execute()
    {
        $this->logger->debug("Cron job run daily");
        $this->syncEveryTwoMins();
    }

    public function syncEveryTwoMins()
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        /** @var \Magenest\Stripe\Helper\Data $dataHelper */
        $dataHelper = $objectManager->create('\Magenest\Stripe\Helper\Data');
        /** @var \Magenest\Stripe\Model\Subscription $subsModel */
        $subsModel = $this->_subscriptionFactory->create();
        $collection = $subsModel->getCollection();
        $canCreateOrder = $this->_config->getCanCreateOrder() === "1" ? true : false;

        /** @var \Magenest\Stripe\Model\Subscription $item */
        foreach ($collection as $item) {
            $subsId = $item->getData('subscription_id');
            $totalCycles = $item->getData('total_cycles');

            $url = 'https://api.stripe.com/v1/subscriptions/' . $subsId;
            $response = $this->_helper->sendRequest(null, $url, 'post');
            $status = $response['status'];
            $transactionId = $response['id'];

            $currentPeriod = $this->_helper->calculateCurrentPeriod($response);
            if ($totalCycles === $currentPeriod) {
                $newResponse = $this->_helper->deleteSubscriptionCron($response);
                $status = $newResponse['status'];
            }

            $now = date("Y-m-d H:i:s");
            // If the current period end has passed, create a new order for it
            if ((strtotime($now) > strtotime($item->getData('period_end'))) && $canCreateOrder && $status === 'active') {
//            if (1) {
                /** @var \Magento\Sales\Model\Order $order */
                $order = $item->placeOrder();
                if (!$order) {
                    continue;
                }

                $payment = $order->getPayment();
                $payment->setTransactionId($transactionId)
                    ->setIsTransactionClosed(0);

                $order->save();

                //the currency Code
                $plan = (array)$response['plan'];
                $grossAmount = $order->getBaseGrandTotal();


                $item->addSequenceOrder($order->getIncrementId());
                $payment->registerCaptureNotification($grossAmount);

                $order->save();
                try {
                    $this->_orderSender->send($order);
                } catch (\Exception $e) {
                    $this->logger->critical($e->getMessage());
                }

                $invoice = $payment->getCreatedInvoice();
                if ($invoice) {
                    // notify customer
                    $message = __('Notified customer about invoice: #') . $invoice->getIncrementId();
                    $order->addStatusHistoryComment($message)
                        ->setIsCustomerNotified(true)
                        ->save();
                }
            }

            $item->addData([
                'status' => $status,
                'period_start' => date("Y-m-d H:i:s", $response['current_period_start']),
                'period_end' => date("Y-m-d H:i:s", $response['current_period_end'])
            ])->save();
        }
    }
}
