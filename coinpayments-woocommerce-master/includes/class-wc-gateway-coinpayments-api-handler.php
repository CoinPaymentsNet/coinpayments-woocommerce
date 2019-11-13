<?php
/**
 * Class WC_Gateway_Coinpayments_API_Handler file.
 *
 * @package WooCommerce\Gateways
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles Refunds and other API requests such as capture.
 *
 * @since 3.0.0
 */
class WC_Gateway_Coinpayments_API_Handler
{

    /**
     *
     */
    const API_URL = 'https://www.coinpayments.net/api.php';


    /**
     * Capture an authorization.
     *
     * @param array $settings .
     * @param bool $accepted_info
     * @return array.
     */
    public static function get_rates($settings, $accepted_info = false)
    {

        $data = array(
            'version' => '1',
            'cmd' => 'rates',
            'accepted' => $accepted_info,
            'key' => $settings['public_key']
        );

        $headers = array(
            'HMAC' => hash_hmac('sha512', http_build_query($data), $settings['private_key']),
            'Content-Type' => 'application/x-www-form-urlencoded',
        );

        $response = Requests::post(esc_url_raw(self::API_URL), $headers, $data);
        $http_response = new WP_HTTP_Requests_Response($response);
        $response = $http_response->to_array();
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (empty($body)) {
            return new WP_Error('paypal-api', 'Empty Response');
        } elseif (is_wp_error($response)) {
            return $response;
        }

        return $body['result'];
    }


    /**
     * Load rates list from coinpayments.net
     * @param $publicKey
     * @param $privateKey
     * @return array|Settings[]
     */
    public static function create_transaction($transaction_args)
    {

        $data = [
            'version' => 1,
            'key' => $transaction_args['public_key'],
            'cmd' => 'create_transaction',
            'amount' => $transaction_args['amount'],
            'currency1' => $transaction_args['custom_currency'],
            'currency2' => $transaction_args['rate'],
            'buyer_email' => $transaction_args['email'],
            'buyer_name' => $transaction_args['name'],
            'invoice' => $transaction_args['invoice'],
            'custom' => $transaction_args['custom'],

            'ipn_url' => $transaction_args['urls']['ipn_url']
        ];

        $headers = [
            'HMAC:' . hash_hmac('sha512', http_build_query($data), $transaction_args['private_key']),
            'Content-Type:application/x-www-form-urlencoded'
        ];

        $curl = curl_init();

        curl_setopt($curl, CURLOPT_URL, self::API_URL);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));

        $result = curl_exec($curl);
        $responseData = json_decode($result, 1);

        return $responseData;
    }
}
