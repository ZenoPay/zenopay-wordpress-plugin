<?php
/*
Plugin Name: zenopay Payment Gateway
Description: A custom payment gateway for WooCommerce by Zeno Limited.
Version: 1.3
Author: Dastani Ferdinandi
Author URI: https://www.zeno.africa
Text Domain: zenopay
Domain Path: /languages
*/

defined('ABSPATH') || exit;

define( 'zenopay_VERSION', '1.3' ); 
define( 'zenopay_MIN_PHP_VER', '8.1.0' );
define( 'zenopay_MAIN_FILE', __FILE__ );
define( 'zenopay_ABSPATH', __DIR__ . '/' );
define( 'zenopay_PLUGIN_URL', untrailingslashit( plugins_url( basename( plugin_dir_path( __FILE__ ) ), basename( __FILE__ ) ) ) );
define( 'zenopay_PLUGIN_PATH', untrailingslashit( plugin_dir_path( __FILE__ ) ) );

define('zenopay_ID', 'zenopay');
define('zenopay_TITLE', 'ZenoPay Payment Gateway');
define('zenopay_DESCRIPTION', 'A custom payment gateway for WooCommerce.');
 
add_action('plugins_loaded', 'zenopay_gateway_init');

function zenopay_gateway_init() { 
	if ( !class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'woo_zenopay_missing_wc_notice' );
		return;
	}   
    require_once zenopay_ABSPATH . 'ZenoPayInit.php';  

    add_filter('woocommerce_payment_gateways', 'add_zenopay_gateway'); 
    register_deactivation_hook(__FILE__, 'zenopay_deactivation');
}
 
function add_zenopay_gateway($methods) {
    $methods[] = 'zenopayGateway';
    return $methods;
}

function zenopay_deactivation() {
    wp_clear_scheduled_hook('zenopay_check_payment_status'); 
}


add_action('wp_ajax_zeno_initiate_payment', 'zeno_initiate_payment_callback');
add_action('wp_ajax_nopriv_zeno_initiate_payment', 'zeno_initiate_payment_callback');

function zeno_initiate_payment_callback() {
    $mobile = intval($_POST['mobile']);
    $order_id = intval($_POST['order_id']); 
    // $order_id = WC()->session->get('order_id');

    $Zeno = new zenopayGateway;
    $orderData = $Zeno->zeno_initiate_payment($mobile, $order_id); 
    
    wp_send_json_success($orderData);
}


add_action('wp_ajax_zeno_payment_status', 'zeno_payment_status_callback');
add_action('wp_ajax_nopriv_zeno_payment_status', 'zeno_payment_status_callback');

function zeno_payment_status_callback() { 
    $order_id = intval($_POST['order_id']); 
    $zeno_id = $_POST['zeno_id']; 
    // $order_id = WC()->session->get('order_id');

    $Zeno = new zenopayGateway;
    $orderData = $Zeno->zeno_payment_status($zeno_id, $order_id); 
    
    wp_send_json_success($orderData);
}


add_action( 'wp_ajax_zeno_create_order_from_cart', 'zeno_create_order_from_cart' );
add_action( 'wp_ajax_nopriv_zeno_create_order_from_cart', 'zeno_create_order_from_cart' );

function zeno_create_order_from_cart() {
    // Start session if not already started
    if ( session_status() === PHP_SESSION_NONE ) {
        session_start();
    }

    // Check if a created order ID already exists in the session
    if ( isset( $_SESSION['zeno_created_order_id'] ) ) {
        $existing_order_id = absint( $_SESSION['zeno_created_order_id'] );

        // If cart is empty and we have an existing order ID, return it
        if ( ! WC()->cart || WC()->cart->is_empty() ) {
            wp_send_json_success([
                'order_id' => $existing_order_id,
            ]);
        }
    }

    // If cart is empty and no session order exists, return error
    if ( ! WC()->cart || WC()->cart->is_empty() ) {
        wp_send_json_error( 'Cart is empty and no order has been created.' );
    }

    // Collect billing & shipping fields from POST
    $billing_fields = array(
        'first_name', 'last_name', 'company', 'address_1', 'address_2',
        'city', 'state', 'postcode', 'country', 'email', 'phone'
    );

    $shipping_fields = array(
        'first_name', 'last_name', 'company', 'address_1', 'address_2',
        'city', 'state', 'postcode', 'country'
    );

    $billing = [];
    $shipping = [];

    foreach ( $billing_fields as $field ) {
        $key = 'billing_' . $field;
        $billing[ $field ] = isset( $_POST[ $key ] ) ? sanitize_text_field( $_POST[ $key ] ) : '';
    }

    foreach ( $shipping_fields as $field ) {
        $key = 'shipping_' . $field;
        $shipping[ $field ] = isset( $_POST[ $key ] ) ? sanitize_text_field( $_POST[ $key ] ) : '';
    }

    try {
        $order = wc_create_order();

        foreach ( WC()->cart->get_cart() as $item ) {
            $order->add_product(
                $item['data'],
                $item['quantity'],
                array(
                    'variation' => $item['variation'],
                    'totals' => array(
                        'subtotal'     => $item['line_subtotal'],
                        'subtotal_tax' => $item['line_subtotal_tax'],
                        'total'        => $item['line_total'],
                        'tax'          => $item['line_tax'],
                    ),
                )
            );
        }

        $order->set_address( $billing, 'billing' );
        $order->set_address( $shipping, 'shipping' );

        if ( isset( $_POST['payment_method'] ) ) {
            $order->set_payment_method( sanitize_text_field( $_POST['payment_method'] ) );
        }

        $order->calculate_totals();
        $order->save();

        // Store created order ID in session
        $_SESSION['zeno_created_order_id'] = $order->get_id();

        // Optional: Empty the cart
        WC()->cart->empty_cart();

        wp_send_json_success([
            'order_id' => $order->get_id(),
        ]);

    } catch ( Exception $e ) {
        wp_send_json_error( $e->getMessage() );
    }
}
