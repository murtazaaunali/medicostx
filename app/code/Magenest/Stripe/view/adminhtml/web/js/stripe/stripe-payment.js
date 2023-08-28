/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
define([
    'jquery',
    'Magenest_Stripe/js/stripe-scripts',
    'underscore'
], function ($, StripePay) {
    'use strict';

    return StripePay.extend({
        // invalidFormValidate: function () {
        //     $('#' + self.code + '_cc_number').val('');
        //     $('#' + self.code + '_cc_cid').val('');
        //
        //     return this;
        // },

        // beforeSubmit: function (event) {
        //
        //     var form = $(event.target),
        //         ccNumber =  form.find("#magenest_stripe_cc_number"),
        //         ccCid = form.find("#magenest_stripe_cc_cid");
        //     var token;
        //
        //
        //         var firstName = $("#order-billing_address_firstname");
        //         var lastName = $("#order-billing_address_lastname");
        //         Stripe.card.createToken({
        //             number: ccNumber.val(),
        //             cvc: ccCid.val(),
        //             exp_month: $('#magenest_stripe_expiration').val(),
        //             exp_year: $('#magenest_stripe_expiration_yr').val(),
        //             name: firstName + " " + lastName,
        //             address_line1: $("#order-billing_address_street0").val(),
        //             address_city: $("#order-billing_address_city").val(),
        //             address_state: $("#order-billing_address_region_id").val(),
        //             address_zip: $("#order-billing_address_postcode").val(),
        //             address_country: $("#order-billing_address_country_id").val()
        //         }, function (status, response) {
        //             console.log(response);
        //             if (response.error) {
        //
        //             } else {
        //                 token = response.id;
        //                 console.log(token);
        //                 form.find('#magenest_stripe_token').val(token);
        //
        //             }
        //         });
        //
        //
        //
        //     return this;
        // }
    });
});
