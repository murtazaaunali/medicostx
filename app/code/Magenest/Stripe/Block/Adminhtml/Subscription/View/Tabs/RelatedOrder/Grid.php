<?php
/**
 * Created by Magenest.
 * Author: Pham Quang Hau
 * Date: 27/05/2016
 * Time: 20:09
 */

namespace Magenest\Stripe\Block\Adminhtml\Subscription\View\Tabs\RelatedOrder;

use Magento\Framework\Registry;
use Magento\Backend\Block\Template\Context;
use Magento\Backend\Helper\Data;
use Magento\Sales\Model\OrderFactory;

class Grid extends \Magento\Backend\Block\Widget\Grid\Extended
{
    protected $_coreRegistry;

    protected $_orderFactory;

    public function __construct(
        Context $context,
        Data $backendHelper,
        Registry $registry,
        OrderFactory $orderFactory,
        $data = []
    ) {
        $this->_coreRegistry = $registry;
        $this->_orderFactory = $orderFactory;
        parent::__construct($context, $backendHelper, $data);
    }

    protected function _construct()
    {
        parent::_construct();
        $this->setId('postGrid');
        $this->setDefaultSort('order_id');
        $this->setDefaultDir('ASC');
        $this->setSaveParametersInSession(true);
        $this->setUseAjax(true);
        $this->setVarNameFilter('post_filter');
    }

    protected function _prepareCollection()
    {
        /** @var \Magenest\Stripe\Model\Subscription $subscription */
        $subscription = $this->_coreRegistry->registry('stripe_subscription_model');
        $orderId = $subscription->getOrderId();
        $sequenceOrderIds = unserialize($subscription->getData('sequence_order_ids'));
        if (!$sequenceOrderIds) {
            $sequenceOrderIds = [$orderId];
        }

        /** @var \Magento\Sales\Model\Order $order */
        $order = $this->_orderFactory->create();

        array_unshift($sequenceOrderIds, $orderId);
        $collection = $order->getCollection()->addFieldToFilter('increment_id', $sequenceOrderIds);
        $this->setCollection($collection);

        parent::_prepareCollection();

        return $this;
    }

    protected function _prepareColumns()
    {
        $this->addColumn(
            'entity_id',
            [
                'header' => __('ID'),
                'type' => 'text',
                'index' => 'increment_id',
                'header_css_class' => 'col-id',
                'column_css_class' => 'col-id'
            ]
        );
        $this->addColumn(
            'created_at',
            [
                'header' => __('Purchased Date'),
                'index' => 'created_at',
                'type' => 'datetime'
            ]
        );
        $this->addColumn(
            'customer_email',
            [
                'header' => __('Customer Email'),
                'index' => 'customer_email',
                'type' => 'text'
            ]
        );
        $this->addColumn(
            'base_grand_total',
            [
                'header' => __('Grand Total (Base)'),
                'index' => 'base_grand_total',
                'type' => 'text'
            ]
        );
        $this->addColumn(
            'grand_total',
            [
                'header' => __('Grand Total (Purchased)'),
                'index' => 'grand_total',
                'type' => 'text'
            ]
        );
        $this->addColumn(
            'status',
            [
                'header' => __('Status'),
                'index' => 'status',
                'type' => 'text'
            ]
        );

        $block = $this->getLayout()->getBlock('grid.bottom.links');
        if ($block) {
            $this->setChild('grid.bottom.links', $block);
        }

        return parent::_prepareColumns();
    }

    public function getRowUrl($row)
    {
        return $this->getUrl(
            'sales/order/view',
            ['order_id' => $row->getEntityId()]
        );
    }
}
