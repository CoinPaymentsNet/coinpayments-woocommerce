<?php
/**
 * WooCommerce WC_AJAX. AJAX Event Handlers.
 *
 * @class   WC_AJAX
 * @package WooCommerce/Classes
 */

defined('ABSPATH') || exit;

/**
 * WC_Ajax class.
 */
class WC_GATEWAY_COINPAYMENTS_AJAX
{

    public static function set_coinpayments_currency()
    {
        $session = WC()->session;
        if ($coinpayments_currency = $_POST['coinpayments_currency']) {
            $wp_session = $session->set('coinpayments_currency', $coinpayments_currency);
        }

    }

    public static function json_coinpayments_data()
    {

        $default_currency = get_woocommerce_currency();
        $gateway = new WC_Gateway_Coinpayments();
        $rates = WC_Gateway_Coinpayments_API_Handler::get_rates($gateway->settings, true);
        $default_rate = WC()->session->get('coinpayments_currency');
        $total = WC()->cart->get_total(false);

        $currencies = [];
        foreach ($rates as $rate_code => $rate_data) {
            if (!$rate_data['accepted'] && $rate_code != $default_currency) {
                unset($rates[$rate_code]);
            } elseif ($rate_data['accepted']) {
                $currencies[$rate_code] = $rate_data['name'];
            }
        }
        $tmpl = 'checkout-currency-block.php';

        $currency_amount = '';
        if ($default_rate) {

            $btc_rate = $rates[$default_currency]['rate_btc'];
            if ($default_rate === 'BTC') {
                $currency_amount = $btc_rate * $total;
            } else {
                $currency_amount = ($btc_rate * $total) / $rates[$default_rate]['rate_btc'];
            }

            $currency_amount = number_format($currency_amount, 7, '.', '') . ' ' . $default_rate;
        }

        $currency_block = wc_get_template_html($tmpl,
            [
                'total' => $total,
                'currencies' => $currencies,
                'default_rate' => $default_rate,
                'default_currency' => $default_currency,
                'currency_amount' => $currency_amount,
            ],
            '',
            plugin_dir_path(__FILE__) . '../templates/'
        );


        $data = [
            'currency_block' => $currency_block,
            'rates' => $rates,
            'total' => $total,
            'default_rate' => $default_rate,
            'default_currency' => $default_currency,
        ];

        wp_send_json($data);
    }

}