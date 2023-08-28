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
        'Magento_Checkout/js/action/place-order',
        'Magento_Checkout/js/action/set-payment-information',
        'Magento_Checkout/js/model/full-screen-loader',
        'Magento_Checkout/js/checkout-data',
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/model/full-screen-loader',
        'Magento_Checkout/js/action/redirect-on-success',
        'Magento_Checkout/js/model/payment/additional-validators',
        'Magenest_Stripe/js/model/payment/messages',
        'https://js.stripe.com/v3/'
    ],
    function ($,
              ko,
              Component,
              placeOrderAction,
              setPaymentInformationAction,
              fullScreenLoadern,
              checkoutData,
              quote,
              fullScreenLoader,
              redirectOnSuccessAction,
              additionalValidators,
              messageContainer
    ) {
        'use strict';

        var stripe = Stripe(window.magenest.stripe.publishableKey);

        return Component.extend({
            defaults: {
                template: 'Magenest_Stripe/payment/stripe-payments-applepay',
                redirectAfterPlaceOrder: true,
                isPlaceOrderSuccessful: false,
                chargeId: '',
                rawCardData:"",
                token:"",
                threedsecure:"",
                payType:""
            },
            messageContainer: messageContainer,

            getCode: function () {
                return 'magenest_stripe_applepay';
            },

            initialize: function () {
                var self = this;
                this._super();
                this.isPlaceOrderActionAllowed.subscribe(function(allowed){
                    if (allowed) {
                        this.requestPayment();
                    }
                }.bind(this))
            },

            requestPayment: function (data, event, parent) {
                var self;
                if(typeof parent !== 'undefined'){
                    self = parent;
                }else{
                    self = this;
                }

                var currencyCode = window.checkoutConfig.quoteData.base_currency_code;
                currencyCode = currencyCode.toLowerCase();
                var baseTotal = window.checkoutConfig.quoteData.base_grand_total;
                var zerodecimal = ['bif', 'djf', 'jpy', 'krw', 'pyg', 'vnd', 'xaf', 'xpf', 'clp', 'gnf', 'kmf', 'mga', 'rwf', 'vuv', 'xof'];
                if (zerodecimal.indexOf(currencyCode)){
                    baseTotal = baseTotal*100;
                }
                var paymentRequest = stripe.paymentRequest({
                    country: 'US',
                    currency: currencyCode.toLowerCase(),
                    total: {
                        label: 'Total',
                        amount: Math.round(baseTotal),
                    },
                });

                var elements = stripe.elements();
                var prButton = elements.create('paymentRequestButton', {
                    paymentRequest: paymentRequest,
                });

                // Check the availability of the Payment Request API first.
                paymentRequest.canMakePayment().then(function(result) {
                    console.log(result);
                    if (result) {
                        prButton.mount('#payment_section');
                    } else {
                        document.getElementById('payment_section').style.display = 'none';
                    }
                });

                paymentRequest.on('token', function(ev) {
                    // Send the token to your server to charge it!
                    self.rawCardData = ev.token;
                    self.token = ev.token.id;

                    self.isPlaceOrderActionAllowed(false);
                    self.getPlaceOrderDeferredObject()
                        .fail(function () {
                            self.isPlaceOrderActionAllowed(true);
                            ev.complete('fail');
                        })
                        .done(function () {
                                ev.complete('success');
                                self.afterPlaceOrder();
                                if (self.redirectAfterPlaceOrder) {
                                    redirectOnSuccessAction.execute();
                                }
                            }
                        );

                    // $.post(window.magenest.stripe.chargeUrl, { token: ev.token.id, form_key:window.checkoutConfig.formKey}).done(function(magenestResponse) {
                    //     console.log(magenestResponse);
                    //     if(magenestResponse.success){
                    //         self.chargeId = magenestResponse.charge_id;
                    //         ev.complete('success');
                    //         //redirect place order
                    //         self.getPlaceOrderDeferredObject().fail(function () {
                    //             self.isPlaceOrderActionAllowed(true);
                    //         }).done(function () {
                    //             if (self.redirectAfterPlaceOrder) {
                    //                 self.afterPlaceOrder();
                    //                 redirectOnSuccessAction.execute();
                    //             }
                    //         });
                    //     }
                    //     if(magenestResponse.error){
                    //         ev.complete('fail');
                    //     }
                    //
                    //     return true;
                    // }).fail(function() {
                    //     self.isPlaceOrderActionAllowed(true);
                    //     if (window.magenest.stripe.applePayDebug) $.post( window.magenest.stripe.appleLogUrl, {apple :'error when posting' });
                    //
                    // });

                });

            },

            /**
             * @return {*}
             */
            getPlaceOrderDeferredObject: function () {
                return $.when(
                    placeOrderAction(this.getData(), this.messageContainer)
                );
            },

            /**
             * Get payment method data
             */
            getData: function () {
                var self = this;
                return {
                    'method': this.item.method,
                    'po_number': null,
                    'additional_data': {
                        token: self.token,
                        // chargeId : self.chargeId,
                        pay_type : "apple pay",
                        rawCardData: JSON.stringify(self.rawCardData)
                    }
                };
            },
            isActive: function() {
                return true;
            }

        });

    }
);