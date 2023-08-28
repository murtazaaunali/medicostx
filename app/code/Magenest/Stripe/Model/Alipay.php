<?php
/**
 * Created by PhpStorm.
 * User: hiennq
 * Date: 26/12/2017
 * Time: 17:59
 */

namespace Magenest\Stripe\Model;

use Magenest\Stripe\Helper\Constant;
use Magento\Payment\Model\Method\AbstractMethod;

class Alipay extends AbstractMethod
{
    const CODE = 'magenest_stripe_alipay';
    protected $_code = self::CODE;

    protected $_isGateway = true;
    protected $_canAuthorize = false;
    protected $_canCapture = true;
    protected $_canCapturePartial = true;
    protected $_canCaptureOnce = true;
    protected $_canVoid = false;
    protected $_canUseInternal = false;
    protected $_canUseCheckout = true;
    protected $_canRefund = true;
    protected $_canRefundInvoicePartial = true;

    protected $_chargeFactory;
    protected $_helper;

    protected $_isInitializeNeeded = false;
    protected $_canOrder = true;

    protected $stripeLogger;
    protected $_messageManager;

    public function __construct(
        \Magenest\Stripe\Helper\Data $dataHelper,
        \Magento\Framework\Message\ManagerInterface $messageManager,
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
        $this->stripeLogger = $stripeLogger;
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
        return "authorize_capture";
    }

    public function validate()
    {
        return parent::validate();
    }

    public function assignData(\Magento\Framework\DataObject $data)
    {
        parent::assignData($data);
        return $this;
    }

    public function initialize($paymentAction, $stateObject)
    {
        /**
         * @var \Magento\Sales\Model\Order $order
         * @var \Magento\Sales\Model\Order\Payment $payment
         */
        $payment = $this->getInfoInstance();
        $order = $payment->getOrder();
        return parent::initialize($paymentAction, $stateObject);
    }

    /**
     * @param \Magento\Payment\Model\InfoInterface|\Magento\Sales\Model\Order\Payment $payment
     * @param float $amount
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function capture(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        $this->_debug("capture order: ". $payment->getOrder()->getIncrementId());
        $transId = $payment->getAdditionalInformation("trans_id");
        $chargeId = $payment->getAdditionalInformation("charge_id");
        $payment->setTransactionId($chargeId)
            ->setParentTransactionId($chargeId)
            ->setLastTransId($chargeId);
        $payment->setIsTransactionClosed(1);
        $payment->setShouldCloseParentTransaction(1);
        return parent::capture($payment, $amount);
    }

    /**
     * @param \Magento\Payment\Model\InfoInterface|\Magento\Sales\Model\Order\Payment $payment
     * @param float $amount
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function refund(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        $order = $payment->getOrder();
        $chargeId = $payment->getAdditionalInformation("charge_id");
        $this->_debug("refund order: ". $order->getIncrementId());
        $multiply = 100;
        if ($this->_helper->isZeroDecimal($order->getBaseCurrencyCode())) {
            $multiply = 1;
        }
        $_amount = $amount * $multiply;
        $request = [
            'charge' => $chargeId,
            'amount' => round($_amount)
        ];

        $response = $this->_helper->sendRequest($request, Constant::REFUND_ENDPOINT, null);
        $this->_debug($response);
        if (isset($response['error'])) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __($response['error']->message)
            );
        }
        $payment->setTransactionId($response['id']);
        $payment->setParentTransactionId($response['id']);
        $payment->setShouldCloseParentTransaction(1);
        if (isset($response['status'])) {
            if ($response['status'] == 'succeeded') {
                //refund success
                $this->_messageManager->addSuccessMessage("Refund action: Success");
            }
            if ($response['status'] == 'pending') {
                //refund pending
                $this->_messageManager->addNoticeMessage("Refund action: Pending");
            }
        }
        return parent::refund($payment, $amount);
    }

    public function void(\Magento\Payment\Model\InfoInterface $payment)
    {
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
