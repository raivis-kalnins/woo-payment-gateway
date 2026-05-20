=== Universal Payment Gateway for WooCommerce ===
Contributors: universal-payment-gateway
Tags: woocommerce, payment gateway, hosted payment, natwest, stripe, paypal
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.1
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Universal hosted-payment gateway framework for WooCommerce with separate configurable provider sections for UK, Baltic and international payment providers.

== Description ==

Universal Payment Gateway for WooCommerce creates one WooCommerce payment method with multiple provider options inside it. Store admins can enable, disable, configure and test each provider independently.

Included provider sections:

* NatWest
* Barclays
* Lloyds
* HSBC
* Standard Chartered
* PayPal
* Amazon Pay
* Square
* WooPayments
* Stripe
* Swedbank
* SEB
* Luminor
* Revolut

Each provider includes a checkout label, checkout description, Test/Live mode, Test/Live Gateway URL, Test/Live Merchant or Store ID, Test/Live Shared Secret, help text and configuration test button.

Important: this is a universal hosted redirect framework. Some providers require their official WooCommerce extensions or provider-specific APIs for full native wallet, refund, webhook, tokenisation or payment-intent functionality.

== Installation ==

1. Upload the plugin zip from WordPress Admin > Plugins > Add New Plugin > Upload Plugin.
2. Activate the plugin.
3. Go to WooCommerce > Settings > Payments.
4. Enable Universal Payment Gateway.
5. Click Manage.
6. Configure the general settings and each provider you need.

== Upgrade Notice ==

= 1.0.0 =
Renames the gateway from the old single-provider Rack Group style into a universal provider-independent payment gateway. Back up the site, deactivate the old plugin, activate this package and test before Live mode.

== Frequently Asked Questions ==

= Does this replace official Stripe, PayPal, Square or WooPayments plugins? =

No. This plugin provides hosted redirect configuration tabs. Official provider plugins may still be required for native checkout buttons, tokenised payments, refunds, disputes, webhooks and subscriptions.

= Are providers conditional? =

Yes. Each provider has its own enable/disable option. Only enabled providers appear at checkout.

= Can I test each provider? =

Yes. Each provider has a Test configuration button in admin. This checks required settings and basic URL reachability, but it does not run a real transaction.

== Changelog ==

= 1.0.0 =
* Renamed to Universal Payment Gateway for WooCommerce.
* Removed Rack Group-specific admin-facing naming.
* Added separate provider sections for UK, Baltic and international providers.
* Added conditional provider enable/disable controls.
* Added Test/Live mode settings per provider.
* Added provider help descriptions.
* Added admin-side provider configuration test button.
* Added checkout provider selector and order meta storage.
* Retained legacy callback compatibility.


= 1.0.1 =
* Hardened hosted gateway request generation for IPG/NatWest-style application errors.
* Empty optional billing/shipping values are now removed before posting to the gateway.
* Added optional 3-D Secure Challenge Indicator control; disabled by default.
* Added provider-level hash encoding setting: hex-encoded IPG format or raw-string SHA256.
* Added pre-redirect validation for required fields including storename and oid.
* Added compatibility fallback for NatWest credentials from the original Rack Group plugin settings.
* Improved debug logging without exposing secret/hash values.
