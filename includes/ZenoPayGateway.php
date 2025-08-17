<?php

class zenopayGateway extends WC_Payment_Gateway
{
    public $api_key;

    public function __construct()
    {
        $this->id = 'zenopay';
        $this->method_title = 'ZenoPay Gateway';
        $this->method_description = 'Lipa kwa mitandao ya simu kupitia ZenoPay';
        $this->has_fields = false;

        $this->icon = apply_filters('zenopay_icon', plugins_url('assets/images/zenopay-logo.png', __FILE__));

        $this->init_form_fields();
        $this->init_settings();

        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->api_key = $this->get_option('api_key');

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_api_' . $this->id, array($this, 'check_payment_status_callback'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    public function init_form_fields()
    {
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
                'description' => __('This controls the title during checkout.', 'zenopay'),
                'default'     => __('Lipa kwa mitandao ya simu', 'zenopay'),
                'desc_tip'    => true,
            ),
            'description' => array(
                'title'       => __('Description', 'zenopay'),
                'type'        => 'textarea',
                'description' => __('This controls the description during checkout.', 'zenopay'),
                'default'     => __('Lipa kwa mitandao ya simu.', 'zenopay'),
            ),
            'api_key' => array(
                'title'       => __('API Key', 'zenopay'),
                'type'        => 'text',
                'description' => __('Your ZenoPay API Key.', 'zenopay'),
                'default'     => '',
                'desc_tip'    => true,
            ),
        );
    }

    public function process_payment($order_id)
    {
        WC()->session->set('order_id', $order_id);
        wc_add_notice(__('Processing payment with ZenoPay.', 'zenopay'), 'success');
        return array('result' => 'success', 'redirect' => '');
    }

    private function send_curl_request($url, $data)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'x-api-key: ' . $this->api_key
        ));

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            error_log('cURL error: ' . curl_error($ch));
            curl_close($ch);
            return null;
        }

        curl_close($ch);
        return json_decode($response, true);
    }

    private function send_get_request($url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'x-api-key: ' . $this->api_key
        ));

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            error_log('cURL GET error: ' . curl_error($ch));
            curl_close($ch);
            return null;
        }

        curl_close($ch);
        return json_decode($response, true);
    }
    

    public function enqueue_scripts()
    {
        wp_enqueue_script('zenopay-custom-script', plugins_url('assets/zenopay.js', __FILE__), array('jquery'), null, true);
        wp_enqueue_style('zenopay-custom-style', plugins_url('assets/zenopay.css', __FILE__));

        wp_localize_script('zenopay-custom-script', 'zenopay_data', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'order_id' => WC()->session->get('order_id'),
            'ZenoPayLogoPath' => $this->icon
        ));
    }

    private function generate_zenopay_order_id($order_id) {
    // Prefix with order number for reference
    $prefix = 'ZP' . $order_id;

    // Generate a random 12-character alphanumeric string
    $random = substr(str_shuffle('0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 12);

    // Combine and ensure max 20 characters
    $zenoOrderId = substr($prefix . $random, 0, 20);

    return $zenoOrderId;
}


    public function zeno_initiate_payment($mobile, $order_id)
    {
        $phoneNumber = substr($mobile, -9); // 744963858
        $region = "TZ";

        $carrierName = getCarrierName($phoneNumber, $region);

        if (!(strpos(strtolower($carrierName), 'invalid') !== false || empty($carrierName))) {
            $order = wc_get_order($order_id);

            $data = array(
                'order_id'     => $this->generate_zenopay_order_id($order->get_id()),
                'buyer_email'  => $order->get_billing_email(),
                'buyer_name'   => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                'buyer_phone'  => '0' . $phoneNumber, // e.g., 0744963858
                'amount'       => $order->get_total(),
                'webhook_url'  => site_url('/wp-json/zenopay/webhook') // your webhook endpoint
            );

            $orderResponseData = $this->send_curl_request('https://zenoapi.com/api/payments/mobile_money_tanzania', $data);

            if (!empty($orderResponseData) && strtolower($orderResponseData['status']) == 'success') {
                return [
                    'status' => true,
                    'image' => apply_filters('zenopay_icon', plugins_url('assets/images/' . strtolower($carrierName) . '-logo.png', __FILE__)),
                    'title' => 'Waiting for Payment',
                    'message' => "Payment is in process...",
                    'zenoOrderId' => $orderResponseData['order_id']
                ];
            } else {
                return [
                    'status' => false,
                    'message' => "255$phoneNumber, Failed to be processed."
                ];
            }
        }

        return [
            'status' => false,
            'message' => "Invalid Mobile Number"
        ];
    }

    

    public function zeno_payment_status($zeno_id, $order_id)
    {
        $order = wc_get_order($order_id);
        $url = 'https://zenoapi.com/api/payments/order-status?order_id=' . urlencode($zeno_id);
        $orderStatus = $this->send_get_request($url);

        if (!empty($orderStatus) && isset($orderStatus['data'][0]['payment_status'])) {
            if (strtolower($orderStatus['data'][0]['payment_status']) === 'completed') {
                $order->update_status('completed', __('Payment completed via ZenoPay.', 'zenopay'));
                WC()->cart->empty_cart();

                WC()->session->set('zenopay_redirect_url', $this->get_return_url($order));
                WC()->session->set('zenopay_show_spinner', true);

                return [
                    'status' => true,
                    'order' => $order_id,
                    'zeno_id' => $zeno_id,
                    'redirect' => true,
                    'message' => "Payment Received Successfully!",
                    'url' => $this->get_return_url($order)
                ];
            }
        }

        return [
            'status' => false,
            'order' => $order_id,
            'zeno_id' => $zeno_id,
            'message' => 'Payment not completed.',
            'orderStatus' => $orderStatus
        ];
    }
}
