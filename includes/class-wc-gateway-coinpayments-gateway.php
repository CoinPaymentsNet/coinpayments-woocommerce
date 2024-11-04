<?php
/**
 * Class WC_Gateway_Coinpayments file.
 *
 * @package WooCommerce\Gateways
 */

class WC_Gateway_Coinpayments extends WC_Payment_Gateway
{
    const GATEWAY_ID = 'coinpayments';

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
        $this->id = self::GATEWAY_ID;
        $this->icon = apply_filters('woocommerce_coinpayments_icon', plugins_url() . '/' . plugin_basename(dirname(__FILE__) . '/../') . '/assets/images/icons/coinpayments.svg');
        $this->has_fields = false;
        $this->method_title = __('CoinPayments.net', 'coinpayments-payment-gateway-for-woocommerce');
        $this->method_description = $this->get_option('description');
        $this->supports = ['products', 'block']; // Add support for blocks

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
    }

    /**
     * Processes and saves options.
     * If there is an error thrown, will continue to save and validate fields, but will leave the erroring field out.
     *
     * @return bool was anything saved?
     */
    public function process_admin_options() {
        // Load form data to get updated values
        $postData = $this->get_post_data();
        $hasErrors = false;

        // Validate required fields
        $clientId = $postData[sprintf('woocommerce_%s_client_id', $this->id)] ?? null;
        if (empty($clientId)) {
            WC_Admin_Settings::add_error('You should provide Client ID.');
            $hasErrors = true;
        }

        $clientSecret = $postData['woocommerce_' . $this->id . '_client_secret'] ?? null;
        if (empty($clientSecret)) {
            WC_Admin_Settings::add_error('You should provide Client Secret.');
            $hasErrors = true;
        }

        if (!$hasErrors) {
            $isWebhooksEnabled = (bool)($postData['woocommerce_' . $this->id . '_webhooks'] ?? false);
            $coinpayments = new WC_Gateway_Coinpayments_API_Handler($clientId, $clientSecret);
            try {
                $coinpayments->get_invoices();
                if ($isWebhooksEnabled && !$coinpayments->isWebhooksExists()) {
                    $coinpayments->create_webhook(WC_Gateway_Coinpayments_API_Handler::PAID_EVENT);
                    $coinpayments->create_webhook(WC_Gateway_Coinpayments_API_Handler::CANCELLED_EVENT);
                }
            } catch (Exception $e) {
                do_action('coinpayments_debug_notification', $e);
                WC_Admin_Settings::add_error(__('CoinPayments.NET credentials are not valid!', 'coinpayments-payment-gateway-for-woocommerce'));
                $hasErrors = true;
            }
        }

        // Proceed with saving if no errors
        if (!$hasErrors) {
            return parent::process_admin_options();
        }

        return false; // Prevents saving if there are errors
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

        $coinpaymentsApi = new WC_Gateway_Coinpayments_API_Handler($this->client_id, $this->client_secret);

        $orderData = $order->get_base_data();
        $invoiceId = $coinpaymentsApi->get_invoice_id($order->get_id());

        if (empty($invoice = WC()->session->get($invoiceId))) {
            $coinCurrency = $coinpaymentsApi->get_coin_currency($orderData['currency']);
            $notesLink = sprintf(
                "%s|Store name: %s|Order #%s",
                admin_url('post.php?post=' . $order_id) . '&action=edit',
                get_bloginfo('name'),
                $order->get_id());

            $invoiceParams = array(
                'invoiceId' => $invoiceId,
                'items' => $this->getOrderItems($order, $coinCurrency),
                'amount' => $this->getOrderAmount($order, $coinCurrency),
                'notesToRecipient' => $notesLink,
            );

            try {
                $invoice = $coinpaymentsApi->create_invoice($invoiceParams, $orderData['billing']);
                $invoice = array_shift($invoice['invoices']);
                WC()->session->set($invoiceId, $invoice);
            } catch (Exception $e) {
                do_action('coinpayments_debug_notification', $e);
                throw new Exception(sprintf('Can\'t create Coinpayments.NET invoice, please contact to %s|%s', get_option('admin_email'), $e->getMessage()), $e->getCode());
            }
        }

        return array(
            'result' => 'success',
            'redirect' => $this->getCoinCheckoutRedirectUrl(
                $invoice['id'],
                $this->get_return_url($order),
                esc_url_raw($order->get_cancel_order_url_raw())
            )
        );
    }

    private function getCoinCheckoutRedirectUrl($coinInvoiceId, $successUrl, $cancelUrl)
    {
        return sprintf(
            '%s/%s/?invoice-id=%s&success-url=%s&cancel-url=%s',
            WC_Gateway_Coinpayments_API_Handler::CHECKOUT_URL,
            WC_Gateway_Coinpayments_API_Handler::API_CHECKOUT_ACTION,
            $coinInvoiceId,
            $successUrl,
            $cancelUrl
        );
    }

    private function getOrderAmount(WC_Order $order, $coinCurrency): array
    {
        $smallestUnitsMultiplier = pow(10, $coinCurrency['decimalPlaces']);
        $orderAmountBreakdown = [
            'subtotal' => [
                'currencyId' => $coinCurrency['id'],
                'displayValue' => $this->getAmountDisplayValue($order->get_subtotal(), $coinCurrency),
                'value' => $order->get_subtotal() * $smallestUnitsMultiplier,
            ]
        ];

        $taxTotal = $order->get_total_tax();
        if ($taxTotal) {
            $orderAmountBreakdown['taxTotal'] = [
                'currencyId' => $coinCurrency['id'],
                'displayValue' => $this->getAmountDisplayValue($taxTotal, $coinCurrency),
                'value' => $taxTotal * $smallestUnitsMultiplier,
            ];
        }

        $shipping = $order->get_shipping_total();
        if ($shipping) {
            $orderAmountBreakdown['shipping'] = [
                'currencyId' => $coinCurrency['id'],
                'displayValue' => $this->getAmountDisplayValue($shipping, $coinCurrency),
                'value' => $shipping * $smallestUnitsMultiplier,
            ];
        }

        $discount = $order->get_discount_total();
        if ($discount) {
            $orderAmountBreakdown['discount'] = [
                'currencyId' => $coinCurrency['id'],
                'displayValue' => $this->getAmountDisplayValue($discount, $coinCurrency),
                'value' => $discount * $smallestUnitsMultiplier,
            ];
        }

        return [
            'breakdown' => $orderAmountBreakdown,
            'currencyId' => $coinCurrency['id'],
            'displayValue' => $this->getAmountDisplayValue($order->get_total(), $coinCurrency),
            'value' => $order->get_total() * $smallestUnitsMultiplier,
        ];
    }

    private function getOrderItems(WC_Order $order, $coinCurrency): array
    {
        $smallestUnitsMultiplier = pow(10, $coinCurrency['decimalPlaces']);

        /** @var WC_Order_Item_Product $item */
        $items = [];
        foreach ($order->get_items() as $item) {
            $itemData = [
                'name' => $item->get_name(),
                'quantity' => [
                    'value' => $item->get_quantity(),
                    'type' => '2',
                ],
                'originalAmount' => [
                    'currencyId' => $coinCurrency['id'],
                    'value' => $item->get_subtotal() * $smallestUnitsMultiplier,
                ],
                'amount' => [
                    'currencyId' => $coinCurrency['id'],
                    'value' => $item->get_total() * $smallestUnitsMultiplier,
                ],
            ];

            $items[] = $itemData;
        }

        return $items;
    }

    private function getAmountDisplayValue(float $amount, array $coinCurrency): string
    {
        return sprintf('%s %s', number_format($amount, $coinCurrency['decimalPlaces']), $coinCurrency['symbol']);
    }

    public function check_wehhook_notification()
    {
        @ob_clean();

        $signature = $_SERVER['HTTP_X_COINPAYMENTS_SIGNATURE'];
        $date = $_SERVER['HTTP_X_COINPAYMENTS_TIMESTAMP'];
        $content = file_get_contents('php://input');

        $cpsApiHandler = new WC_Gateway_Coinpayments_API_Handler($this->client_id, $this->client_secret);
        $request_data = json_decode($content, true);
        $invoice = $request_data['invoice'] ?? null;
        if (is_null($invoice)) {
            return;
        }

        if (!isset($invoice['invoiceId'])) {
            return;
        }

        $invoiceState = $invoice['state'] ?? '';
        $isValidSignature = $cpsApiHandler->check_data_signature($signature, 'POST', $content, $invoiceState, $date);
        if ($this->webhooks && $isValidSignature) {
            $invoice_str = $invoice['invoiceId'];
            $invoice_str = explode('|', $invoice_str);
            $host_hash = array_shift($invoice_str);
            $invoice_id = array_shift($invoice_str);
            if ($host_hash == md5(get_site_url())) {
                if (!empty($order = wc_get_order($invoice_id))) {
                    if ($invoiceState == WC_Gateway_Coinpayments_API_Handler::PAID_EVENT) {
                        update_post_meta($order->get_id(), 'CoinPayments payment complete', 'Yes');
                        $order->payment_complete();
                    } elseif ($invoiceState == WC_Gateway_Coinpayments_API_Handler::CANCELLED_EVENT) {
                        $order->update_status('cancelled', 'CoinPayments.net Payment cancelled/timed out');
                    }
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