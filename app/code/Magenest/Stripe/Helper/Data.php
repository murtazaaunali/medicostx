<?php
/**
 * Created by Magenest.
 * Author: Pham Quang Hau
 * Date: 20/05/2016
 * Time: 12:01
 */

namespace Magenest\Stripe\Helper;

use Magento\Framework\App\Helper\Context;
use Magento\Framework\HTTP\ZendClientFactory;
use Magento\Framework\Encryption\EncryptorInterface;
use Magenest\Stripe\Model\CustomerFactory;
use Magenest\Stripe\Helper\Config;

class Data extends \Magento\Framework\App\Helper\AbstractHelper
{
    protected $_encryptor;

    protected $_httpClientFactory;

    protected $_customerFactory;

    protected $_config;

    public function __construct(
        Context $context,
        EncryptorInterface $encryptorInterface,
        ZendClientFactory $clientFactory,
        CustomerFactory $customerFactory,
        Config $config
    ) {
        $this->_encryptor = $encryptorInterface;
        $this->_httpClientFactory = $clientFactory;
        $this->_customerFactory = $customerFactory;
        $this->_config = $config;
        parent::__construct($context);
    }

    /**
     * @param string $url
     * @param array $requestPost
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function sendRequestDelete($url, $requestPost = null)
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $key = $this->_config->getSecretKey();
        //$httpHeaders = new \Zend\Http\Headers();
        $httpHeaders = $objectManager->create('\Zend\Http\Headers');
        $httpHeaders->addHeaders([
            'Authorization' => 'Bearer ' . $key,
        ]);
        //$request = new \Zend\Http\Request();
        $request = $objectManager->create('\Zend\Http\Request');
        $request->setHeaders($httpHeaders);
        $request->setUri($url);
        $request->setMethod(\Zend\Http\Request::METHOD_DELETE);

        if (!!$requestPost) {
            $request->getPost()->fromArray($requestPost);
        }

        //$client = new \Zend\Http\Client();
        $client = $objectManager->create('\Zend\Http\Client');
        $options = [
            'adapter' => 'Zend\Http\Client\Adapter\Curl',
            'curloptions' => [CURLOPT_FOLLOWLOCATION => true],
            'maxredirects' => 0,
            'timeout' => 30
        ];
        $client->setOptions($options);
        try {
            $response = $client->send($request);
            $responseBody = $response->getBody();
            $responseBody = (array)json_decode($responseBody);

            return $responseBody;
        } catch (\Exception $e) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __('Cannot send request to Stripe servers.')
            );
        }
    }

    public function sendRequest($request, $url, $request_type = null)
    {
        /** @var \Magento\Framework\HTTP\ZendClient $client */
        $client = $this->_httpClientFactory->create();

