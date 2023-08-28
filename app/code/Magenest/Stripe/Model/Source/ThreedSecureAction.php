<?php
/**
 * Created by PhpStorm.
 * User: magenest
 * Date: 26/05/2017
 * Time: 15:55
 */

namespace Magenest\Stripe\Model\Source;

use Magento\Framework\Option\ArrayInterface;

class ThreedSecureAction implements ArrayInterface
{
    public function toOptionArray()
    {
        return [
            [
                'value' => '0',
                'label' => __('Off'),
            ],
            [
                'value' => '1',
                'label' => __('Check when required')
            ],
            [
                'value' => '2',
                'label' => __('Optional 3D Secure')
            ],
            [
                'value' => '3',
                'label' => __('Required 3D Secure')
            ]
        ];
    }
}
