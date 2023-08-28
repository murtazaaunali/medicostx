<?php
/**
 * Created by PhpStorm.
 * User: magenest
 * Date: 27/05/2017
 * Time: 16:01
 */

namespace Magenest\Stripe\Controller\Checkout;

use Magenest\Stripe\Helper\Constant;
use Magento\Framework\App\Action\Context;
use Magento\Checkout\Model\Session as CheckoutSession;

class ThreedSecure extends \Magento\Framework\App\Action\Action
{
    protected $_checkoutSession;
    protected $_subscriptionFactory;
    protected $_chargeFactory;
    protected $invoiceSender;
    protected $transactionFactory;
    protected $jsonFactory;
    protected $stripeConfig;
    protected $storeManagerInterface;
    protected $stripeLogger;

    public function __construct(
        Context $context,
        CheckoutSession $session,
        \Magenest\Stripe\Model\SubscriptionFactory $subscriptionFactory,
        \Magenest\Stripe\Model\ChargeFactory $chargeFactory,
        \Magento\Sales\Model\Order\Email\Sender\InvoiceSender $invoiceSender,
        \Magento\Framework\DB\TransactionFactory $transactionFactory,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        \Magenest\Stripe\Helper\Config $stripeConfig,
        \Magento\Store\Model\StoreManagerInterface $storeManagerInterface,
        \Magenest\Stripe\Helper\Logger $stripeLogger
    ) {
        parent::__construct($context);
        $this->_checkoutSession = $session;
        $this->_subscriptionFactory = $subscriptionFactory;
        $this->_chargeFactory = $chargeFactory;
        $this->invoiceSender = $invoiceSender;
        $this->transactionFactory = $transactionFactory;
        $this->jsonFactory = $resultJsonFactory;
        $this->stripeConfig = $stripeConfig;
        $this->storeManagerInterface = $storeManagerInterface;
        $this->stripeLogger = $stripeLogger;
    }

    public function execute()
    {
        $result = $this->jsonFactory->create();
        if ($this->getRequest()->isAjax()) {
            try {
                $order = $this->_checkoutSession->getLastRealOrder();
                /** @var \Magento\Sales\Model\Order\Payment $payment */
                $payment = $order->getPayment();
                $threeDAction = $payment->getAdditionalInformation(Constant::ADDITIONAL_THREEDS);
                if ($threeDAction == 'false') {
                    return $result->setData([
                        'success' => true,
                        'threeDSercueActive' => false,
                        'defaultPay' => true
                    ]);
                } else {
                    if ($threeDAction == 'true') {
                        $threeDSecureUrl = $payment->getAdditionalInformation("threed_secure_url");

                        return $result->setData([
                            'success' => true,
                            'threeDSercueActive' => true,
                            'threeDSercueUrl' => $threeDSecureUrl,
                            'defaultPay' => false
                        ]);
                    } else {
                        return $result->setData([
                            'error' => true,
                            'message' => "Payment exception"
                        ]);
                    }
                }
            } catch (\Exception $e) {
                $this->stripeLogger->critical($e->getMessage());

                return $result->setData([
                    'error' => true,
                    'message' => "Payment exception"
                ]);
            }
        }

        return false;
    }
}
