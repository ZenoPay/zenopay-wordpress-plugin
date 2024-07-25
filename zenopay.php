<?php
/*
Plugin Name: ZenoPay Payment Gateway
Description: A custom payment gateway for WooCommerce by Zeno Limited.
Version: 1.0
Author: Dastani Ferdinandi
Author URI: https://www.zeno.africa
Text Domain: zenopay
Domain Path: /languages
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

add_action('plugins_loaded', 'zenopay_gateway_init');

function zenopay_gateway_init() {
    if (!class_exists('WC_Payment_Gateway')) {
        add_action('admin_notices', function() {
            echo '<div class="error"><p>' . __('WooCommerce is not installed or activated. Please install and activate WooCommerce.', 'zenopay') . '</p></div>';
        });
        return;
    }

    class WC_Gateway_ZenoPay extends WC_Payment_Gateway {
        public function __construct() {
            $this->id = 'zenopay';
            $this->method_title = 'ZenoPay Payment Gateway';
            $this->method_description = 'A custom payment gateway for WooCommerce.';
            $this->has_fields = true;

            // Set plugin image
            $this->icon = apply_filters('zenopay_icon', plugins_url('assets/zenopay-logo.png', __FILE__));

            // Load settings
            $this->init_form_fields();
            $this->init_settings();
            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');
            $this->account_id = $this->get_option('account_id');
            $this->api_key = $this->get_option('api_key');
            $this->secret_key = $this->get_option('secret_key');

            // Debugging the account ID, api_key, and secret_key
            error_log('ZenoPay Account ID: ' . $this->account_id);
            error_log('ZenoPay API Key: ' . $this->api_key);
            // Avoid logging the secret_key for security reasons, handle it securely

            // Add action hooks
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            add_action('woocommerce_api_' . $this->id, array($this, 'handle_webhook'));
        }

        public function init_form_fields() {
            $this->form_fields = array(
                'enabled' => array(
                    'title'   => __('Enable/Disable', 'zenopay'),
                    'type'    => 'checkbox',
                    'label'   => __('Enable ZenoPay Payment Gateway', 'zenopay'),
                    'default' => 'no',
                ),
                'title' => array(
                    'title'       => __('Title', 'zenopay'),
                    'type'        => 'text',
                    'description' => __('This controls the title which the user sees during checkout.', 'zenopay'),
                    'default'     => __('Credit Card Payment', 'zenopay'),
                    'desc_tip'    => true,
                ),
                'description' => array(
                    'title'       => __('Description', 'zenopay'),
                    'type'        => 'textarea',
                    'description' => __('This controls the description which the user sees during checkout.', 'zenopay'),
                    'default'     => __('Pay with your credit card.', 'zenopay'),
                ),
                'account_id' => array(
                    'title'       => __('Account ID', 'zenopay'),
                    'type'        => 'text',
                    'description' => __('Your ZenoPay Account ID.', 'zenopay'),
                    'default'     => '',
                    'desc_tip'    => true,
                ),
                'api_key' => array(
                    'title'       => __('API Key', 'zenopay'),
                    'type'        => 'text',
                    'description' => __('Your ZenoPay API Key.', 'zenopay'),
                    'default'     => '',
                    'desc_tip'    => true,
                ),
                'secret_key' => array(
                    'title'       => __('Secret Key', 'zenopay'),
                    'type'        => 'password',
                    'description' => __('Your ZenoPay Secret Key.', 'zenopay'),
                    'default'     => '',
                    'desc_tip'    => true,
                ),
            );
        }

        public function process_payment($order_id) {
            $order = wc_get_order($order_id);

            // Prepare the data for the cURL request
            $data = array(
                'create_order' => 1,
                'buyer_email'  => $order->get_billing_email(),
                'buyer_name'   => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                'buyer_phone'  => $order->get_billing_phone(),
                'amount'       => $order->get_total(),
                'account_id'   => $this->get_option('account_id'),
                'api_key'      => $this->api_key,
                'secret_key'   => $this->secret_key,
            );

            // Debugging the request data
            error_log('Request Data: ' . http_build_query($data));

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'https://ezycard.zeno.africa/process.php');
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $response = curl_exec($ch);

            if (curl_errno($ch)) {
                $error_msg = curl_error($ch);
                wc_add_notice(__('Curl error: ' . $error_msg, 'zenopay'), 'error');
                error_log('cURL error: ' . $error_msg);
                curl_close($ch);
                return array(
                    'result'   => 'failure',
                    'redirect' => '',
                );
            } else {
                curl_close($ch);
                $responseData = json_decode($response, true);

                // Debugging the API response
                error_log('API Response: ' . print_r($responseData, true));

                if (isset($responseData['message'])) {
                    wc_add_notice(htmlspecialchars($responseData['message']), 'notice');
                    return array(
                        'result'   => 'failure',
                        'redirect' => '',
                    );
                } else {
                    $redirect_url = isset($responseData['redirect_url']) ? $responseData['redirect_url'] : '';

                    if ($redirect_url) {
                        $order->update_status('pending', __('Awaiting ZenoPay payment', 'zenopay'));
                        WC()->cart->empty_cart();
                        return array(
                            'result'   => 'success',
                            'redirect' => $redirect_url,
                        );
                    } else {
                        wc_add_notice(__('Unexpected response from the server.', 'zenopay'), 'error');
                        return array(
                            'result'   => 'failure',
                            'redirect' => '',
                        );
                    }
                }
            }
        }

        public function handle_webhook() {
            // Webhook handling logic
        }
    }

    function add_zenopay_gateway($methods) {
        $methods[] = 'WC_Gateway_ZenoPay';
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'add_zenopay_gateway');

    function zenopay_payment_gateway_icon_style() {
        echo '<style>
            .woocommerce-payment-methods .payment_method_zenopay img {
                max-width: 200px;
                height: auto;
            }
        </style>';
    }
    add_action('wp_head', 'zenopay_payment_gateway_icon_style');
}
