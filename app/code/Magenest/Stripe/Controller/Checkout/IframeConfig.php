<?php
/**
 * Created by PhpStorm.
 * User: joel
 * Date: 02/01/2017
 * Time: 18:18
 */

namespace Magenest\Stripe\Controller\Checkout;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Checkout\Model\Session;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Webapi\Exception;
use Magento\Framework\Controller\ResultInterface;
use Magenest\Stripe\Helper\Config;

class IframeConfig extends Action
{
    protected $_checkoutSession;

    protected $_orderRepository;

    protected $_config;

    public function __construct(
        Context $context,
        Session $checkoutSession,
        OrderRepositoryInterface $orderRepository,
        Config $config,
        \Magenest\Stripe\Helper\Data $stripeHelper
    ) {
        parent::__construct($context);
        $this->_checkoutSession = $checkoutSession;
        $this->_orderRepository = $orderRepository;
        $this->_config = $config;
        $this->stripeHelper = $stripeHelper;
    }

    public function execute()
    {
        $result = $this->resultFactory->create(ResultFactory::TYPE_JSON);

        try {
            $quote = $this->_checkoutSession->getQuote();
            $grandTotal = $quote->getBaseGrandTotal();
            $orderCurrency = $quote->getBaseCurrencyCode();
            $currency = $quote->getBaseCurrencyCode();
            $multiply = 100;
            if ($this->stripeHelper->isZeroDecimal($currency)) {
                $multiply = 1;
            }
            $_amount = $grandTotal * $multiply;
            $customerEmail = $quote->getCustomerEmail();
            $canCollectBilling = $this->_config->getCheckoutCanCollectBilling();
            $canCollectZipCode = $this->_config->getCheckoutCanCollectZip();
            $displayName = $this->_config->getDisplayName();
            $imageUrl = $this->_config->getCheckoutImageUrl();

            $result->setData([
                'grand_total' => $_amount,
                'order_currency' => $orderCurrency,
                'customer_email' => $customerEmail,
                'can_collect_billing' => $canCollectBilling,
                'can_collect_zip' => $canCollectZipCode,
                'display_name' => $displayName,
                'allow_remember' => $this->_config->getAllowRemember(),
                'accept_bitcoin' => $this->_config->getCanAcceptBitcoin(),
                'accept_alipay' => $this->_config->getCanAcceptAlipay(),
                'image_url' => $imageUrl
            ]);
        } catch (\Exception $e) {
            return $this->getErrorResponse($result);
        }

        return $result;
    }

    private function getErrorResponse(ResultInterface $controllerResult)
    {
        /** @var \Magento\Framework\Controller\Result\Json $controllerResult */
        $controllerResult->setHttpResponseCode(Exception::HTTP_BAD_REQUEST);
        $controllerResult->setData([
            'message' => __('Sorry, but something went wrong'),
            'error' => true
        ]);

        return $controllerResult;
    }
}
