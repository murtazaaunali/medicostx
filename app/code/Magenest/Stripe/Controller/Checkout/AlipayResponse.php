<?php
/**
 * Created by PhpStorm.
 * User: hiennq
 * Date: 26/12/2017
 * Time: 18:44
 */

namespace Magenest\Stripe\Controller\Checkout;

use Magenest\Stripe\Helper\Constant;
use Magento\Framework\App\Action\Context;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;

class AlipayResponse extends \Magento\Framework\App\Action\Action
{
    protected $_checkoutSession;
    protected $_chargeFactory;
    protected $invoiceSender;
    protected $transactionFactory;
    protected $jsonFactory;
    protected $stripeConfig;
    protected $storeManagerInterface;
    protected $stripeLogger;
    protected $orderSender;
    protected $_customerSession;
    protected $stripeHelper;

    /**
     * @var \Magento\Quote\Model\QuoteManagement
     */
    protected $quoteManagement;

    public function __construct(
        Context $context,
        CheckoutSession $session,
        \Magenest\Stripe\Model\ChargeFactory $chargeFactory,
        \Magento\Sales\Model\Order\Email\Sender\InvoiceSender $invoiceSender,
        \Magento\Framework\DB\TransactionFactory $transactionFactory,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        \Magenest\Stripe\Helper\Config $stripeConfig,
        \Magento\Store\Model\StoreManagerInterface $storeManagerInterface,
        \Magenest\Stripe\Helper\Logger $stripeLogger,
        OrderSender $orderSender,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Quote\Model\QuoteManagement $quoteManagement,
        \Magenest\Stripe\Helper\Data $stripeHelper
    ) {
        parent::__construct($context);
        $this->_checkoutSession = $session;
        $this->_chargeFactory = $chargeFactory;
        $this->invoiceSender = $invoiceSender;
        $this->transactionFactory = $transactionFactory;
        $this->jsonFactory = $resultJsonFactory;
        $this->stripeConfig = $stripeConfig;
        $this->storeManagerInterface = $storeManagerInterface;
        $this->stripeLogger = $stripeLogger;
        $this->orderSender = $orderSender;
        $this->_customerSession = $customerSession;
        $this->quoteManagement = $quoteManagement;
        $this->stripeHelper = $stripeHelper;
    }

    public function execute()
    {
        try {
            $this->_debug("----begin stripe alipay processing----");
            $quote = $this->_checkoutSession->getQuote();
            $response = $this->getRequest()->getParams();
            $this->_debug($response);
            $sourceId = $this->getRequest()->getParam('source');
            $clientSecretResponse = $this->getRequest()->getParam('client_secret');
            $clientSecret = $this->_checkoutSession->getClientSecret();
            if ($clientSecret != $clientSecretResponse) {
                //fail
                $this->_debug("validate secret key error");
                $this->_checkoutSession->unsClientSecret();
                $this->messageManager->addErrorMessage("Data validate error");
                return $this->_redirect('checkout/cart');
            }
            //place order
            $source = $this->retrieveSource($sourceId);
            if (isset($source['error'])) {
                $this->_debug($source);
                $this->_checkoutSession->unsClientSecret();
                $errMsg = $this->getResponseError($source);
                $this->messageManager->addErrorMessage($errMsg);
                return $this->_redirect('checkout/cart');
            }
            if ($source['status'] == 'chargeable') {
                $this->_debug($source);
                $grandTotal = $quote->getBaseGrandTotal();
                $baseCurrency = strtolower($quote->getBaseCurrencyCode());
                if (!$this->stripeHelper->isZeroDecimal($baseCurrency)) {
                    $grandTotal = $grandTotal*100;
                }
                $request = [
                    'amount'=>$grandTotal,
                    'currency' => $baseCurrency,
                    'source' => $sourceId
                ];
                $response = $this->stripeHelper->sendRequest($request, Constant::CHARGE_ENDPOINT);
                $this->_debug($response);
                if (isset($response['status']) && ($response['status']=='succeeded')) {
                    if ($response['paid'] == 'true') {
                        //craete order
                        $quote->getPayment()->setMethod('magenest_stripe_alipay');
                        if (!$this->_customerSession->isLoggedIn()) {
                            $quote->setCheckoutMethod(\Magento\Quote\Model\QuoteManagement::METHOD_GUEST);
                            $quote->setCustomerId(null);
                            $email = $quote->getBillingAddress()->getEmail() ;
                            $quote->setCustomerEmail($email);
                            $quote->setCustomerIsGuest(true);
                            $quote->setCustomerGroupId(\Magento\Customer\Api\Data\GroupInterface::NOT_LOGGED_IN_ID);
                        }
                        $payment = $quote->getPayment();
                        $transactionId = isset($response['balance_transaction'])?$response['balance_transaction']:"";
                        $chargeId = isset($response['id'])?$response['id']:"";
                        $payment->setAdditionalInformation("trans_id", $transactionId);
                        $payment->setAdditionalInformation("charge_id", $chargeId);

                        $order = $this->quoteManagement->submit($quote);
                        $order->setEmailSent(0);

                        if ($order->getCanSendNewEmailFlag()) {
                            try {
                                $this->orderSender->send($order);
                            } catch (\Exception $e) {
                                $this->stripeLogger->critical($e->getMessage());
                            }
                        }

                        $order->save();
                        $this->_checkoutSession->setLastQuoteId($quote->getId());
                        $this->_checkoutSession->setLastSuccessQuoteId($quote->getId());
                        $this->_checkoutSession->setLastOrderId($order->getId());
                        $this->_checkoutSession->setLastRealOrderId($order->getIncrementId());
                        $this->_checkoutSession->setLastOrderStatus($order->getStatus());
                        $this->_checkoutSession->unsClientSecret();
                        $increment_id = $order->getRealOrderId();
                        $this->messageManager->addSuccessMessage("Your order (ID: $increment_id) was successful!");
                        return $this->_redirect('checkout/onepage/success');
                    }
                } else {
                    $this->_debug($source);
                    $this->_checkoutSession->unsClientSecret();
                    $errMsg = $this->getResponseError($response);
                    $this->messageManager->addErrorMessage($errMsg);
                    return $this->_redirect('checkout/cart');
                }
            } else {
                if ($source['status'] == 'failed') {
                    $this->_checkoutSession->unsClientSecret();
                    $this->_checkoutSession->clearQuote();
                    $this->messageManager->addErrorMessage("Payment was decline");
                    return $this->_redirect('checkout/cart');
                }
            }
            return $this->_redirect('checkout/cart');
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage("Payment Exception");
            $this->_debug($e->getMessage());
            return $this->_redirect('checkout/cart');
        }
    }

    private function retrieveSource($sourceId)
    {
        $url = Constant::SOURCE_ENDPOINT."/".$sourceId;
        $response = $this->stripeHelper->sendRequest(false, $url);
        return $response;
    }

    private function getResponseError($response)
    {
        if (isset($response['error'])) {
            $err = $response['error'];
            if (isset($err->message)) {
                return $err->message;
            }
            return "Payment error";
        } else {
            return "Payment error exception";
        }
    }

    /**
     * @param array|string $debugData
     */
    private function _debug($debugData)
    {
        $this->stripeLogger->debug(var_export($debugData, true));
    }
}
