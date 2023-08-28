<?php
/**
 * Created by Magenest.
 * Author: Pham Quang Hau
 * Date: 29/05/2016
 * Time: 01:51
 */

namespace Magenest\Stripe\Controller\Customer;

use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\Action;
use Magento\Framework\View\Result\PageFactory;
use Magento\Framework\Registry;
use Psr\Log\LoggerInterface;

class Detail extends Action
{
    protected $_resultPageFactory;

    protected $_logger;

    protected $_coreRegistry;

    public function __construct(
        Context $context,
        PageFactory $pageFactory,
        LoggerInterface $loggerInterface,
        Registry $registry
    ) {
        $this->_resultPageFactory = $pageFactory;
        $this->_logger = $loggerInterface;
        $this->_coreRegistry = $registry;
        parent::__construct($context);
    }

    public function execute()
    {
        $id = $this->getRequest()->getParam('id');

        $this->_coreRegistry->register('customer_view_subscription_id', $id);
        $sub_id = $this->_objectManager->get('\Magenest\Stripe\Model\Subscription')->load($id)->getSubscriptionId();

        $this->_view->loadLayout();
        if ($block = $this->_view->getLayout()->getBlock('stripe_customer_subs_detail')) {
            $block->setRefererUrl($this->_redirect->getRefererUrl());
        }
        $this->_view->getPage()->getConfig()->getTitle()->set(__('Subscription "') . $sub_id . '"');
        $this->_view->renderLayout();
    }
}
