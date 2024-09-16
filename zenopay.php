<?php
/*
Plugin Name: ZenoPay Payment Gateway
Description: A custom payment gateway for WooCommerce by Zeno Limited.
Version: 1.1
Author: Dastani Ferdinandi
Author URI: https://www.zeno.africa
Text Domain: zenopay
Domain Path: /languages
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

add_action('plugins_loaded', 'zenopay_gateway_init');

function zenopay_gateway_init() {
    if (!class_exists('WC_Payment_Gateway')) {
        add_action('admin_notices', function () {
            echo '<div class="error"><p>' . __('WooCommerce is not installed or activated. Please install and activate WooCommerce.', 'zenopay') . '</p></div>';
        });
        return;
    }

    class WC_Gateway_ZenoPay extends WC_Payment_Gateway {
        public function __construct() {
            $this->id = 'zenopay';
            $this->method_title = 'ZenoPay Payment Gateway';
            $this->method_description = 'A custom payment gateway for WooCommerce.';
            $this->has_fields = false;

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

            // Add action hooks
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            add_action('woocommerce_api_' . $this->id, array());
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
                    'default'     => __('Lipa kwa mitandao ya simu', 'zenopay'),
                    'desc_tip'    => true,
                ),
                'description' => array(
                    'title'       => __('Description', 'zenopay'),
                    'type'        => 'textarea',
                    'description' => __('This controls the description which the user sees during checkout.', 'zenopay'),
                    'default'     => __('Lipa kwa mitandao ya simu.', 'zenopay'),
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
        
            // Send cURL request
            $responseData = $this->send_curl_request('https://api.zeno.africa', $data);
        
            if (!$responseData) {
                wc_add_notice(__('Unexpected response from the server. Please try again later.', 'zenopay'), 'error');
                return array('result' => 'failure', 'redirect' => '');
            }
        
            if (isset($responseData['redirect_url'])) {
                $order->update_status('pending', __('Awaiting ZenoPay payment', 'zenopay'));
                WC()->cart->empty_cart();
        
                // Pass the redirect URL and show_spinner flag to the frontend
                WC()->session->set('zenopay_redirect_url', $responseData['redirect_url']);
                WC()->session->set('zenopay_show_spinner', true);
        
                // Redirect to the custom page to show the spinner
                return array('result' => 'success', 'redirect' => wc_get_checkout_url() . '?show_spinner=true');
            } 
        }
        

        private function send_curl_request($url, $data) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 60); // Timeout in seconds
            $response = curl_exec($ch);

            if (curl_errno($ch)) {
                error_log('cURL error: ' . curl_error($ch));
                curl_close($ch);
                return null;
            }

            curl_close($ch);
            return json_decode($response, true);
        }

        public function check_payment_status($order_id) {
            $order = wc_get_order($order_id);
            $data = array(
                'check_status' => 1,
                'order_id'     => $order_id,
                'api_key'      => $this->api_key,
                'secret_key'   => $this->secret_key,
            );

            // Send cURL request
            $responseData = $this->send_curl_request('https://api.zeno.africa/status.php', $data);

            if ($responseData && isset($responseData['payment_status']) && $responseData['payment_status'] === 'COMPLETE') {
                $order->update_status('completed', __('Payment completed via ZenoPay.', 'zenopay'));
            } elseif ($responseData) {
                error_log('ZenoPay Payment Status: ' . print_r($responseData, true));
            }
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
                max-width: 128px;
            }
        </style>';
    }
    add_action('wp_head', 'zenopay_payment_gateway_icon_style');

    function enqueue_zenopay_scripts() {
        wp_enqueue_script('zenopay-custom-script', plugins_url('assets/zenopay-custom.js', __FILE__), array('jquery'), null, true);
        wp_enqueue_style('zenopay-custom-style', plugins_url('assets/zenopay-custom.css', __FILE__));
        wp_localize_script('zenopay-custom-script', 'zenopayParams', array(
            'redirect_url' => WC()->session->get('zenopay_redirect_url'),
            'show_spinner' => isset($_GET['show_spinner']) && $_GET['show_spinner'] === 'true'
        ));
    }
    add_action('wp_enqueue_scripts', 'enqueue_zenopay_scripts');

    function add_phone_validation_message() {
        echo '<div id="phone-validation-message"></div>';
    }
    add_action('woocommerce_after_checkout_form', 'add_phone_validation_message');
}
