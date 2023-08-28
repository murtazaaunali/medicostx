<?php
/**
 * Created by Magenest.
 * Author: Pham Quang Hau
 * Date: 08/05/2016
 * Time: 15:13
 */

namespace Magenest\Stripe\Model\Source;

use Magento\Framework\Option\ArrayInterface;

class PaymentAction implements ArrayInterface
{
    public function toOptionArray()
    {
        return [
            [
                'value' => 'authorize',
                'label' => __('Authorize Only'),
            ],
            [
                'value' => 'authorize_capture',
                'label' => __('Authorize and Capture')
            ]
        ];
    }
}
