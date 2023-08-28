<?php
/**
 * Created by PhpStorm.
 * User: joel
 * Date: 01/01/2017
 * Time: 00:08
 */

namespace Magenest\Stripe\Model;

use Magento\Payment\Model\Method\AbstractMethod;

class GiroPay extends AbstractMethod
{
    const CODE = 'magenest_stripe_giropay';
    protected $_code = self::CODE;

    protected $_isGateway = true;
    protected $_canAuthorize = false;
    protected $_canCapture = true;
    protected $_canCapturePartial = false;
    protected $_canCaptureOnce = true;
    protected $_canVoid = false;
    protected $_canUseInternal = false;
    protected $_canUseCheckout = true;
    protected $_canRefund = true;
    protected $_canRefundInvoicePartial = true;
    protected $_chargeFactory;
    protected $_helper;
    protected $stripeLogger;
    protected $_checkoutSession;
    protected $_messageManager;

    public function __construct(
        \Magenest\Stripe\Helper\Data $dataHelper,
        ChargeFactory $chargeFactory,
        \Magento\Checkout\Model\Session $checkoutSession,
        StripePaymentMethod $stripePaymentMethod,
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
        \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
        \Magento\Payment\Helper\Data $paymentData,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Payment\Model\Method\Logger $logger,
        \Magenest\Stripe\Helper\Logger $stripeLogger,
        \Magento\Framework\Message\ManagerInterface $messageManager,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->_checkoutSession = $checkoutSession;
        $this->stripeLogger = $stripeLogger;
        $this->_helper = $dataHelper;
        $this->_chargeFactory = $chargeFactory;
        $this->stripeCard = $stripePaymentMethod;
        $this->_messageManager = $messageManager;
        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $paymentData,
            $scopeConfig,
            $logger,
            $resource,
            $resourceCollection,
            $data
        );
    }

    public function getConfigPaymentAction()
    {
        return parent::ACTION_AUTHORIZE_CAPTURE;
    }

    /**
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param float $amount
     * @return $this
     * @throws \Exception
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function capture(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        $this->_debug("capture action");
        /** @var \Magento\Sales\Model\Order $order */
        $order = $payment->getOrder();
        $request = [
            "amount" => round($amount * 100),
            "currency" => $order->getBaseCurrencyCode(),
            'capture' => 'true',
            "source" => $payment->getAdditionalInformation("source")
        ];

        $url = 'https://api.stripe.com/v1/charges';
        $response = $this->_helper->sendRequest($request, $url, null);
        $this->_debug($response);
        if (isset($response['status']) && ($response['status'] == 'succeeded')) {
            $payment->setTransactionId($response['id'])
                ->setParentTransactionId($response['id'])
                ->setIsTransactionClosed(false)
                ->setShouldCloseParentTransaction(false)
                ->setCcTransId($response['id'])
                ->setLastTransId($response['id']);


            $data = [
                'charge_id' => $response['id'],
                'order_id' => $order->getIncrementId(),
                'customer_id' => $order->getCustomerId(),
                'status' => 'captured'
            ];
            $chargeModel = $this->_chargeFactory->create();
            $chargeModel->addData($data)->save();
        } else {
            $message = isset($response['error']->message) ? $response['error']->message : "Something went wrong. Please try again later";
            $this->_checkoutSession->setMessageError($message);
            throw new \Exception(
                __($message)
            );
        }
        $this->_debug("end capture action");

        return parent::capture($payment, $amount);
    }

    public function refund(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        $this->_debug("refund action");
        try {
            /** @var \Magenest\Stripe\Model\Charge $chargeModel */
            $chargeModel = $this->_chargeFactory->create();

            /** @var \Magento\Sales\Model\Order $order */
            $order = $payment->getOrder();
            $orderId = $order->getIncrementId();

            $charge = $chargeModel->getCollection()->addFieldToFilter('order_id', $orderId)->getFirstItem();
            if ($charge->getId()) {
                $url = 'https://api.stripe.com/v1/refunds';

                $request = [
                    'charge' => $charge->getData('charge_id'),
                    'amount' => round($amount * 100)
                ];

                $response = $this->_helper->sendRequest($request, $url, null);
                $this->_debug($response);

                if ($response['status'] == 'succeeded') {
                    $payment->setTransactionId($response['id']);
                    $payment->setParentTransactionId($charge->getData('charge_id'));
                    $payment->setShouldCloseParentTransaction(1);
                } elseif ($response['status'] == 'pending') {
                    $payment->setTransactionId($response['id']);
                    $payment->setParentTransactionId($charge->getData('charge_id'));
                    $payment->setShouldCloseParentTransaction(1);
                    $this->_messageManager->addWarningMessage("Refund pending");
                } else {
                    throw new \Magento\Framework\Exception\LocalizedException(
                        __('Something went wrong while refunding. Please try again.')
                    );
                }
            } else {
                throw new \Magento\Framework\Exception\LocalizedException(
                    __('Charge doesn\'t exist. Please try again later.')
                );
            }
        } catch (\Exception $e) {
            $this->stripeLogger->critical($e->getMessage());
            throw new \Magento\Framework\Exception\LocalizedException(
                __('Payment Exception')
            );
        }
        $this->_debug(" end refund action");

        return $this;
    }

    /**
     * @param array|string $debugData
     */
    protected function _debug($debugData)
    {
        $this->stripeLogger->debug(var_export($debugData, true));
    }
}
