<?php
/**
 * Class WC_Gateway_Coinpayments file.
 *
 * @package WooCommerce\Gateways
 */

class WC_Gateway_Coinpayments extends WC_Payment_Gateway
{

    public static $webhook_checked;

    /**
     * WC_Gateway_Coinpayments constructor.
     * @throws Exception
     */
    public function __construct()
    {


        $this->id = 'coinpayments';
        $this->icon = apply_filters('woocommerce_coinpayments_icon', plugins_url() . '/coinpayments-payment-gateway-for-woocommerce/assets/images/icons/coinpayments.png');
        $this->has_fields = false;
        $this->method_title = __('CoinPayments.net', 'coinpayments-payment-gateway-for-woocommerce');

        // Load the settings.
        $this->init_form_fields();
        $this->init_settings();

        // Define user set variables
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->client_id = $this->get_option('client_id');
        $this->client_secret = $this->get_option('client_secret');
        $this->webhooks = $this->get_option('webhooks');


        $this->form_submission_method = $this->get_option('form_submission_method') == 'yes' ? true : false;

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_api_wc_gateway_coinpayments', array($this, 'check_ipn_response'));

        $save_action = ($_SERVER['REQUEST_METHOD'] == 'POST' &&
            isset($_GET['page']) && $_GET['page'] == "wc-settings" &&
            isset($_GET['tab']) && $_GET['tab'] == "checkout" &&
            isset($_GET['section']) && $_GET['section'] == "coinpayments");

        if ($save_action && !self::$webhook_checked) {
            self::$webhook_checked = true;
            $coinpayments = new WC_Gateway_Coinpayments_API_Handler($_POST['client_id'], $_POST['webhooks'], $_POST['client_secret']);
            if (!empty($this->client_id) && !empty($this->webhooks) && !empty($this->client_secret)) {
                if (!$coinpayments->check_webhook()) {
                    $coinpayments->create_webhook();
                }
            }
        }

    }

    /**
     * Init settings for gateways.
     */
    public function init_settings()
    {
        parent::init_settings();
        $this->enabled = !empty($this->settings['enabled']) && 'yes' === $this->settings['enabled'] ? 'yes' : 'no';
    }

    /**
     * Admin Panel Options
     */
    public function admin_options()
    {

        ?>
        <h3><?php _e('CoinPayments.net', 'coinpayments-payment-gateway-for-woocommerce'); ?></h3>
        <p><?php _e('Completes checkout via CoinPayments.net', 'coinpayments-payment-gateway-for-woocommerce'); ?></p>

        <table class="form-table">
            <?php
            // Generate the HTML For the settings form.
            $this->generate_settings_html();
            ?>
        </table><!--/.form-table-->

        <?php

    }

    /**
     * Initialise Gateway Settings Form Fields
     *
     * @access public
     * @return void
     */
    public function init_form_fields()
    {
        $this->form_fields = include plugin_dir_path(__FILE__) . '/settings-coinpayments.php';
    }

    /**
     * Process the payment and return the result
     *
     * @access public
     * @param int $order_id
     * @return string
     * @throws Exception
     */
    public function process_payment($order_id)
    {

        $order = wc_get_order($order_id);

        if ($order->get_status() != 'completed' && get_post_meta($order->get_id(), 'CoinPayments payment complete', true) != 'Yes') {
            $order->update_status('pending', 'Customer is being redirected to CoinPayments...');
        }

        $coinpayments_api = new WC_Gateway_Coinpayments_API_Handler($this->client_id, $this->webhooks, $this->client_secret);

        $invoice_id = sprintf('%s|%s', md5(get_site_url()), $order->data['id']);

        $currency_code = $order->data['currency'];
        $coin_currency = $coinpayments_api->get_coin_currency($currency_code);

        $amount = intval(number_format($order->data['total'], $coin_currency['decimalPlaces'], '', ''));
        $display_value = $order->data['total'];

        $invoice = $coinpayments_api->create_invoice($invoice_id, $coin_currency['id'], $amount, $display_value);

        $coinpayments_args = array(
            'invoice-id' => $invoice['id'],
            'success-url' => $this->get_return_url($order),
            'cancel-url' => esc_url_raw($order->get_cancel_order_url_raw()),
        );
        $coinpayments_args = http_build_query($coinpayments_args, '', '&');
        $redirect_url = sprintf('%s/%s?%s', WC_Gateway_Coinpayments_API_Handler::API_URL, WC_Gateway_Coinpayments_API_Handler::API_CHECKOUT_ACTION, $coinpayments_args);

        return $redirect_url;
    }

    function check_ipn_response()
    {

        @ob_clean();

        $signature = $_SERVER['HTTP_X_COINPAYMENTS_SIGNATURE'];
        $content = file_get_contents('php://input');

        $coinpayments_api = new WC_Gateway_Coinpayments_API_Handler($this->client_id, $this->webhooks, $this->client_secret);

        $request_data = json_decode($content, true);

        if ($coinpayments_api->check_data_signature($signature, $content) && isset($request_data['invoice']['invoiceId'])) {
            $invoice_str = $request_data['invoice']['invoiceId'];
            $invoice_str = explode('|', $invoice_str);

            $host_hast = array_shift($invoice_str);
            $invoice_id = array_shift($invoice_str);

            if ($host_hast == md5(get_site_url())) {

                $order = wc_get_order($invoice_id);

                if ($request_data['invoice']['status'] == 'Pending') {
                    $order->update_status('pending', 'CoinPayments.net Payment pending');
                } elseif ($request_data['invoice']['status'] == 'Completed') {
                    update_post_meta($order->get_id(), 'CoinPayments payment complete', 'Yes');
                    $order->payment_complete();
                } elseif ($request_data['invoice']['status'] == 'Cancelled') {
                    $order->update_status('cancelled', 'CoinPayments.net Payment cancelled/timed out');
                }
            }
        }
    }

}

class WC_Coinpayments extends WC_Gateway_Coinpayments
{
    public function __construct()
    {
        _deprecated_function('WC_Coinpayments', '1.4', 'WC_Gateway_Coinpayments');
        parent::__construct();
    }
}