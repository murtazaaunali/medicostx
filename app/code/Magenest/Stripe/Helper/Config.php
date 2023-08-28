<?php
/**
 * Created by PhpStorm.
 * User: joel
 * Date: 13/10/2016
 * Time: 23:55
 */

namespace Magenest\Stripe\Helper;

use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\App\Helper\Context;
use Magento\Store\Model\StoreManagerInterface;

class Config extends \Magento\Framework\App\Helper\AbstractHelper
{
    protected $_encryptor;

    protected $storeManager;

    public function __construct(
        Context $context,
        EncryptorInterface $encryptor,
        StoreManagerInterface $storeManager
    ) {
        parent::__construct($context);
        $this->_encryptor = $encryptor;
        $this->storeManager = $storeManager;
    }

    public function getIsSandboxMode()
    {
        return $this->scopeConfig->getValue(
            'payment/magenest_stripe/test',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    public function isSave()
    {
        return $this->scopeConfig->getValue(
            'payment/magenest_stripe/save',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    public function getPaymentAction()
    {
        return $this->scopeConfig->getValue(
            'payment/magenest_stripe/payment_action',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    public function getPaymentActionIframe()
    {
        return $this->scopeConfig->getValue(
            'payment/magenest_stripe_iframe/payment_action',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    public function getConfigValue($value)
    {
        $configValue = $this->scopeConfig->getValue(
            'payment/magenest_stripe/' . $value,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );

        return $this->_encryptor->decrypt($configValue);
    }

    public function getIsTotalCycleEnabled()
    {
        return $this->scopeConfig->getValue(
            'payment/magenest_stripe/additional_config/enable_total_cycle',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    public function getMaxTotalCycle()
    {
        return $this->scopeConfig->getValue(
            'payment/magenest_stripe/additional_config/max_total_cycle',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    public function getIsCancelAtPeriodEnd()
    {
        return $this->scopeConfig->getValue(
            'payment/magenest_stripe/additional_config/cancel_period_end',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    public function getCanCreateOrder()
    {
        return $this->scopeConfig->getValue(
            'payment/magenest_stripe/additional_config/create_order',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    public function getCheckoutCanCollectBilling()
    {
        return $this->scopeConfig->getValue(
            'payment/magenest_stripe_iframe/collect_billing',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    public function getCheckoutCanCollectZip()
    {
        return $this->scopeConfig->getValue(
            'payment/magenest_stripe_iframe/collect_zip',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    public function getDisplayName()
    {
        return $this->scopeConfig->getValue(
            'payment/magenest_stripe_iframe/display_name',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    public function getAllowRemember()
    {
        return $this->scopeConfig->getValue(
            'payment/magenest_stripe_iframe/allow_remember',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    public function getCanAcceptBitcoin()
    {
        return $this->scopeConfig->getValue(
            'payment/magenest_stripe_iframe/allow_bitcoin',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    public function getCanAcceptAlipay()
    {
        return $this->scopeConfig->getValue(
            'payment/magenest_stripe_iframe/allow_alipay',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    public function getCheckoutImageUrl()
    {
        $baseUrl = $this->storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA) . 'stripe/';
        $imageId = $this->scopeConfig->getValue(
            'payment/magenest_stripe_iframe/upload_image_id',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
        if (!!$imageId) {
            return $baseUrl . $imageId;
        } else {
            return null;
        }
//        return $baseUrl . $this->scopeConfig->getValue(
//            'payment/magenest_stripe_iframe/upload_image_id',
//            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
//        );
    }

    public function isIframeActive()
    {
        return $this->scopeConfig->getValue(
            'payment/magenest_stripe_iframe/active',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    public function isDebugMode()
    {
        return 1;
//        return $this->scopeConfig->getValue(
//            'payment/magenest_stripe/debug',
//            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
//        );
    }

    public function getPublishableKey()
    {
        $isTest = $this->getIsSandboxMode();
        if ($isTest) {
            return $this->getConfigValue('test_publishable');
        } else {
            return $this->getConfigValue('live_publishable');
        }
    }

    public function getSecretKey()
    {
        $isTest = $this->getIsSandboxMode();
        if ($isTest) {
            return $this->getConfigValue('test_secret');
        } else {
            return $this->getConfigValue('live_secret');
        }
    }

    public function getThreedsecure()
    {
        return $this->scopeConfig->getValue(
            'payment/magenest_stripe/threedsecure',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    public function getInstructions()
    {
        return preg_replace('/\s+|\n+|\r/', ' ', $this->scopeConfig->getValue(
            'payment/magenest_stripe/instructions',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        ));
    }

    public function sendMailCustomer()
    {
        return $this->scopeConfig->getValue(
            'payment/magenest_stripe/email_customer',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    public function getNewOrderStatus()
    {
        return $this->scopeConfig->getValue(
            'payment/magenest_stripe/order_status',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }
}
