<?php
/**
 * Class WC_Gateway_Coinpayments file.
 *
 * @package WooCommerce\Gateways
 */

class WC_Gateway_Coinpayments extends WC_Payment_Gateway
{

    var $ipn_url;

    /**
     * Constructor for the gateway.
     *
     * @access public
     * @return void
     */
    public function __construct()
    {


        add_action('wp_loaded', array($this, 'validate_credentials'));

        $this->id = 'coinpayments';
        $this->icon = apply_filters('woocommerce_coinpayments_icon', plugins_url() . '/coinpayments-payment-gateway-for-woocommerce/assets/images/icons/coinpayments.png');
        $this->has_fields = false;
        $this->method_title = __('CoinPayments.net', 'coinpayments-payment-gateway-for-woocommerce');
        $this->ipn_url = add_query_arg('wc-api', 'WC_Gateway_Coinpayments', home_url('/'));

        // Load the settings.
        $this->init_form_fields();
        $this->init_settings();

        // Define user set variables
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->mode = $this->get_option('mode');
        $this->rate = $this->get_option('rate');
        $this->merchant_id = $this->get_option('merchant_id');
        $this->public_key = $this->get_option('public_key');
        $this->private_key = $this->get_option('private_key');
        $this->ipn_secret = $this->get_option('ipn_secret');

        $this->form_submission_method = $this->get_option('form_submission_method') == 'yes' ? true : false;

        $validate_for_page = ($_GET['page'] == "wc-settings" &&
            $_GET['tab'] == "checkout" &&
            $_GET['section'] == "coinpayments");


        if (
            $validate_for_page &&
            $this->validate_credentials() &&
            ($this->rates = WC_Gateway_Coinpayments_API_Handler::get_rates($this->settings))
        ) {
            foreach ($this->rates as $rateKey => $rateData) {
                $rates[$rateKey] = $rateData['name'];
            }
            $this->form_fields['currency']['options'] = array_merge($this->form_fields['currency']['options'], $rates);
        } else {
            $GLOBALS['hide_save_button'] = true;
            $GLOBALS['validate_coin_credentials'] = true;
            $this->form_fields = array_intersect_key($this->form_fields, array_flip(['merchant_id', 'public_key', 'private_key']));
        }

        // Logs
        $this->log = new WC_Logger();

        // Actions
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

        // Payment listener/API hook
        add_action('woocommerce_api_wc_gateway_coinpayments', array($this, 'check_ipn_response'));

        if (is_checkout() && $this->mode == 'direct') {
            wp_register_script('coinpayments_currency_block', plugins_url() . '/coinpayments-payment-gateway-for-woocommerce/assets/js/coinpayments_currency_block.js', array('jquery'), WC_VERSION);
            wp_enqueue_script('coinpayments_currency_block');
        }

    }


    /**
     * Init settings for gateways.
     */
    public function init_settings()
    {
        parent::init_settings();
        $this->enabled = !empty($this->settings['enabled']) && 'yes' === $this->settings['enabled'] ? 'yes' : 'no';

        if ($_POST['validate']) {

            $this->settings['merchant_id'] = $_POST['woocommerce_coinpayments_merchant_id'];
            $this->settings['public_key'] = $_POST['woocommerce_coinpayments_public_key'];
            $this->settings['private_key'] = $_POST['woocommerce_coinpayments_private_key'];

        }

    }

    /**
     * Admin Panel Options
     * - Options for bits like 'title' and availability on a country-by-country basis
     *
     * @since 1.0.0
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

        <?php if (!empty($GLOBALS['validate_coin_credentials'])) : ?>
        <p class="submit">
            <button name="validate" class="button-primary" type="submit"
                    value="<?php esc_attr_e('Validate credentials', 'coinpayments-payment-gateway-for-woocommerce'); ?>"><?php esc_html_e('Validate credentials', 'coinpayments-payment-gateway-for-woocommerce'); ?></button>
        </p>
    <?php endif;

    }

    /**
     * Initialise Gateway Settings Form Fields
     *
     * @access public
     * @return void
     */
    function init_form_fields()
    {
        $this->form_fields = include plugin_dir_path(__FILE__) . '/settings-coinpayments.php';
    }

