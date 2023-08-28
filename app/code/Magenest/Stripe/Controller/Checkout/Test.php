<?php
/**
 * Created by PhpStorm.
 * User: magenest
 * Date: 27/05/2017
 * Time: 16:01
 */

namespace Magenest\Stripe\Controller\Checkout;

use Magenest\Stripe\Helper\Constant;
use Magento\Framework\App\Action\Context;
use Magento\Checkout\Model\Session as CheckoutSession;

class Test extends \Magento\Framework\App\Action\Action
{
    protected $helper;
    protected $cron;

    public function __construct(
        Context $context,
        \Magenest\Stripe\Helper\SubscriptionHelper $subscriptionHelper,
        \Magenest\Stripe\Helper\Data $stripeHelper,
        \Magenest\Stripe\Model\Cron $cron
    ) {
        $this->cron = $cron;
        parent::__construct($context);
        $this->helper = $subscriptionHelper;
        $this->stripeHelper = $stripeHelper;
    }

    public function execute()
    {
    }
}
