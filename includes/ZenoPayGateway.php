<?php

class zenopayGateway extends WC_Payment_Gateway
{

    public $api_key;
 

    public function __construct()
    {
        $this->id = zenopay_ID;
        $this->method_title = zenopay_TITLE;
        $this->method_description = zenopay_DESCRIPTION;
        $this->has_fields = false;

        // Set plugin image
        $this->icon = apply_filters('zenopay_icon', plugins_url('assets/images/zenopay-logo.png', __FILE__));

        // Load settings
        $this->init_form_fields();
        $this->init_settings();
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');


        
        $this->api_key = $this->get_option('api_key');
        

        // Add action hooks
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
                'label'   => __('Enable zenopay Payment Gateway', 'zenopay'),
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
            
            'api_key' => array(
                'title'       => __('API Key', 'zenopay'),
                'type'        => 'text',
                'description' => __('Your zenopay API Key.', 'zenopay'),
                'default'     => '',
                'desc_tip'    => true,
            ),
            
        );
    }

    public function process_payment($order_id)
    {
        WC()->session->set('order_id', $order_id);
        $data = array('result' => 'failure', 'redirect' => '');
        wc_add_notice(__('Processing payment with ZenoPay.', 'zenopay'), 'success');
        return $data;
    }


    private function send_curl_request($url, $data)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data)); // Send JSON
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json',
        'x-api-key: ' . $this->api_key // Add API key in headers
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

    public  function zeno_initiate_payment($mobile, $order_id)
    {


        $phoneNumber = substr($mobile, -9);
        $region = "TZ";

        $carrierName = getCarrierName($phoneNumber, $region);

        if (!(strpos(strtolower($carrierName), 'invalid') !== false || empty($carrierName))) {
            // $order = wc_get_order($order_id);
            $order = wc_get_order($order_id);

            // echo($order->get_billing_email());

            // Prepare the data for the cURL request
            $data = array(
                'order_id'     => $order->get_id(), // or generate UUID if required

                'buyer_email'  => $order->get_billing_email(),
                'buyer_name'   => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                // 'buyer_phone'  => $order->get_billing_phone(),
                'buyer_phone'  => '255'.$phoneNumber,
                'amount'       => $order->get_total(),
              
                'api_key'      => $this->api_key,
                'webhook_url'  => site_url('/wp-json/zenopay/webhook') // Or your actual webhook

                
            );

            // Send cURL request
            $orderResponseData = $this->send_curl_request('https://zenoapi.com/api/payments/mobile_money_tanzania', $data);

            if (strtolower($orderResponseData['status']) == 'success') {
                $orderData = [
                    'status' => true,
                    'image' =>  apply_filters('zenopay_icon', plugins_url('assets/images/' . strtolower($carrierName) . '-logo.png', __FILE__)),
                    'title' => 'Waiting for Payment',
                    'message' => "Payment is in process...",
                    'zenoOrderId' => $orderResponseData['order_id']
                ];
            } else {
                $orderData['status'] = false;
                if (
                    strtolower($orderResponseData['status']) == 'error' &&
                    strpos(strtolower($orderResponseData['message']), 'failed') !== false
                ) {
                    $orderData['message'] = "Please Verify this number 255$phoneNumber";
                } else {
                    $orderData['message'] = "255$phoneNumber, Failed to be processed.";
                }
            }
        }

        if (strpos(strtolower($carrierName), 'invalid') !== false || empty($carrierName)) {
            $orderData['status'] = false;
            $orderData['message'] = "Invalid Mobile Number";
        }


        return $orderData;
    }

    public function zeno_payment_status($zeno_id, $order_id)
    {
        $order = wc_get_order($order_id);
        $data = array(
            'check_status' => 1,
            'order_id'     => $zeno_id,
            'api_key'      => $this->api_key,
          
        );


        // Send cURL request
        $orderStatus = $this->send_curl_request('https://zenoapi.com/api/payments/order-status', $data);

        if (isset($orderStatus['status']) && $orderStatus['status'] == 'success') {
            if (isset($orderStatus['payment_status']) && strtolower($orderStatus['payment_status']) == 'completed') {

                $order->update_status('completed', __('Payment completed via ZenoPay.', 'zenopay'));
                WC()->cart->empty_cart();

                // Pass the redirect URL to the frontend
                WC()->session->set('zenopay_redirect_url', $this->get_return_url($order));
                WC()->session->set('zenopay_show_spinner', true);

                $orderData = [
                    'status' => true,
                    'order' => $order_id,
                    'zeno_id' => $zeno_id,
                    'redirect' => false,
                    'message' => "Payment Received Successfully..!",
                    'url' => $this->get_return_url($order)
                ];
            } else {
                $orderData = [
                    'status' => false,
                    'redirect' => false,
                    'order' => $order_id,
                    'zeno_id' => $zeno_id,
                    'orderStatus' => $orderStatus
                ];
            }
        } else {
            $orderData = [
                'status' => false,
                'order' => $order_id,
                'zeno_id' => $zeno_id,
                'orderStatus' => $orderStatus,
            ];
        }

        return $orderData;
    }
}