    /**
     * @access public
     * @return bool
     */
    public function validate_credentials()
    {

        $valid = false;

        if (!empty($this->settings['merchant_id']) &&
            !empty($this->settings['public_key']) &&
            !empty($this->settings['private_key'])) {
            $valid = true;
        }

        return $valid;
    }

    /**
     * @param array $transaction
     *
     * @return array StatusTimeData
     */
    public function get_status_time_data($transaction)
    {

        $now = time();
        $time_left = $transaction['expire'] - $now;
        $time_diff = sprintf('%02dm %02ds', intval($time_left / 60), ($time_left % 60));
        return [
            'time_left' => $time_left,
            'time_diff' => $time_diff
        ];
    }

    /**
     * Process the payment and return the result
     *
     * @access public
     * @param int $order_id
     * @return array
     */
    function process_payment($order_id)
    {

        $order = wc_get_order($order_id);

        if ($this->settings->mode == 'redirect') {
            $results = array(
                'result' => 'success',
                'redirect' => $this->generate_coinpayments_redirect_url($order),
            );
        } else {
            $results = array(
                'result' => 'success',
                'redirect' => $this->generate_coinpayments_direct_url($order),
            );
        }

        return $results;

    }

    /**
     * Generate the coinpayments button link
     *
     * @access public
     * @param mixed $order_id
     * @return string
     */
    function generate_coinpayments_redirect_url($order)
    {
        global $woocommerce;

        if ($order->get_status() != 'completed' && get_post_meta($order->get_id(), 'CoinPayments payment complete', true) != 'Yes') {
            //$order->update_status('on-hold', 'Customer is being redirected to CoinPayments...');
            $order->update_status('pending', 'Customer is being redirected to CoinPayments...');
        }

        $coinpayments_adr = "https://www.coinpayments.net/index.php?";
        $coinpayments_args = $this->get_coinpayments_redirect_args($order);
        $coinpayments_adr .= http_build_query($coinpayments_args, '', '&');
        return $coinpayments_adr;
    }

    /**
     * Get CoinPayments.net redirect-mode Args
     *
     * @access public
     * @param mixed $order
     * @return array
     */
    function get_coinpayments_redirect_args($order)
    {
        global $woocommerce;

        $order_id = $order->get_id();

        if (in_array($order->get_billing_country(), array('US', 'CA'))) {
            $order->set_billing_phone(str_replace(array('( ', '-', ' ', ' )', '.'), '', $order->get_billing_phone()));
        }

        // CoinPayments.net Args
        $coinpayments_args = array(
            'cmd' => '_pay_auto',
            'merchant' => $this->merchant_id,
            'allow_extra' => 0,
            // Get the currency from the order, not the active currency
            'currency' => $this->settings['currency'],
            'reset' => 1,
            'success_url' => $this->get_return_url($order),
            'cancel_url' => esc_url_raw($order->get_cancel_order_url_raw()),

            // Order key + ID
            'invoice' => $this->invoice_prefix . $order->get_order_number(),
            'custom' => serialize(array($order->get_id(), $order->get_order_key())),

            // IPN
            'ipn_url' => $this->ipn_url,

            // Billing Address info
            'first_name' => $order->get_billing_first_name(),
            'last_name' => $order->get_billing_last_name(),
            'email' => $order->get_billing_email(),
        );

        $coinpayments_args = apply_filters('woocommerce_coinpayments_redirect_args', $coinpayments_args);

        return $coinpayments_args;
    }

    /**
     * Generate the coinpayments transaction
     *
     * @access public
     * @param mixed $order_id
     * @return string
     */
    function generate_coinpayments_direct_url($order)
    {
        $order_number = $order->get_order_number();
        $transactions = WC()->session->get('transactions');
        if (!$transactions[$order_number]) {

            $transaction_args = $this->get_coinpayments_direct_args($order);

            $transaction = WC_Gateway_Coinpayments_API_Handler::create_transaction($transaction_args);
            $transaction['expire'] = time() + $transaction['result']['timeout'];
            $transactions[$order_number] = $transaction['result'];
            WC()->session->set('transactions', $transactions);


            if ($order->get_status() != 'completed' && get_post_meta($order->get_id(), 'CoinPayments payment complete', true) != 'Yes') {
                $order->update_status('pending', 'Customer is being redirected to CoinPayments...');
            }

        }
        $redirect_adr = '/payment/coinpayments/status/' . $order->get_order_number();

        return $redirect_adr;
    }

