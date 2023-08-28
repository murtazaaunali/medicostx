<?php
/**
 * Created by PhpStorm.
 * User: joel
 * Date: 28/10/2016
 * Time: 14:18
 */

namespace Magenest\Stripe\Model\Plugin;

use Psr\Log\LoggerInterface;
use Magento\Framework\ObjectManagerInterface;

class ConfigurableProduct
{
    public function aroundAssignProductToOption(
        \Magento\ConfigurableProduct\Model\Product\Type\Configurable $subject,
        callable $proceed,
        $optionProduct,
        $option,
        $product
    ) {

        if ($optionProduct) {
            $option->setProduct($optionProduct);
        }

        return $this;
    }
}
