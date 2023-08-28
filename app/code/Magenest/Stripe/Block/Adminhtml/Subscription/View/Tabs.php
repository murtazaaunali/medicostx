<?php
/**
 * Created by Magenest.
 * Author: Pham Quang Hau
 * Date: 27/05/2016
 * Time: 09:58
 */

namespace Magenest\Stripe\Block\Adminhtml\Subscription\View;

class Tabs extends \Magento\Backend\Block\Widget\Tabs
{
    protected function _construct()
    {
        parent::_construct();
        $this->setId('subscription_tabs');
        $this->setDestElementId('edit_form');
        $this->setTitle(__('Subscription Information'));
    }
}
