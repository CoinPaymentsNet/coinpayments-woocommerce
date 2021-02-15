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
     * @var string
     */
    protected $client_id;
    /**
     * @var string
     */
    protected $client_secret;
    /**
     * @var string
     */
    protected $webhooks;
    /**
     * @var string
     */
    protected $debug_email;

    /**
     * WC_Gateway_Coinpayments constructor.
     * @throws Exception
     */
    public function __construct()
    {


        $this->id = 'coinpayments';
        $this->icon = apply_filters('woocommerce_coinpayments_icon', plugins_url() . '/' . plugin_basename(dirname(__FILE__) . '/../') . '/assets/images/icons/coinpayments.svg');
        $this->has_fields = false;
        $this->method_title = __('CoinPayments.net', 'coinpayments-payment-gateway-for-woocommerce');

        // Load the settings.
        $this->init_form_fields();
        $this->init_settings();

        // Define user set variables
        $this->title = $this->get_option('title');
        $coinpayments_link = sprintf(
            '<a href="%s" target="_blank" title="CoinPayments.net">CoinPayments.net</a>',
            esc_url('https://alpha.coinpayments.net/')
        );

        $coin_description = 'Pay with Bitcoin, Litecoin, or other altcoins via ';
        $this->description = sprintf('%s<br/>%s<br/>%s', $this->get_option('description'), $coin_description, $coinpayments_link);
        $this->client_id = $this->get_option('client_id');
        $this->client_secret = $this->get_option('client_secret');
        $this->webhooks = $this->get_option('webhooks');
        $this->debug_email = $this->get_option('debug_email');


        $this->form_submission_method = $this->get_option('form_submission_method') == 'yes' ? true : false;

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_api_wc_gateway_coinpayments', array($this, 'check_wehhook_notification'));
        add_action('coinpayments_debug_notification', array($this, 'send_debug_email'));


        $save_action = ($_SERVER['REQUEST_METHOD'] == 'POST' &&
            isset($_GET['page']) && $_GET['page'] == "wc-settings" &&
            isset($_GET['tab']) && $_GET['tab'] == "checkout" &&
            isset($_GET['section']) && $_GET['section'] == "coinpayments");

        if ($save_action && !self::$webhook_checked) {
            self::$webhook_checked = true;
            $coinpayments = new WC_Gateway_Coinpayments_API_Handler($_POST['woocommerce_coinpayments_client_id'], $_POST['woocommerce_coinpayments_webhooks'], $_POST['woocommerce_coinpayments_client_secret']);
            if (!empty($_POST['woocommerce_coinpayments_client_id']) && !empty($_POST['woocommerce_coinpayments_webhooks']) && !empty($_POST['woocommerce_coinpayments_client_secret'])) {
                try {
                    if (!$coinpayments->check_webhook()) {
                        $coinpayments->create_webhook(WC_Gateway_Coinpayments_API_Handler::PAID_EVENT);
                        $coinpayments->create_webhook(WC_Gateway_Coinpayments_API_Handler::PENDING_EVENT);
                        $coinpayments->create_webhook(WC_Gateway_Coinpayments_API_Handler::CANCELLED_EVENT);
                    }
                } catch (Exception $e) {
                    do_action('coinpayments_debug_notification', $e);
                    add_action('admin_notices', array($this, 'admin_error'), 10, 1);
                }
            }
        }

    }

    public function admin_error($message)
    {
        echo '<div class="notice notice-error is-dismissible"> <p>' . __('CoinPayments.NET credentials is not valid!', 'coinpayments-payment-gateway-for-woocommerce') . '</p></div>';
    }


    public function send_debug_email($error)
    {
        if (!empty($this->debug_email)) {
            $to = $this->debug_email;
            $subject = __('Coinpayments.NET gateway debug notification | ' . get_bloginfo('name'), 'coinpayments-payment-gateway-for-woocommerce');
            $body = __('There are next issues with coinpayments payment gateway:<br/>', 'coinpayments-payment-gateway-for-woocommerce');
            $body .= $error->getMessage();
            $headers = array('Content-Type: text/html; charset=UTF-8');
            wp_mail($to, $subject, $body, $headers);
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
     * @return array
     * @throws Exception
     */
    public function process_payment($order_id)
    {

        $order = wc_get_order($order_id);

        if ($order->get_status() != 'completed' && get_post_meta($order->get_id(), 'CoinPayments payment complete', true) != 'Yes') {
            $order->update_status('pending', 'Customer is being redirected to CoinPayments...');
        }

        $coinpayments_api = new WC_Gateway_Coinpayments_API_Handler($this->client_id, $this->webhooks, $this->client_secret);

        $order_data = $order->get_base_data();

        $invoice_id = $coinpayments_api->get_invoice_id($order->get_id());

        if (empty($invoice = WC()->session->get($invoice_id))) {
            $currency_code = $order_data['currency'];
            $coin_currency = $coinpayments_api->get_coin_currency($currency_code);

            $notes_link = sprintf(
                "%s|Store name: %s|Order #%s",
                admin_url('post.php?post=' . $order_id) . '&action=edit',
                get_bloginfo('name'),
                $order->get_id());

            $invoice_params = array(
                'invoice_id' => $invoice_id,
                'currency_id' => $coin_currency['id'],
                'amount' => intval(number_format($order_data['total'], $coin_currency['decimalPlaces'], '', '')),
                'display_value' => $order_data['total'],
                'billing_data' => $order_data['billing'],
                'notes_link' => $notes_link,
            );

            try {
                $invoice = $coinpayments_api->create_invoice($invoice_params);
                if ($this->webhooks) {
                    $invoice = array_shift($invoice['invoices']);
                }
                WC()->session->set($invoice_id, $invoice);
            } catch (Exception $e) {
                do_action('coinpayments_debug_notification', $e);
                throw new Exception(sprintf('Can\'t create Coinpayments.NET invoice, please contact to %s', get_option('admin_email')), $e->getCode());
            }
        }

        $coinpayments_args = array(
            'invoice-id' => $invoice['id'],
            'success-url' => $this->get_return_url($order),
            'cancel-url' => esc_url_raw($order->get_cancel_order_url_raw()),
        );
        $coinpayments_args = http_build_query($coinpayments_args, '', '&');
        $redirect_url = sprintf('%s/%s/?%s', WC_Gateway_Coinpayments_API_Handler::CHECKOUT_URL, WC_Gateway_Coinpayments_API_Handler::API_CHECKOUT_ACTION, $coinpayments_args);

        return array('result' => 'success', 'redirect' => $redirect_url);
    }

    public function check_wehhook_notification()
    {

        @ob_clean();

        $signature = $_SERVER['HTTP_X_COINPAYMENTS_SIGNATURE'];
        $content = file_get_contents('php://input');

        $coinpayments_api = new WC_Gateway_Coinpayments_API_Handler($this->client_id, $this->webhooks, $this->client_secret);

        $request_data = json_decode($content, true);

        if ($this->webhooks && $coinpayments_api->check_data_signature($signature, $content, $request_data['invoice']['status']) && isset($request_data['invoice']['invoiceId'])) {
            $invoice_str = $request_data['invoice']['invoiceId'];
            $invoice_str = explode('|', $invoice_str);

            $host_hash = array_shift($invoice_str);
            $invoice_id = array_shift($invoice_str);

            if ($host_hash == md5(get_site_url())) {
                if (!empty($order = wc_get_order($invoice_id))) {
                    $completed_statuses = $this->get_completed_statuses();
                    if (in_array($request_data['invoice']['status'], $completed_statuses)) {
                        update_post_meta($order->get_id(), 'CoinPayments payment complete', 'Yes');
                        $order->payment_complete();
                    } elseif ($request_data['invoice']['status'] == WC_Gateway_Coinpayments_API_Handler::CANCELLED_EVENT) {
                        $order->update_status('cancelled', 'CoinPayments.net Payment cancelled/timed out');
                    }
                }
            }
        }
    }

    /**
     * @return array
     */
    public function get_completed_statuses()
    {
        return array(
            WC_Gateway_Coinpayments_API_Handler::PAID_EVENT,
            WC_Gateway_Coinpayments_API_Handler::PENDING_EVENT
        );
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