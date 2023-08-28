<?php
/**
 * Created by PhpStorm.
 * User: hiennq
 * Date: 27/12/2017
 * Time: 11:32
 */

namespace Magenest\Stripe\Controller\Checkout;

use Magenest\Stripe\Helper\Constant;
use Magento\Framework\App\Action\Context;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Controller\ResultFactory;

class AlipaySource extends \Magento\Framework\App\Action\Action
{
    protected $_checkoutSession;
    protected $_chargeFactory;
    protected $invoiceSender;
    protected $transactionFactory;
    protected $jsonFactory;
    protected $stripeConfig;
    protected $storeManagerInterface;
    protected $stripeLogger;
    protected $_formKeyValidator;
    protected $stripeHelper;
    private $billingAddressPersister;


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
        \Magento\Framework\Data\Form\FormKey\Validator $formKeyValidator,
        \Magenest\Stripe\Helper\Data $stripeHelper,
        \Magento\Quote\Model\Quote\Address\BillingAddressPersister $billingAddressPersister
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
        $this->_formKeyValidator = $formKeyValidator;
        $this->stripeHelper = $stripeHelper;
        $this->billingAddressPersister = $billingAddressPersister;
    }

    public function execute()
    {
        $this->stripeLogger->debug("create alipay source");
        $result = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        if (!$this->_formKeyValidator->validate($this->getRequest())) {
            return $result->setData([
                'error' => true,
                'message' => "Invalid Form Key"
            ]);
        }
        try {
            $baseUrl = $this->storeManagerInterface->getStore()->getBaseUrl();
            $quote = $this->_checkoutSession->getQuote();
            $billingAddress = json_decode($this->getRequest()->getParam('billingAddress'), true);

            if ($billingAddress) {
                $billing = $quote->getBillingAddress();
                $this->billingAddressPersister->save($quote, $billing);
            }

            $quote->setPaymentMethod('magenest_stripe_alipay');
            $quote->save();
            $quote->getPayment()->importData(['method' => 'magenest_stripe_alipay']);
            $grandTotal = $quote->getBaseGrandTotal();
            $baseCurrency = strtolower($quote->getBaseCurrencyCode());
            if (!$this->stripeHelper->isZeroDecimal($baseCurrency)) {
                $grandTotal = $grandTotal*100;
            }
            $request = [
                "type" => "alipay",
                "currency" =>$baseCurrency,
                "redirect" => [
                    "return_url" => $baseUrl."stripe/checkout/alipayResponse/"
                ],
                "amount" => round($grandTotal),
            ];

            $this->stripeLogger->debug(var_export($request, true));
            $response = $this->stripeHelper->sendRequest($request, Constant::SOURCE_ENDPOINT, null);
            if (isset($response['error'])) {
                $result->setData([
                    'error' => true,
                    'err_response' => json_encode($response['error'])
                ]);
                return $result;
            }
            $redirectUrl = $response['redirect']->url;
            $sourceId = $response['id'];
            $clientSecret = $response['client_secret'];
            $this->_checkoutSession->setClientSecret($clientSecret);
            $this->stripeLogger->debug(var_export($response, true));
            $data = [
                'success' => true,
                'error' => false,
                'redirect_url' => $redirectUrl
            ];
            $result->setData($data);
        } catch (\Exception $e) {
            $result->setData(['status' => 'error' ,
                'code' => 'error',
                'message' => $e->getMessage()
            ]);
        }

        return $result;
    }
}
