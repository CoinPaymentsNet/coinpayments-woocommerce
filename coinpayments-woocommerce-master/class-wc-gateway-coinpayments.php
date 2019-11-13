<?php
if (!defined('ABSPATH')) exit; // Exit if accessed directly

/**
 * Plugin Name: WooCommerce CoinPayments.net Gateway
 * Plugin URI: https://www.coinpayments.net/
 * Description:  Provides a CoinPayments.net Payment Gateway.
 * Author: CoinPayments.net
 * Author URI: https://www.coinpayments.net/
 * Version: 1.0.12
 */

/**
 * CoinPayments.net Gateway
 * Based on the PayPal Standard Payment Gateway
 *
 * Provides a CoinPayments.net Payment Gateway.
 *
 * @class        WC_Coinpayments
 * @extends      WC_Gateway_Coinpayments
 * @version      1.0.12
 * @package      WooCommerce\Gateways
 * @author       CoinPayments.net
 */

add_action('plugins_loaded', 'coinpayments_gateway_load', 0);

function coinpayments_gateway_load()
{

    class WC_Gateway_Coinpayments_Plugin
    {

        public function __construct()
        {

            $this->load_textdomain();
            $this->includes();
            $this->actions();
            $this->filters();

        }

        public function load_textdomain()
        {
            load_plugin_textdomain('coinpayments-payment-gateway-for-woocommerce', false, plugin_basename(dirname(__FILE__)) . '/i18n/languages');
        }

        public function includes()
        {
            include_once dirname(__FILE__) . '/includes/class-wc-gateway-coinpayments-gateway.php';
            include_once dirname(__FILE__) . '/includes/class-wc-gateway-coinpayments-api-handler.php';
            include_once dirname(__FILE__) . '/includes/class-wc-gateway-coinpayments-ajax.php';
        }

        public function actions()
        {
            add_action('init', [__CLASS__, 'custom_rewrite_rule'], 10, 0);
            add_action('template_redirect', [__CLASS__, 'coin_page'], 10, 0);
            add_action('wc_ajax_json_coinpayments_data', array('WC_GATEWAY_COINPAYMENTS_AJAX', 'json_coinpayments_data'));
            add_action('wc_ajax_set_coinpayments_currency', array('WC_GATEWAY_COINPAYMENTS_AJAX', 'set_coinpayments_currency'));
        }

        public function filters()
        {
            add_filter('woocommerce_payment_gateways', [__CLASS__, 'add_gateway'], 0);
            add_filter('query_vars', [__CLASS__, 'add_query_vars'], 0);
        }

        function add_gateway($methods)
        {
            if (!in_array('WC_Gateway_Coinpayments', $methods)) {
                $methods[] = 'WC_Gateway_Coinpayments';
            }
            return $methods;
        }

        function custom_rewrite_rule()
        {
            add_rewrite_rule('payment/coinpayments/([^/]*)/([^/]*)/?', 'index.php?coinpayments_page=$matches[1]&coin_param=$matches[2]', 'top');
        }

        function add_query_vars($vars)
        {
            $vars[] = 'coinpayments_page';
            $vars[] = 'coin_param';
            return $vars;
        }

        function coin_page()
        {
            $page = get_query_var('coinpayments_page');


            switch ($page) {
                case 'status':

                    $order_id = get_query_var('coin_param');
                    $transactions = WC()->session->get('transactions');

                    if (isset($transactions[$order_id])) {
                        $transaction = $transactions[$order_id];
                        $custom_currency = WC()->session->get('coinpayments_currency');
                        $timeData = self::get_status_time_data($transaction);
                        $transaction['time_left'] = $timeData['time_left'];
                        $transaction['time_diff'] = $timeData['time_diff'];
                        include plugin_dir_path(__FILE__) . 'templates/payment-status-page.php';
                        die();
                    }
                    break;
            }

        }

    }

    new WC_Gateway_Coinpayments_Plugin();
}
