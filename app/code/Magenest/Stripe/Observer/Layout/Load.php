<?php
/**
 * Created by Magenest.
 * Author: Pham Quang Hau
 * Date: 24/05/2016
 * Time: 02:04
 */

namespace Magenest\Stripe\Observer\Layout;

use Magento\Framework\Event\ObserverInterface;

class Load implements ObserverInterface
{
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $fullActionName = $observer->getEvent()->getFullActionName();

        /** @var \Magento\Framework\View\Layout $layout */
        $layout = $observer->getEvent()->getLayout();
        $handler = '';
        if ($fullActionName == 'catalog_product_view') {
            $handler = 'catalog_product_view_stripe';
        }

        if ($handler) {
            $layout->getUpdate()->addHandle($handler);
        }
    }
}
