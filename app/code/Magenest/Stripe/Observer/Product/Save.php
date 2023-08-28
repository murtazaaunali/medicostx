<?php
/**
 * Created by Magenest.
 * Author: Pham Quang Hau
 * Date: 20/05/2016
 * Time: 01:15
 */

namespace Magenest\Stripe\Observer\Product;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magenest\Stripe\Model\AttributeFactory;
use Magenest\Stripe\Helper\Data as HelperData;
use Psr\Log\LoggerInterface;

class Save implements ObserverInterface
{
    protected $_logger;

    protected $_request;

    protected $_storeManager;

    protected $_attributeFactory;

    protected $_helper;

    public function __construct(
        LoggerInterface $loggerInterface,
        RequestInterface $requestInterface,
        StoreManagerInterface $storeManagerInterface,
        AttributeFactory $attributeFactory,
        HelperData $helperData
    ) {
        $this->_logger = $loggerInterface;
        $this->_request = $requestInterface;
        $this->_storeManager = $storeManagerInterface;
        $this->_attributeFactory = $attributeFactory;
        $this->_helper = $helperData;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $data = $this->_request->getParams();
        $product = $observer->getProduct();
        $productId = $product->getId();
        $productName = $product->getName();
        $price = $product->getPrice();
        $currency = $this->_storeManager->getStore()->getCurrentCurrency()->getCode();

        if (array_key_exists('product', $data)) {
            if (array_key_exists('stripe_billing_options', $data['product'])) {
                $billing = $data['product']['stripe_billing_options'];

                /** @var \Magenest\Stripe\Model\Attribute $model */
                $model = $this->_attributeFactory->create();
                $object = $model->getCollection()->addFieldToFilter('entity_id', $productId)->getFirstItem();

                // existed plans
                $options_before = unserialize($object->getData('value'));

                // new plans
                $options_after = $billing;

                // if no plan existed for this product
                if ((!isset($options_before) || empty($options_before)) && is_array($options_after)) {
                    foreach ($options_after as $option) {
                        $option = (array)$option;

                        if (array_key_exists('is_delete', $option)) {
                            if ($option['is_delete']) {
                                continue;
                            }
                        }

                        if (!is_array($option)) {
                            throw new \Magento\Framework\Exception\LocalizedException(
                                __('Some plan options are missing or incorrect. Please try again.')
                            );
                        }

                        $request = [
                            'id' => $option['plan_id'],
                            'currency' => strtolower($currency),
                            'name' => $productName,
                            'interval' => $option['unit_id'],
                            'interval_count' => $option['frequency'],
                            'amount' => round($price * 100)
                        ];
                        if ($option['is_trial_enabled'] == 'yes') {
                            $request['trial_period_days'] = $option['trial_day'];
                        }

                        $response = $this->_helper->sendRequest($request, 'https://api.stripe.com/v1/plans', null);

                        if (!isset($response['id'])) {
                            $errorMsg = isset($response['error']->message) ? $response['error']->message :
                                "Something went wrong while creating plans. Please try again later.";
                            throw new \Magento\Framework\Exception\LocalizedException(__($errorMsg));
                        }
                    }
                } else {
                    // delete removed plans
                    if (is_array($options_before)) {
                        foreach ($options_before as $option) {
                            $is_plan_exist_after = $this->checkPlanExistAfter($option, $options_after);
                            if ($is_plan_exist_after == 0) {
                                $this->_helper->deletePlan($option['plan_id']);
                            }
                        }
                    }

                    // add new plan
                    if (is_array($options_after)) {
                        foreach ($options_after as $option) {
                            if (!is_array($option)) {
                                throw new \Magento\Framework\Exception\LocalizedException(
                                    __('Some plan options are missing or incorrect. Please try again.')
                                );
                            }

                            if (array_key_exists('is_delete', $option)) {
                                if ($option['is_delete']) {
                                    continue;
                                }
                            }

                            $is_plan_exist_before = $this->checkPlanExistBefore($option, $options_before);
                            if ($is_plan_exist_before == 0) {
                                $request = [
                                    'id' => $option['plan_id'],
                                    'currency' => strtolower($currency),
                                    'name' => $productName,
                                    'interval' => strtolower($option['unit_id']),
                                    'interval_count' => $option['frequency'],
                                    'amount' => round($price * 100)
                                ];
                                if ($option['is_trial_enabled'] == 'yes') {
                                    $request['trial_period_days'] = $option['trial_day'];
                                }

                                $response = $this->_helper->sendRequest(
                                    $request,
                                    'https://api.stripe.com/v1/plans',
                                    null
                                );
                                if (!$response['id']) {
                                    throw new \Magento\Framework\Exception\LocalizedException(
                                        __('Something went wrong while creating plans. Please try again later.')
                                    );
                                }
                            }
                        }
                    }
                }

                $finalBilling = [];
                foreach ($billing as $item) {
                    if (array_key_exists('is_delete', $item)) {
                        if ($item['is_delete']) {
                            continue;
                        }
                    }

                    array_push($finalBilling, $item);
                }

                // Save new billing options
                if ($object->getId()) {
                    $object->setValue(serialize($finalBilling))->save();
                } else {
                    $data = [
                        'entity_id' => $productId,
                        'value' => serialize($finalBilling)
                    ];
                    $model->setData($data)->save();
                }
            }
        }
    }

    public function checkPlanExistBefore($option, $options_before)
    {
        $is_exist_before = 0;
        foreach ($options_before as $option_before) {
            if ($option['plan_id'] == $option_before['plan_id']) {
                $is_exist_before = 1;
            }
        }

        return $is_exist_before;
    }

    public function checkPlanExistAfter($option, $options_after)
    {
        $is_exist_after = 0;
        foreach ($options_after as $option_after) {
            if ($option['plan_id'] == $option_after['plan_id']) {
                $is_exist_after = 1;
            }
        }

        return $is_exist_after;
    }
}
