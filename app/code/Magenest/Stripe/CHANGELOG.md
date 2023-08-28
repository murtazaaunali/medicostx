# Change Log
All notable changes to this extension will be documented in this file.
This extension adheres to [Magenest](http://magenest.com/).

## [100.3.0] - 2017-12-27
Stripe now compatible with 
```
Magento Commerce 2.1.x, 2.2.x, 
Magento OpenSource 2.1.x, 2.2.x
```
### Added
-   Improve security
-   Support: Stripe.js v3
-   Support: Apple Pay
-   Support: Android Pay(Pay with Google)
-   Support: Giro Pay
-   Support: Alipay
-   Add validate payment source when receive from customer
-   Stripe logger will stored in var/log/stripe
-   Add sort order option in backend
-   Add Payment Instruction text box in backend
-   Add support information in backend
### Fixed
-   Save card, delete card error
-   Fix bug response duplicated. 
### Removed
-   Remove dependency with Stripe Library (Don't need to run `composer require stripe/stripe-php`)
-   Remove option enable debug log

## [100.2.0] - 2017-10-11
-   Stripe payment now support for Mageneto 2.2, Php 7.1.x
### Fixed
-   Fix bug order status when create subscription with shipping product.
-   Fix bug status = fraud when create subscription by cronjob.
-   Fix payment performing 3d secure action
-   Fix checkout layout
### Added
-   Add payment notify for customer when credit card rejected by bank.
-   Add notify warning when customer input wrong public key or private key.
-   Add console debug in web browser

## [1.0.8] - 2017-07-16
### Added
-   User can save 3d secure card
-   Subscription order with 3d secure card
### Fixed
-   Fix bug check 3d secure source when data response
-   Fix bug send email for customer
-   Fix bug order status
-   Fix bug show message error.
### Removed
-   Alipay (current not support)

## [1.0.7] - 2017-06-10
### Added
-   3d secure for stripe payment
-   3d secure for stripe payment iframe
### Fixed
-   Fix bug shipping address
-   Fix bug iframe payment display

## [1.0.6] - 2017-04-16
### Added
-   Save card for stripe Payment
### Fixed
-   Fix bug payment page
-   Fix bug payment Stripe iframe
-   Fix bug stripe bit coin
-   fix bug email error
-   Fix missing card info
-   fix code
### Removed
-   Send mail func 
-   Order total limit

## [1.0.5] - 2017-02-16
### Added
- Fix Javascript issues relating to Stripe.js

## [1.0.4] - 2017-01-06
### Added
1. Stripe Checkout iframe is added
2. If Stripe Checkout is enabled, admin can allow customers to checkout using Credit Card, Alipay or Bitcoin
3. Fix grouped product error on frontend

## [1.0.3] - 2016-11-27
### Added
1. A new order will now be created as a new billing cycle starts
2. Minor logic fixes

## [1.0.2] - 2016-10-13
### Added
1. Admin can enable Stripe.js
2. Additional frontend interface fixes
3. Customer now can decide how many billing cycles that the subscription will go on
4. Fix bug in checkout page when customer can not place order with other methods when Stripe is not active

## [1.0.1] - 2016-07-30
### Added
1. Magento 2.1 compatible

## [1.0.0] - 2016-06-15
### Added
1. Allow customers to checkout using Stripe Payment Gateway
2. Allow admins to easily tweak and manage payments via Stripe
3. Allow admins to manage Stripe subscription profiles for each products
4. Allow customers to subscribe to a recurring subscription product.