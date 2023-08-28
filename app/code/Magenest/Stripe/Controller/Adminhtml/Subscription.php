<?php
/**
 * Created by Magenest.
 * Author: Pham Quang Hau
 * Date: 26/05/2016
 * Time: 21:55
 */

namespace Magenest\Stripe\Controller\Adminhtml;

use Magento\Backend\App\Action;
use Magento\Framework\View\Result\PageFactory;
use Magento\Framework\Registry;
use Psr\Log\LoggerInterface;
use Magenest\Stripe\Model\SubscriptionFactory;

abstract class Subscription extends Action
{
    protected $_subscriptionFactory;

    protected $_pageFactory;

    protected $_logger;

    protected $_coreRegistry;

    public function __construct(
        Action\Context $context,
        PageFactory $pageFactory,
        SubscriptionFactory $subscriptionFactory,
        LoggerInterface $loggerInterface,
        Registry $registry
    ) {
        $this->_pageFactory = $pageFactory;
        $this->_logger = $loggerInterface;
        $this->_subscriptionFactory = $subscriptionFactory;
        $this->_coreRegistry = $registry;
        parent::__construct($context);
    }

    protected function _initAction()
    {
        /** @var \Magento\Backend\Model\View\Result\Page $resultPage */
        $resultPage = $this->_pageFactory->create();
        $resultPage->setActiveMenu('Magenest_Stripe::subscription')
            ->addBreadcrumb(__('Subscription Manager'), __('Subscription Manager'));

        $resultPage->getConfig()->getTitle()->set(__('Subscription Manager'));

        return $resultPage;
    }

    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('Magenest_Stripe::subscription');
    }
}