    /**
     * Get CoinPayments.net redirect-mode Args
     *
     * @access public
     * @param mixed $order
     * @return array
     */
    function get_coinpayments_direct_args($order)
    {
        global $woocommerce;

        $default_currency = get_woocommerce_currency();
        $custom_currency = WC()->session->get('coinpayments_currency');

        $amount = $order->get_total();
        $amount = ($this->rates[$default_currency]['rate_btc'] * $amount) / $this->rates[$custom_currency]['rate_btc'];

        // CoinPayments.net Args
        $coinpayments_args = array(
            'rates' => WC_Gateway_Coinpayments_API_Handler::get_rates($this->settings),
            'default_currency' => $default_currency,
            'custom_currency' => $custom_currency,
            'receive_currency' => $this->settings['currency'],
            'amount' => $amount,
            'public_key' => $this->settings['public_key'],
            'private_key' => $this->settings['private_key'],
            'email' => $order->get_billing_email(),
            'name' => trim(sprintf('%s %s', $order->get_billing_first_name(), $order->get_billing_last_name())),
            'urls' => array(
                'success_url' => $this->get_return_url($order),
                'cancel_url' => esc_url_raw($order->get_cancel_order_url_raw()),
                'ipn_url' => $this->ipn_url,
            ),
            'invoice' => $this->invoice_prefix . $order->get_order_number(),
            'custom' => serialize(array($order->get_id(), $order->get_order_key())),
        );

        $coinpayments_args = apply_filters('woocommerce_coinpayments_direct_args', $coinpayments_args);

        return $coinpayments_args;
    }

    /**
     * Check CoinPayments.net IPN validity
     **/
    function check_ipn_request_is_valid()
    {
        global $woocommerce;

        $order = false;
        $error_msg = "Unknown error";
        $auth_ok = false;

        if (isset($_POST['ipn_mode']) && $_POST['ipn_mode'] == 'hmac') {
            if (isset($_SERVER['HTTP_HMAC']) && !empty($_SERVER['HTTP_HMAC'])) {
                $request = file_get_contents('php://input');
                if ($request !== FALSE && !empty($request)) {
                    if (isset($_POST['merchant']) && $_POST['merchant'] == trim($this->merchant_id)) {
                        $hmac = hash_hmac("sha512", $request, trim($this->ipn_secret));
                        if ($hmac == $_SERVER['HTTP_HMAC']) {
                            $auth_ok = true;
                        } else {
                            $error_msg = 'HMAC signature does not match';
                        }
                    } else {
                        $error_msg = 'No or incorrect Merchant ID passed';
                    }
                } else {
                    $error_msg = 'Error reading POST data';
                }
            } else {
                $error_msg = 'No HMAC signature sent.';
            }
        } else {
            $error_msg = "Unknown IPN verification method.";
        }

        if ($auth_ok) {
            if (!empty($_POST['invoice']) && !empty($_POST['custom'])) {
                $order = $this->get_coinpayments_order($_POST);
            }

            if ($order !== FALSE) {
                if ($_POST['ipn_type'] == "button" || $_POST['ipn_type'] == "simple" || $_POST['ipn_type'] == "api") {
                    if ($_POST['merchant'] == $this->merchant_id) {
                        print "IPN check OK\n";
                        return true;
                    } else {
                        $error_msg = "Merchant ID doesn't match!";
                    }
                } else {
                    $error_msg = "ipn_type != api, button or simple";
                }
            } else {
                $error_msg = "Could not find order info for order: " . $_POST['invoice'];
            }
        }

        $report = "Error Message: " . $error_msg . "\n\n";

        $report .= "POST Fields\n\n";
        foreach ($_POST as $key => $value) {
            $report .= $key . '=' . $value . "\n";
        }

        if ($order) {
            $order->update_status('on-hold', sprintf(__('CoinPayments.net IPN Error: %s', 'coinpayments-payment-gateway-for-woocommerce'), $error_msg));
        }
        if (!empty($this->debug_email)) {
            mail($this->debug_email, "CoinPayments.net Invalid IPN", $report);
        }
        mail(get_option('admin_email'), sprintf(__('CoinPayments.net Invalid IPN', 'coinpayments-payment-gateway-for-woocommerce'), $error_msg), $report);
        die('IPN Error: ' . $error_msg);
        return false;
    }

