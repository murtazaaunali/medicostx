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
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Webapi\Exception;
use Magento\Framework\Controller\ResultInterface;
use Magenest\Stripe\Helper\Config;

class GiroPayConfig extends Action
{
    protected $_checkoutSession;

    protected $_config;

    protected $quoteFactory;
    /**
     * @var \Magento\Quote\Model\Quote\Address\BillingAddressPersister
     */
    private $billingAddressPersister;

    public function __construct(
        Context $context,
        Session $checkoutSession,
        \Magento\Quote\Model\QuoteFactory $quoteFactory,
        \Magento\Quote\Model\Quote\Address\BillingAddressPersister $billingAddressPersister,
        Config $config,
        \Magenest\Stripe\Helper\Data $stripeHelper
    ) {
        parent::__construct($context);
        $this->billingAddressPersister = $billingAddressPersister;
        $this->quoteFactory = $quoteFactory;
        $this->_checkoutSession = $checkoutSession;
        $this->_config = $config;
        $this->stripeHelper = $stripeHelper;
    }

    public function execute()
    {
        $result = $this->resultFactory->create(ResultFactory::TYPE_JSON);

        try {
            /**
             * @var \Magento\Quote\Model\Quote $quote
             */
            $billingAddress = json_decode($this->getRequest()->getParam('billingAddress'), true);
            if ($billingAddress) {
                $quote = $this->_checkoutSession->getQuote();
                $billing = $quote->getBillingAddress();
                $this->billingAddressPersister->save($quote, $billing);
            }
            $grandTotal = $quote->getBaseGrandTotal();
            $this->_checkoutSession->setBaseGrandTotalForCheck($grandTotal);
            $this->_checkoutSession->setEmailForCheck($this->getRequest()->getParam('guestEmail'));
            $result->setData([
                'error' => false,
                'amount' => floatval($grandTotal)
            ]);
        } catch (\Exception $e) {
            $result->setData([
                'error' => true
            ]);
        }

        return $result;
    }
}
