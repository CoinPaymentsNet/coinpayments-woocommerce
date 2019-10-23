<?php
/**
 * Settings for PayPal Gateway.
 *
 * @package WooCommerce/Classes/Payment
 */

defined('ABSPATH') || exit;

return array(
    'enabled' => array(
        'title' => __('Enable/Disable', 'coinpayments-payment-gateway-for-woocommerce'),
        'type' => 'checkbox',
        'label' => __('Enable CoinPayments.net', 'coinpayments-payment-gateway-for-woocommerce'),
        'default' => 'yes'
    ),
    'title' => array(
        'title' => __('Title', 'coinpayments-payment-gateway-for-woocommerce'),
        'type' => 'text',
        'description' => __('This controls the title which the user sees during checkout.', 'coinpayments-payment-gateway-for-woocommerce'),
        'default' => __('CoinPayments.net', 'coinpayments-payment-gateway-for-woocommerce'),
        'desc_tip' => true,
    ),
    'description' => array(
        'title' => __('Description', 'coinpayments-payment-gateway-for-woocommerce'),
        'type' => 'textarea',
        'description' => __('This controls the description which the user sees during checkout.', 'coinpayments-payment-gateway-for-woocommerce'),
        'default' => __('Pay with Bitcoin, Litecoin, or other altcoins via CoinPayments.net', 'coinpayments-payment-gateway-for-woocommerce')
    ),
    'mode' => array(
        'title' => __('Mode', 'coinpayments-payment-gateway-for-woocommerce'),
        'type' => 'select',
        'description' => __('This controls the plugins mode after checkout.', 'coinpayments-payment-gateway-for-woocommerce'),
        'options' => array(
            '' => '-- ' . __('Select plugin mode', 'coinpayments-payment-gateway-for-woocommerce') . ' --',
            'redirect' => __('Redirect mode', 'coinpayments-payment-gateway-for-woocommerce'),
            'direct' => __('Direct mode', 'coinpayments-payment-gateway-for-woocommerce'),
        ),
        'default' => __('Pay with Bitcoin, Litecoin, or other altcoins via CoinPayments.net', 'coinpayments-payment-gateway-for-woocommerce')
    ),
    'currency' => array(
        'title' => __('Receive Currency', 'coinpayments-payment-gateway-for-woocommerce'),
        'type' => 'select',
        'description' => __('This controls the plugins mode after checkout.', 'coinpayments-payment-gateway-for-woocommerce'),
        'options' => array(
            '' => '-- ' . __('Select receive currency', 'coinpayments-payment-gateway-for-woocommerce') . ' --',
        ),
        'default' => __('Pay with Bitcoin, Litecoin, or other altcoins via CoinPayments.net', 'coinpayments-payment-gateway-for-woocommerce')
    ),
    'merchant_id' => array(
        'title' => __('Merchant ID', 'coinpayments-payment-gateway-for-woocommerce'),
        'type' => 'text',
        'description' => __('Please enter your CoinPayments.net Merchant ID.', 'coinpayments-payment-gateway-for-woocommerce'),
        'default' => '',
    ),
    'public_key' => array(
        'title' => __('Public key', 'coinpayments-payment-gateway-for-woocommerce'),
        'type' => 'text',
        'description' => __('Please enter your CoinPayments.net Public key.', 'coinpayments-payment-gateway-for-woocommerce'),
        'default' => '',
    ),
    'private_key' => array(
        'title' => __('Private key', 'coinpayments-payment-gateway-for-woocommerce'),
        'type' => 'text',
        'description' => __('Please enter your CoinPayments.net Private key.', 'coinpayments-payment-gateway-for-woocommerce'),
        'default' => '',
    ),
    'ipn_secret' => array(
        'title' => __('IPN Secret', 'coinpayments-payment-gateway-for-woocommerce'),
        'type' => 'text',
        'description' => __('Please enter your CoinPayments.net IPN Secret.', 'coinpayments-payment-gateway-for-woocommerce'),
        'default' => '',
    ),
    'validated' => array(
        'type' => 'hidden',
        'default' => $this->validated,
    ),
);
