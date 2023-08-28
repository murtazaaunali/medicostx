<?php
/**
 * Created by Magenest.
 * Author: Pham Quang Hau
 * Date: 11/05/2016
 * Time: 13:33
 */

namespace Magenest\Stripe\Model;

use Magenest\Stripe\Helper\Constant;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Magento\Framework\Api\ExtensionAttributesFactory;
use Magento\Framework\Api\AttributeValueFactory;
use Magento\Payment\Helper\Data;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Payment\Model\Method\Logger;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Directory\Model\CountryFactory;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Magenest\Stripe\Model\ChargeFactory;
use Magenest\Stripe\Model\CustomerFactory;
use Magenest\Stripe\Helper\Data as DataHelper;
use Magenest\Stripe\Model\SubscriptionFactory;
use Magenest\Stripe\Helper\Config as ConfigHelper;

class StripePaymentMethod extends \Magento\Payment\Model\Method\Cc
{
    const CODE = 'magenest_stripe';
    protected $_code = self::CODE;
    protected $_isGateway = true;
    protected $_canCapture = true;
    protected $_canCapturePartial = true;
    protected $_canCaptureOnce = true;
    protected $_canAuthorize = true;
    protected $_canRefund = true;
    protected $_canRefundInvoicePartial = true;
    protected $_canVoid = true;
    protected $_countryFactory;
    protected $_minAmount = null;
    protected $_maxAmount = null;
    protected $_testSecret = null;
    protected $_testPublishable = null;
    protected $_liveSecret = null;
    protected $_livePublishable = null;
    protected $_isTest = null;
    protected $_sendEmail = null;
    protected $_planId = '';
    protected $_totalCycles;
    protected $_checkoutSession;
    protected $_encryptor;
    protected $_chargeFactory;
    protected $_customerFactory;
    protected $_cardFactory;
    protected $_helper;
    protected $_subscriptionFactory;
    protected $_orderSender;
    public $_config;
    protected $_isCustomer;
    protected $_supportedCurrencyCodes = [
        'AUD',
        'CAD',
        'CZK',
        'DKK',
        'EUR',
        'HKD',
        'HUF',
        'ILS',
        'JPY',
        'MXN',
        'NOK',
        'NZD',
        'PLN',
        'GBP',
        'RUB',
        'SGD',
        'SEK',
        'CHF',
        'TWD',
        'THB',
        'USD',
    ];

    /**
     * @var \Magenest\Stripe\Helper\Logger $stripeLogger
     */
    public $stripeLogger;
    protected $customerSession;
    protected $_isInitializeNeeded = true;
    protected $_canOrder = true;
    protected $_messageManager;
    protected $storeManagerInterface;
    protected $subscriptionHelper;

    public function __construct(
        Context $context,
        Registry $registry,
        ExtensionAttributesFactory $extensionFactory,
        AttributeValueFactory $customAttributeFactory,
        Data $paymentData,
        ScopeConfigInterface $scopeConfig,
        Logger $logger,
        ModuleListInterface $moduleList,
        TimezoneInterface $localeDate,
        CountryFactory $countryFactory,
        CheckoutSession $checkoutSession,
        EncryptorInterface $encryptorInterface,
        ChargeFactory $chargeFactory,
        CustomerFactory $customerFactory,
        DataHelper $dataHelper,
        SubscriptionFactory $subscriptionFactory,
        OrderSender $orderSender,
        ConfigHelper $config,
        \Magenest\Stripe\Helper\Logger $stripeLogger,
        \Magenest\Stripe\Model\CardFactory $cardFactory,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Framework\Message\ManagerInterface $messageManager,
        \Magento\Store\Model\StoreManagerInterface $storeManagerInterface,
        \Magenest\Stripe\Helper\SubscriptionHelper $subscriptionHelper,
        $data = []
    ) {
        $this->_cardFactory = $cardFactory;
        $this->_countryFactory = $countryFactory;
        $this->_checkoutSession = $checkoutSession;
        $this->_encryptor = $encryptorInterface;
        $this->_chargeFactory = $chargeFactory;
        $this->_customerFactory = $customerFactory;
        $this->_helper = $dataHelper;
        $this->_subscriptionFactory = $subscriptionFactory;
        $this->_orderSender = $orderSender;
        $this->_config = $config;

        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $paymentData,
            $scopeConfig,
            $logger,
            $moduleList,
            $localeDate,
            null,
            null,
            $data
        );

