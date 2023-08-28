/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
/*browser:true*/
/*global define*/
define(
    [
        'Magento_Payment/js/view/payment/cc-form',
        'jquery',
        'Magento_Checkout/js/model/quote',
        'Magento_Customer/js/model/customer',
        'Magento_Checkout/js/model/full-screen-loader',
        'Magento_Checkout/js/action/redirect-on-success',
        'Magenest_Stripe/js/model/payment/messages',
        'Magento_Checkout/js/model/payment/additional-validators',
        'Magenest_Stripe/js/model/credit-card-validation/validator'
    ],
    function (Component, $, quote,customer, fullScreenLoader, redirectOnSuccessAction, messageContainer, additionalValidators) {
        'use strict';

        var address = quote.isVirtual() ? quote.billingAddress() : quote.shippingAddress();
        return Component.extend({
            defaults: {
                template: 'Magenest_Stripe/payment/stripe-payments-method',
                token: '',
                isSave: window.magenest.stripe.isSave,
                hasCard: (window.magenest.stripe.hasCard>0),
                id: '',
                redirectAfterPlaceOrder: false,
                threedsecure: "",
                rawCardData: ""
            },
            messageContainer: messageContainer,

            // Joel: added token function
            initStripe: function() {
                var self = this;

                var stripeResponseHandler = function (status, response) {
                    console.log(response);
                    if (response.error) {
                        self.messageContainer.addErrorMessage({
                            message: response.error.message
                        });
                        $(".loading-mask").hide();
                        self.isPlaceOrderActionAllowed(true);
                    } else {
                        self.token = response.id;
                        self.threedsecure = response.card.three_d_secure;
                        self.rawCardData = response.card;
                        self.placeOrder();
                    }
                };

                return stripeResponseHandler;
            },

            createToken: function() {
                var self = this;
                if (self.getVal() === "0") {
                    var firstName = quote.billingAddress().firstname;
                    var lastName = quote.billingAddress().lastname;
                    //fullScreenLoader.startLoader();
                    this.isPlaceOrderActionAllowed(false);
                    if (this.validate()) {
                        var stripeResponseHandler = this.initStripe();
                        var address = quote.billingAddress();
                        var owner = {
                            address: {
                                postal_code: address.postcode,
                                city:address.city,
                                country:address.countryId,
                                line1:address.street[0],
                                line2:address.street[1],
                                state:address.region
                            },
                            name: firstName +" "+ lastName,
                            email: (customer.customerData.email===null)?quote.guestEmail:customer.customerData.email
                        };

                        if (address.telephone) {
                            owner.phone = address.telephone;
                        }

                        Stripe.source.create({
                            type: 'card',
                            card: {
                                number: $('#magenest_stripe_cc_number').val(),
                                cvc: $('#magenest_stripe_cc_cid').val(),
                                exp_month: $('#magenest_stripe_expiration').val(),
                                exp_year: $('#magenest_stripe_expiration_yr').val()
                            },
                            owner: owner
                        }, stripeResponseHandler);
                    } else {
                        $(".loading-mask").hide();
                        this.isPlaceOrderActionAllowed(true);
                    }
                }
                else {
                    self.token = "0";
                    self.placeOrder();
                }
            },
            addOption: function (id) {
                var checkFlag = window.magenest.stripe.hasCard;
                var cards = $.parseJSON(window.magenest.stripe.cards);
                if (checkFlag>0)
                {
                    var i;
                    $('#'+id).append('<option value="0">Select a saved card</option>');
                    for (i=0; i<checkFlag; i++){
                        var option = '<option value="'+cards[i].card_id+'">' +
                            cards[i].brand + ' xxxxxxxxxxxx'+ cards[i].last4 + '  ' + cards[i].exp_month + '/' + cards[i].exp_year +
                            '</option>';
                        $('#'+id).append(option);
                    }
                }
            },
            initialize: function () {
                var self = this;
                this._super();
                this.isAdd();
            },
            isAdd: function() {
                var self = this;
                var check = true;
                var myInterval = setInterval(function() {
                    self.selectFunc(check);
                }, 1000);
            },
            getVal: function () {
                var self = this;
                var card_id = $('#'+ self.getCode() + '-card-id').val();
                if (typeof (card_id) == 'undefined'){
                    card_id = "0";
                }
                return card_id;

            },
            selectFunc : function (check) {
                var self = this;
                $('#'+self.getCode() + '-card-id').on('change', function () {
                    var card = this.value;
                    //console.log(self.getVal());
                    if (card === "0"){
                        if (!check)
                        {
                            fullScreenLoader.startLoader();
                            $('#'+self.getCode() + '-form-div').show();
                            setTimeout(function(){
                                fullScreenLoader.stopLoader();
                            }, 700);
                            check = true;
                        }
                    }
                    else{
                        if (check)
                        {
                            fullScreenLoader.startLoader();
                            $('#'+self.getCode() + '-form-div').hide();
                            setTimeout(function(){
                                fullScreenLoader.stopLoader();
                            }, 700);
                            check = false;
                        }
                    }
                });
            },
            getData: function() {
                var self = this;
                var card_id = self.getVal();
                var isSave = $('#stripe-save').is(":checked");
                return {
                    'method': this.item.method,
                    'additional_data': {
                        'saved': isSave ? "1" : "0",
                        "stripe_token": this.token,
                        "card_id": card_id,
                        "three_d_secure": this.threedsecure,
                        "raw_card_data": JSON.stringify(this.rawCardData)
                    }
                }
            },
            // End of token functions

            getCode: function() {
                return 'magenest_stripe';
            },

            isActive: function() {
                return true;
            },

            validate: function() {
                var self = this;
                if(window.magenest.stripe.publishableKey===""){
                    self.messageContainer.addErrorMessage({
                        message: "Stripe public key error"
                    });
                    return false;
                }
                return true;
            },

            getInstructions: function () {
                return window.magenest.stripe.instructions;
            },

            placeOrder: function (data, event) {
                var self = this;

                if (event) {
                    event.preventDefault();
                }

                if (this.validate() && additionalValidators.validate()) {
                    this.isPlaceOrderActionAllowed(false);

                    this.getPlaceOrderDeferredObject()
                        .fail(
                            function () {
                                $(".loading-mask").hide();
                                self.isPlaceOrderActionAllowed(true);
                            }
                        ).done(
                        function () {
                            self.afterPlaceOrder();

                            if (self.redirectAfterPlaceOrder) {
                                redirectOnSuccessAction.execute();
                            }
                        }
                    );

                    return true;
                }

                return false;
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
            }
        });

    }
);
