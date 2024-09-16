 
 
jQuery(document).ready(function($) {
    // Create and append custom styles for SweetAlert2
    const style2 = document.createElement('style');
    style2.innerHTML = `
    .swal2-confirm.btn-block {
        display: block;
        width: 200px !important;
        padding: 10px;
        font-size: 18px;
        background-color: #007bff;
        color: white;
        border: none;
        border-radius: 0.25rem;
    }
    .swal2-confirm.btn-block:hover {
        background-color: #0056b3;
    }
    `;
    document.head.appendChild(style2);



    let timerInterval;
    let waitingTime = 90; 
    let userMobile = null;
    let orderId = zenopay_data.order_id;
    let ZenoPayOrderId = null;
    let bkOvalay = 'rgba(0,0,0,0.8)';
    let txtColor = '#333'; 


    const systemUrl =  zenopay_data.ajax_url; 
    const ZenoPayLogo = zenopay_data.ZenoPayLogoPath; 

    const welcome_message = '<center><h1 style= "color: red; font-size: 3rem; margin: 0px; padding: 0px;">Weka Namba ya Mtandao Husika</h1><img src="' + ZenoPayLogo + '"></center>'; 
    const session_expire_message = 'Your session has expired. Please try again.'; 
    const retry_button_text = 'Retry';
    const change_number_button_text = 'Change Number';
    const pay_button_text = 'Pay Now';
    const payment_placeholder = 'Enter your payment number' ;  

    
    // Load SweetAlert2 and initialize payment process
    function loadSweetAlert() {
        var script = document.createElement('script');
        script.src = "https://cdn.jsdelivr.net/npm/sweetalert2@11";
        script.onload = function() {
            console.log('SweetAlert2 loaded successfully');
            initPaymentProcess();
        };
        document.head.appendChild(script);
    } 

    // Initialize payment process on checkout form submission
    function initPaymentProcess() {
        $('form.checkout').on('submit', function(e) {
            e.preventDefault(); // Prevent form submission
            startPaymentProcess();
        });
    } 

    // Start the payment process
    function startPaymentProcess() {
        Swal.fire({
            html: `${welcome_message}`,
            input: "text",
            inputAttributes: {
                autocapitalize: "off",
                placeholder: `${payment_placeholder}`,
                required: true
            },
            showCancelButton: false,
            confirmButtonText: `${pay_button_text}`,
            showLoaderOnConfirm: true,
            preConfirm: (mobile) => handlePayment(mobile),
            allowOutsideClick: false,
            color: txtColor,
            backdrop: bkOvalay,
            customClass: {
                confirmButton: "btn-block btn-primary"  
            }
        });
    }
 
    // Handle the payment process
    function handlePayment(mobile) {
        userMobile = mobile;
        return fetchUserData(userMobile).then(data => {
            if (data) { 
                if (data.status) {
                    showPaymentStatus(data);
                } else {
                    Swal.fire({
                        title: 'Error',
                        text: data.message,
                        icon: 'error',
                        confirmButtonText: retry_button_text,
                        cancelButtonText: change_number_button_text,
                        showCancelButton: true,
                        allowOutsideClick: false,
                        color: txtColor,
                        backdrop: bkOvalay,
                        preConfirm: () => handlePayment(userMobile)
                    }).then(result => {
                        if (result.isDismissed) {
                            startPaymentProcess();
                        }
                    });
                }
            }
        });
    }

   
    // Fetch user data based on mobile number
    function fetchUserData(mobile) {
        const url = systemUrl;
        const data = {
            action: 'zeno_initiate_payment',
            order_id: orderId,
            mobile: mobile
        }; 
        return new Promise((resolve, reject) => {
            $.ajax({
                url: url,
                type: 'POST',
                data: data,
                success: function(response) { 
                    if(response.data.status) {
                        ZenoPayOrderId = response.data.zenoOrderId
                    } 
                    resolve(response.data);
                },
                error: function() { 
                    reject({ status: false, message: 'Network Error' });
                }
            });
        });
    }


    // Show payment status in SweetAlert
    function showPaymentStatus(orderInfo) {
        Swal.fire({
            allowOutsideClick: false,
            html: `${orderInfo.message}`,
            imageUrl: orderInfo.image,
            imageHeight: 110,
            showConfirmButton: false,
            showLoaderOnConfirm: true,
            color: txtColor,
            backdrop: bkOvalay,
            didOpen: () => {
                Swal.showLoading();
                startOrderCheck(orderInfo);
            },
            willClose: () => clearInterval(timerInterval)
        });
    }


    // Start checking the order status with a countdown
    function startOrderCheck() {
        let timer = waitingTime;
        let timerInterval = setInterval(async () => {
            Swal.update({ title: `Expire in ${timer--} seconds` });

            if (timer % 15 === 0) {
                const orderInfo = await fetchOrderStatus();
                if (orderInfo && orderInfo.status) {
                    clearInterval(timerInterval);
                    Swal.fire({
                        html: `${orderInfo.message}`,
                        icon: "success",
                        timer: 3000,
                        timerProgressBar: true,
                        allowOutsideClick: false,
                        color: txtColor,
                        backdrop: bkOvalay,
                        willClose: () => {

                            if (orderInfo.redirect && isSameOrigin(orderInfo.url)) {
                                window.location = orderInfo.url; 
                            } else {
                                window.open(orderInfo.url, '_self'); 
                            } 
                        }
                    });
                }
            }

            if (timer < 0) {
                clearInterval(timerInterval);
                Swal.fire({
                    title: 'Time expired',
                    text: session_expire_message,
                    icon: 'warning',
                    allowOutsideClick: false,
                    color: txtColor,
                    backdrop: bkOvalay,
                    confirmButtonText: retry_button_text,
                    cancelButtonText: change_number_button_text,
                    showCancelButton: true,
                    preConfirm: () => handlePayment(userMobile)
                }).then(result => {
                    if (result.isDismissed) {
                        startPaymentProcess();
                    }
                });
            }
        }, 1000);
    } 

    // Fetch order status based on order ID
    function fetchOrderStatus() {
        const url = systemUrl;
        const data = {
            action: 'zeno_payment_status',
            order_id: orderId,
            zeno_id: ZenoPayOrderId
        }; 
        return new Promise((resolve, reject) => {
            $.ajax({
                url: url,
                type: 'POST',
                data: data,
                success: function(response) {  
                    resolve(response.data);
                },
                error: function() { 
                    reject({});
                }
            });
        });
    } 

    loadSweetAlert();
});
 