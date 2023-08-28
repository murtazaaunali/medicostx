/**
 * Created by joel on 31/12/2016.
 */
/*browser:true*/
/*global define*/
define(
    [
        'jquery',
        'ko',
        'Magento_Checkout/js/view/payment/default',
        'Magento_Checkout/js/action/set-payment-information',
        'Magento_Checkout/js/model/full-screen-loader',
        'Magento_Checkout/js/checkout-data',
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/model/full-screen-loader',
        'Magento_Checkout/js/action/redirect-on-success',
        'Magento_Checkout/js/model/payment/additional-validators',
        'Magenest_Stripe/js/model/payment/messages',
        'Magento_Customer/js/model/customer',
        'Magento_Checkout/js/action/set-billing-address',
        'mage/url'
    ],
    function ($,
              ko,
              Component,
              setPaymentInformationAction,
              fullScreenLoadern,
              checkoutData,
              quote,
              fullScreenLoader,
              redirectOnSuccessAction,
              additionalValidators,
              messageContainer,
              customer,
              setBillingAddressAction,
              url
    ) {
        'use strict';

        //var quoteData = window.checkoutConfig.quoteData;
        //var stripe, elements;
        return Component.extend({
            defaults: {
                template: 'Magenest_Stripe/payment/stripe-payments-alipay',
                redirectAfterPlaceOrder: true
            },
            messageContainer: messageContainer,

            initialize: function () {
                var self = this;
                this._super();
                //stripe = Stripe(window.magenest.stripe.publishableKey);
                //elements = stripe.elements();
            },

            requestAlipay: function () {
                var self = this;
                fullScreenLoader.startLoader();
                self.isPlaceOrderActionAllowed(false);
                setBillingAddressAction();
                setPaymentInformationAction(
                    self.messageContainer,
                    {
                        method: this.getCode()
                    }
                );
                $.post(
                    url.build('stripe/checkout/alipaySource'), {
                        form_key:window.checkoutConfig.formKey,
                        billingAddress: ko.toJSON(quote.billingAddress()),
                        shippingAddress: ko.toJSON(quote.shippingAddress()),
                        guestEmail: quote.guestEmail
                    }).done(function(response) {
                    fullScreenLoader.stopLoader(true);
                    self.isPlaceOrderActionAllowed(true);
                    if(response.success){
                        $.mage.redirect(response.redirect_url);
                    }
                    if(response.error){
                        self.messageContainer.addErrorMessage({
                            message: "Payment error"
                        });
                        console.log(response);
                    }
                }).fail(function() {
                        fullScreenLoader.stopLoader(true);
                        self.isPlaceOrderActionAllowed(true);
                        self.messageContainer.addErrorMessage({
                            message: "Payment error"
                        });

                    }
                );
                // var currency = quoteData.base_currency_code;
                // currency = currency.toLowerCase();
                // var amount = quoteData.base_grand_total;
                // if (!window.magenest.stripe.isZeroDecimal){
                //     amount = amount * 100;
                // }
                // stripe.createSource({
                //     type: 'alipay',
                //     amount: amount,
                //     currency: currency,
                //     redirect: {
                //         return_url: url.build('stripe/checkout/alipayResponse'),
                //     }
                // }).then(function(result) {
                //     console.log(result);
                //     if(result.source){
                //         //$.mage.redirect(result.source.redirect.url);
                //     } else{
                //         if(result.error){
                //             self.messageContainer.addErrorMessage({
                //                 message: result.error.message
                //             });
                //         }
                //     }
                // });
            },

            getCode: function () {
                return 'magenest_stripe_alipay';
            }
        });
    }
);