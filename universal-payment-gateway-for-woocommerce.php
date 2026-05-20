<?php
/**
 * Plugin Name: Universal Payment Gateway for WooCommerce
 * Description: Universal hosted-payment gateway shell for WooCommerce with separate configurable providers for UK, Baltic and international payment providers.
 * Version:     1.0.1
 * Author:      Universal Payment Gateway
 * Text Domain: universal-payment-gateway
 * Requires Plugins: woocommerce
 * WC requires at least: 8.0
 * WC tested up to: 10.5
 * License:     GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'before_woocommerce_init', 'universal_payments_gateway_declare_compatibility' );
function universal_payments_gateway_declare_compatibility() {
    if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
    }
}

add_action( 'plugins_loaded', 'universal_payments_gateway_init', 11 );
function universal_payments_gateway_init() {
    if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
        return;
    }

    class WC_Gateway_Universal_Payments extends WC_Payment_Gateway {
        private $providers = array();

        public function __construct() {
            $this->id                 = 'universal_payments_gateway';
            $this->method_title       = __( 'Universal Payment Gateway', 'universal-payment-gateway' );
            $this->method_description = __( 'Universal hosted-payment gateway with provider-level admin tabs, enable/disable controls and test configuration checks.', 'universal-payment-gateway' );
            $this->has_fields         = true;
            $this->supports           = array( 'products' );
            $this->providers          = $this->get_providers();

            $this->init_form_fields();
            $this->init_settings();

            $this->title       = $this->get_option( 'title', __( 'Secure Payment', 'universal-payment-gateway' ) );
            $this->description = $this->get_option( 'description', __( 'Choose a secure payment provider.', 'universal-payment-gateway' ) );
            $this->enabled     = $this->get_option( 'enabled', 'no' );

            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
            add_action( 'woocommerce_api_wc_gateway_universal_payments_redirect', array( $this, 'render_redirect_form' ) );
            add_action( 'woocommerce_api_wc_gateway_universal_payments_response', array( $this, 'handle_response' ) );
            add_action( 'woocommerce_api_wc_gateway_universal_payments_notify', array( $this, 'handle_notification' ) );
            add_action( 'wp_ajax_universal_payments_test_provider', array( $this, 'ajax_test_provider' ) );

            // Backward-compatible old callback URLs from the original single-provider version.
            add_action( 'woocommerce_api_wc_gateway_rack_group_redirect', array( $this, 'render_redirect_form' ) );
            add_action( 'woocommerce_api_wc_gateway_rack_group_response', array( $this, 'handle_response' ) );
            add_action( 'woocommerce_api_wc_gateway_rack_group_notify', array( $this, 'handle_notification' ) );
        }

        private function get_providers() {
            return array(
                'natwest' => array(
                    'name' => 'NatWest',
                    'default_title' => 'NatWest Pay',
                    'default_description' => 'Pay securely by card through NatWest.',
                    'default_test_url' => 'https://test.ipg-online.com/connect/gateway/processing',
                    'default_live_url' => 'https://www.ipg-online.com/connect/gateway/processing',
                    'help' => 'Use this tab for NatWest/IPG-style hosted payment pages. Enter the Store ID and Shared Secret supplied by NatWest, keep Test mode enabled first, complete test orders, then switch to Live mode after bank approval.',
                ),
                'barclays' => array(
                    'name' => 'Barclays',
                    'default_title' => 'Barclays Payment',
                    'default_description' => 'Pay securely through Barclays.',
                    'default_test_url' => '',
                    'default_live_url' => '',
                    'help' => 'Create or access your Barclays merchant/payment gateway account, copy the hosted payment endpoint, merchant/store ID and signing secret into this tab, then test before going live.',
                ),
                'lloyds' => array(
                    'name' => 'Lloyds',
                    'default_title' => 'Lloyds Payment',
                    'default_description' => 'Pay securely through Lloyds.',
                    'default_test_url' => '',
                    'default_live_url' => '',
                    'help' => 'Use the credentials and hosted payment URL supplied by Lloyds/Cardnet or the connected PSP. Confirm the required hash format with Lloyds before taking live payments.',
                ),
                'hsbc' => array(
                    'name' => 'HSBC',
                    'default_title' => 'HSBC Payment',
                    'default_description' => 'Pay securely through HSBC.',
                    'default_test_url' => '',
                    'default_live_url' => '',
                    'help' => 'Enter the hosted payment URL, merchant ID and secret supplied by HSBC or the HSBC payment gateway provider. Test with sandbox details first.',
                ),
                'standard_chartered' => array(
                    'name' => 'Standard Chartered',
                    'default_title' => 'Standard Chartered Payment',
                    'default_description' => 'Pay securely through Standard Chartered.',
                    'default_test_url' => '',
                    'default_live_url' => '',
                    'help' => 'Add the hosted payment endpoint and merchant credentials supplied by Standard Chartered or its PSP partner. Confirm required fields and hash rules before live launch.',
                ),
                'paypal' => array(
                    'name' => 'PayPal',
                    'default_title' => 'PayPal',
                    'default_description' => 'Pay securely with PayPal.',
                    'default_test_url' => '',
                    'default_live_url' => '',
                    'help' => 'For full PayPal checkout, refunds and webhooks, use the official PayPal WooCommerce integration. This tab is for stores using a hosted redirect URL supplied by their payment provider.',
                ),
                'amazon_pay' => array(
                    'name' => 'Amazon Pay',
                    'default_title' => 'Amazon Pay',
                    'default_description' => 'Pay securely with Amazon Pay.',
                    'default_test_url' => '',
                    'default_live_url' => '',
                    'help' => 'For native Amazon Pay buttons and buyer wallet features, use the official Amazon Pay integration. This tab supports hosted redirect configurations only.',
                ),
                'square' => array(
                    'name' => 'Square',
                    'default_title' => 'Square',
                    'default_description' => 'Pay securely with Square.',
                    'default_test_url' => '',
                    'default_live_url' => '',
                    'help' => 'For Square card forms, refunds and inventory sync, use the official Square integration. This tab is for a hosted Square/payment-link style endpoint if supplied.',
                ),
                'woopayments' => array(
                    'name' => 'WooPayments',
                    'default_title' => 'WooPayments',
                    'default_description' => 'Pay securely with WooPayments.',
                    'default_test_url' => '',
                    'default_live_url' => '',
                    'help' => 'WooPayments is normally installed as its own WooCommerce extension. This tab can be used only where a hosted redirect endpoint and signing credentials are available.',
                ),
                'stripe' => array(
                    'name' => 'Stripe',
                    'default_title' => 'Stripe',
                    'default_description' => 'Pay securely with Stripe.',
                    'default_test_url' => '',
                    'default_live_url' => '',
                    'help' => 'For native Stripe Elements, Payment Intents, refunds and webhooks, use the official Stripe WooCommerce extension. This tab supports hosted redirect/Checkout URL style configurations only.',
                ),
                'swedbank' => array(
                    'name' => 'Swedbank',
                    'default_title' => 'Swedbank',
                    'default_description' => 'Pay securely through Swedbank.',
                    'default_test_url' => '',
                    'default_live_url' => '',
                    'help' => 'Use Swedbank Baltic e-commerce credentials or PSP-provided hosted payment credentials. Add endpoint, merchant ID and signing secret, then test each Baltic market/currency required.',
                ),
                'seb' => array(
                    'name' => 'SEB',
                    'default_title' => 'SEB',
                    'default_description' => 'Pay securely through SEB.',
                    'default_test_url' => '',
                    'default_live_url' => '',
                    'help' => 'Use the SEB merchant portal or PSP documentation to obtain the hosted endpoint, merchant ID and signing secret. Test EUR payments before enabling Live mode.',
                ),
                'luminor' => array(
                    'name' => 'Luminor',
                    'default_title' => 'Luminor',
                    'default_description' => 'Pay securely through Luminor.',
                    'default_test_url' => '',
                    'default_live_url' => '',
                    'help' => 'Enter the hosted payment URL and merchant credentials supplied by Luminor or the chosen Baltic PSP. Confirm exact response/hash fields before live use.',
                ),
                'revolut' => array(
                    'name' => 'Revolut',
                    'default_title' => 'Revolut Pay',
                    'default_description' => 'Pay securely with Revolut.',
                    'default_test_url' => '',
                    'default_live_url' => '',
                    'help' => 'For native Revolut Pay and card processing, use Revolut Business/API credentials or the official integration. This tab is for hosted redirect configurations where endpoint and secret are supplied.',
                ),
            );
        }

        public function init_form_fields() {
            $fields = array(
                'enabled' => array(
                    'title'   => __( 'Enable / Disable', 'universal-payment-gateway' ),
                    'type'    => 'checkbox',
                    'label'   => __( 'Enable Universal Payment Gateway at checkout', 'universal-payment-gateway' ),
                    'default' => 'no',
                ),
                'title' => array(
                    'title'       => __( 'Checkout Title', 'universal-payment-gateway' ),
                    'type'        => 'text',
                    'description' => __( 'Main payment method title shown at checkout.', 'universal-payment-gateway' ),
                    'default'     => __( 'Secure Payment', 'universal-payment-gateway' ),
                    'desc_tip'    => true,
                ),
                'description' => array(
                    'title'       => __( 'Checkout Description', 'universal-payment-gateway' ),
                    'type'        => 'textarea',
                    'description' => __( 'Intro text shown above the available provider options.', 'universal-payment-gateway' ),
                    'default'     => __( 'Choose your preferred secure payment provider.', 'universal-payment-gateway' ),
                    'desc_tip'    => true,
                ),
                'default_provider' => array(
                    'title'       => __( 'Default Provider', 'universal-payment-gateway' ),
                    'type'        => 'select',
                    'description' => __( 'Provider pre-selected at checkout when enabled.', 'universal-payment-gateway' ),
                    'default'     => 'natwest',
                    'options'     => $this->provider_options(),
                ),
                'transaction_type' => array(
                    'title'       => __( 'Transaction Type', 'universal-payment-gateway' ),
                    'type'        => 'select',
                    'default'     => 'sale',
                    'options'     => array(
                        'sale'    => __( 'Sale', 'universal-payment-gateway' ),
                        'preauth' => __( 'Pre-Auth', 'universal-payment-gateway' ),
                    ),
                ),
                'debug' => array(
                    'title'   => __( 'Debug Log', 'universal-payment-gateway' ),
                    'type'    => 'checkbox',
                    'label'   => __( 'Enable logging to WooCommerce > Status > Logs', 'universal-payment-gateway' ),
                    'default' => 'no',
                ),
                'send_3ds_challenge_indicator' => array(
                    'title'   => __( '3-D Secure Challenge Indicator', 'universal-payment-gateway' ),
                    'type'    => 'checkbox',
                    'label'   => __( 'Send threeDSRequestorChallengeIndicator=01 with IPG Connect requests', 'universal-payment-gateway' ),
                    'default' => 'no',
                    'description' => __( 'Leave disabled unless your processor specifically asks for this field. Sending an unsupported value can cause an application error at the hosted gateway.', 'universal-payment-gateway' ),
                ),
            );

            foreach ( $this->providers as $provider_id => $provider ) {
                $prefix = $provider_id . '_';
                $fields[ $prefix . 'section' ] = array(
                    'title'       => $provider['name'],
                    'type'        => 'provider_title',
                    'description' => $provider['help'],
                    'provider_id' => $provider_id,
                );
                $fields[ $prefix . 'enabled' ] = array(
                    'title'   => __( 'Provider Status', 'universal-payment-gateway' ),
                    'type'    => 'checkbox',
                    'label'   => sprintf( __( 'Enable %s at checkout', 'universal-payment-gateway' ), $provider['name'] ),
                    'default' => ( 'natwest' === $provider_id ) ? 'yes' : 'no',
                    'class'   => 'universal-provider-field universal-provider-field-' . esc_attr( $provider_id ),
                );
                $fields[ $prefix . 'title' ] = array(
                    'title'       => __( 'Checkout Label', 'universal-payment-gateway' ),
                    'type'        => 'text',
                    'default'     => $provider['default_title'],
                    'description' => __( 'Label shown to customers for this provider.', 'universal-payment-gateway' ),
                    'class'       => 'universal-provider-field universal-provider-field-' . esc_attr( $provider_id ),
                );
                $fields[ $prefix . 'description' ] = array(
                    'title'       => __( 'Provider Description', 'universal-payment-gateway' ),
                    'type'        => 'textarea',
                    'default'     => $provider['default_description'],
                    'description' => __( 'Description shown when this provider is available at checkout.', 'universal-payment-gateway' ),
                    'class'       => 'universal-provider-field universal-provider-field-' . esc_attr( $provider_id ),
                );
                $fields[ $prefix . 'payment_mode' ] = array(
                    'title'       => __( 'Mode', 'universal-payment-gateway' ),
                    'type'        => 'select',
                    'default'     => 'test',
                    'options'     => array(
                        'test' => __( 'Test', 'universal-payment-gateway' ),
                        'live' => __( 'Live', 'universal-payment-gateway' ),
                    ),
                    'class'       => 'universal-provider-field universal-provider-field-' . esc_attr( $provider_id ),
                );
                $fields[ $prefix . 'hash_encoding' ] = array(
                    'title'       => __( 'Hash Encoding', 'universal-payment-gateway' ),
                    'type'        => 'select',
                    'default'     => 'hex',
                    'options'     => array(
                        'hex' => __( 'SHA256 over hex-encoded string (common IPG Connect)', 'universal-payment-gateway' ),
                        'raw' => __( 'SHA256 over raw string', 'universal-payment-gateway' ),
                    ),
                    'description' => __( 'Use hex unless your processor documentation says the hash must be generated over the raw concatenated string.', 'universal-payment-gateway' ),
                    'class'       => 'universal-provider-field universal-provider-field-' . esc_attr( $provider_id ),
                );
                $fields[ $prefix . 'test_gateway_url' ] = array(
                    'title'       => __( 'Test Gateway URL', 'universal-payment-gateway' ),
                    'type'        => 'text',
                    'default'     => $provider['default_test_url'],
                    'description' => __( 'Hosted payment endpoint for test/sandbox transactions.', 'universal-payment-gateway' ),
                    'class'       => 'universal-provider-field universal-provider-field-' . esc_attr( $provider_id ),
                );
                $fields[ $prefix . 'test_store_id' ] = array(
                    'title'       => __( 'Test Merchant / Store ID', 'universal-payment-gateway' ),
                    'type'        => 'text',
                    'class'       => 'universal-provider-field universal-provider-field-' . esc_attr( $provider_id ),
                );
                $fields[ $prefix . 'test_shared_secret' ] = array(
                    'title'       => __( 'Test Shared Secret', 'universal-payment-gateway' ),
                    'type'        => 'password',
                    'class'       => 'universal-provider-field universal-provider-field-' . esc_attr( $provider_id ),
                );
                $fields[ $prefix . 'live_gateway_url' ] = array(
                    'title'       => __( 'Live Gateway URL', 'universal-payment-gateway' ),
                    'type'        => 'text',
                    'default'     => $provider['default_live_url'],
                    'description' => __( 'Hosted payment endpoint for live transactions.', 'universal-payment-gateway' ),
                    'class'       => 'universal-provider-field universal-provider-field-' . esc_attr( $provider_id ),
                );
                $fields[ $prefix . 'live_store_id' ] = array(
                    'title'       => __( 'Live Merchant / Store ID', 'universal-payment-gateway' ),
                    'type'        => 'text',
                    'class'       => 'universal-provider-field universal-provider-field-' . esc_attr( $provider_id ),
                );
                $fields[ $prefix . 'live_shared_secret' ] = array(
                    'title'       => __( 'Live Shared Secret', 'universal-payment-gateway' ),
                    'type'        => 'password',
                    'class'       => 'universal-provider-field universal-provider-field-' . esc_attr( $provider_id ),
                );
                $fields[ $prefix . 'test_button' ] = array(
                    'title'       => __( 'Test Configuration', 'universal-payment-gateway' ),
                    'type'        => 'provider_test_button',
                    'provider_id' => $provider_id,
                    'provider_name' => $provider['name'],
                    'class'       => 'universal-provider-field universal-provider-field-' . esc_attr( $provider_id ),
                );
            }

            $this->form_fields = $fields;
        }

        private function provider_options() {
            $options = array();
            foreach ( $this->providers as $provider_id => $provider ) {
                $options[ $provider_id ] = $provider['name'];
            }
            return $options;
        }

        public function admin_options() {
            echo '<h2>' . esc_html( $this->get_method_title() ) . '</h2>';
            echo wp_kses_post( wpautop( $this->get_method_description() ) );
            echo '<div class="universal-provider-tabs-wrapper">';
            echo '<h2 class="nav-tab-wrapper universal-provider-tabs">';
            echo '<a href="#" class="nav-tab nav-tab-active" data-provider="general">' . esc_html__( 'General', 'universal-payment-gateway' ) . '</a>';
            foreach ( $this->providers as $provider_id => $provider ) {
                echo '<a href="#" class="nav-tab" data-provider="' . esc_attr( $provider_id ) . '">' . esc_html( $provider['name'] ) . '</a>';
            }
            echo '</h2>';
            echo '<p class="description">' . esc_html__( 'Each provider has its own enable switch, mode, credentials, help text and configuration test button. Disabled providers are hidden from checkout.', 'universal-payment-gateway' ) . '</p>';
            echo '</div>';
            echo '<table class="form-table universal-payments-settings-table">';
            $this->generate_settings_html();
            echo '</table>';
            $this->print_admin_script();
        }


        public function generate_provider_title_html( $key, $data ) {
            $provider_id = isset( $data['provider_id'] ) ? $data['provider_id'] : '';
            ob_start();
            ?>
            <tr valign="top" class="universal-provider-section universal-provider-section-<?php echo esc_attr( $provider_id ); ?>">
                <th colspan="2">
                    <h3><?php echo esc_html( $data['title'] ); ?></h3>
                    <div class="universal-provider-help"><strong><?php esc_html_e( 'How to connect:', 'universal-payment-gateway' ); ?></strong> <?php echo esc_html( $data['description'] ); ?></div>
                </th>
            </tr>
            <?php
            return ob_get_clean();
        }

        public function generate_provider_test_button_html( $key, $data ) {
            $field_key = $this->get_field_key( $key );
            $provider_id = isset( $data['provider_id'] ) ? $data['provider_id'] : '';
            $provider_name = isset( $data['provider_name'] ) ? $data['provider_name'] : '';
            ob_start();
            ?>
            <tr valign="top" class="universal-provider-field universal-provider-field-<?php echo esc_attr( $provider_id ); ?>">
                <th scope="row" class="titledesc"><label for="<?php echo esc_attr( $field_key ); ?>"><?php echo esc_html( $data['title'] ); ?></label></th>
                <td class="forminp">
                    <button type="button" class="button universal-test-provider" data-provider="<?php echo esc_attr( $provider_id ); ?>"><?php echo esc_html( sprintf( __( 'Test %s configuration', 'universal-payment-gateway' ), $provider_name ) ); ?></button>
                    <span class="universal-test-result" id="universal-test-result-<?php echo esc_attr( $provider_id ); ?>"></span>
                    <p class="description"><?php esc_html_e( 'This checks whether the enabled mode has a Gateway URL, Merchant/Store ID and Shared Secret. If the URL is reachable, the response code is shown. It does not perform a real bank transaction.', 'universal-payment-gateway' ); ?></p>
                </td>
            </tr>
            <?php
            return ob_get_clean();
        }

        private function print_admin_script() {
            $provider_ids = array_keys( $this->providers );
            $nonce = wp_create_nonce( 'universal_payments_test_provider' );
            ?>
            <style>
                .universal-provider-help { background:#fff; border-left:4px solid #2271b1; padding:12px; margin:8px 0; }
                .universal-test-result { margin-left:10px; font-weight:600; }
                .universal-test-result.success { color:#008a20; }
                .universal-test-result.error { color:#b32d2e; }
            </style>
            <script>
                jQuery(function($){
                    var providers = <?php echo wp_json_encode( $provider_ids ); ?>;
                    function providerForRow($row) {
                        var html = $row.html() || '';
                        for (var i = 0; i < providers.length; i++) {
                            if ($row.hasClass('universal-provider-section-' + providers[i]) || $row.hasClass('universal-provider-field-' + providers[i]) || html.indexOf('universal_payments_gateway_' + providers[i] + '_') !== -1 || html.indexOf('data-provider=\"' + providers[i] + '\"') !== -1 || html.indexOf("data-provider='" + providers[i] + "'") !== -1) {
                                return providers[i];
                            }
                        }
                        return 'general';
                    }
                    function showTab(provider) {
                        $('.universal-provider-tabs .nav-tab').removeClass('nav-tab-active');
                        $('.universal-provider-tabs .nav-tab[data-provider="' + provider + '"]').addClass('nav-tab-active');
                        $('.universal-payments-settings-table tr').each(function(){
                            var rowProvider = providerForRow($(this));
                            if ('general' === provider) {
                                $(this).toggle('general' === rowProvider);
                            } else {
                                $(this).toggle(provider === rowProvider);
                            }
                        });
                    }
                    $('.universal-provider-tabs').on('click', '.nav-tab', function(e){
                        e.preventDefault();
                        showTab($(this).data('provider'));
                    });
                    $('.universal-test-provider').on('click', function(e){
                        e.preventDefault();
                        var provider = $(this).data('provider');
                        var $result = $('#universal-test-result-' + provider);
                        $result.removeClass('success error').text('<?php echo esc_js( __( 'Testing...', 'universal-payment-gateway' ) ); ?>');
                        $.post(ajaxurl, {
                            action: 'universal_payments_test_provider',
                            nonce: '<?php echo esc_js( $nonce ); ?>',
                            provider: provider
                        }).done(function(response){
                            if (response && response.success) {
                                $result.addClass('success').text(response.data.message);
                            } else {
                                $result.addClass('error').text(response && response.data && response.data.message ? response.data.message : '<?php echo esc_js( __( 'Configuration test failed.', 'universal-payment-gateway' ) ); ?>');
                            }
                        }).fail(function(){
                            $result.addClass('error').text('<?php echo esc_js( __( 'Configuration test failed.', 'universal-payment-gateway' ) ); ?>');
                        });
                    });
                    showTab('general');
                });
            </script>
            <?php
        }

        public function ajax_test_provider() {
            if ( ! current_user_can( 'manage_woocommerce' ) ) {
                wp_send_json_error( array( 'message' => __( 'Permission denied.', 'universal-payment-gateway' ) ) );
            }
            check_ajax_referer( 'universal_payments_test_provider', 'nonce' );

            $provider_id = isset( $_POST['provider'] ) ? sanitize_key( wp_unslash( $_POST['provider'] ) ) : '';
            if ( empty( $this->providers[ $provider_id ] ) ) {
                wp_send_json_error( array( 'message' => __( 'Unknown provider.', 'universal-payment-gateway' ) ) );
            }

            $config = $this->get_provider_config( $provider_id );
            if ( 'yes' !== $config['enabled'] ) {
                wp_send_json_error( array( 'message' => __( 'Provider is disabled. Enable it before testing.', 'universal-payment-gateway' ) ) );
            }
            if ( empty( $config['gateway_url'] ) || empty( $config['store_id'] ) || empty( $config['shared_secret'] ) ) {
                wp_send_json_error( array( 'message' => __( 'Missing Gateway URL, Merchant/Store ID or Shared Secret for the selected mode.', 'universal-payment-gateway' ) ) );
            }

            $response = wp_remote_head( $config['gateway_url'], array( 'timeout' => 10, 'redirection' => 2 ) );
            if ( is_wp_error( $response ) ) {
                wp_send_json_success( array( 'message' => sprintf( __( 'Required fields are present. URL check warning: %s', 'universal-payment-gateway' ), $response->get_error_message() ) ) );
            }
            $code = wp_remote_retrieve_response_code( $response );
            wp_send_json_success( array( 'message' => sprintf( __( 'Required fields are present. Gateway URL returned HTTP %s.', 'universal-payment-gateway' ), $code ? $code : __( 'no status', 'universal-payment-gateway' ) ) ) );
        }

        public function payment_fields() {
            if ( $this->description ) {
                echo wp_kses_post( wpautop( $this->description ) );
            }

            $enabled_providers = $this->get_enabled_providers();
            if ( empty( $enabled_providers ) ) {
                echo '<p>' . esc_html__( 'No payment providers are currently enabled. Please choose another payment method or contact the store.', 'universal-payment-gateway' ) . '</p>';
                return;
            }

            $default = $this->get_option( 'default_provider', key( $enabled_providers ) );
            if ( empty( $enabled_providers[ $default ] ) ) {
                $default = key( $enabled_providers );
            }

            echo '<div class="universal-payments-provider-options">';
            foreach ( $enabled_providers as $provider_id => $provider ) {
                $config = $this->get_provider_config( $provider_id );
                echo '<p class="form-row universal-payments-provider-option">';
                echo '<label>';
                echo '<input type="radio" name="universal_payment_provider" value="' . esc_attr( $provider_id ) . '" ' . checked( $provider_id, $default, false ) . '> ';
                echo '<strong>' . esc_html( $config['title'] ) . '</strong>';
                if ( ! empty( $config['description'] ) ) {
                    echo '<br><small>' . esc_html( $config['description'] ) . '</small>';
                }
                echo '</label>';
                echo '</p>';
            }
            echo '</div>';
        }

        public function validate_fields() {
            $provider_id = isset( $_POST['universal_payment_provider'] ) ? sanitize_key( wp_unslash( $_POST['universal_payment_provider'] ) ) : '';
            $enabled_providers = $this->get_enabled_providers();

            if ( empty( $provider_id ) || empty( $enabled_providers[ $provider_id ] ) ) {
                wc_add_notice( __( 'Please choose an available payment provider.', 'universal-payment-gateway' ), 'error' );
                return false;
            }

            $config = $this->get_provider_config( $provider_id );
            if ( empty( $config['gateway_url'] ) || empty( $config['store_id'] ) || empty( $config['shared_secret'] ) ) {
                wc_add_notice( sprintf( __( '%s is not fully configured. Please use another payment provider or contact the store.', 'universal-payment-gateway' ), $this->providers[ $provider_id ]['name'] ), 'error' );
                return false;
            }

            return true;
        }

        public function process_payment( $order_id ) {
            $order = wc_get_order( $order_id );
            if ( ! $order ) {
                wc_add_notice( __( 'Invalid order.', 'universal-payment-gateway' ), 'error' );
                return array( 'result' => 'failure' );
            }

            $provider_id = isset( $_POST['universal_payment_provider'] ) ? sanitize_key( wp_unslash( $_POST['universal_payment_provider'] ) ) : $this->get_option( 'default_provider', 'natwest' );
            $enabled_providers = $this->get_enabled_providers();
            if ( empty( $enabled_providers[ $provider_id ] ) ) {
                wc_add_notice( __( 'Selected payment provider is not available.', 'universal-payment-gateway' ), 'error' );
                return array( 'result' => 'failure' );
            }

            $config = $this->get_provider_config( $provider_id );
            if ( empty( $config['store_id'] ) || empty( $config['shared_secret'] ) || empty( $config['gateway_url'] ) ) {
                wc_add_notice( sprintf( __( '%s payment gateway is not fully configured.', 'universal-payment-gateway' ), $this->providers[ $provider_id ]['name'] ), 'error' );
                return array( 'result' => 'failure' );
            }

            $merchant_transaction_id = $order->get_order_number() . '-' . wp_generate_password( 8, false, false );
            $order->update_meta_data( '_universal_payments_provider', $provider_id );
            $order->update_meta_data( '_universal_payments_merchant_transaction_id', $merchant_transaction_id );
            $order->save();
            $order->update_status( 'pending', sprintf( __( 'Awaiting %s payment.', 'universal-payment-gateway' ), $this->providers[ $provider_id ]['name'] ) );

            return array(
                'result'   => 'success',
                'redirect' => add_query_arg(
                    array(
                        'wc-api'   => 'wc_gateway_universal_payments_redirect',
                        'order_id' => $order->get_id(),
                        'key'      => $order->get_order_key(),
                    ),
                    home_url( '/' )
                ),
            );
        }

        public function render_redirect_form() {
            $order_id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;
            $key      = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : '';
            $order    = wc_get_order( $order_id );

            if ( ! $order || ! hash_equals( $order->get_order_key(), $key ) ) {
                wc_add_notice( __( 'Invalid payment request.', 'universal-payment-gateway' ), 'error' );
                wp_safe_redirect( wc_get_cart_url() );
                exit;
            }

            $provider_id = $this->get_order_provider_id( $order );
            $config = $this->get_provider_config( $provider_id );
            if ( empty( $config['store_id'] ) || empty( $config['shared_secret'] ) || empty( $config['gateway_url'] ) ) {
                wc_add_notice( sprintf( __( '%s payment gateway is not fully configured.', 'universal-payment-gateway' ), $this->providers[ $provider_id ]['name'] ), 'error' );
                wp_safe_redirect( wc_get_cart_url() );
                exit;
            }

            $currency_numeric = $this->get_currency_number( $order->get_currency() );
            if ( empty( $currency_numeric ) ) {
                wc_add_notice( __( 'This currency is not configured for universal payments.', 'universal-payment-gateway' ), 'error' );
                wp_safe_redirect( wc_get_cart_url() );
                exit;
            }

            $transaction_time = gmdate( 'Y:m:d-H:i:s' );
            $charge_total     = wc_format_decimal( $order->get_total(), 2 );
            $merchant_txn_id  = $order->get_meta( '_universal_payments_merchant_transaction_id' );
            if ( empty( $merchant_txn_id ) ) {
                $merchant_txn_id = $order->get_meta( '_rack_group_merchant_transaction_id' );
            }
            if ( empty( $merchant_txn_id ) ) {
                $merchant_txn_id = $order->get_meta( '_universal_payments_legacy_merchant_transaction_id' );
            }
            if ( empty( $merchant_txn_id ) ) {
                $merchant_txn_id = $order->get_order_number() . '-' . wp_generate_password( 8, false, false );
                $order->update_meta_data( '_universal_payments_merchant_transaction_id', $merchant_txn_id );
                $order->save();
            }

            $hash = $this->create_hash( $config['store_id'], $transaction_time, $charge_total, $currency_numeric, $config['shared_secret'], $config['hash_encoding'] );

            $response_url = add_query_arg(
                array(
                    'wc-api'   => 'wc_gateway_universal_payments_response',
                    'order_id' => $order->get_id(),
                    'key'      => $order->get_order_key(),
                ),
                home_url( '/' )
            );
            $notify_url = add_query_arg( array( 'wc-api' => 'wc_gateway_universal_payments_notify' ), home_url( '/' ) );

            $fields = array(
                'txntype'                            => $this->get_option( 'transaction_type', 'sale' ),
                'oid'                                => $order->get_id(),
                'timezone'                           => 'Europe/London',
                'txndatetime'                        => $transaction_time,
                'hash_algorithm'                     => 'SHA256',
                'hash'                               => $hash,
                'storename'                          => $config['store_id'],
                'mode'                               => 'payonly',
                'checkoutoption'                     => 'combinedpage',
                'comments'                           => $this->providers[ $provider_id ]['name'] . ' WooCommerce Universal Payment Gateway',
                'bcompany'                           => $order->get_billing_company(),
                'bname'                              => trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ),
                'baddr1'                             => $order->get_billing_address_1(),
                'baddr2'                             => $order->get_billing_address_2(),
                'bcity'                              => $order->get_billing_city(),
                'bstate'                             => $order->get_billing_state(),
                'bcountry'                           => $order->get_billing_country(),
                'bzip'                               => $order->get_billing_postcode(),
                'phone'                              => $order->get_billing_phone(),
                'email'                              => $order->get_billing_email(),
                'sname'                              => trim( $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name() ),
                'saddr1'                             => $order->get_shipping_address_1(),
                'saddr2'                             => $order->get_shipping_address_2(),
                'scity'                              => $order->get_shipping_city(),
                'sstate'                             => $order->get_shipping_state(),
                'scountry'                           => $order->get_shipping_country(),
                'szip'                               => $order->get_shipping_postcode(),
                'chargetotal'                        => $charge_total,
                'currency'                           => $currency_numeric,
                'merchantTransactionId'              => $merchant_txn_id,
                'responseFailURL'                    => $response_url,
                'responseSuccessURL'                 => $response_url,
                'transactionNotificationURL'         => $notify_url,
                'authenticateTransaction'            => 'true',
            );

            if ( 'yes' === $this->get_option( 'send_3ds_challenge_indicator', 'no' ) ) {
                $fields['threeDSRequestorChallengeIndicator'] = '01';
            }

            $required_fields = array(
                'txntype',
                'oid',
                'timezone',
                'txndatetime',
                'hash_algorithm',
                'hash',
                'storename',
                'mode',
                'chargetotal',
                'currency',
                'merchantTransactionId',
                'responseFailURL',
                'responseSuccessURL',
                'transactionNotificationURL',
            );
            $fields = $this->clean_gateway_fields( $fields, $required_fields );

            $missing = $this->missing_required_gateway_fields( $fields, $required_fields );
            if ( ! empty( $missing ) ) {
                $this->log( 'Payment redirect blocked. Missing required fields: ' . implode( ', ', $missing ) );
                wc_add_notice( sprintf( __( '%s payment request is missing required fields. Please check gateway settings.', 'universal-payment-gateway' ), $this->providers[ $provider_id ]['name'] ), 'error' );
                wp_safe_redirect( wc_get_checkout_url() );
                exit;
            }

            $this->log( 'Redirect form provider ' . $provider_id . ': ' . wc_print_r( array_diff_key( $fields, array( 'hash' => true, 'shared_secret' => true ) ), true ) );

            nocache_headers();
            echo '<!doctype html><html><head><meta charset="utf-8"><title>' . esc_html__( 'Redirecting to payment...', 'universal-payment-gateway' ) . '</title></head><body>';
            echo '<p>' . esc_html__( 'Redirecting to the secure payment page. Please wait...', 'universal-payment-gateway' ) . '</p>';
            echo '<form id="universal-payment-form" method="post" action="' . esc_url( $config['gateway_url'] ) . '">';
            foreach ( $fields as $name => $value ) {
                echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '">';
            }
            echo '<noscript><button type="submit">' . esc_html__( 'Continue to payment', 'universal-payment-gateway' ) . '</button></noscript>';
            echo '</form><script>document.getElementById("universal-payment-form").submit();</script></body></html>';
            exit;
        }

        public function handle_response() {
            $order_id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : ( isset( $_REQUEST['oid'] ) ? absint( $_REQUEST['oid'] ) : 0 );
            $key      = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : '';
            $order    = wc_get_order( $order_id );

            if ( ! $order || ( $key && ! hash_equals( $order->get_order_key(), $key ) ) ) {
                wc_add_notice( __( 'Invalid payment response.', 'universal-payment-gateway' ), 'error' );
                wp_safe_redirect( wc_get_cart_url() );
                exit;
            }

            $provider_id = $this->get_order_provider_id( $order );
            $valid = $this->verify_gateway_response( 'response_hash', $provider_id );
            $status = $this->request_value( 'status' );
            $approval_code = $this->request_value( 'approval_code' );
            $merchant_txn_id = $this->request_value( 'merchantTransactionId' );

            if ( $valid && 'APPROVED' === strtoupper( $status ) && 0 === strpos( strtoupper( $approval_code ), 'Y:' ) ) {
                if ( ! $order->is_paid() ) {
                    $order->payment_complete( $merchant_txn_id );
                    $order->add_order_note( sprintf( __( '%1$s payment approved. Approval code: %2$s', 'universal-payment-gateway' ), $this->providers[ $provider_id ]['name'], $approval_code ) );
                    WC()->cart->empty_cart();
                }
                wp_safe_redirect( $this->get_return_url( $order ) );
                exit;
            }

            $message = $approval_code ? $approval_code : __( 'Payment was not approved.', 'universal-payment-gateway' );
            $order->update_status( 'failed', sprintf( __( '%1$s payment failed or could not be verified. Message: %2$s', 'universal-payment-gateway' ), $this->providers[ $provider_id ]['name'], $message ) );
            wc_add_notice( __( 'There was an issue with the payment. Please try again or use another payment method.', 'universal-payment-gateway' ), 'error' );
            wp_safe_redirect( wc_get_checkout_url() );
            exit;
        }

        public function handle_notification() {
            $order_id = isset( $_REQUEST['oid'] ) ? absint( $_REQUEST['oid'] ) : 0;
            $order    = wc_get_order( $order_id );

            if ( ! $order ) {
                status_header( 400 );
                exit;
            }

            $provider_id = $this->get_order_provider_id( $order );
            $valid = $this->verify_gateway_response( 'notification_hash', $provider_id );
            $status = $this->request_value( 'status' );
            $approval_code = $this->request_value( 'approval_code' );
            $merchant_txn_id = $this->request_value( 'merchantTransactionId' );

            if ( $valid && 'APPROVED' === strtoupper( $status ) && ( 0 === strpos( strtoupper( $approval_code ), 'Y:' ) || false !== stripos( $approval_code, 'waiting 3dsecure' ) ) ) {
                if ( ! $order->is_paid() ) {
                    $order->payment_complete( $merchant_txn_id );
                    $order->add_order_note( sprintf( __( '%1$s payment notification approved. Approval code: %2$s', 'universal-payment-gateway' ), $this->providers[ $provider_id ]['name'], $approval_code ) );
                }
            } elseif ( $valid && ! $order->is_paid() ) {
                $order->update_status( 'failed', sprintf( __( '%1$s payment notification received but not approved. Message: %2$s', 'universal-payment-gateway' ), $this->providers[ $provider_id ]['name'], $approval_code ) );
            }

            status_header( 200 );
            exit;
        }


        private function clean_gateway_fields( $fields, $required_fields ) {
            $clean = array();
            foreach ( $fields as $key => $value ) {
                if ( is_array( $value ) ) {
                    continue;
                }
                $value = trim( (string) $value );
                if ( '' === $value && ! in_array( $key, $required_fields, true ) ) {
                    continue;
                }
                $clean[ $key ] = $value;
            }
            return $clean;
        }

        private function missing_required_gateway_fields( $fields, $required_fields ) {
            $missing = array();
            foreach ( $required_fields as $field ) {
                if ( ! isset( $fields[ $field ] ) || '' === trim( (string) $fields[ $field ] ) ) {
                    $missing[] = $field;
                }
            }
            return $missing;
        }

        private function get_enabled_providers() {
            $enabled = array();
            foreach ( $this->providers as $provider_id => $provider ) {
                if ( 'yes' === $this->get_option( $provider_id . '_enabled', 'no' ) ) {
                    $enabled[ $provider_id ] = $provider;
                }
            }
            return $enabled;
        }

        private function get_provider_config( $provider_id ) {
            if ( empty( $this->providers[ $provider_id ] ) ) {
                $provider_id = 'natwest';
            }
            $mode = $this->get_option( $provider_id . '_payment_mode', 'test' );
            $config = array(
                'provider_id'    => $provider_id,
                'enabled'        => $this->get_option( $provider_id . '_enabled', 'no' ),
                'title'          => $this->get_option( $provider_id . '_title', $this->providers[ $provider_id ]['default_title'] ),
                'description'    => $this->get_option( $provider_id . '_description', $this->providers[ $provider_id ]['default_description'] ),
                'mode'           => $mode,
                'gateway_url'    => trim( $this->get_option( $provider_id . '_' . $mode . '_gateway_url' ) ),
                'store_id'       => trim( $this->get_option( $provider_id . '_' . $mode . '_store_id' ) ),
                'shared_secret'  => trim( $this->get_option( $provider_id . '_' . $mode . '_shared_secret' ) ),
                'hash_encoding'  => $this->get_option( $provider_id . '_hash_encoding', 'hex' ),
            );

            // Compatibility fallback for sites upgraded from the original Rack Group plugin.
            if ( 'natwest' === $provider_id && ( empty( $config['gateway_url'] ) || empty( $config['store_id'] ) || empty( $config['shared_secret'] ) ) ) {
                $legacy = get_option( 'woocommerce_rack_group_gateway_settings', array() );
                if ( is_array( $legacy ) ) {
                    $legacy_mode = isset( $legacy['payment_mode'] ) ? $legacy['payment_mode'] : $mode;
                    $config['mode'] = $legacy_mode;
                    if ( empty( $config['gateway_url'] ) && ! empty( $legacy[ $legacy_mode . '_gateway_url' ] ) ) {
                        $config['gateway_url'] = trim( $legacy[ $legacy_mode . '_gateway_url' ] );
                    }
                    if ( empty( $config['store_id'] ) && ! empty( $legacy[ $legacy_mode . '_store_id' ] ) ) {
                        $config['store_id'] = trim( $legacy[ $legacy_mode . '_store_id' ] );
                    }
                    if ( empty( $config['shared_secret'] ) && ! empty( $legacy[ $legacy_mode . '_shared_secret' ] ) ) {
                        $config['shared_secret'] = trim( $legacy[ $legacy_mode . '_shared_secret' ] );
                    }
                }
            }

            return $config;
        }

        private function get_order_provider_id( $order ) {
            $provider_id = $order->get_meta( '_universal_payments_provider' );
            if ( empty( $provider_id ) ) {
                $provider_id = 'natwest';
            }
            if ( empty( $this->providers[ $provider_id ] ) ) {
                $provider_id = 'natwest';
            }
            return $provider_id;
        }

        private function create_hash( $store_id, $transaction_time, $charge_total, $currency, $shared_secret, $hash_encoding = 'hex' ) {
            $string_to_hash = $store_id . $transaction_time . $charge_total . $currency . $shared_secret;
            return $this->sha256_hash( $string_to_hash, $hash_encoding );
        }

        private function sha256_hash( $string_to_hash, $hash_encoding = 'hex' ) {
            if ( 'raw' === $hash_encoding ) {
                return hash( 'sha256', $string_to_hash );
            }
            return hash( 'sha256', bin2hex( $string_to_hash ) );
        }

        private function verify_gateway_response( $hash_field, $provider_id ) {
            $provided_hash = $this->request_value( $hash_field );
            if ( empty( $provided_hash ) ) {
                $this->log( 'Missing ' . $hash_field );
                return false;
            }

            $config = $this->get_provider_config( $provider_id );
            $approval_code = $this->request_value( 'approval_code' );
            $charge_total  = $this->request_value( 'chargetotal' );
            $currency      = $this->request_value( 'currency' );
            $txn_datetime  = $this->request_value( 'txndatetime' );

            if ( 'response_hash' === $hash_field ) {
                $string_to_hash = $config['shared_secret'] . $approval_code . $charge_total . $currency . $txn_datetime . $config['store_id'];
            } else {
                $string_to_hash = $charge_total . $config['shared_secret'] . $currency . $txn_datetime . $config['store_id'] . $approval_code;
            }

            $expected = $this->sha256_hash( $string_to_hash, $config['hash_encoding'] );
            $valid = hash_equals( strtolower( $expected ), strtolower( $provided_hash ) );
            if ( ! $valid ) {
                $this->log( 'Hash verification failed for ' . $hash_field . ' / provider ' . $provider_id );
            }
            return $valid;
        }

        private function request_value( $key ) {
            return isset( $_REQUEST[ $key ] ) ? sanitize_text_field( wp_unslash( $_REQUEST[ $key ] ) ) : '';
        }

        private function get_currency_number( $currency_code ) {
            $currencies = array(
                'GBP' => '826', 'EUR' => '978', 'USD' => '840', 'AED' => '784', 'ARS' => '032', 'AUD' => '036',
                'CAD' => '124', 'CHF' => '756', 'DKK' => '208', 'HKD' => '344', 'JPY' => '392', 'NOK' => '578',
                'NZD' => '554', 'SEK' => '752', 'SGD' => '702', 'ZAR' => '710', 'PLN' => '985', 'CZK' => '203',
                'HUF' => '348', 'RON' => '946', 'BGN' => '975', 'ISK' => '352', 'NIO' => '558', 'TRY' => '949',
            );
            return isset( $currencies[ $currency_code ] ) ? $currencies[ $currency_code ] : '';
        }

        private function log( $message ) {
            if ( 'yes' !== $this->get_option( 'debug', 'no' ) ) {
                return;
            }
            $logger = wc_get_logger();
            $logger->info( $message, array( 'source' => 'universal-payment-gateway' ) );
        }
    }

    add_filter( 'woocommerce_payment_gateways', 'universal_payments_gateway_add_gateway' );
    function universal_payments_gateway_add_gateway( $gateways ) {
        $gateways[] = 'WC_Gateway_Universal_Payments';
        return $gateways;
    }
}
