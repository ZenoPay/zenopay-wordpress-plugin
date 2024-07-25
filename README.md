# ZenoPay-Wordpress-WooCommerce-Plugin
Here’s a detailed README for GitHub that covers installation, configuration, and usage of the **ZenoPay Payment Gateway** plugin:

---

# ZenoPay Payment Gateway for WooCommerce

![ZenoPay Logo](https://www.zeno.africa/assets/zenopay-logo.png)

## Overview

The **ZenoPay Payment Gateway** plugin integrates ZenoPay into WooCommerce, enabling merchants to accept payments directly through the ZenoPay platform. This plugin is designed for ease of use and provides a straightforward setup process.

## Features

- **Seamless Integration:** Integrates ZenoPay with WooCommerce for easy online payments.
- **Customizable Settings:** Configure payment title, description, and your ZenoPay Account ID.
- **Error Logging:** Logs cURL request and response for troubleshooting.
- **Webhook Support:** Placeholder for handling ZenoPay webhook notifications.

## Installation

### 1. Download the Plugin

You can download the latest version of the ZenoPay Payment Gateway plugin from the [Releases](https://github.com/zenoltd/zenopay-woocommerce/releases) page.

### 2. Upload the Plugin to WordPress

1. **Log in** to your WordPress admin dashboard.
2. Navigate to **Plugins > Add New** and click **Upload Plugin**.
3. Click **Choose File** and select the `zenopay-payment-gateway.zip` file you downloaded.
4. Click **Install Now** and then **Activate Plugin**.

### 3. Configure the Plugin

1. Go to **WooCommerce > Settings > Payments**.
2. Find **ZenoPay Payment Gateway** and click **Manage**.
3. Enter your **ZenoPay Account ID** which you will receive upon opening a ZenoPay account using the [ZenoPay app](https://www.zeno.africa). 

   **Note:** If you do not have an Account ID, please [sign up for ZenoPay](https://www.zeno.africa) to get one.

4. Configure the following settings:
   - **Enable/Disable:** Check this box to enable the ZenoPay Payment Gateway for your store.
   - **Title:** Enter the title that will be displayed on the checkout page.
   - **Description:** Enter a description for the payment method.

5. Click **Save changes**.

## Usage

Once configured, **ZenoPay Payment Gateway** will appear as a payment option during checkout. Customers will be redirected to the ZenoPay payment page to complete the transaction.

## Webhook Handling

The `handle_webhook` function is a placeholder for you to add your webhook handling logic. You can extend this function to process payment notifications from ZenoPay.

```php
public function handle_webhook() {
    // Webhook handling logic
}
```

## Troubleshooting

- **cURL Errors:** Check your server’s cURL configuration and make sure it is up-to-date. Review the `debug.log` for cURL errors and ensure your Account ID is correct.
- **API Response Issues:** Look into the `debug.log` file for API request and response logs to diagnose problems.

## Contributing

Contributions are welcome! If you find a bug or have suggestions for improvements, please [open an issue](https://github.com/zenoltd/zenopay-woocommerce/issues) or submit a [pull request](https://github.com/zenoltd/zenopay-woocommerce/pulls).

## Changelog

### [1.0.0] - 2024-07-15
- Initial release of the ZenoPay Payment Gateway for WooCommerce.

## License

This plugin is licensed under the [GPL v3 License](https://opensource.org/licenses/GPL-3.0). See [LICENSE](LICENSE) for more details.

## Author

**Dastani Ferdinandi**  
[Zeno Limited](https://www.zeno.africa)  
[Contact](https://www.zeno.africa/support)

---

Feel free to customize the README to better fit your project or preferences!# zenopay-wordpress-plugin
