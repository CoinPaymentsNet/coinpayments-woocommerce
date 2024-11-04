<?php

use Automattic\WooCommerce\Utilities\FeaturesUtil;

if (!defined('ABSPATH')) exit; // Exit if accessed directly

/**
 * Plugin Name: WooCommerce CoinPayments.net Gateway
 * Plugin URI: https://www.coinpayments.net/
 * Description:  Provides a CoinPayments.net Payment Gateway.
 * Author: CoinPayments.net
 * Author URI: https://www.coinpayments.net/
 * Version: 2.0.1
 */

/**
 * CoinPayments.net Gateway
 * Based on the PayPal Standard Payment Gateway
 *
 * Provides a CoinPayments.net Payment Gateway.
 *
 * @class        WC_Coinpayments
 * @extends      WC_Gateway_Coinpayments
 * @version      2.0.1
 * @package      WooCommerce\Gateways
 * @author       CoinPayments.net
 */
if (function_exists('coinpayments_gateway_load')) {
    $reflFunc = new ReflectionFunction('coinpayments_gateway_load');
    $pluginFile = $reflFunc->getFileName();
    $pluginFileParts = array_slice(explode('/', $pluginFile), -2);
    $pluginPath = implode('/', $pluginFileParts);
    deactivate_plugins(array($pluginPath), true);
} else {
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
                add_action('init', array(__CLASS__, 'custom_rewrite_rule'), 10, 0);
                add_action('rest_api_init', array(__CLASS__, 'rest_api_init'));
                add_action('before_woocommerce_init', array(__CLASS__, 'woocommerce_blocks_loaded'));
                add_action('woocommerce_blocks_loaded', array(__CLASS__, 'coinpayments_gateway_block_support'));
            }

            public function filters()
            {
                add_filter('woocommerce_payment_gateways', array(__CLASS__, 'add_gateway'), 0);
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

            public static function rest_api_init()
            {
                register_rest_route('wc/v3', '/payments/coinpayments-gateway', array(
                    'methods' => 'POST',
                    'callback' => array(__CLASS__, 'handle_coinpayments_gateway_payment'),
                    'permission_callback' => '__return_true',
                ));
            }

            public static function handle_coinpayments_gateway_payment(WP_REST_Request $request)
            {
                $parameters = $request->get_json_params();
                // Process the payment using $parameters
                return new WP_REST_Response(array('result' => 'success', 'redirect' => wc_get_checkout_url()), 200);
            }

            public static function woocommerce_blocks_loaded()
            {
                if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
                    FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__);
                    FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__);
                }
            }

            public static function coinpayments_gateway_block_support()
            {
                if( ! class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
                    return;
                }

                // here we're including our "gateway block support class"
                require_once __DIR__ . '/includes/class-wc-gateway-coinpayments-blocks-support.php';

                // registering the PHP class we have just included
                add_action(
                    'woocommerce_blocks_payment_method_type_registration',
                    function( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {
                        $payment_method_registry->register( new WC_CoinPayments_Gateway_Blocks_Support() );
                    }
                );
            }
        }

        if (class_exists('WC_Payment_Gateway')) {
            new WC_Gateway_Coinpayments_Plugin();
        }
    }
}
