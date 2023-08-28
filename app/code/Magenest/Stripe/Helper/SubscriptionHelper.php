<?php
/**
 * Created by PhpStorm.
 * User: magenest
 * Date: 10/07/2017
 * Time: 16:00
 */

namespace Magenest\Stripe\Helper;

class SubscriptionHelper
{
    protected $_helper;

    public function __construct(
        Data $stripeDataHelper
    ) {
        $this->_helper = $stripeDataHelper;
    }

    public function retrievePlan($planId)
    {
        $url = 'https://api.stripe.com/v1/plans/' . urlencode($planId);
        $response = $this->_helper->sendRequest("", $url, 'post');

        return $response;
    }

    public function calTrialPeriodDay($planId)
    {
        try {
            $day = 0;
            $planData = $this->retrievePlan($planId);
            if (isset($planData['object']) && ($planData['object'] == 'plan')) {
                $interval = $planData['interval'];
                $intervalCount = $planData['interval_count'];
                $day = $intervalCount;
                switch ($interval) {
                    case 'day':
                        break;
                    case 'week':
                        $day = $intervalCount * 7;
                        break;
                    case 'month':
                        $day = $intervalCount * 30;
                        break;
                    case 'year':
                        $day = $intervalCount * 365;
                        break;
                }
            }

            return $day;
        } catch (\Exception $e) {
            return 0;
        }
    }
}
