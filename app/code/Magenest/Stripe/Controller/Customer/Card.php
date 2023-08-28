<?php

namespace Magenest\Stripe\Controller\Customer;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Customer\Model\Session as CustomerSession;
use Magenest\Stripe\Helper\Data as DataHelper;
use Magenest\Stripe\Model\CustomerFactory;

class Card extends Action
{
    protected $_customerSession;

    protected $_cardFactory;

    protected $_resultJsonFactory;

    protected $_config;

    protected $_jsonFactory;

    protected $_helper;

    protected $_customerFactory;

    protected $_stripeModel;

    public function __construct(
        Context $context,
        CustomerSession $customerSession,
        \Magenest\Stripe\Model\CardFactory $cardFactory,
        \Magenest\Stripe\Helper\Config $config,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        DataHelper $dataHelper,
        \Magenest\Stripe\Model\CustomerFactory $customerFactory,
        \Magenest\Stripe\Model\StripePaymentMethod $stripePaymentMethod
    ) {
        $this->_customerFactory = $customerFactory;
        $this->_customerSession = $customerSession;
        $this->_cardFactory = $cardFactory;
        $this->_config = $config;
        $this->_jsonFactory = $resultJsonFactory;
        $this->_stripeModel = $stripePaymentMethod;
        parent::__construct($context);
        $this->_helper = $dataHelper;
    }

    public function dispatch(RequestInterface $request)
    {
        $loginUrl = $this->_objectManager->get('Magento\Customer\Model\Url')->getLoginUrl();

        if (!$this->_customerSession->authenticate($loginUrl)) {
            $this->_actionFlag->set('', self::FLAG_NO_DISPATCH, true);
        }

        return parent::dispatch($request);
    }

    public function execute()
    {
        $this->_view->loadLayout();
//        if ($block = $this->_view->getLayout()->getBlock('Wepay_customer_subscription_list')) {
//            $block->setRefererUrl($this->_redirect->getRefererUrl());
//        }
        $this->_view->getPage()->getConfig()->getTitle()->set(__('Stripe Stored Cards'));
        $this->_view->renderLayout();
    }
}
