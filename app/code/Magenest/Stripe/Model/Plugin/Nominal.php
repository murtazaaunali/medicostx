<?php
/**
 * Created by Magenest.
 * Author: Pham Quang Hau
 * Date: 24/05/2016
 * Time: 17:01
 */

namespace Magenest\Stripe\Model\Plugin;

use Psr\Log\LoggerInterface;

class Nominal
{
    protected $_logger;

    public function __construct(
        LoggerInterface $loggerInterface
    ) {
        $this->_logger = $loggerInterface;
    }

    public function aroundAddItem(
        \Magento\Quote\Model\Quote $subject,
        \Closure $proceed,
        \Magento\Quote\Model\Quote\Item $item
    ) {
        $canBeAdded = 1;

        foreach ($subject->getAllVisibleItems() as $cartItem) {
            $buyInfo = $cartItem->getBuyRequest();
            if ($options = $buyInfo->getAdditionalOptions()) {
                foreach ($options as $key => $value) {
                    if ($value && $key == 'Plan ID') {
                        $canBeAdded = 0;
                    }
                }
            }
        }

        $itemCount = $subject->getItemsCount();

        /** @var \Magento\Quote\Model\Quote\Item $product */
        $buyInfo = $item->getBuyRequest();

        if ($options = $buyInfo->getAdditionalOptions()) {
            foreach ($options as $key => $value) {
                if ($value && $key == 'Plan ID' && $itemCount > 0) {
                    $canBeAdded = 0;
                }
            }
        }

        if ($canBeAdded == 0) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __('Item with subscription option can be purchased standalone only.')
            );
        }

        $proceed($item);

        return $subject;
    }
}