        $this->_minAmount = $this->getConfigData('additional_config/min_order_total');
        $this->_maxAmount = $this->getConfigData('additional_config/max_order_total');
        $this->_testSecret = $this->_encryptor->decrypt($this->getConfigData('test_secret'));
        $this->_testPublishable = $this->_encryptor->decrypt($this->getConfigData('test_publishable'));
        $this->_liveSecret = $this->_encryptor->decrypt($this->getConfigData('live_secret'));
        $this->_livePublishable = $this->_encryptor->decrypt($this->getConfigData('live_publishable'));
        $this->_isTest = $this->getConfigData('test');
        $this->_sendEmail = $this->getConfigData('email_customer');
        $this->stripeLogger = $stripeLogger;
        $this->customerSession = $customerSession;
        $this->_messageManager = $messageManager;
        $this->storeManagerInterface = $storeManagerInterface;
        $this->subscriptionHelper = $subscriptionHelper;
    }

    public function canUseInternal()
    {
        return true;
    }

    public function isAvailable(\Magento\Quote\Api\Data\CartInterface $quote = null)
    {
        return \Magento\Payment\Model\Method\AbstractMethod::isAvailable($quote);
    }

    public function validate()
    {
        return \Magento\Payment\Model\Method\AbstractMethod::validate();
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
            $_cardID = isset($additionalDataRef['card_id']) ? $additionalDataRef['card_id'] : "0";
            $_saved = isset($additionalDataRef['saved']) ? $additionalDataRef['saved'] : "0";
            $threeDSecure = isset($additionalDataRef['three_d_secure']) ? $additionalDataRef['three_d_secure'] : "";
            $infoInstance = $this->getInfoInstance();
            $infoInstance->setAdditionalInformation('payment_token', $_paymentToken);
            $infoInstance->setAdditionalInformation('card_id', $_cardID);
            $infoInstance->setAdditionalInformation('saved', $_saved);
            $infoInstance->setAdditionalInformation('three_d_secure', $threeDSecure);
            $infoInstance->setAdditionalInformation('raw_card_data', $rawCardData);
            $this->addPaymentInfoData($infoInstance, $_cardID, $rawCardData);
            $this->addSourceToStripe($infoInstance, $_cardID, $_paymentToken);
        } catch (\Exception $e) {
            $this->stripeLogger->critical($e->getMessage());
            throw new \Magento\Framework\Exception\LocalizedException(
                __('Data error.')
            );
        }

        return $this;
    }

    public function addSourceToStripe($infoInstance, $_cardID, $_paymentToken)
    {
        if ($this->customerSession->isLoggedIn()) {
            try {
                $customerModel = $this->_customerFactory->create();
                /** @var \Magenest\Stripe\Model\Customer $customer */
                //find customer in DB
                $customer = $customerModel->getCollection()
                    ->addFieldToFilter('magento_customer_id', $this->customerSession->getCustomerId())
                    ->getFirstItem();
                /**
                 * if customer registered and have data in db, get stripe cus_id
                 * else: create customer data
                 */
                $stripeCustomerId = null;
                if ($customer->getId()) {
                    //is a customer
                    $stripeCustomerId = $customer->getData('stripe_customer_id');
                    //check stripe customer id
                    $checkResp = $this->_helper->checkStripeCustomerId($stripeCustomerId);
                    $this->_debug($checkResp);
                    if (isset($checkResp['error'])) {
                        //delete old and create new customer
                        $customer->delete();
                        $stripeCustomerId = $this->createCustomer();
                    }
                } else {
                    /**
                     * create stripe customer
                     */
                    $stripeCustomerId = $this->createCustomer();
                }
                if ($_paymentToken != '0') {
                    $response = $this->addSourceToCustomer($stripeCustomerId, $_paymentToken);
                    $this->_debug($response);
                }
                if ($_cardID != "0") {
                    $infoInstance->setAdditionalInformation('payment_token', $_cardID);
                }
            } catch (\Exception $e) {
                throw new \Magento\Framework\Exception\LocalizedException(
                    __($e->getMessage())
                );
            }
        }
    }

    public function addPaymentInfoData($infoInstance, $_cardID, $rawCardData)
    {
        if ($_cardID == "0") {
            $cardData = json_decode($rawCardData);
            try {
                $infoInstance->addData(
                    [
                        'cc_type' => $cardData->brand,
                        'cc_last_4' => $cardData->last4,
                        'cc_exp_month' => $cardData->exp_month,
                        'cc_exp_year' => $cardData->exp_year
                    ]
                );
            } catch (\Exception $e) {
                $this->stripeLogger->critical($e->getMessage());
            }
        } else {
            $cardData = $this->getCardInfo($_cardID);
            $infoInstance->setAdditionalInformation('three_d_secure', $cardData['threed_secure']);
            $infoInstance->addData(
                [
                    'cc_type' => $cardData['brand'],
                    'cc_last_4' => $cardData['last4'],
                    'cc_exp_month' => $cardData['exp_month'],
                    'cc_exp_year' => $cardData['exp_year']
                ]
            );
        }
    }

    public function initialize($paymentAction, $stateObject)
    {
        /**
         * @var \Magento\Sales\Model\Order $order
         */
        $payment = $this->getInfoInstance();
        $order = $payment->getOrder();
        $this->_debug("-------Stripe init: orderid: " . $order->getIncrementId());
        //$stateObject->setData('state', \Magento\Sales\Model\Order::STATE_PROCESSING);
        //$stateObject->setData('status', \Magento\Sales\Model\Order::STATE_PENDING_PAYMENT);
        $stateObject->setIsNotified($order->getCustomerNoteNotify());
        $amount = $order->getBaseGrandTotal();
        //if internal order
        if ($this->_appState->getAreaCode() == 'adminhtml') {
            $stateObject->setData('state', \Magento\Sales\Model\Order::STATE_PROCESSING);
            $this->placeOrder($payment, $amount, $paymentAction);

            return parent::initialize($paymentAction, $stateObject);
        }
        $threeDSecureAction = $this->_config->getThreedsecure();
        $threeDSecureStatus = $payment->getAdditionalInformation("three_d_secure");
        //save payment action
        $payment->setAdditionalInformation(Constant::ADDITIONAL_PAYMENT_ACTION, $paymentAction);
        //if admin set not use 3d secure -> normal payment
        if ($threeDSecureAction == 0) {
            $stateObject->setData('state', \Magento\Sales\Model\Order::STATE_PROCESSING);
            //$stateObject->setData('status', \Magento\Sales\Model\Order::STATE_PROCESSING);
            $this->placeOrder($payment, $amount, $paymentAction);
        }
        //if admin set 3d secure is auto
        if ($threeDSecureAction == 1) {
            //if card require
            if ($threeDSecureStatus == 'required') {
                $this->order($payment, $amount);
            } else {  //if not require, normal pay
                $stateObject->setData('state', \Magento\Sales\Model\Order::STATE_PROCESSING);
                //$stateObject->setData('status', \Magento\Sales\Model\Order::STATE_PROCESSING);
                $this->placeOrder($payment, $amount, $paymentAction);
            }
        }
        //3d secure on
        if ($threeDSecureAction == 2) {
            //if card not support, normal pay
            if ($threeDSecureStatus == 'not_supported') {
                $stateObject->setData('state', \Magento\Sales\Model\Order::STATE_PROCESSING);
                //$stateObject->setData('status', \Magento\Sales\Model\Order::STATE_PROCESSING);
                $this->placeOrder($payment, $amount, $paymentAction);
            } else { //else, go 3d secure
                $this->order($payment, $amount);
            }
        }
        //3d secure force require
        if ($threeDSecureAction == 3) {
            $this->order($payment, $amount);
        }

        return parent::initialize($paymentAction, $stateObject); // TODO: Change the autogenerated stub
    }

    /**
     * Function place order for non-3ds payment
     * @param \Magento\Payment\Model\InfoInterface|\Magento\Sales\Model\Order\Payment $payment
     * @param float $amount
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function placeOrder($payment, $amount, $paymentAction)
    {
        $this->_debug("Place order action");
        $order = $payment->getOrder();
        //$order->setStatus(\Magento\Sales\Model\Order::STATE_PROCESSING);
        //$order->setState(\Magento\Sales\Model\Order::STATE_PROCESSING);
        $totalDue = $order->getTotalDue();
        $baseTotalDue = $order->getBaseTotalDue();
        $_paymentToken = $payment->getAdditionalInformation('payment_token');
        $cardData = json_decode($payment->getAdditionalInformation('raw_card_data'));
        $_saved = $payment->getAdditionalInformation('saved');
        $isSubscriptionOrder = $this->isSubscriptionOrder($payment);
        if (($isSubscriptionOrder) && ($order->getCustomerIsGuest())) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __('You need create an account to order subscription product')
            );
        }

//        if ($_saved == "1"){
//            if(($this->customerSession->isLoggedIn())|($isSubscriptionOrder)) {
//                $this->saveCard($_paymentToken, $cardData);
//            }
//        }

        //3d secure: false
        $payment->setAdditionalInformation(Constant::ADDITIONAL_THREEDS, "false");
        if ($paymentAction == 'authorize') {
            $payment->setAmountAuthorized($totalDue);
            $payment->authorize(true, $baseTotalDue);
        } else {
            $payment->setAmountAuthorized($totalDue);
            $payment->setBaseAmountAuthorized($baseTotalDue);
            $payment->capture(null);
        }
    }

    /**
     * Function order for 3d secure check
     * @param \Magento\Payment\Model\InfoInterface|\Magento\Sales\Model\Order\Payment $payment
     * @param float $amount
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function order(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        $this->_debug("Order action, 3ds on");
        $threeDSecureAction = $this->_config->getThreedsecure();
        /** @var \Magento\Sales\Model\Order $order */
