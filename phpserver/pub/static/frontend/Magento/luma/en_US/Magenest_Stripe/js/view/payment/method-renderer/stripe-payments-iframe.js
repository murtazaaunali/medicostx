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
        'https://checkout.stripe.com/checkout.js'
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
              messageContainer
    ) {
        'use strict';

        var handler = StripeCheckout.configure({
            key: window.magenest.stripe.publishableKey,
            locale: 'auto'
        });
        
        return Component.extend({
            defaults: {
                template: 'Magenest_Stripe/payment/stripe-payments-iframe',
                redirectAfterPlaceOrder: false,
                isPlaceOrderSuccessful: false,
                subscriptionId: '',
                chargeId: '',
                rawCardData:"",
                token:"",
                threedsecure:"",
                payType:""
            },
            messageContainer: messageContainer,

            getCode: function () {
                return 'magenest_stripe_iframe';
            },

            bodyFreezeScroll: function () {
                var bodyWidth = window.document.body.offsetWidth;
                var css = window.document.body.style;
                css.overflow = "hidden";
                css.marginTop = "0px";
                css.marginRight = (css.marginRight ? '+=' : '') + (window.document.body.offsetWidth - bodyWidth);
            },

            iframePlaceOrder: function () {
                function stripeResponseHandler(status, response) {
                    //console.log(response);
                    self.token = response.id;
                    self.rawCardData = response.card;
                    self.threedsecure = response.card.three_d_secure;
                    self.placeOrder();
                }

                var self = this;
                self.isPlaceOrderActionAllowed(false);
                fullScreenLoader.startLoader();

                $.ajax({
                    url: window.magenest.stripe.iframeConfigUrl,
                    type: 'GET',
                    async: true,
                    success: function (result) {
                        //console.log(result);
                        fullScreenLoader.stopLoader(true);

                        if (!result.error) {
                            var canCollectBilling = Boolean(result.can_collect_billing === '1');
                            var canCollectZipCode = Boolean(result.can_collect_zip === '1');
                            var allowRemember = Boolean(result.allow_remember === '1');
                            var acceptBitcoin = Boolean(result.accept_bitcoin === '1');
                            var acceptAlipay = Boolean(result.accept_alipay === '1');
                            handler.open({
                                name: result.display_name,
                                amount: result.grand_total,
                                currency: result.order_currency,
                                email: result.customer_email,
                                billingAddress: canCollectBilling,
                                locale: "en",
                                zipCode: canCollectZipCode,
                                image: result.image_url,
                                allowRememberMe: allowRemember,
                                bitcoin: acceptBitcoin,
                                alipay: acceptAlipay,
                                token: function (response) {
                                    //console.log(response);
                                    self.payType = response.type;
                                    if(response.type==='card') {
                                        var address = quote.billingAddress();
                                        Stripe.source.create({
                                            type: 'card',
                                            token: response.id,
                                            owner: {
                                                address: {
                                                    postal_code: response.card.address_zip,
                                                    city: response.card.address_city,
                                                    country:response.card.country,
                                                    line1:response.card.address_line1,
                                                    line2:response.card.address_line2,
                                                    state:response.card.address_state
                                                },
                                                name: response.card.name,
                                                email: response.email,
                                                //phone: address.telephone
                                            }
                                        }, stripeResponseHandler);
                                    }

                                    if(response.type==='source_bitcoin') {
                                        self.token = response.id;
                                        self.rawCardData = "";
                                        self.threedsecure = "not_supported";
                                        self.placeOrder();
                                    }

                                },
                                closed: function () {
                                    self.isPlaceOrderActionAllowed(true);
                                }
                            });
                            self.bodyFreezeScroll();
                        }
                    }
                });
            },

            afterPlaceOrder: function () {
                var self = this;
                $.ajax({
                    url: window.magenest.stripe.threedSecureUrl,
                    dataType: "json",
                    type: 'POST',
                    success: function (response) {
                        //console.log(response);
                        if (response.success) {
                            //default pay -> success page
                            if(response.defaultPay){
                                redirectOnSuccessAction.execute();
                            }
                            if(response.threeDSercueActive){
                                window.location = response.threeDSercueUrl;
                            }

                        }
                        if (response.error){
                            self.messageContainer.addErrorMessage({
                                message: response.message
                            });
                        }
                    },
                    error: function (response) {
                        self.messageContainer.addErrorMessage({
                            message: "Error, please try again"
                        });
                    }
                })
            },

            isActive: function() {
                return true;
            },

            getData: function() {
                var self = this;
                return {
                    'method': this.item.method,
                    'additional_data': {
                        "stripe_token": this.token,
                        "raw_card_data": JSON.stringify(this.rawCardData),
                        "three_d_secure": this.threedsecure,
                        "pay_type": this.payType
                    }
                }
            }
        });

    }
);
