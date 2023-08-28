<?php
/**
 * Created by Magenest.
 * Author: Pham Quang Hau
 * Date: 17/05/2016
 * Time: 15:12
 */

namespace Magenest\Stripe\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Customer extends AbstractDb
{
    protected function _construct()
    {
        $this->_init('magenest_stripe_customer', 'id');
    }
}
