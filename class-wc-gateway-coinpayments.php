<?php
if (!defined('ABSPATH')) exit; // Exit if accessed directly

/**
 * Plugin Name: WooCommerce CoinPayments.net Gateway
 * Plugin URI: https://www.coinpayments.net/
 * Description:  Provides a CoinPayments.net Payment Gateway.
 * Author: CoinPayments.net
 * Author URI: https://www.coinpayments.net/
 * Version: 2.0.0
 */

/**
 * CoinPayments.net Gateway
 * Based on the PayPal Standard Payment Gateway
 *
 * Provides a CoinPayments.net Payment Gateway.
 *
 * @class        WC_Coinpayments
 * @extends      WC_Gateway_Coinpayments
 * @version      2.0.0
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
        }

        public function actions()
        {
            add_action('init', [__CLASS__, 'custom_rewrite_rule'], 10, 0);
        }

        public function filters()
        {
            add_filter('woocommerce_payment_gateways', [__CLASS__, 'add_gateway'], 0);
        }

        public static function add_gateway($methods)
        {
            if (!in_array('WC_Gateway_Coinpayments', $methods)) {
                $methods[] = 'WC_Gateway_Coinpayments';
            }
            return $methods;
        }

        public static function custom_rewrite_rule()
        {
            add_rewrite_rule('payment/coinpayments/([^/]*)/([^/]*)/?', 'index.php?coinpayments_page=$matches[1]&coin_param=$matches[2]', 'top');
        }

    }

    new WC_Gateway_Coinpayments_Plugin();
}