//        if($this->isSubscriptionOrder($payment)){
//            //excfeption when subscription with 3d secure
//            throw new \Magento\Framework\Exception\LocalizedException(
//                __('Payment error')
//            );
//        }
        $order = $payment->getOrder();
        $order->setCanSendNewEmailFlag(false);
        //$order->setStatus(\Magento\Sales\Model\Order::STATE_PENDING_PAYMENT);
        //$order->setState(\Magento\Sales\Model\Order::STATE_PENDING_PAYMENT);
        $order->addStatusHistoryComment(__("Customer redirect to 3d Secure"));
        $currency = $order->getBaseCurrencyCode();
        $multiply = 100;
        if ($this->_helper->isZeroDecimal($currency)) {
            $multiply = 1;
        }
        $_amount = $amount * $multiply;
        $cardSrc = $payment->getAdditionalInformation('payment_token');
        $returnUrl = $this->storeManagerInterface->getStore()->getBaseUrl() . "stripe/checkout/threedSecureResponse";
//        $source = \Stripe\Source::create(array(
//            "amount" => round($_amount),
//            "currency" => strtoupper($currency),
//            "type" => "three_d_secure",
//            "three_d_secure" => array(
//                "card" => $cardSrc,
//            ),
//            "redirect" => array(
//                "return_url" => $returnUrl
//            ),
//        ));
        $request = [
            "amount" => round($_amount),
            "currency" => strtoupper($currency),
            "type" => "three_d_secure",
            "three_d_secure" => array(
                "card" => $cardSrc,
            ),
            "redirect" => array(
                "return_url" => $returnUrl
            ),
        ];
        $url = "https://api.stripe.com/v1/sources";
        $source = $this->_helper->sendRequest($request, $url, "post");
        $this->_debug($source);
        $clientSecret = $source['client_secret'];
        $redirectStatus = $source['redirect']->status;
        //status = pending ==> card pending 3d secure
        if ($redirectStatus == 'pending') {
            $threeDSecureUrl = $source['redirect']->url;
            //3d secure: true
            $payment->setAdditionalInformation(Constant::ADDITIONAL_THREEDS, "true");
            $payment->setAdditionalInformation("threed_secure_url", $threeDSecureUrl);
            $payment->setAdditionalInformation("client_secret", $clientSecret);
        } else {
            if ($threeDSecureAction != 3) {
                $threeDSecureUrl = $source['redirect']->url;
                $payment->setAdditionalInformation(Constant::ADDITIONAL_THREEDS, "true");
                $payment->setAdditionalInformation("threed_secure_url", $threeDSecureUrl);
                $payment->setAdditionalInformation("client_secret", $clientSecret);
            } else {
                throw new \Magento\Framework\Exception\LocalizedException(
                    __('Something went wrong. Please try again later.')
                );
            }
        }


        return parent::order($payment, $amount); // TODO: Change the autogenerated stub
    }

    /**
     * @param \Magento\Payment\Model\InfoInterface|\Magento\Sales\Model\Order\Payment $payment
     * @param float $amount
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function authorize(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        $this->_debug("authorize action");
        $isSubscriptionOrder = $this->isSubscriptionOrder($payment);
        //if not check 3ds
        if ($payment->getAdditionalInformation(Constant::ADDITIONAL_THREEDS) == 'false') {
            //payment token of new tokenizer card
            $paymentToken = $payment->getAdditionalInformation('payment_token'); // THIS IS THE TOKEN
        } else {
            //3ds card source now working with subscription
            $paymentToken = $payment->getAdditionalInformation('payment_token_secure');
        }
        $originCardId = $payment->getAdditionalInformation('payment_token');
        //saved card id
        $cardId = $payment->getAdditionalInformation('card_id');
        /** @var \Magento\Sales\Model\Order $order */
        $order = $payment->getOrder();
        $chargeModel = $this->_chargeFactory->create();
        $customerId = $order->getCustomerId();
        $multiply = 100;
        if ($this->_helper->isZeroDecimal($order->getBaseCurrencyCode())) {
            $multiply = 1;
        }
        $_amount = $amount * $multiply;
        // If the order does not contains subscription product
        if (!$isSubscriptionOrder) {
            try {
                //if logged in, pay with customer id
                if ($this->customerSession->isLoggedIn()) {
                    $request = [
                        "amount" => round($_amount),
                        "currency" => $order->getBaseCurrencyCode(),
                        'capture' => 'false',
                        "customer" => $this->getStripeCustomerId(),
                        "source" => $paymentToken,
                        "description" => "OrderId: " . $order->getIncrementId(),
                        "metadata" => [
                            'order_id' => $order->getIncrementId(),
                            'magento_customer_id' => $order->getCustomerId(),
                            'customer_email' => $order->getCustomerEmail()
                        ]
                    ];
                } else {
                    $request = [
                        "amount" => round($_amount),
                        "currency" => $order->getBaseCurrencyCode(),
                        'capture' => 'false',
                        "source" => $paymentToken,
                        "description" => "OrderId: " . $order->getIncrementId(),
                        "metadata" => [
                            'order_id' => $order->getIncrementId(),
                            'magento_customer_id' => $order->getCustomerId(),
                            'customer_email' => $order->getCustomerEmail()
                        ]
                    ];
                }
                if ($this->_config->sendMailCustomer()) {
                    $request['receipt_email'] = $order->getCustomerEmail();
                }
                $this->_debug($request);
                $url = 'https://api.stripe.com/v1/charges';
                $response = $this->_helper->sendRequest($request, $url, null);
                $this->_debug($response);
                if (isset($response['status']) && ($response['status'] == 'succeeded')) {
                    $order->setCanSendNewEmailFlag(true);
                    $threeDSecureStatus = $payment->getAdditionalInformation("three_d_secure");
                    $_saved = $payment->getAdditionalInformation('saved');
                    $_paymentToken = $payment->getAdditionalInformation('payment_token');
                    $cardData = json_decode($payment->getAdditionalInformation('raw_card_data'));
                    if ($_saved == "1") {
                        if (($this->customerSession->isLoggedIn())) {
                            $this->saveCard($_paymentToken, $cardData, $threeDSecureStatus);
                        }
                    }
                    $payment->setAmount($amount);
                    $payment->setTransactionId($response['id'])
                        ->setParentTransactionId($response['id'])
                        ->setIsTransactionClosed(false)
                        ->setShouldCloseParentTransaction(false)
                        ->setCcTransId($response['id'])
                        ->setLastTransId($response['id']);
                    //$payment->setParentTransactionId($response['id']);
                    $data = [
                        'charge_id' => $response['id'],
                        'order_id' => $order->getIncrementId(),
                        'customer_id' => $customerId,
                        'status' => 'authorized'
                    ];

                    $chargeModel->addData($data)->save();
                } else {
                    $message = isset($response['error']->message) ? $response['error']->message : "Something went wrong. Please try again later";
                    $this->_checkoutSession->setMessageError($message);
                    throw new \Magenest\Stripe\Exception\StripePaymentException(
                        __($message)
                    );
                }
            } catch (\Magenest\Stripe\Exception\StripePaymentException $e) {
                $this->stripeLogger->critical($e->getMessage());
                throw new \Magenest\Stripe\Exception\StripePaymentException(
                    __($e->getMessage())
                );
            } catch (\Exception $e) {
                $this->stripeLogger->critical($e->getMessage());
                throw new \Magento\Framework\Exception\LocalizedException(
                    __($e->getMessage())
                );
            }
        } else {
            // If the order contains a subscription product
            if ($this->_planId) {
                try {
                    $url = "https://api.stripe.com/v1/customers/" . $this->getStripeCustomerId();
                    $response = $this->_helper->sendRequest([
                        'default_source' => $originCardId
                    ], $url, "post");
                    //$cu = \Stripe\Customer::retrieve($this->getStripeCustomerId());
                    $this->_debug($response);
                    $this->changeCardToDefault($customerId, $originCardId);
                    $threeDAction = $payment->getAdditionalInformation(Constant::ADDITIONAL_THREEDS);
                    $subscriptionPlan = $this->subscriptionHelper->retrievePlan($this->_planId);
                    //3d secure off -> normal pay
                    if (isset($subscriptionPlan['id'])) {
                        //if trail period subscription
                        if (isset($subscriptionPlan['trial_period_days'])) {
                            // Create subsription
                            $subscriptionUrl = 'https://api.stripe.com/v1/subscriptions';
                            $subscriptionRequest = [
                                'plan' => $this->_planId,
                                'customer' => $this->getStripeCustomerId(),
                                "metadata" => [
                                    'order_id' => $order->getIncrementId(),
                                    'magento_customer_id' => $order->getCustomerId(),
                                    'customer_email' => $order->getCustomerEmail()
                                ]
                            ];

                            $response = $this->_helper->sendRequest($subscriptionRequest, $subscriptionUrl, 'post');
                            $this->_debug($response);
                        } else {
                            //non trial subsciprion
                            if ($threeDAction == 'false') {
                                //3ds off
                                // Create subsription normally
                                $subscriptionUrl = 'https://api.stripe.com/v1/subscriptions';
                                $subscriptionRequest = [
                                    'plan' => $this->_planId,
                                    'customer' => $this->getStripeCustomerId(),
                                    "metadata" => [
                                        'order_id' => $order->getIncrementId(),
                                        'magento_customer_id' => $order->getCustomerId(),
                                        'customer_email' => $order->getCustomerEmail()
                                    ]
                                ];

                                $response = $this->_helper->sendRequest($subscriptionRequest, $subscriptionUrl, 'post');
                                $this->_debug($response);
                            } else {
                                //3d secure on
                                //create first charge
                                $request = [
                                    "amount" => round($_amount),
                                    "currency" => $order->getBaseCurrencyCode(),
                                    'capture' => 'true',
                                    "customer" => $this->getStripeCustomerId(),
                                    "source" => $paymentToken,
                                    "description" => "OrderId: " . $order->getIncrementId(),
                                    "metadata" => [
                                        'order_id' => $order->getIncrementId(),
                                        'magento_customer_id' => $order->getCustomerId(),
                                        'customer_email' => $order->getCustomerEmail()
                                    ]
                                ];
                                if ($this->_config->sendMailCustomer()) {
                                    $request['receipt_email'] = $order->getCustomerEmail();
                                }
                                $url = 'https://api.stripe.com/v1/charges';
                                $response = $this->_helper->sendRequest($request, $url, null);
                                //create subscription with trial period
                                if (($response['status'] == 'succeeded') && (isset($response['status']))) {
                                    $trialDay = $this->subscriptionHelper->calTrialPeriodDay($this->_planId);
                                    $subscriptionUrl = 'https://api.stripe.com/v1/subscriptions';
                                    $subscriptionRequest = [
                                        'plan' => $this->_planId,
                                        'customer' => $this->getStripeCustomerId(),
                                        'trial_period_days' => $trialDay,
                                        "metadata" => [
                                            'order_id' => $order->getIncrementId(),
                                            'magento_customer_id' => $order->getCustomerId(),
                                            'customer_email' => $order->getCustomerEmail()
                                        ]
                                    ];

                                    $response = $this->_helper->sendRequest(
                                        $subscriptionRequest,
                                        $subscriptionUrl,
                                        'post'
                                    );
                                    $this->_debug($response);
                                } else {
                                    throw new \Magento\Framework\Exception\LocalizedException(
                                        __('Something went wrong. Please try again later.')
                                    );
                                }
                            }
                        }
                    } else {
                        $message = 'Subscription create error';
                        $this->_checkoutSession->setMessageError("Subscription create error");
                        throw new \Magento\Framework\Exception\LocalizedException(
                            __($message)
                        );
                    }

                    $threeDSecureStatus = $payment->getAdditionalInformation("three_d_secure");
                    $_saved = $payment->getAdditionalInformation('saved');
                    $_paymentToken = $payment->getAdditionalInformation('payment_token');
                    $cardData = json_decode($payment->getAdditionalInformation('raw_card_data'));
                    if ($_saved == "1") {
                        if (($this->customerSession->isLoggedIn())) {
                            $this->saveCard($_paymentToken, $cardData, $threeDSecureStatus);
                        }
                    }
                    //update payment

                    $payment->setAmount($amount);
                    $payment->setTransactionId($response['id'])
                        ->setParentTransactionId($response['id'])
                        ->setIsTransactionClosed(false)
                        ->setShouldCloseParentTransaction(false)
                        ->setCcTransId($response['id'])
                        ->setLastTransId($response['id']);

                    $subsObj = $this->_subscriptionFactory->create();

                    $subsData = [
                        'order_id' => $order->getIncrementId(),
                        'subscription_id' => $response['id'],
                        'period_start' => date("Y-m-d H:i:s", $response['current_period_start']),
                        'period_end' => date("Y-m-d H:i:s", $response['current_period_end']),
                        'customer_id' => $customerId,
                        'status' => $response['status']
                    ];

                    $this->_totalCycles = $this->getTotalCycle($payment);

                    if ($this->_totalCycles) {
                        $subsData['total_cycles'] = $this->_totalCycles;
                    }

                    $subsObj->addData($subsData)->save();
                } catch (\Exception $e) {
                    $this->stripeLogger->critical($e->getMessage());
                    throw new \Magento\Framework\Exception\LocalizedException(
                        __('Something went wrong. Please try again later.')
                    );
                }
            } else {
                throw new \Magento\Framework\Exception\LocalizedException(
                    __('Plan ID not found.')
                );
            }
        }

        return $this;
    }

    public function isSubscriptionOrder($payment)
    {
        /** @var \Magento\Sales\Model\Order $order */
        $order = $payment->getOrder();

        $items = $order->getItems();

        foreach ($items as $item) {
            $buyInfo = $item->getBuyRequest();
            $options = $buyInfo->getAdditionalOptions();
            if ($options) {
                foreach ($options as $key => $value) {
                    if ($key == 'Plan ID') {
                        $this->_planId = $value;

                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function getTotalCycle($payment)
    {
        /** @var \Magento\Sales\Model\Order $order */
        $order = $payment->getOrder();

        $items = $order->getItems();

        foreach ($items as $item) {
            $buyInfo = $item->getBuyRequest();
            $options = $buyInfo->getAdditionalOptions();
            if ($options) {
                foreach ($options as $key => $value) {
                    if ($key == 'Total Cycles') {
                        return $value;
                    }
                }
            }
        }

        return 0;
    }

    public function capture(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        try {
            $this->_debug("capture action");
            /** @var \Magenest\Stripe\Model\Charge $chargeModel */
            $chargeModel = $this->_chargeFactory->create();
            /** @var \Magento\Sales\Model\Order $order */
            $order = $payment->getOrder();
            $orderId = $order->getIncrementId();
            if ($this->_appState->getAreaCode() == 'adminhtml') {
                if ($this->isSubscriptionOrder($payment)) {
                    throw new \Magento\Framework\Exception\LocalizedException(
                        __('Can not capture subscription order')
                    );
                }
            }
            $multiply = 100;
            if ($this->_helper->isZeroDecimal($order->getBaseCurrencyCode())) {
                $multiply = 1;
            }
            $_amount = $amount * $multiply;
            $charge = $chargeModel->getCollection()->addFieldToFilter('order_id', $orderId)->getFirstItem();
            if (!$charge->getId()) {
                $this->authorize($payment, $amount);
                $charge = $chargeModel->getCollection()->addFieldToFilter('order_id', $orderId)->getFirstItem();
            }

            if ($charge->getId()) {
                $status = $charge->getData('status');
                if ($status != 'captured') {
                    $url = 'https://api.stripe.com/v1/charges/' . $charge->getData('charge_id') . '/capture';
                    $request = [
                        'amount' => round($_amount)
                    ];
                    if ($this->_config->sendMailCustomer()) {
                        $request['receipt_email'] = $order->getCustomerEmail();
                    }
                    $response = $this->_helper->sendRequest($request, $url, null);
                    $this->_debug($response);
                    if ($response['status'] == 'succeeded') {
                        $payment->setStatus(\Magento\Payment\Model\Method\AbstractMethod::STATUS_SUCCESS)
                            ->setShouldCloseParentTransaction(0)
                            ->setIsTransactionClosed(1);
                        $payment->setTransactionId($response['id']);
                        $payment->setParentTransactionId($charge->getData('charge_id'));
                        $data = [
                            'status' => 'captured'
                        ];

                        $charge->addData($data)->save();
                    } else {
                        throw new \Magenest\Stripe\Exception\StripePaymentException(
                            __('Capture fail')
                        );
                    }
                } else {
                    throw new \Magenest\Stripe\Exception\StripeAlreadyCaptureException(
                        __('The order has already been captured.')
                    );
                }
            }
        } catch (\Magenest\Stripe\Exception\StripePaymentException $e) {
            $this->stripeLogger->critical($e->getMessage());
            throw new \Magenest\Stripe\Exception\StripePaymentException(
                __($e->getMessage())
            );
        } catch (\Magenest\Stripe\Exception\StripeAlreadyCaptureException $e) {
            $this->_messageManager->addErrorMessage(__('The order has already been captured.'));
            $this->stripeLogger->debug($e->getMessage());
            throw new \Magenest\Stripe\Exception\StripeAlreadyCaptureException(
                __($e->getMessage())
            );
        } catch (\Exception $e) {
            $this->stripeLogger->debug($e->getMessage());
            throw new \Magento\Framework\Exception\LocalizedException(
                __($e->getMessage())
            );
        }
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
            $multiply = 100;
            if ($this->_helper->isZeroDecimal($order->getBaseCurrencyCode())) {
                $multiply = 1;
            }
            $_amount = $amount * $multiply;
            $charge = $chargeModel->getCollection()->addFieldToFilter('order_id', $orderId)->getFirstItem();
            if ($charge->getId()) {
                $url = 'https://api.stripe.com/v1/refunds';

                $request = [
                    'charge' => $charge->getData('charge_id'),
                    'amount' => round($_amount)
                ];

                $response = $this->_helper->sendRequest($request, $url, null);
                $this->_debug($response);
                if ($response['status'] == 'succeeded') {
                    $payment->setTransactionId($response['id']);
                    $payment->setParentTransactionId($charge->getData('charge_id'));
                    $payment->setShouldCloseParentTransaction(1);
                } else {
                    if ($response['status'] == 'pending') {
                        $payment->setTransactionId($response['id']);
                        $payment->setParentTransactionId($charge->getData('charge_id'));
                        $payment->setShouldCloseParentTransaction(1);
                        $this->_messageManager->addWarningMessage("Refund pending");
                    } else {
                        throw new \Magento\Framework\Exception\LocalizedException(
                            __('Something went wrong while refunding. Please try again.')
                        );
                    }
                }
            } else {
                throw new \Magento\Framework\Exception\LocalizedException(
                    __('Charge doesn\'t exist. Please try again later.')
                );
            }
        } catch (\Exception $e) {
            $this->stripeLogger->critical($e->getMessage());
            throw new \Magento\Framework\Exception\LocalizedException(
                __('Charge doesn\'t exist. Please try again later.')
            );
        }

        return $this;
    }

    public function changeCardToDefault($customerId, $cardId)
    {
        try {
            $cardModel = $this->_cardFactory->create();
            $check = $this->_cardFactory->create()
                ->getCollection()
                ->addFieldToFilter('card_id', $cardId)
                ->getFirstItem();
            if ($check->getId()) {
                $collections = $cardModel->getCollection()
                    ->addFieldToFilter("magento_customer_id", $customerId);

                foreach ($collections as $collection) {
                    if ($collection->getData()['status'] === "default") {
                        $collection->setData("status", "active");
                        $collection->save();
                    }
                }
                $check->setData("status", "default");
                $check->save();
                $cardModel->save();
            }
        } catch (\Exception $e) {
            return false;
        }

        return true;
    }

    /**
     * Create a stripe customer object
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param $token
     * @return mixed
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function createCustomer()
    {
        try {
            $customerModel = $this->_customerFactory->create();

//            $cu = \Stripe\Customer::create(array(
//                "description" => $this->customerSession->getCustomer()->getEmail(),
//                "email" => $this->customerSession->getCustomer()->getEmail()
//            ));
            $url = 'https://api.stripe.com/v1/customers';

            $request = [
                "description" => $this->customerSession->getCustomer()->getEmail(),
                "email" => $this->customerSession->getCustomer()->getEmail()
            ];

            $cu = $this->_helper->sendRequest($request, $url, null);
            $customerModel->addData([
                'magento_customer_id' => $this->customerSession->getCustomerId(),
                'stripe_customer_id' => $cu['id']
            ])->save();
            $stripeCustomerId = $cu['id'];
        } catch (\Exception $e) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __('Create customer fail')
            );
        }

        return $stripeCustomerId;
    }

    public function void(\Magento\Payment\Model\InfoInterface $payment)
    {
        $this->_debug("void action");
        /** @var \Magenest\Stripe\Model\Charge $chargeModel */
        $chargeModel = $this->_chargeFactory->create();

        /** @var \Magento\Sales\Model\Order $order */
        $order = $payment->getOrder();
        $orderId = $order->getIncrementId();

        $charge = $chargeModel->getCollection()->addFieldToFilter('order_id', $orderId)->getFirstItem();
        if ($charge->getId()) {
            $url = 'https://api.stripe.com/v1/refunds';

            $request = [
                'charge' => $charge->getData('charge_id')
            ];

            $response = $this->_helper->sendRequest($request, $url, null);
            $this->_debug($response);
            if ($response['status'] == 'succeeded') {
                $payment->setTransactionId($response['id']);
                $payment->setIsTransactionClosed(true);

                $data = [
                    'status' => 'cancelled'
                ];

                $charge->addData($data)->save();
            } else {
                throw new \Magento\Framework\Exception\LocalizedException(
                    __('Something went wrong while cancelling. Please try again.')
                );
            }
        } else {
            throw new \Magento\Framework\Exception\LocalizedException(
                __('Charge doesn\'t exist. Please try again later.')
            );
        }

        return $this;
    }

    public function cancel(\Magento\Payment\Model\InfoInterface $payment)
    {
        $this->void($payment);
    }

    public function canUseForCurrency($currencyCode)
    {
        if (!in_array($currencyCode, $this->_supportedCurrencyCodes)) {
            return false;
        }

        return true;
    }

    public function saveCard($sourceId, $cardData, $threeDSecureStatus = null)
    {
        $expMonth = $cardData->exp_month;
        $expYear = $cardData->exp_year;
        $brand = $cardData->brand;
        $cardCountry = $cardData->country;
        $cardLast4 = $cardData->last4;
        $cardModel = $this->_cardFactory->create();
        $data = [
            'magento_customer_id' => $this->customerSession->getCustomerId(),
            'card_id' => $sourceId,
            'brand' => $brand,
            'last4' => (string)$cardLast4,
            'exp_month' => (string)$expMonth,
            'exp_year' => (string)$expYear,
            'status' => "active",
            'threed_secure' => $threeDSecureStatus
        ];

        $cardModel->addData($data)->save();
    }

    public function addSourceToCustomer($stripeCustomerId, $source)
    {
        $request = [
            'source' => $source
        ];
        $url = 'https://api.stripe.com/v1/customers/' . $stripeCustomerId . '/sources';
        $response = $this->_helper->sendRequest($request, $url, 'post');
    }

    public function getStripeCustomerId()
    {
        $customer = $this->_customerFactory->create()->getCollection()
            ->addFieldToFilter('magento_customer_id', $this->customerSession->getCustomerId())
            ->getFirstItem();

        /**
         * if customer registered and have data in db, get stripe cus_id
         * else: create customer data
         */
        return $customer->getData('stripe_customer_id');
    }

    public function getCardInfo($cardId)
    {
        return $this->_cardFactory->create()->getCollection()
            ->addFieldToFilter('card_id', $cardId)
            ->getFirstItem()
            ->getData();
    }

    /**
     * @param array|string $debugData
     */
    protected function _debug($debugData)
    {
        $debugOn = $this->getDebugFlag();
        if ($debugOn === true) {
            $this->stripeLogger->debug(var_export($debugData, true));
        }
    }
}
