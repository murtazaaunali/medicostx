<?php
/**
 * Created by Magenest.
 * Author: Pham Quang Hau
 * Date: 27/05/2016
 * Time: 08:34
 */

namespace Magenest\Stripe\Block\Adminhtml\Subscription;

use Magento\Backend\Block\Widget\Context;
use Magento\Backend\Block\Widget\Form\Container as FormContainer;
use Magento\Framework\Registry;

class View extends FormContainer
{
    protected $_coreRegistry;

    public function __construct(
        Context $context,
        Registry $registry,
        $data = []
    ) {
        $this->_coreRegistry = $registry;
        parent::__construct($context, $data);
    }

    protected function _construct()
    {
        $this->_objectId = 'id';
        $this->_blockGroup = 'Magenest_Stripe';
        $this->_controller = 'adminhtml_subscription';
        parent::_construct();

        $this->buttonList->remove('delete');
        $this->buttonList->remove('reset');
        $this->buttonList->remove('save');

        $this->_mode = 'view';
    }

    public function getHeaderText()
    {
        $model = $this->_coreRegistry->registry('stripe_subscription_model');

        return __("View Subscription '%1'", $this->escapeHtml($model->getSubscriptionId()));
    }
}
