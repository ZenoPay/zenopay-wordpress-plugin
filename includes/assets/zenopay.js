jQuery(document).ready(function($) {
    // === CONFIG ===
    const config = {
        timerInterval: null,
        waitingTime: 90,
        userMobile: null,
        orderId: zenopay_data.order_id,
        ZenoPayOrderId: null,
        systemUrl: zenopay_data.ajax_url,
        paymentMethodId: 'zenopay',
        isZenoPayActive: false,
        maxInjectionAttempts: 5,
        injectionAttempts: 0
    };

    // === UTILITIES ===
    const utils = {
        // Store original event handlers
        originalHandlers: new Map(),
        
        // Disable all other button handlers
        disableOtherHandlers: function() {
            const button = document.getElementById('place_order');
            if (!button) return;

            // Clone the element to remove all event listeners
            const newButton = button.cloneNode(true);
            button.parentNode.replaceChild(newButton, button);
            
            // Restore our control handler
            $(newButton).on('click.zenopay', handleZenoPayment);
            return newButton;
        },
        
        // Restore original handlers
        restoreHandlers: function() {
            const button = document.getElementById('place_order');
            if (!button || !this.originalHandlers.has(button)) return;
            
            const handlers = this.originalHandlers.get(button);
            handlers.forEach(({ type, handler }) => {
                button.addEventListener(type, handler);
            });
        },
        
        // Robust injection with retry
        injectPaymentPrompt: function() {
            try {
                const zenoUI = `
                    <div id="zenopay-prompt" style="margin-top: 20px; padding: 10px 0; display: none;">
                        <p style="color: red; text-align: center;">Weka Namba ya Simu ya Kulipa nayo</p>
                        <input type="text" id="zenopay-mobile-input" placeholder="Enter your payment number"
                            required style="padding: 10px; font-size: 1.2rem; width: 100%; max-width: 400px; display: block; margin: 0 auto;">
                        <div id="zenopay-status" style="margin-top: 20px; text-align: center;"></div>
                    </div>`;

                const paymentMethodElement = $(`li.wc_payment_method.payment_method_${config.paymentMethodId}`);
                
                if (paymentMethodElement.length) {
                    // Check if prompt already exists
                    if ($('#zenopay-prompt').length === 0) {
                        paymentMethodElement.after(zenoUI);
                        console.log('ZenoPay prompt injected successfully');
                        return true;
                    }
                    return true;
                } else {
                    // Fallback injection
                    if (config.injectionAttempts < config.maxInjectionAttempts) {
                        config.injectionAttempts++;
                        console.warn(`Payment method not found, retrying (${config.injectionAttempts}/${config.maxInjectionAttempts})...`);
                        setTimeout(() => this.injectPaymentPrompt(), 500);
                        return false;
                    } else {
                        console.error('Failed to inject ZenoPay prompt after maximum attempts');
                        return false;
                    }
                }
            } catch (error) {
                console.error('Error injecting payment prompt:', error);
                return false;
            }
        }
    };

    // === PAYMENT UI ===
    const ui = {
        init: function() {
            // Initialize with retry logic
            if (!utils.injectPaymentPrompt()) {
                return;
            }
            
            // Initialize phone sync
            this.syncBillingPhone();
            $('#billing_phone').on('input', this.syncBillingPhone);
        },
        
        syncBillingPhone: function() {
            const billingPhone = $('#billing_phone').val();
            if (billingPhone && billingPhone.length > 5) {
                $('#zenopay-mobile-input').val(billingPhone);
            }
        },
        
        update: function() {
            config.isZenoPayActive = $('input[name="payment_method"]:checked').val() === config.paymentMethodId;
            
            $('#zenopay-prompt').toggle(config.isZenoPayActive);
            
            if (config.isZenoPayActive) {
                // Take control of the button
                utils.disableOtherHandlers();
            } else {
                // Release control
                utils.restoreHandlers();
            }
        },
        
        showStatus: function(message, color = 'black') {
            $('#zenopay-status').html(`<p style="color: ${color};">${message}</p>`);
        },
        
        setButtonState: function(disabled) {
            const button = $('#place_order');
            button.prop('disabled', disabled);
            disabled ? button.addClass('processing') : button.removeClass('processing');
        }
    };

    // === PAYMENT HANDLERS ===
    function handleZenoPayment(e) {
        if (!config.isZenoPayActive) return;
        
        e.preventDefault();
        e.stopImmediatePropagation();
        
        const mobile = $('#zenopay-mobile-input').val();
        if (!mobile || mobile.length < 5) {
            ui.showStatus('Please enter a valid phone number', 'red');
            return;
        }
        
        ui.setButtonState(true);
        ui.showStatus('Processing... Please wait.');
        
        paymentHandler.initiate(mobile)
            .catch(() => ui.showStatus('Payment failed to start. Try again.', 'red'))
            .finally(() => ui.setButtonState(false));
    }

    const paymentHandler = {
        initiate: function(mobile) {
            config.userMobile = mobile;
            
            return orderHandler.create()
                .then(() => this.processPayment(mobile))
                .then(data => {
                    if (data.status) {
                        config.ZenoPayOrderId = data.zenoOrderId;
                        this.monitorPayment();
                    } else {
                        ui.showStatus('Payment failed. Please try again.', 'red');
                    }
                });
        },
        
        processPayment: function(mobile) {
            return new Promise((resolve, reject) => {
                $.ajax({
                    url: config.systemUrl,
                    type: 'POST',
                    data: {
                        action: 'zeno_initiate_payment',
                        order_id: config.orderId,
                        mobile: mobile
                    },
                    success: (response) => resolve(response.data),
                    error: () => reject({ status: false, message: 'Network Error' })
                });
            });
        },
        
        monitorPayment: function() {
            ui.showStatus('Payment initiated. Waiting for confirmation...');
            
            let timer = config.waitingTime;
            config.timerInterval = setInterval(async () => {
                if (timer % 15 === 0) {
                    const status = await orderHandler.checkStatus();
                    if (status && status.status) {
                        clearInterval(config.timerInterval);
                        ui.showStatus(status.message, 'green');
                        setTimeout(() => window.location = status.url, 3000);
                    }
                }
                
                if (timer-- < 0) {
                    clearInterval(config.timerInterval);
                    ui.showStatus('Payment timed out. You can try again.', 'orange');
                }
            }, 1000);
        }
    };

    const orderHandler = {
        create: function() {
            return new Promise((resolve, reject) => {
                $.ajax({
                    url: config.systemUrl,
                    type: 'POST',
                    data: $('form.checkout').serialize() + '&action=zeno_create_order_from_cart',
                    success: (response) => {
                        if (response.success && response.data.order_id) {
                            config.orderId = response.data.order_id;
                            resolve();
                        } else {
                            ui.showStatus('Failed to create order.', 'red');
                            reject();
                        }
                    },
                    error: () => {
                        ui.showStatus('Network error while creating order.', 'red');
                        reject();
                    }
                });
            });
        },
        
        checkStatus: function() {
            return new Promise((resolve) => {
                $.ajax({
                    url: config.systemUrl,
                    type: 'POST',
                    data: {
                        action: 'zeno_payment_status',
                        order_id: config.orderId,
                        zeno_id: config.ZenoPayOrderId
                    },
                    success: (response) => resolve(response.data),
                    error: () => resolve(null)
                });
            });
        }
    };

    // === INITIALIZATION ===
    $(function() {
        // Wait for WooCommerce to initialize payment methods
        const initInterval = setInterval(() => {
            if ($('ul.wc_payment_methods').length > 0) {
                clearInterval(initInterval);
                ui.init();
                
                // Set up event handlers
                $('form.checkout')
                    .on('change', 'input[name="payment_method"]', ui.update)
                    .on('submit', function(e) {
                        if (config.isZenoPayActive) e.preventDefault();
                    });
                
                $(document.body).on('updated_checkout', function() {
                    // Re-inject UI if needed after checkout updates
                    utils.injectPaymentPrompt();
                    ui.update();
                });
                
                // Initial UI update
                ui.update();
            }
        }, 100);
    });
});