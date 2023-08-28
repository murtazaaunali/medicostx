<?php
/**
 * Created by Magenest.
 * Author: Pham Quang Hau
 * Date: 25/05/2016
 * Time: 16:49
 */

namespace Magenest\Stripe\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Subscription extends AbstractDb
{
    protected function _construct()
    {
        $this->_init('magenest_stripe_subscription', 'id');
    }
}
