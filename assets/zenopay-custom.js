jQuery(document).ready(function($) {
    // Custom phone number validation and message display
    $('#billing_phone').on('blur', function() {
        var phone = $(this).val();
        var messageElement = $('#phone-validation-message');

        if (phone.startsWith('074')) {
            messageElement.text('You are using Vodacom.');
        } else if (phone.startsWith('06')) {
            messageElement.text('You are using Tigo.');
        } else {
            messageElement.text('Please enter a valid phone number.');
        }
    });

    // Show loading spinner if specified
    if (zenopayParams.show_spinner) {
        $('#payment').append('<div id="zenopay-spinner" style="text-align: center; margin: 20px 0;"><img src="' + zenopayParams.redirect_url + '" alt="Loading..." /><p>Waiting for payment to be completed...</p></div>');
    }
});
