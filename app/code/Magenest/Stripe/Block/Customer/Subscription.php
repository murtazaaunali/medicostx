<?php
/**
 * Created by Magenest.
 * Author: Pham Quang Hau
 * Date: 28/05/2016
 * Time: 13:44
 */

namespace Magenest\Stripe\Block\Customer;

use Magento\Catalog\Block\Product\Context;
use Magento\Customer\Helper\Session\CurrentCustomer;
use Magento\Sales\Model\OrderFactory;
use Magenest\Stripe\Model\SubscriptionFactory;
use Magenest\Stripe\Helper\Data as DataHelper;

class Subscription extends \Magento\Framework\View\Element\Template
{
    protected $_currentCustomer;

    protected $_subscriptionFactory;

    protected $_helper;

    protected $_orderFactory;

    public function __construct(
        Context $context,
        CurrentCustomer $currentCustomer,
        SubscriptionFactory $subscriptionFactory,
        DataHelper $dataHelper,
        OrderFactory $orderFactory,
        array $data
    ) {
        $this->_currentCustomer = $currentCustomer;
        $this->_subscriptionFactory = $subscriptionFactory;
        $this->_helper = $dataHelper;
        $this->_orderFactory = $orderFactory;
        parent::__construct($context, $data);
    }

    public function getCustomerSubscriptions()
    {
        $customerId = $this->_currentCustomer->getCustomerId();

        $subs = $this->_subscriptionFactory->create()
            ->getCollection()->addFieldToFilter('customer_id', $customerId);

        return $subs;
    }

    public function getOrderViewUrl($order_id)
    {
        /** @var \Magento\Sales\Model\Order $order */
        $order = $this->_orderFactory->create();
        $orderId = $order->loadByIncrementId($order_id)->getId();

        return $this->getUrl('sales/order/view', ['order_id' => $orderId]);
    }

    public function getSubscriptionDetailUrl($id)
    {
        return $this->getUrl('stripe/customer/detail', [
            'id' => $id
        ]);
    }
}
