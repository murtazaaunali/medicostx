<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 13/11/2017
 * Time: 10:32
 */

namespace Magenest\Stripe\Model;

use Magenest\Stripe\Helper\Logger;
use Magento\Payment\Model\Method\AbstractMethod;

class ApplePay extends AbstractMethod
{
    const CODE = 'magenest_stripe_applepay';
    protected $_code = self::CODE;
    protected $_isGateway = true;
    protected $_canAuthorize = false;
    protected $_canCapture = true;
    protected $_canCapturePartial = false;
    protected $_canCaptureOnce = false;
    protected $_canVoid = false;
    protected $_canUseInternal = false;
    protected $_canUseCheckout = true;
    protected $_canRefund = true;
    protected $_canRefundInvoicePartial = true;
    protected $_canOrder = false;

    protected $stripeLogger;
    protected $stripeCard;

    public function __construct(
        \Magenest\Stripe\Helper\Data $dataHelper,
        ChargeFactory $chargeFactory,
        StripePaymentMethod $stripePaymentMethod,
        \Magenest\Stripe\Helper\Logger $stripeLogger,
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
        \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
        \Magento\Payment\Helper\Data $paymentData,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Payment\Model\Method\Logger $logger,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->_helper = $dataHelper;
        $this->_chargeFactory = $chargeFactory;
        $this->stripeLogger = $stripeLogger;
        $this->stripeCard = $stripePaymentMethod;
        parent::__construct($context, $registry, $extensionFactory, $customAttributeFactory, $paymentData, $scopeConfig, $logger, $resource, $resourceCollection, $data);
    }

    public function assignData(\Magento\Framework\DataObject $data)
    {
        parent::assignData($data);
        try {
            $this->_debug("begin stripe applepay");
            $_tmpData = $data->_data;
            $_serializedAdditionalData = serialize($_tmpData['additional_data']);
            $additionalDataRef = $_serializedAdditionalData;
            $additionalDataRef = unserialize($additionalDataRef);
            $_paymentToken = $additionalDataRef['token'];
            $payType = $additionalDataRef['pay_type'];
//            $chargeId = $additionalDataRef['chargeId'];

            $infoInstance = $this->getInfoInstance();
            $infoInstance->setAdditionalInformation('payment_token', $_paymentToken);
            $infoInstance->setAdditionalInformation('payType', $payType);
//            $infoInstance->setAdditionalInformation('chargeId', $chargeId);
//
//            $infoInstance->addData(
//                [
//                    'payment_token' => $_paymentToken,
//                    'charge_id'   => $chargeId
//                ]
//            );
        } catch (\Exception $e) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __('Data error.')
            );
        }
    }

    public function validate()
    {
        return true;
    }

    public function getConfigPaymentAction()
    {
        return "authorize_capture";
    }

    public function capture(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        try {
            /** @var \Magento\Sales\Model\Order $order */
            $order = $payment->getOrder();
            $orderId = $order->getIncrementId();
            $customerId = $order->getCustomerId();
            $this->_debug("applepay capture, orderid: ".$orderId);

            $token = $payment->getAdditionalInformation('payment_token');
            $currency = strtolower($order->getBaseCurrencyCode());
            $multiply = 100;
            if ($this->_helper->isZeroDecimal($order->getBaseCurrencyCode())) {
                $multiply = 1;
            }
            $_amount = $amount * $multiply;
            $request = [
                "amount" => round($_amount),
                "currency" =>$currency,
                'capture' => 'true',
                "source" => $token,
            ];

            $this->stripeLogger->debug(var_export($request, true));
            $url = 'https://api.stripe.com/v1/charges';
            $response = $this->_helper->sendRequest($request, $url, null);
            $this->stripeLogger->debug(var_export($response, true));
            if (isset($response['error'])) {
                $message = isset($response['error']->message)?$response['error']->message:"Payment Exception";
                throw new \Magento\Framework\Exception\LocalizedException(
                    __($message)
                );
            } else {
                $chargeId = $response['id'];
            }

            $payment->setStatus(\Magento\Payment\Model\Method\AbstractMethod::STATUS_SUCCESS)
                ->setShouldCloseParentTransaction(0)
                ->setIsTransactionClosed(1);

            $payment->setTransactionId($chargeId)
                ->setParentTransactionId($chargeId)
                ->setLastTransId($chargeId);
            //save the charge information
            $data = [
                'charge_id' => $chargeId,
                'order_id' => $order->getIncrementId(),
                'customer_id' => $customerId,
                'status' => 'captured'
            ];

            $chargeModel = $this->_chargeFactory->create();
            $chargeModel->addData($data)->save();
        } catch (\Exception $e) {
            $this->stripeLogger->debug($e->getMessage());
            throw new \Magento\Framework\Exception\LocalizedException(
                __('Payment Exception')
            );
        }

        return parent::capture($payment, $amount);
    }

    /**
     * @param \Magento\Payment\Model\InfoInterface|\Magento\Sales\Model\Order\Payment $payment
     * @param float $amount
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function refund(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        $this->stripeCard->refund($payment, $amount);

        return parent::refund($payment, $amount);
    }

    public function void(\Magento\Payment\Model\InfoInterface $payment)
    {
        $this->stripeCard->void($payment);

        return parent::void($payment);
    }

    public function cancel(\Magento\Payment\Model\InfoInterface $payment)
    {
        $this->void($payment);

        return parent::cancel($payment);
    }

    /**
     * @param array|string $debugData
     */
    protected function _debug($debugData)
    {
        $this->stripeLogger->debug(var_export($debugData, true));
    }
}
