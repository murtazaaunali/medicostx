<?php
/**
 * Created by Magenest.
 * Author: Pham Quang Hau
 * Date: 26/05/2016
 * Time: 21:55
 */

namespace Magenest\Stripe\Controller\Adminhtml\Subscription;

use Magento\Backend\App\Action;
use Magenest\Stripe\Controller\Adminhtml\Subscription;

class Index extends Subscription
{
    public function execute()
    {
        $resultPage = $this->_initAction();
        $resultPage->getConfig()->getTitle()->prepend(__('Subscription Manager'));

        return $resultPage;
    }

    public function _isAllowed()
    {
        return $this->_authorization->isAllowed('Magenest_Stripe::subscription');
    }
}
