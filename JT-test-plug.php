<?php
/*
 * Plugin Name: James Test Payment Gateway
 * Plugin URI: https://www.coinpayments.net/merchant/>>>>???????.html
 * Description: Payment Gateway for CoinPayments Cryptocurrency Platform
 * Author: James Taylor
 * Author URI: http://mySite.com
 * Version: 1.0.0
 *
 * NOTE TEST VERSION!!!			REQUIRE RENAME NAMING CONVENTION -> 'jtest' to 'Coinpayments' etc
 *
 */


/*
 * This action hook registers our PHP class as a WooCommerce payment gateway
 */
add_filter( 'woocommerce_payment_gateways', 'jtest_add_gateway_class' );
function jtest_add_gateway_class( $gateways ) {
	$gateways[] = 'WC_jtest_Gateway'; // your class name is here
	return $gateways;
}


 
/*
 * The class itself, please note that it is inside plugins_loaded action hook
 */
add_action( 'plugins_loaded', 'jtest_init_gateway_class' );
function jtest_init_gateway_class() {
 
	class WC_jtest_Gateway extends WC_Payment_Gateway {
 
 		/**
 		 * Class constructor, more about it in Step 3
 		 */
 		public function __construct() {
 
	$this->id = 'jtest'; // payment gateway plugin ID
	$this->icon = ''; // URL of the icon that will be displayed on checkout page near your gateway name
	$this->has_fields = true; // in case you need a custom credit card form
	$this->method_title = 'jtest Gateway';
	$this->method_description = 'Description of jtest payment gateway'; // will be displayed on the options page
 
	// gateways can support subscriptions, refunds, saved payment methods,
	// but in this tutorial we begin with simple payments
	$this->supports = array(
		'products'
	);
 
	// Method with all the options fields
	$this->init_form_fields();
 
	// Load the settings.
	$this->init_settings();
	$this->title = $this->get_option( 'title' );
	$this->description = $this->get_option( 'description' );
	$this->enabled = $this->get_option( 'enabled' );
	$this->testmode = 'yes' === $this->get_option( 'testmode' );
	$this->private_key = $this->testmode ? $this->get_option( 'test_private_key' ) : $this->get_option( 'private_key' );
	$this->publishable_key = $this->testmode ? $this->get_option( 'test_publishable_key' ) : $this->get_option( 'publishable_key' );
 
	// This action hook saves the settings
	add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
 
	// We need custom JavaScript to obtain a token
	add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );
 
	// You can also register a webhook here
	// add_action( 'woocommerce_api_{webhook name}', array( $this, 'webhook' ) );
 
 		}
 
		/**
 		 * Plugin options / settings
 		 */
 		public function init_form_fields(){
 
	$this->form_fields = array(
		'enabled' => array(
			'title'       => 'Enable/Disable',
			'label'       => 'Enable jtest Gateway',
			'type'        => 'checkbox',
			'description' => '',
			'default'     => 'no'
		),
		'title' => array(
			'title'       => 'Title',
			'type'        => 'text',
			'description' => 'This controls the title which the user sees during checkout.',
			'default'     => 'Test CoinPayments',
			'desc_tip'    => true,
		),
		'description' => array(
			'title'       => 'Description',
			'type'        => 'textarea',
			'description' => 'This controls the description which the user sees during checkout.',
			'default'     => 'Pay with Crypto Currency',
		),
		'testmode' => array(
			'title'       => 'Test mode',
			'label'       => 'Enable Test Mode',
			'type'        => 'checkbox',
			'description' => 'Test Mode For Gateway',
			'default'     => 'yes',
			'desc_tip'    => true,
		),
		
		'publishable_key' => array(
			'title'       => 'Live Public Key',
			'type'        => 'text'
		),
		'private_key' => array(
			'title'       => 'Live Private Key',
			'type'        => 'password'
		),

		'IPN_Secret' => array(
			'title'       => 'Merchant Secret',
			'type'        => 'password'
		)
	);
}
 
	 	
 
		/**
		 * APPEARS ON CHECKOUT AS CUSTOM FORM
		 */
		public function payment_fields() {
 
 
		}
 
		/*
		 * Call Script on complete
		 */
	 	public function payment_scripts() {
 

	if ( ! is_cart() && ! is_checkout() && ! isset( $_GET['pay_for_order'] ) ) {
		return;
	}

	if ( 'no' === $this->enabled ) {
		return;
	}
 

	if ( empty( $this->private_key ) || empty( $this->publishable_key ) ) {
		return;
	}
 

	if ( ! $this->testmode && ! is_ssl() ) {
		return;
	}
 

	wp_enqueue_script( 'jtest_js', 'https://orion-api-testnet.starhermit.com/static/js/checkout.js' );
 

	wp_register_script( 'woocommerce_jtest', plugins_url( 'jtest.js', __FILE__ ), array( 'jquery', 'jtest_js' ) );
 

	wp_localize_script( 'woocommerce_jtest', 'jtest_params', array(
		'publishableKey' => $this->publishable_key
	) );
 
	wp_enqueue_script( 'woocommerce_jtest' );
 
	 	}
 
		/*
 		 * Fields validation, GENERALLY USED TO VALIDATE CREDIT CARD
		 */
		public function validate_fields() {
 

 
		}
 
		/*
		 * process payment here
		 */
		public function process_payment( $order_id ) {
 
global $woocommerce;
 

	$order = wc_get_order( $order_id );
 
 
	/*
 	 * Array with parameters for API interaction
	 */
	$args = array();
 
	/*
	 * Your API interaction could be built with wp_remote_post()
 	 */
	 $response = wp_remote_post( '{payment processor endpoint}', $args );
 
 
	 if( !is_wp_error( $response ) ) {
 
		 $body = json_decode( $response['body'], true );
 
		 // it could be different depending on your payment processor
		 if ( $body['response']['responseCode'] == 'APPROVED' ) {
 
			// we received the payment
			$order->payment_complete();
			$order->reduce_order_stock();
 
			// some notes to customer (replace true with false to make it private)
			$order->add_order_note( 'Hey, your order is paid! Thank you!', true );
 
			// Empty cart
			$woocommerce->cart->empty_cart();
 
			// Redirect to the thank you page
			return array(
				'result' => 'success',
				'redirect' => $this->get_return_url( $order )
			);
 
		 } else {
			wc_add_notice(  'Please try again.', 'error' );
			return;
		}
 
	} else {
		wc_add_notice(  'Connection error.', 'error' );
		return;
	}
 
	 	}
 
		/*
		 * IPN SETUP
		 */
		public function webhook() {
 
$order = wc_get_order( $_GET['id'] );
	$order->payment_complete();
	$order->reduce_order_stock();
 
	update_option('webhook_debug', $_GET);
 
	 	}
 	}
}