        $isTest = $this->scopeConfig->getValue(
            'payment/magenest_stripe/test',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
        $testSecret = $this->_config->getConfigValue('test_secret');
        $liveSecret = $this->_config->getConfigValue('live_secret');

        if ($isTest) {
            $headers = [
                'Authorization: Bearer ' . $testSecret
            ];
        } else {
            $headers = [
                'Authorization: Bearer ' . $liveSecret
            ];
        }

        $client->setUri($url);
        $client->setHeaders($headers);

        $client->setConfig(['maxredirects' => 0, 'timeout' => 30]);
        if ($request_type == 'post' || $request_type == null) {
            $client->setMethod(\Zend_Http_Client::POST);
        }
        if ($request_type == 'delete') {
            $client->setMethod(\Zend_Http_Client::DELETE);
        }


        if ($request) {
            $client->setParameterPost($request);
        }

        try {
            $response = $client->request();
            $responseBody = $response->getBody();
            $responseBody = (array)json_decode($responseBody);

            return $responseBody;
        } catch (\Exception $e) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __('Cannot send request to Stripe servers.')
            );
        }
    }

    public function deletePlan($planId)
    {
        $isTest = $this->scopeConfig->getValue(
            'payment/magenest_stripe/test',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );

        $testSecret = $this->_config->getConfigValue('test_secret');
        $liveSecret = $this->_config->getConfigValue('live_secret');

//        if ($isTest) {
//            \Stripe\Stripe::setApiKey($testSecret);
//        } else {
//            \Stripe\Stripe::setApiKey($liveSecret);
//        }

//        $plan = \Stripe\Plan::retrieve($planId);
//        $response = $plan->delete();
//        $response = $response->getLastResponse();
//
//        $body = $response->json;
        $url = "https://api.stripe.com/v1/plans/" . $planId;
        $body = $this->sendRequestDelete($url);

        if (!isset($body['deleted']) || $body['deleted'] != 'true') {
            throw new \Magento\Framework\Exception\LocalizedException(
                __('Something went wrong while deleting plans.')
            );
        }
    }

    public function deleteSubscription($subsId)
    {
        $isTest = $this->scopeConfig->getValue(
            'payment/magenest_stripe/test',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );

        $isCancelAtPeriodEnd = $this->_config->getIsCancelAtPeriodEnd();

        $testSecret = $this->_config->getConfigValue('test_secret');
        $liveSecret = $this->_config->getConfigValue('live_secret');

//        if ($isTest) {
//            \Stripe\Stripe::setApiKey($testSecret);
//        } else {
//            \Stripe\Stripe::setApiKey($liveSecret);
//        }

        //$sub = \Stripe\Subscription::retrieve($subsId);
        $url = "https://api.stripe.com/v1/subscriptions/" . $subsId;
        if ($isCancelAtPeriodEnd) {
            //$response = $sub->cancel(['at_period_end' => true]);
            $response = $this->sendRequestDelete($url, ['at_period_end' => "true"]);
        } else {
            //$response = $sub->cancel();
            $response = $this->sendRequestDelete($url);
        }


        //$response = $response->getLastResponse();

        //$body = $response->json;

        return $response;
    }

    public function deleteSubscriptionCron($response)
    {
        $isTest = $this->scopeConfig->getValue(
            'payment/magenest_stripe/test',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
        $subsId = $response['id'];

        $testSecret = $this->_config->getConfigValue('test_secret');
        $liveSecret = $this->_config->getConfigValue('live_secret');

//        if ($isTest) {
//            \Stripe\Stripe::setApiKey($testSecret);
//        } else {
//            \Stripe\Stripe::setApiKey($liveSecret);
//        }

        //$sub = \Stripe\Subscription::retrieve($subsId);
        //$response = $sub->cancel(['at_period_end' => true]);

        //$response = $response->getLastResponse();

        //$body = $response->json;
        $url = "https://api.stripe.com/v1/subscriptions/" . $subsId;
        $body = $this->sendRequestDelete($url, ['at_period_end' => "true"]);

        return $body;
    }

    public function calculateCurrentPeriod($response)
    {
        if ($response['status'] == 'active') {
            $numberOfPeriodsPassed = 0;
            if ($response['trial_end']) {
                $mainStart = $response['trial_end'];
            } else {
                $mainStart = $response['created'];
            }

            $periodInterval = $response['current_period_end'] - $response['current_period_start'];
            $intervalFromStart = $response['current_period_start'] - $mainStart;

            $numberOfPeriodsPassed = round($intervalFromStart / $periodInterval);

            return $numberOfPeriodsPassed++;
        } else {
            return 0;
        }
    }

    public function checkStripeCustomerId($cusId)
    {
        $url = 'https://api.stripe.com/v1/customers/' . $cusId;

        return $this->sendRequest([], $url, null);
    }

    public function isZeroDecimal($currency)
    {
        return in_array(strtolower($currency), [
            'bif',
            'djf',
            'jpy',
            'krw',
            'pyg',
            'vnd',
            'xaf',
            'xpf',
            'clp',
            'gnf',
            'kmf',
            'mga',
            'rwf',
            'vuv',
            'xof'
        ]);
    }
}