    /**
     * Successful Payment!
     *
     * @access public
     * @param array $posted
     * @return void
     */
    function successful_request($posted)
    {
        global $woocommerce;

        $posted = stripslashes_deep($posted);

        // Custom holds post ID
        if (!empty($_POST['invoice']) && !empty($_POST['custom'])) {
            $order = $this->get_coinpayments_order($posted);
            if ($order === FALSE) {
                die("IPN Error: Could not find order info for order: " . $_POST['invoice']);
            }

            $this->log->add('coinpayments', 'Order #' . $order->get_id() . ' payment status: ' . $posted['status_text']);
            $order->add_order_note('CoinPayments.net Payment Status: ' . $posted['status_text']);

            if ($order->get_status() != 'completed' && get_post_meta($order->get_id(), 'CoinPayments payment complete', true) != 'Yes') {
                // no need to update status if it's already done
                if (!empty($posted['txn_id']))
                    update_post_meta($order->get_id(), 'Transaction ID', $posted['txn_id']);
                if (!empty($posted['first_name']))
                    update_post_meta($order->get_id(), 'Payer first name', $posted['first_name']);
                if (!empty($posted['last_name']))
                    update_post_meta($order->get_id(), 'Payer last name', $posted['last_name']);
                if (!empty($posted['email']))
                    update_post_meta($order->get_id(), 'Payer email', $posted['email']);

                if ($posted['status'] >= 100 || $posted['status'] == 2 || ($this->allow_zero_confirm && $posted['status'] >= 0 && $posted['received_confirms'] > 0 && $posted['received_amount'] >= $posted['amount2'])) {
                    print "Marking complete\n";
                    update_post_meta($order->get_id(), 'CoinPayments payment complete', 'Yes');
                    $order->payment_complete();
                } else if ($posted['status'] < 0) {
                    print "Marking cancelled\n";
                    $order->update_status('cancelled', 'CoinPayments.net Payment cancelled/timed out: ' . $posted['status_text']);
                    mail(get_option('admin_email'), sprintf(__('Payment for order %s cancelled/timed out', 'coinpayments-payment-gateway-for-woocommerce'), $order->get_order_number()), $posted['status_text']);
                } else {
                    print "Marking pending\n";
                    $order->update_status('pending', 'CoinPayments.net Payment pending: ' . $posted['status_text']);
                }
            }
            die("IPN OK");
        }
    }

    /**
     * Check for CoinPayments IPN Response
     *
     * @access public
     * @return void
     */
    function check_ipn_response()
    {

        @ob_clean();

        if (!empty($_POST) && $this->check_ipn_request_is_valid()) {
            $this->successful_request($_POST);
        } else {
            wp_die("CoinPayments.net IPN Request Failure");
        }
    }

    /**
     * get_coinpayments_order function.
     *
     * @access public
     * @param mixed $posted
     * @return void
     */
    function get_coinpayments_order($posted)
    {
        $custom = maybe_unserialize(stripslashes_deep($posted['custom']));

        // Backwards comp for IPN requests
        if (is_numeric($custom)) {
            $order_id = (int)$custom;
            $order_key = $posted['invoice'];
        } elseif (is_string($custom)) {
            $order_id = (int)str_replace($this->invoice_prefix, '', $custom);
            $order_key = $custom;
        } else {
            list($order_id, $order_key) = $custom;
        }

        $order = wc_get_order($order_id);

        if ($order === FALSE) {
            // We have an invalid $order_id, probably because invoice_prefix has changed
            $order_id = wc_get_order_id_by_order_key($order_key);
            $order = wc_get_order($order_id);
        }

        // Validate key
        if ($order === FALSE || $order->get_order_key() !== $order_key) {
            return FALSE;
        }

        return $order;
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