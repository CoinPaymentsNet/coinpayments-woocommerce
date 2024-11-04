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

    const API_URL = 'https://api.coinpayments.com';
    const CHECKOUT_URL = 'https://checkout.coinpayments.com';
    const API_VERSION = '1';

    const API_WEBHOOK_ACTION = 'merchant/clients/%s/webhooks';
    const API_MERCHANT_INVOICE_ACTION = 'merchant/invoices';
    const API_CURRENCIES_ACTION = 'currencies';
    const API_CHECKOUT_ACTION = 'checkout';
    const FIAT_TYPE = 'fiat';

    const PAID_EVENT = 'Paid';
    const CANCELLED_EVENT = 'Cancelled';

    /**
     * @var string
     */
    protected $client_id;

    /**
     * @var string
     */
    protected $client_secret;

    /**
     * WC_Gateway_Coinpayments_API_Handler constructor.
     * @param $client_id
     * @param $client_secret
     */
    public function __construct($client_id, $client_secret)
    {
        $this->client_id = $client_id;
        $this->client_secret = $client_secret;
    }

    /**
     * @return bool
     * @throws Exception
     */
    public function isWebhooksExists()
    {
        $webhooks_list = $this->get_webhooks_list();
        if (empty($webhooks_list)) {
            return false;
        }

        $webhooks_urls_list = array();
        if (!empty($webhooks_list['items'])) {
            $webhooks_urls_list = array_map(function ($webHook) {
                return $webHook['notificationsUrl'];
            }, $webhooks_list['items']);
        }

        if (in_array($this->get_notification_url(self::PAID_EVENT), $webhooks_urls_list) &&
            in_array($this->get_notification_url(self::CANCELLED_EVENT), $webhooks_urls_list)) {
            return true;
        }

        return false;
    }

    /**
     * @param $event
     * @return bool|mixed
     * @throws Exception
     */
    public function create_webhook($event)
    {

        $action = sprintf(self::API_WEBHOOK_ACTION, $this->client_id);

        $params = array(
            "notificationsUrl" => $this->get_notification_url($event),
            "notifications" => array(
                sprintf("invoice%s", $event),
            ),
        );

        return $this->send_request('POST', $action, $params);
    }

    /**
     * @return bool|mixed
     * @throws Exception
     */
    public function get_webhooks_list()
    {

        return $this->send_request('GET', sprintf(self::API_WEBHOOK_ACTION, $this->client_id));
    }

    /**
     * @param $name
     * @return mixed
     * @throws Exception
     */
    public function get_coin_currency($name)
    {

        $params = array(
            'types' => self::FIAT_TYPE,
            'q' => $name,
        );

        $items = $this->get_coin_currencies($params);

        return array_shift($items);
    }

    /**
     * @param array $params
     * @return bool|mixed
     * @throws Exception
     */
    public function get_coin_currencies($params = array())
    {
        return $this->send_request('GET', self::API_CURRENCIES_ACTION, $params);
    }

    /**
     * @param $receivedSignature
     * @param $method
     * @param $content
     * @param $event
     * @param $date
     * @return bool
     */
    public function check_data_signature($receivedSignature, $method, $content, $event, $date)
    {
        $requestUrl = $this->get_notification_url($event);
        $expectedSignature = $this->create_signature($method, $requestUrl, $date, $content);

        return $receivedSignature == $expectedSignature;
    }

    /**
     * @param $invoiceParams
     * @return bool|mixed
     * @throws Exception
     */
    public function create_invoice($invoiceParams, $billingInfo)
    {
        $action = self::API_MERCHANT_INVOICE_ACTION;
        $params = $invoiceParams;
        $params['clientId'] = $this->client_id;
        $params = $this->append_billing_data($params, $billingInfo);
        $params = $this->append_invoice_metadata($params);

        return $this->send_request('POST', $action, $params);
    }

    public function get_invoices()
    {
        return $this->send_request('GET', self::API_MERCHANT_INVOICE_ACTION);
    }

    /**
     * @param $signature_string
     * @param $client_secret
     * @return string
     */
    public function encode_signature_string($signature_string, $client_secret)
    {
        return base64_encode(hash_hmac('sha256', $signature_string, $client_secret, true));
    }

    /**
     * @param $action
     * @return string
     */
    public function get_api_url($action)
    {
        return sprintf('%s/api/v%s/%s', self::API_URL, self::API_VERSION, $action);
    }

    /**
     * @param $order_id
     * @return string
     */
    public function get_invoice_id($order_id)
    {
        return sprintf('%s|%s', md5(get_site_url()), $order_id);
    }

    protected function get_notification_url(string $event): string
    {
        $url = add_query_arg('wc-api', 'WC_Gateway_Coinpayments', home_url('/'));
        $url = add_query_arg('clientId', $this->client_id, $url);

        return  add_query_arg('event', $event, $url);
    }

    /**
     * @param $method
     * @param $api_action
     * @param null $params
     * @return bool|mixed
     * @throws Exception
     */
    protected function send_request($method, $api_action, $params = null)
    {
        $response = false;

        $api_url = $this->get_api_url($api_action);
        $date = new \Datetime();
        $timestamp = $date->format('Y-m-d\TH:i:s');
        try {

            $curl = curl_init();

            $options = array(
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_SSL_VERIFYPEER => false,
            );

            $headers = array(
                'Content-Type: application/json',
            );


            $content = !empty($params) ? json_encode($params) : '';
            $signature = $this->create_signature($method, $api_url, $timestamp, $content);
            $headers[] = 'X-CoinPayments-Client: ' . $this->client_id;
            $headers[] = 'X-CoinPayments-Timestamp: ' . $timestamp;
            $headers[] = 'X-CoinPayments-Signature: ' . $signature;
            $options[CURLOPT_HTTPHEADER] = $headers;
            $options[CURLOPT_HEADER] = true;
            if ($method == 'POST') {
                $options[CURLOPT_POST] = true;
                $options[CURLOPT_POSTFIELDS] = json_encode($params);
            } elseif ($method == 'GET' && !empty($params)) {
                $api_url .= '?' . http_build_query($params);
            }

            $options[CURLOPT_URL] = $api_url;

            curl_setopt_array($curl, $options);

            $result = curl_exec($curl);

            $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $headerSize = curl_getinfo($curl, CURLINFO_HEADER_SIZE);

            $body = substr($result, $headerSize);

            if (substr($http_code, 0, 1) == 2) {
                $response = json_decode($body, true);
            } elseif (curl_error($curl)) {
                throw new Exception($body, $http_code);
            } elseif ($http_code == 400) {
                throw new Exception($body, 400);
            } elseif (substr($http_code, 0, 1) == 4) {
                throw new Exception(__('CoinPayments.NET authentication failed!', 'coinpayments-payment-gateway-for-woocommerce'), $http_code);
            }
            curl_close($curl);

        } catch (Exception $e) {
            throw new Exception($e->getMessage(), $e->getCode(), $e);
        }
        return $response;
    }

    /**
     * @param $request_data
     * @return mixed
     */
    protected function append_invoice_metadata($request_data)
    {
        $request_data['metadata'] = array(
            "integration" => sprintf("Woocommerce v.%s", WC()->version),
            "hostname" => get_site_url(),
        );

        return $request_data;
    }

    /**
     * @param $request_params
     * @param $billing_data
     * @return array
     */
    function append_billing_data($request_params, $billing_data)
    {

        $request_params['buyer'] = array(
            'companyName' => $billing_data['company'],
            'name' => array(
                'firstName' => $billing_data['first_name'],
                'lastName' => $billing_data['last_name']
            ),
            'phoneNumber' => $billing_data['phone'],
        );

        if (preg_match('/^.*@.*$/', $billing_data['email'])) {
            $request_params['buyer']['emailAddress'] = $billing_data['email'];
        }

        if (!empty($billing_data['address_1']) &&
            !empty($billing_data['city']) &&
            preg_match('/^([A-Z]{2})$/', $billing_data['country'])
        ) {
            $request_params['buyer']['address'] = array(
                'address1' => $billing_data['address_1'],
                'address2' => $billing_data['address_2'],
                'provinceOrState' => $billing_data['state'],
                'city' => $billing_data['city'],
                'countryCode' => $billing_data['country'],
                'postalCode' => $billing_data['postcode'],
            );

        }

        return $request_params;
    }

    /**
     * @param $method
     * @param $api_url
     * @param $date
     * @param $params
     * @return string
     */
    protected function create_signature($method, $api_url, $date, $params): string
    {
        $signature_data = [chr(239), chr(187), chr(191), $method, $api_url, $this->client_id, $date];
        if (!empty($params)) {
            $signature_data[] = $params;
        }

        return $this->encode_signature_string(implode('', $signature_data), $this->client_secret);
    }

}
