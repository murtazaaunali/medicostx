<?php
/**
 * Created by Magenest.
 * Author: Pham Quang Hau
 * Date: 27/05/2016
 * Time: 08:29
 */

namespace Magenest\Stripe\Controller\Adminhtml\Subscription;

use Magenest\Stripe\Controller\Adminhtml\Subscription;

class View extends Subscription
{
    public function execute()
    {
        $id = $this->getRequest()->getParam('id');
        /** @var \Magenest\Subscription\Model\Profile $model */
        $model = $this->_subscriptionFactory->create();
        if ($id) {
            $model->load($id);
            if (!$model->getId()) {
                $this->messageManager->addError(__('This subscription no longer exists.'));

                $resultRedirect = $this->resultRedirectFactory->create();

                return $resultRedirect->setPath('*/*/');
            } else {
                $data = $this->_objectManager->get('Magento\Backend\Model\Session')->getFormData(true);
                if (!empty($data)) {
                    $model->setData($data);
                }

                $this->_coreRegistry->register('stripe_subscription_model', $model);
                /** @var \Magento\Backend\Model\View\Result\Page $resultPage */
                $resultPage = $this->_initAction();
                $title = __('View Subscription "') . $model->getSubscriptionId() . '"';
                $resultPage->getConfig()->getTitle()
                    ->prepend($title);

                return $resultPage;
            }
        } else {
            throw new \Magento\Framework\Exception\LocalizedException(
                __('This subscription no longer exists.')
            );
        }
    }
}
