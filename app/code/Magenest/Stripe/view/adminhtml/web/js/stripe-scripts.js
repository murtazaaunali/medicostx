/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
define([
    'jquery',
    'uiComponent',
    'underscore',
    'https://js.stripe.com/v2/'
], function ($, Class) {
    'use strict';

    $('#btn_tokenizer').on("click", function (e) {
        e.preventDefault();
        e.stopPropagation();
        var form = $('#edit_form'),
            ccNumber =  form.find("#magenest_stripe_cc_number"),
            ccCid = form.find("#magenest_stripe_cc_cid");
        var token;
        var firstName = $("#order-billing_address_firstname");
        var lastName = $("#order-billing_address_lastname");
        Stripe.card.createToken({
            number: ccNumber.val(),
            cvc: ccCid.val(),
            exp_month: $('#magenest_stripe_expiration').val(),
            exp_year: $('#magenest_stripe_expiration_yr').val(),
            name: firstName + " " + lastName,
            address_line1: $("#order-billing_address_street0").val(),
            address_city: $("#order-billing_address_city").val(),
            address_state: $("#order-billing_address_region_id").val(),
            address_zip: $("#order-billing_address_postcode").val(),
            address_country: $("#order-billing_address_country_id").val()
        }, function (status, response) {
            console.log(response);
            if (response.error) {
                $('#text_warning').text("Card validate error");
                alert("apply card error");
                form.find('#magenest_stripe_token').val("");
                form.find('#magenest_stripe_cc_number').val("");
                form.find('#magenest_stripe_cc_cid').val("");
            } else {
                $('#text_warning').text("Card validated");
                token = response.id;
                //console.log(token);
                form.find('#magenest_stripe_token').val(token);
                // form.find('#magenest_stripe_cc_number').val("****************");
                // form.find('#magenest_stripe_cc_cid').val("****");
            }
        });

    });

    return Class.extend({
        defaults: {
            formSelector: '#edit_form',
            active: true,
            scriptLoaded: false,
            imports: {
                onActiveChange: 'active'
            },
            code: "magenest_stripe"
        },

        initObservable: function () {
            //console.log(window.publicKey);
            this._super()
                .observe('active scriptLoaded');
            $(this.formSelector).off('changePaymentMethod.' + this.code)
                .on('changePaymentMethod.' + this.code, this.changePaymentMethod.bind(this));
            Stripe.setPublishableKey(window.publicKey);
            return this;
        },

        onActiveChange: function (isActive) {
            //console.log(isActive);
           // this.disableEventListeners();

            //if (isActive) {
                window.order.addExcludedPaymentMethod(this.code);

                if (!this.scriptLoaded()) {
                    this.loadScript();
                }
                this.enableEventListeners();
            //}
        },

        enableEventListeners: function () {
            // $(this.formSelector).on('invalid-form.validate.' + this.code, this.invalidFormValidate.bind(this))
            //     .on('afterValidate.beforeSubmit', this.beforeSubmit.bind(this));

            $(this.formSelector).on('afterValidate.beforeSubmit', this.beforeSubmit.bind(this));
        },

        disableEventListeners: function () {
            $(self.formSelector).off('invalid-form.validate.' + this.code)
                .off('afterValidate.beforeSubmit');
        },

        loadScript: function () {
            //var state = this.scriptLoaded;

            //$('body').trigger('processStart');
            // require([this.cryptUrl], function () {
            //     state(true);
            //     $('body').trigger('processStop');
            // });

            return this;
        },

        changePaymentMethod: function (event, method) {
            this.active(method === this.code);

            return this;
        },

        invalidFormValidate: function () {
            return this;
        },

        beforeSubmit: function () {
            return this;
        }

    });
});
