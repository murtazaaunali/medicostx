<?php
/**
 * Created by Magenest.
 * Author: Pham Quang Hau
 * Date: 27/05/2016
 * Time: 10:07
 */

namespace Magenest\Stripe\Block\Adminhtml\Subscription\View\Tabs;

use Magento\Framework\ObjectManagerInterface;
use Magenest\Stripe\Helper\Data as DataHelper;

class Info extends \Magento\Sales\Block\Adminhtml\Order\AbstractOrder implements
    \Magento\Backend\Block\Widget\Tab\TabInterface
{
    protected $_objectManager;

    protected $_helper;

    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Sales\Helper\Admin $adminHelper,
        ObjectManagerInterface $interface,
        DataHelper $dataHelper,
        array $data
    ) {
        $this->_objectManager = $interface;
        $this->_helper = $dataHelper;
        parent::__construct($context, $registry, $adminHelper, $data);
    }

    public function getSubscription()
    {
        /** @var \Magenest\Stripe\Model\Subscription $profile */
        $subscription = $this->_coreRegistry->registry('stripe_subscription_model');

        return $subscription;
    }

    public function getSubscriptionDetail()
    {
        $subscription = $this->getSubscription();
        $subsId = $subscription->getSubscriptionId();

        $url = 'https://api.stripe.com/v1/subscriptions/' . $subsId;
        $response = $this->_helper->sendRequest(null, $url, 'post');

        return $response;
    }

    public function getOrder()
    {
        $subscription = $this->getSubscription();
        $orderId = $subscription->getOrderId();

        /** @var \Magento\Sales\Model\Order $order */
        $order = $this->_objectManager->get('Magento\Sales\Model\Order');
        $order->loadByIncrementId($orderId);

        return $order;
    }


    public function getTabLabel()
    {
        return __('Subscription Information');
    }

    public function getTabTitle()
    {
        return __('Subscription Information');
    }

    public function canShowTab()
    {
        return true;
    }

    public function isHidden()
    {
        return false;
    }
}
