/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

define([
    'jquery',
    'Magenest_Stripe/order/create/scripts'
], function (jQuery) {
    'use strict';

    var $el = jQuery('#edit_form'),
        config,
        baseUrl,
        order,
        payment;

    if (!$el.length || !$el.data('order-config')) {
        return;
    }

    config = $el.data('order-config');
    baseUrl = $el.data('load-base-url');

    order = new StripeOrder(config);
    order.setLoadBaseUrl(baseUrl);

    payment = {
        switchMethod: order.switchPaymentMethod.bind(order)
    };

    window.order = order;
    window.payment = payment;
});