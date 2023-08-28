<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 14/11/2017
 * Time: 10:14
 */

namespace Magenest\Stripe\Controller\Checkout;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\ResponseInterface;
use Magento\Checkout\Model\Session;
use Magenest\Stripe\Helper\Config;
use Magento\Framework\Controller\ResultFactory;

class Charge extends Action
{
    protected $_checkoutSession;

    protected $_config;

    protected $stripeHelper;

    protected $_chargeFactory;

    protected $stripeLogger;

    protected $_formKeyValidator;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        Session $checkoutSession,
        Config $config,
        \Magenest\Stripe\Helper\Data $stripeHelper,
        \Magenest\Stripe\Model\ChargeFactory $chargeFactory,
        \Magenest\Stripe\Helper\Logger $stripeLogger,
        \Magento\Framework\Data\Form\FormKey\Validator $formKeyValidator
    ) {
        parent::__construct($context);
        $this->_checkoutSession = $checkoutSession;
        $this->_config = $config;
        $this->stripeHelper = $stripeHelper;
        $this->_chargeFactory = $chargeFactory;
        $this->stripeLogger = $stripeLogger;
        $this->_formKeyValidator = $formKeyValidator;
    }
    /**
     * Execute action based on request and return result
     *
     * Note: Request will be added as operation argument in future
     *
     * @return \Magento\Framework\Controller\ResultInterface|ResponseInterface
     * @throws \Magento\Framework\Exception\NotFoundException
     */
    public function execute()
    {
        //$this->stripeLogger->debug(var_export($_REQUEST, true));
        $this->stripeLogger->debug("apple charging");
        $paymentToken = $this->getRequest()->getParam('token');

        $result = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        if (!$this->_formKeyValidator->validate($this->getRequest())) {
            return $result->setData([
                'error' => true,
                'message' => "Invalid Form Key"
            ]);
        }
        if (!$paymentToken) {
            return $result->setData([
                'status' => 'error' ,
                'code' => 'error',
                'error' => true
            ]);
        }
        try {
            $quote = $this->_checkoutSession->getQuote();

            $grandTotal = $quote->getBaseGrandTotal();
            $baseCurrency = $quote->getBaseCurrencyCode();
            $customerId = $quote->getCustomerId();
            if (!$this->stripeHelper->isZeroDecimal($baseCurrency)) {
                $grandTotal = $grandTotal*100;
            }
            $request = [
                "amount" => round($grandTotal),
                "currency" =>$baseCurrency,
                'capture' => 'true',
                "source" => $paymentToken,
            ];

            $this->stripeLogger->debug(var_export($request, true));
            $url = 'https://api.stripe.com/v1/charges';
            $response = $this->stripeHelper->sendRequest($request, $url, null);
            $this->stripeLogger->debug(var_export($response, true));
            $data = [];
            if (isset($response['status']) && $response['status'] == 'succeeded') {
                $data = [
                    'code' => "success",
                    'charge_id' => $response['id'],
                    'quote_id' => $quote->getId(),
                    'customer_id' => $customerId,
                    'status' => 'captured',
                    'success' => true,
                    'error' => false
                ];
            } else {
                if (isset($response['error'])) {
                    $data['message'] = $response['error']->message;
                    $data['type'] = $response['error']->type;
                    $data['code'] = 'error';
                    $data['error'] = true;
                } else {
                    $data['message'] = _("Payment exception");
                    $data['code'] = 'error';
                    $data['error'] = true;
                }
            }
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
