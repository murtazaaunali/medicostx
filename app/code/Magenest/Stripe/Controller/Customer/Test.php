<?php
/**
 * Created by Magenest.
 * Author: Pham Quang Hau
 * Date: 29/05/2016
 * Time: 20:12
 */

namespace Magenest\Stripe\Controller\Customer;

use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\Action;
use Magenest\Stripe\Helper\Data as HelperData;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\StoreManagerInterface;

class Test extends Action
{
    protected $_helper;

    protected $scopeConfig;

    protected $_storeManager;

    public function __construct(
        Context $context,
        HelperData $data,
        ScopeConfigInterface $scopeConfig,
        StoreManagerInterface $storeManager
    ) {
        $this->_helper = $data;
        $this->scopeConfig = $scopeConfig;
        $this->_storeManager = $storeManager;
        parent::__construct($context);
    }

    public function execute()
    {
    }
}
