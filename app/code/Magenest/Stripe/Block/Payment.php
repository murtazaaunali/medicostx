<?php
/**
 * Created by PhpStorm.
 * User: hiennq
 * Date: 5/24/17
 * Time: 8:45 AM
 */

namespace Magenest\Stripe\Block;

use Magento\Payment\Block\Form;
use Magento\Payment\Model\Config;
use Magento\Payment\Gateway\ConfigInterface;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

/**
 * Class Payment
 */
class Payment extends Template
{
    /**
     * @var ConfigInterface
     */
    private $config;

    /**
     * Constructor
     *
     * @param Context $context
     * @param ConfigInterface $config
     * @param array $data
     */
    public function __construct(
        Context $context,
        \Magenest\Stripe\Helper\Config $config,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->config = $config;
    }

    /**
     * @return string
     */
    public function getPublishableKey()
    {
        $isTest = $this->config->getIsSandboxMode();
        if ($isTest) {
            return $this->config->getConfigValue('test_publishable');
        } else {
            return $this->config->getConfigValue('live_publishable');
        }
    }

    /**
     * @return string
     */
    public function getCode()
    {
        return "magenest_stripe";
    }

    /**
     * @inheritdoc
     */
    public function toHtml()
    {
        return parent::toHtml();
    }
}
