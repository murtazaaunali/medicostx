<?php
/**
 * Created by Magenest.
 * Author: Pham Quang Hau
 * Date: 24/05/2016
 * Time: 15:44
 */

namespace Magenest\Stripe\Observer\Option;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Psr\Log\LoggerInterface;

class Cart implements ObserverInterface
{
    protected $_logger;

    public function __construct(
        LoggerInterface $loggerInterface
    ) {
        $this->_logger = $loggerInterface;
    }

    public function execute(Observer $observer)
    {
        $item = $observer->getEvent()->getQuoteItem();
        $buyInfo = $item->getBuyRequest();

        if ($options = $buyInfo->getAdditionalOptions()) {
            $additionalOptions = [];
            foreach ($options as $key => $value) {
                if ($value) {
                    $additionalOptions[] = array(
                        'label' => $key,
                        'value' => $value
                    );
                }
            }
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $productMetadata = $objectManager->get('Magento\Framework\App\ProductMetadataInterface');
            $version = $productMetadata->getVersion();
            if (version_compare($version, "2.2.0") < 0) {
                $item->addOption(array(
                    'code' => 'additional_options',
                    'value' => serialize($additionalOptions)
                ));
            } else {
                $item->addOption(array(
                    'code' => 'additional_options',
                    'value' => json_encode($additionalOptions)
                ));
            }
        }
    }
}
