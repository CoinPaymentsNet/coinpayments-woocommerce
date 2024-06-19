<?php

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

class WC_CoinPayments_Gateway_Blocks_Support extends AbstractPaymentMethodType
{
    public function initialize()
    {
        // get payment gateway settings
        $this->settings = get_option( sprintf("woocommerce_%s_settings", WC_Gateway_Coinpayments::GATEWAY_ID), array() );
        $this->name = WC_Gateway_Coinpayments::GATEWAY_ID;
    }

    /**
     * @return bool
     */
    public function is_active() {
        return ! empty( $this->settings[ 'enabled' ] ) && 'yes' === $this->settings[ 'enabled' ];
    }

    public function get_payment_method_script_handles() {

        wp_register_script(
            'wc-coinpayments-blocks-integration',
            plugin_dir_url( __DIR__ ) . 'assets/js/coinpayments-gateway-block.js',
            array(
                'wc-blocks-registry',
                'wc-settings',
                'wp-element',
                'wp-html-entities',
            ),
            null, // or time() or filemtime( ... ) to skip caching
            true
        );

        return array( 'wc-coinpayments-blocks-integration' );
    }

    public function get_payment_method_data() {
        return array(
            'name'         => $this->name,
            'title'        => $this->get_setting( 'title' ),
            'description'  => $this->get_setting( 'description' ),
            'icon'         => plugin_dir_url( __DIR__ ) . 'assets/images/icons/coinpayments.png',
        );
    }
}
