<?php
/**
 * Created by PhpStorm.
 * User: joel
 * Date: 01/01/2017
 * Time: 00:08
 */

namespace Magenest\Stripe\Model;

use Magenest\Stripe\Helper\Constant;
use Magento\Payment\Model\Method\AbstractMethod;

class StripePaymentIframe extends AbstractMethod
{
    const CODE = 'magenest_stripe_iframe';
    protected $_code = self::CODE;

    protected $_isGateway = true;
    protected $_canAuthorize = true;
    protected $_canCapture = true;
    protected $_canCapturePartial = true;
    protected $_canCaptureOnce = true;
    protected $_canVoid = true;
    protected $_canUseInternal = false;
    protected $_canUseCheckout = true;
    protected $_canRefund = true;
    protected $_canRefundInvoicePartial = true;

    protected $stripeCard;
    protected $_chargeFactory;
    protected $_helper;

    protected $_isInitializeNeeded = true;
    protected $_canOrder = true;

    public function __construct(
        \Magenest\Stripe\Helper\Data $dataHelper,
        ChargeFactory $chargeFactory,
        StripePaymentMethod $stripePaymentMethod,
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
        $this->stripeCard = $stripePaymentMethod;
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

    public function validate()
    {
        return true;
    }

    public function assignData(\Magento\Framework\DataObject $data)
    {
        parent::assignData($data);
        try {
            $_tmpData = $data->_data;
            $_serializedAdditionalData = serialize($_tmpData['additional_data']);
            $additionalDataRef = $_serializedAdditionalData;
            $additionalDataRef = unserialize($additionalDataRef);
            $rawCardData = $additionalDataRef['raw_card_data'];
            $_paymentToken = $additionalDataRef['stripe_token'];
            $payType = $additionalDataRef['pay_type'];
            $_cardID = "0";
            $_saved = "0";
            $threeDSecure = isset($additionalDataRef['three_d_secure']) ? $additionalDataRef['three_d_secure'] : "";
            $infoInstance = $this->getInfoInstance();
            $infoInstance->setAdditionalInformation('payment_token', $_paymentToken);
            $infoInstance->setAdditionalInformation('card_id', $_cardID);
            $infoInstance->setAdditionalInformation('saved', $_saved);
            $infoInstance->setAdditionalInformation('three_d_secure', $threeDSecure);
            $infoInstance->setAdditionalInformation('raw_card_data', $rawCardData);
            $infoInstance->setAdditionalInformation('pay_type', $payType);
        } catch (\Exception $e) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __('Data error.')
            );
        }

        if ($payType == 'card') {
            $this->stripeCard->addPaymentInfoData($infoInstance, $_cardID, $rawCardData);
            $this->stripeCard->addSourceToStripe($infoInstance, $_cardID, $_paymentToken);
        }

