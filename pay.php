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
    $order_id = WC()->session->get('order_id');

    $Zeno = new zenopayGateway;
    $orderData = $Zeno->zeno_initiate_payment($mobile, $order_id); 
    
    wp_send_json_success($orderData);
}


add_action('wp_ajax_zeno_payment_status', 'zeno_payment_status_callback');
add_action('wp_ajax_nopriv_zeno_payment_status', 'zeno_payment_status_callback');

function zeno_payment_status_callback() { 
    $order_id = intval($_POST['order_id']); 
    $zeno_id = $_POST['zeno_id']; 
    $order_id = WC()->session->get('order_id');

    $Zeno = new zenopayGateway;
    $orderData = $Zeno->zeno_payment_status($zeno_id, $order_id); 
    
    wp_send_json_success($orderData);
}