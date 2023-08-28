<?php

namespace Magenest\Stripe\Block\Customer;

use Magento\Catalog\Block\Product\Context;
use Magento\Customer\Helper\Session\CurrentCustomer;
use Magento\Sales\Model\OrderFactory;
use Stripe;

class Card extends \Magento\Framework\View\Element\Template
{
    protected $_currentCustomer;

    protected $_helper;

    protected $_orderFactory;

    protected $_cardFactory;

    protected $_Config;

    protected $_customerSession;

    public function __construct(
        Context $context,
        CurrentCustomer $currentCustomer,
        \Magenest\Stripe\Model\CardFactory $cardFactory,
        \Magenest\Stripe\Helper\Config $config,
        OrderFactory $orderFactory,
        \Magento\Customer\Model\Session $customerSession,
        array $data
    ) {
        $this->_currentCustomer = $currentCustomer;
        $this->_orderFactory = $orderFactory;
        $this->_cardFactory = $cardFactory;
        $this->_config = $config;
        $this->_customerSession = $customerSession;
        parent::__construct($context, $data);
    }

    public function getDataCard()
    {
        $customer_id = $this->_customerSession->getCustomerId();
        $model = $this->_cardFactory->create()
            ->getCollection()
            ->addFieldToFilter('magento_customer_id', $customer_id)
            ->getData();
        $this->checkFlag = count($model);

        return $model;
    }
}
