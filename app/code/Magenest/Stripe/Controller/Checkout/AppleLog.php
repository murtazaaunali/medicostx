<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 14/11/2017
 * Time: 17:01
 */

namespace Magenest\Stripe\Controller\Checkout;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magenest\Stripe\Helper\Config;

class AppleLog extends Action
{
    protected $_config;

    protected $_stripeLogger;

    public function __construct(
        Context $context,
        Config $config,
        \Magenest\Stripe\Helper\Logger $logger
    ) {
        $this->_config = $config;
        $this->_stripeLogger = $logger;
        parent::__construct($context);
    }

    /**
     * Execute action based on request and return result
     *
     * Note: Request will be added as operation argument in future
     *
     * @return \Magento\Framework\Controller\ResultInterface|ResponseInterface
     * @throws \Magento\Framework\Exception\NotFoundException
     */
    public function execute()
    {
        $param =  $this->getRequest()->getParam('apple');

        $this->_stripeLogger->debug(serialize($param));
    }
}
