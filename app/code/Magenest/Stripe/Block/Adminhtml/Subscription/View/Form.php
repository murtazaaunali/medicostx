<?php
/**
 * Created by Magenest.
 * Author: Pham Quang Hau
 * Date: 27/05/2016
 * Time: 09:58
 */

namespace Magenest\Stripe\Block\Adminhtml\Subscription\View;

class Form extends \Magento\Backend\Block\Widget\Form\Generic
{
    protected function _prepareForm()
    {
        $form = $this->_formFactory->create(
            [
                'data' =>
                    [
                        'id' => 'edit_form',
                        'action' => $this->getData('action'),
                        'method' => 'post',
                        'enctype' => 'multipart/form-data'
                    ]
            ]
        );

        $form->setUseContainer(true);
        $this->setForm($form);

        return parent::_prepareForm();
    }
}
