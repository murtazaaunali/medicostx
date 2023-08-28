<?php
/**
 * Created by Magenest.
 * Author: Pham Quang Hau
 * Date: 24/05/2016
 * Time: 02:05
 */

namespace Magenest\Stripe\Observer\Layout;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Psr\Log\LoggerInterface;

class Add implements ObserverInterface
{
    protected $_logger;

    public function __construct(
        LoggerInterface $loggerInterface
    ) {
        $this->_logger = $loggerInterface;
    }

    public function execute(Observer $observer)
    {
        $product = $observer->getEvent()->getProduct();
        if ($product->getTypeId() != 'grouped') {
            $product->setHasOptions(true);
        }
    }
}