        return $this;
    }

    public function initialize($paymentAction, $stateObject)
    {
        /**
         * @var \Magento\Sales\Model\Order $order
         */
        $payment = $this->getInfoInstance();
        $order = $payment->getOrder();
//        $stateObject->setData('state', \Magento\Sales\Model\Order::STATE_PROCESSING);
        //$stateObject->setData('status', \Magento\Sales\Model\Order::STATE_PENDING_PAYMENT);
        $stateObject->setIsNotified($order->getCustomerNoteNotify());
        $amount = $order->getBaseGrandTotal();
        $threeDSecureAction = $this->stripeCard->_config->getThreedsecure();
        $threeDSecureStatus = $payment->getAdditionalInformation("three_d_secure");
        //save payment action
        $payment->setAdditionalInformation(Constant::ADDITIONAL_PAYMENT_ACTION, $paymentAction);
        $payType = $payment->getAdditionalInformation("pay_type");

        //bitcoin
        if ($payType == "source_bitcoin") {
            $stateObject->setData('state', \Magento\Sales\Model\Order::STATE_PROCESSING);
            $this->bitcoinPlaceOrder($payment);
        }

        //card pay
        if ($payType == "card") {
            //if admin set not use 3d secure -> normal payment
            if ($threeDSecureAction == 0) {
                $stateObject->setData('state', \Magento\Sales\Model\Order::STATE_PROCESSING);
                //$stateObject->setData('status', \Magento\Sales\Model\Order::STATE_PROCESSING);
                $this->stripeCard->placeOrder($payment, $amount, $paymentAction);
            }
            //if admin set 3d secure is auto
            if ($threeDSecureAction == 1) {
                //if card require
                if ($threeDSecureStatus == 'required') {
                    $this->order($payment, $amount);
                } else {  //if not require, normal pay
                    $stateObject->setData('state', \Magento\Sales\Model\Order::STATE_PROCESSING);
                    //$stateObject->setData('status', \Magento\Sales\Model\Order::STATE_PROCESSING);
                    $this->stripeCard->placeOrder($payment, $amount, $paymentAction);
                }
            }
            //3d secure on
            if ($threeDSecureAction == 2) {
                //if card not support, normal pay
                if ($threeDSecureStatus == 'not_supported') {
                    $stateObject->setData('state', \Magento\Sales\Model\Order::STATE_PROCESSING);
                    //$stateObject->setData('status', \Magento\Sales\Model\Order::STATE_PROCESSING);
                    $this->stripeCard->placeOrder($payment, $amount, $paymentAction);
                } else { //else, go 3d secure
                    $this->order($payment, $amount);
                }
            }
        }

        return parent::initialize($paymentAction, $stateObject);
    }

    public function order(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        $this->stripeCard->order($payment, $amount);

        return parent::order($payment, $amount); // TODO: Change the autogenerated stub
    }

    /**
     * @param \Magento\Payment\Model\InfoInterface|\Magento\Sales\Model\Order\Payment $payment
     * @param float $amount
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function authorize(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        $this->stripeCard->authorize($payment, $amount);

        return parent::authorize($payment, $amount);
    }

    /**
     * @param \Magento\Payment\Model\InfoInterface|\Magento\Sales\Model\Order\Payment $payment
     * @param float $amount
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function capture(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        $payType = $payment->getAdditionalInformation("pay_type");
        if ($payType == 'source_bitcoin') {
            $this->bitcoinCapture($payment, $amount);
            $order = $payment->getOrder();
            $order->addStatusHistoryComment("This order use bitcoin to capture");
        }
        if ($payType == 'card') {
            $this->stripeCard->capture($payment, $amount);
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
     * Function place order bitcoin
     * @param \Magento\Payment\Model\InfoInterface|\Magento\Sales\Model\Order\Payment $payment
     * @param float $amount
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function bitcoinPlaceOrder($payment)
    {
        $order = $payment->getOrder();
//        $order->setStatus(\Magento\Sales\Model\Order::STATE_PROCESSING);
//        $order->setState(\Magento\Sales\Model\Order::STATE_PROCESSING);
        $totalDue = $order->getTotalDue();
        $baseTotalDue = $order->getBaseTotalDue();
        $isSubscriptionOrder = $this->stripeCard->isSubscriptionOrder($payment);
        if ($isSubscriptionOrder) {
            $this->_logger->debug("Place order fail");
            throw new \Magento\Framework\Exception\LocalizedException(
                __('Place order fail')
            );
        }
        $payment->setCcType("Bitcoin");
        $payment->setAdditionalInformation(Constant::ADDITIONAL_THREEDS, "false");
        $payment->setAmountAuthorized($totalDue);
        $payment->setBaseAmountAuthorized($baseTotalDue);
        $payment->capture(null);
    }

    /**
     * Function capture bitcoin payment
     * @param \Magento\Payment\Model\InfoInterface|\Magento\Sales\Model\Order\Payment $payment
     * @param float $amount
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function bitcoinCapture($payment, $amount)
    {
        $paymentToken = $payment->getAdditionalInformation('payment_token'); // THIS IS THE TOKEN
        /** @var \Magento\Sales\Model\Order $order */
        $order = $payment->getOrder();
        $chargeModel = $this->_chargeFactory->create();
        $customerId = $order->getCustomerId();

        try {
            //if logged in, pay with customer id
            $request = [
                "amount" => round($amount * 100),
                "currency" => $order->getBaseCurrencyCode(),
                'capture' => 'true',
                "source" => $paymentToken
            ];

            $url = 'https://api.stripe.com/v1/charges';
            $response = $this->_helper->sendRequest($request, $url, null);
            $this->_logger->debug(serialize($response));
            if ($response['status'] == 'succeeded') {
                $payment->setAmount($amount);
                $payment->setTransactionId($response['id'])
                    ->setParentTransactionId($response['id'])
                    ->setIsTransactionClosed(false)
                    ->setShouldCloseParentTransaction(false)
                    ->setCcTransId($response['id'])
                    ->setLastTransId($response['id']);

                $data = [
                    'charge_id' => $response['id'],
                    'order_id' => $order->getIncrementId(),
                    'customer_id' => $customerId,
                    'status' => 'captured'
                ];

                $chargeModel->addData($data)->save();
            } else {
                throw new \Magento\Framework\Exception\LocalizedException(
                    __('Something went wrong. Please try again later.')
                );
            }
        } catch (\Exception $e) {
            $this->_logger->debug($e->getMessage());
            throw new \Magento\Framework\Exception\LocalizedException(
                __('Something went wrong. Please try again later.')
            );
        }
    }
}
