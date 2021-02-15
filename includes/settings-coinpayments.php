<?php
/**
 * Settings for CoinPayments.NET Gateway.
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
        'default' => __('')
    ),
    'client_id' => array(
        'title' => __('Client ID', 'coinpayments-payment-gateway-for-woocommerce'),
        'type' => 'text',
        'description' => __('Please enter your CoinPayments.net Client ID.', 'coinpayments-payment-gateway-for-woocommerce'),
        'default' => '',
    ),
    'webhooks' => array(
        'title' => __('Webhooks', 'coinpayments-payment-gateway-for-woocommerce'),
        'type' => 'select',
        'description' => __('This controls the plugins mode after checkout.', 'coinpayments-payment-gateway-for-woocommerce'),
        'options' => array(
            '' => '-- ' . __('Enable to use webhooks', 'coinpayments-payment-gateway-for-woocommerce') . ' --',
            '1' => __('Enabled', 'coinpayments-payment-gateway-for-woocommerce'),
            '0' => __('Disabled', 'coinpayments-payment-gateway-for-woocommerce'),
        ),
        'default' => __('Pay with Bitcoin, Litecoin, or other altcoins via CoinPayments.net', 'coinpayments-payment-gateway-for-woocommerce')
    ),
    'client_secret' => array(
        'title' => __('Client Secret', 'coinpayments-payment-gateway-for-woocommerce'),
        'type' => 'text',
        'description' => __('Please enter your CoinPayments.net Client Secret.', 'coinpayments-payment-gateway-for-woocommerce'),
        'default' => '',
    ),
    'debug_email' => array(
        'title' => __('Debug email', 'coinpayments-payment-gateway-for-woocommerce'),
        'type' => 'text',
        'description' => __('You will be notified about errors in the payment gateway at this email address.', 'coinpayments-payment-gateway-for-woocommerce'),
        'default' => '',
    ),
);
