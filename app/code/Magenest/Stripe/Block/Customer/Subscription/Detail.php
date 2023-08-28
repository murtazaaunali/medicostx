<?php
/**
 * Created by Magenest.
 * Author: Pham Quang Hau
 * Date: 29/05/2016
 * Time: 02:04
 */

namespace Magenest\Stripe\Block\Customer\Subscription;

use Magento\Catalog\Block\Product\Context;
use Magento\Framework\ObjectManagerInterface;
use Magenest\Stripe\Model\SubscriptionFactory;
use Magenest\Stripe\Helper\Data as DataHelper;

class Detail extends \Magento\Framework\View\Element\Template
{
    protected $_subscriptionFactory;

    protected $_objectManager;

    protected $_helper;

    protected $_coreRegistry;

    public function __construct(
        Context $context,
        SubscriptionFactory $subscriptionFactory,
        ObjectManagerInterface $objectManagerInterface,
        DataHelper $dataHelper,
        $data = []
    ) {
        $this->_subscriptionFactory = $subscriptionFactory;
        $this->_objectManager = $objectManagerInterface;
        $this->_helper = $dataHelper;
        $this->_coreRegistry = $context->getRegistry();
        parent::__construct($context, $data);
    }

    public function getSubscription()
    {
        $id = $this->_coreRegistry->registry('customer_view_subscription_id');

        $sub = $this->_subscriptionFactory->create()->load($id);

        return $sub;
    }

    public function getSubsDetail()
    {
        $sub = $this->getSubscription();
        $subsId = $sub->getSubscriptionId();

        $url = 'https://api.stripe.com/v1/subscriptions/' . $subsId;
        $response = $this->_helper->sendRequest(null, $url, 'post');

        return $response;
    }

    public function getCancelUrl()
    {
        $sub = $this->getSubscription();
        $subsId = $sub->getSubscriptionId();

        return $this->getUrl(
            'stripe/customer/cancel',
            [
                'sub_id' => $subsId,
            ]
        );
    }
}
