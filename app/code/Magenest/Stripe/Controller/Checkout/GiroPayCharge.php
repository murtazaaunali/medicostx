<?php
/**
 * Created by PhpStorm.
 * User: magenest
 * Date: 30/05/2017
 * Time: 20:43
 */

namespace Magenest\Stripe\Controller\Checkout;

use Magenest\Stripe\Helper\Constant;
use Magento\Framework\App\Action\Context;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Session as CustomerSession;

class GiroPayCharge extends \Magento\Framework\App\Action\Action
{
    /**
     * @var CheckoutSession
     */
    protected $_checkoutSession;


    protected $_customerSession;
    /**
     * @var \Magenest\Stripe\Helper\Config
     */
    protected $stripeConfig;
    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManagerInterface;
    /**
     * @var \Magenest\Stripe\Helper\Logger
     */
    protected $stripeLogger;
    /**
     * @var \Magento\Quote\Model\QuoteManagement
     */
    protected $quoteManagement;
    /**
     * @var \Magenest\Stripe\Helper\Data
     */
    protected $_helper;

    public function __construct(
        Context $context,
        CheckoutSession $checkoutSession,
        CustomerSession $customerSession,
        \Magenest\Stripe\Helper\Config $stripeConfig,
        \Magento\Store\Model\StoreManagerInterface $storeManagerInterface,
        \Magenest\Stripe\Helper\Logger $stripeLogger,
        \Magenest\Stripe\Helper\Data $dataHelper,
        \Magento\Quote\Model\QuoteManagement $quoteManagement
    ) {
        parent::__construct($context);
        $this->_helper = $dataHelper;
        $this->quoteManagement = $quoteManagement;
        $this->_checkoutSession = $checkoutSession;
        $this->_customerSession = $customerSession;
        $this->stripeConfig = $stripeConfig;
        $this->storeManagerInterface = $storeManagerInterface;
        $this->stripeLogger = $stripeLogger;
    }

    /**
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\ResultInterface|void
     * @throws \Exception
     */
    public function execute()
    {
        try {
            $response = $this->getRequest()->getParams();
            $this->stripeLogger->addDebug('----------goripay_response-----------');
            $this->stripeLogger->addDebug(print_r($response, true));
            $this->stripeLogger->addDebug('----------end goripay_response-----------');
            $quote = $this->_checkoutSession->getQuote();
            if ($this->checkValid($quote, $response)) {
                $quote->getPayment()->setMethod('magenest_stripe_giropay');
                if (!$this->_customerSession->isLoggedIn()) {
                    $quote->setCheckoutMethod(\Magento\Quote\Model\QuoteManagement::METHOD_GUEST);
                    $quote->setCustomerId(null);
                    $email = empty($quote->getBillingAddress()->getEmail()) ? $this->_checkoutSession->getEmailForCheck() : $quote->getBillingAddress()->getEmail() ;
                    $quote->setCustomerEmail($email);
                    $quote->setCustomerIsGuest(true);
                    $quote->setCustomerGroupId(\Magento\Customer\Api\Data\GroupInterface::NOT_LOGGED_IN_ID);
                }
                $quote->getPayment()->setAdditionalInformation("source", $this->getRequest()->getParam('source'));
                $order = $this->quoteManagement->submit($quote);
                $objectManager = \Magento\Framework\App\ObjectManager::getInstance();

                $emailSender = $objectManager->create('\Magento\Sales\Model\Order\Email\Sender\OrderSender');
                $emailSender->send($order);
                $order->save();
                $this->_checkoutSession->setLastQuoteId($quote->getId());
                $this->_checkoutSession->setLastSuccessQuoteId($quote->getId());
                $this->_checkoutSession->setLastOrderId($order->getId());
                $this->_checkoutSession->setLastRealOrderId($order->getIncrementId());
                $this->_checkoutSession->setLastOrderStatus($order->getStatus());
                $increment_id = $order->getRealOrderId();
                $this->messageManager->addSuccessMessage("Your order (ID: $increment_id) was successful!");
                return $this->_redirect('checkout/onepage/success');
            }
        } catch (\Exception $e) {
            $this->stripeLogger->critical($e->getMessage());
            $this->messageManager->addErrorMessage($e->getMessage());
            $this->_redirect('checkout/cart');
        }
    }

    /**
     * @param \Magento\Quote\Model\Quote $quote
     * @param array $response
     * @throws \Exception
     */
    private function checkValid($quote, $response)
    {
        $source = $this->retrieveSource($quote, $response['source']);
        $clientSecret = $source['client_secret'];
        $clientSecretConfirm = $response['client_secret'];

        if ($clientSecret != $clientSecretConfirm) {
            throw new \Exception(
                __('Secret hash validate fail.')
            );
        }
        return true;
    }

    /**
     * @param $cardSource
     * @return array
     * @throws \Exception
     */
    private function retrieveSource($quote, $cardSource)
    {
//        \Stripe\Stripe::setApiKey($this->stripeConfig->getSecretKey());
        $urlReq = Constant::SOURCE_ENDPOINT."/".$cardSource;
        $sourceResponse = $this->_helper->sendRequest(false, $urlReq);
        $this->stripeLogger->addDebug('----------card_source-----------');
        $this->stripeLogger->addDebug(print_r($sourceResponse, true));
        $this->stripeLogger->addDebug('----------end card_source-----------');
        if (isset($sourceResponse['error'])) {
            throw new \Exception(
                __('Unable to charge this source. Please try again.')
            );
        }
        if ($sourceResponse['status'] !== 'chargeable') {
            throw new \Exception(
                __('Unable to charge this source. Please try again.')
            );
        }
        $this->checkAmount($quote, $sourceResponse['amount']);
        return $sourceResponse;
    }

    /**
     * @param \Magento\Quote\Model\Quote $quote
     * @param $responseAmount
     * @throws \Exception
     */
    private function checkAmount($quote, $responseAmount)
    {
        $multiply = 100;
        if ($this->_helper->isZeroDecimal($quote->getBaseCurrencyCode())) {
            $multiply = 1;
        }
        $amount = $this->_checkoutSession->getBaseGrandTotalForCheck() * $multiply;
        if ($amount != $responseAmount) {
            throw new \Exception(
                __('Quote is not valid.')
            );
        }
    }
}
