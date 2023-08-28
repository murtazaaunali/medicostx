<?php
/**
 * Created by PhpStorm.
 * User: thaivh
 * Date: 28/3/17
 * Time: 09:04
 */

namespace Magenest\Stripe\Controller\Customer;

use Magenest\Stripe\Controller\Customer\Card as Card;

class Del extends Card
{
    public function execute()
    {
        if ($this->getRequest()->isAjax()) {
            $customerId = $this->_customerSession->getCustomerId();
            $result = $this->_jsonFactory->create();
            $id = $this->getRequest()->getParam('id');
            $cardModel = $this->_cardFactory->create();
            $data = $cardModel->load($id);
            $cardId = $data->getData('card_id');
            $status = $data->getData('status');
            $tableMagentoCustomerId = $data->getData('magento_customer_id');
            if ($tableMagentoCustomerId != $customerId) {
                return $result->setData([
                    'success' => false,
                    'mess' => "Exception when delete"
                ]);
            }
            $customer = $this->_customerFactory->create()
                ->getCollection()
                ->addFieldToFilter('magento_customer_id', $customerId)
                ->getFirstItem();
            $stripeCustomerId = $customer->getData()['stripe_customer_id'];
            $out = [];
            if ($status !== "error") {
                try {
                    $urlCus = "https://api.stripe.com/v1/customers/" . $stripeCustomerId;
                    $url = "https://api.stripe.com/v1/customers/" . $stripeCustomerId . "/sources/" . $cardId;
                    $response = $this->_helper->sendRequestDelete($url);
                    if ($response['id']) {
                        $response = $this->_helper->sendRequest([], $url, 'get');
                        if ($response['error']) {
                            $data->delete();
                            $out = [
                                'success' => true,
                                'mess' => 'success'
                            ];
                        } else {
                            $out = [
                                'success' => false,
                                'mess' => "Has something wrong while setting your default card!! Please try again"
                            ];
                            $data->setData("status", "error");
                            $data->save();
                        }
                    }
                    $customer = $this->_helper->sendRequest([], $urlCus, "get");
                    if (isset($customer['default_source'])) {
                        $this->_stripeModel->changeCardToDefault($customerId, $customer['default_source']);
                        $out['default'] = '';
                        $defaultSource = $cardModel->getCollection()
                            ->addFieldToFilter('card_id', $customer['default_source'])
                            ->getFirstItem()->getId();
                        if ($defaultSource) {
                            $out['default'] = $defaultSource;
                        }
                    }
                } catch (\Exception $e) {
                    $out = [
                        'success' => false,
                        'mess' => "Has something wrong while setting your default card !!"
                    ];
                    $data->setData("status", "error");
                    $data->save();
                }
            } else {
                $data->delete();
                $out = [
                    'success' => true,
                    'mess' => 'success'
                ];
            }
            $result->setData($out);

            return $result;
        }

        return "er";
    }
}
