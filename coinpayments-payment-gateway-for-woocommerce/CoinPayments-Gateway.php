<?php
/*
 * Plugin Name: WooCommerce CoinPayments.net Gateway
 * Plugin URI: https://www.coinpayments.net/
 * Description: CoinPayments.net Payment Gateway.
 * Author: CoinPayments.net
 * Author URI: https://www.coinpayments.net/
 * Version: 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;
add_action( 'plugins_loaded', 'coinpayments_gateway_load', 0 );
function coinpayments_gateway_load() {

   if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
      return;
   }

   /*
    * Add the gateway to WooCommerce.
    */
   add_filter( 'woocommerce_payment_gateways', 'wccoinpayments_add_gateway' );

   /*
    * Add Settings link to plugins menu for WC below 2.1
    */
   if (version_compare(WOOCOMMERCE_VERSION, "2.1") <= 0) {
      add_filter('plugin_action_links', 'coinpayments_plugin_action_links', 10, 2);
      function coinpayments_plugin_action_links($links, $file) {
         static $this_plugin;
         if (!$this_plugin) {
            $this_plugin = plugin_basename(__FILE__);
         }
         if ($file == $this_plugin) {
            $settings_link = '<a href="' . get_bloginfo('wpurl') . '/wp-admin/admin.php?page=woocommerce_settings&tab=payment_gateways&section=coinpayments">Settings</a>';
            array_unshift($links, $settings_link);
         }
         return $links;
      }


   /*
    * Add Settings link to plugins menu for WC 2.1 and above
    */ 
   } else {
      add_filter('plugin_action_links', 'coinpayments_plugin_action_links', 10, 2);
      function coinpayments_plugin_action_links($links, $file) {
         static $this_plugin;
         if (!$this_plugin) {
            $this_plugin = plugin_basename(__FILE__);
         }
         if ($file == $this_plugin) {
            $settings_link = '<a href="' . get_bloginfo('wpurl') . '/wp-admin/admin.php?page=wc-settings&tab=checkout&section=coinpayments">Settings</a>';
            array_unshift($links, $settings_link);
         }
         return $links;
      }
   }

   function wccoinpayments_add_gateway( $methods ) {
      if (!in_array('WC_Gateway_Coinpayments', $methods)) {
         $methods[] = 'WC_Gateway_Coinpayments';
      }
      return $methods;
   }

   class WC_Gateway_Coinpayments extends WC_Payment_Gateway {

   /*
    * Constructor for the gateway.
    */
   public function __construct() {
      global $woocommerce;
      $this->id           = 'coinpayments';
      $this->icon         = apply_filters( 'woocommerce_coinpayments_icon', plugins_url().'/coinpayments-payment-gateway-for-woocommerce/icon.png' );
      $this->has_fields   = false;
      $this->method_title = __( 'CoinPayments.net', 'woocommerce' );
      $this->ipn_url      = add_query_arg( 'wc-api', 'WC_Gateway_Coinpayments', home_url( '/' ) );

      // Load the settings.
      $this->init_form_fields();
      $this->init_settings();

      // Define user set variables
      $this->title 			= $this->get_option( 'title' );
      $this->description 		= $this->get_option( 'description' );
      $this->merchant_id 		= $this->get_option( 'merchant_id' );
      $this->ipn_secret   		= $this->get_option( 'ipn_secret' );
      $this->currency   		= $this->get_option( 'currency' );
      $this->form_submission_method 	= $this->get_option( 'form_submission_method' ) == 'yes' ? true : false;
      $this->simple_total 		= $this->get_option( 'simple_total' ) == 'yes' ? true : false;

      /* Legacy Fields from original plugin
       *
       *$this->debug_email	   = $this->get_option( 'debug_email' );
       *$this->send_shipping	   = $this->get_option( 'send_shipping' );
       *$this->allow_zero_confirm  = $this->get_option( 'allow_zero_confirm' ) == 'yes' ? true : false;
       *$this->invoice_prefix	   = $this->get_option( 'invoice_prefix', 'WC-' );
       */

      // Logs
      $this->log = new WC_Logger();

      // Actions
      add_action( 'woocommerce_receipt_coinpayments', array( $this, 'receipt_page' ) );
      add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

      // Payment listener/API hook
      add_action( 'woocommerce_api_wc_gateway_coinpayments', array( $this, 'check_ipn_response' ) );

   }

   /*
    * Admin Panel Options
    */
   public function admin_options() {
      echo '<h3>' . __('CoinPayments Gateway', 'WC_Gateway_Coinpayments') . '</h3>';
      echo '<p>' . __('CoinPayments Gateway Provides Cryptocurrency Solutions for Your Site.', 'WC_Gateway_Coinpayments') . '</p>';
      echo '<table class="form-table">';
      $this->generate_settings_html();
      echo '</table>';
   }

   /*
    * Initialise Gateway Settings Form Fields
    */
   function init_form_fields() {
      $this->form_fields = array(
         'enabled' => array(
            'title'   => __( 'Enable/Disable', 'woocommerce' ),
            'type'    => 'checkbox',
            'label'   => __( 'Enable CoinPayments.net', 'woocommerce' ),
            'default' => 'yes'
         ),
         'title' => array(
            'title'       => __( 'Title', 'woocommerce' ),
            'type'        => 'text',
            'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce' ),
            'default'     => __( 'CoinPayments.net', 'woocommerce' ),
            'desc_tip'    => true
         ),
         'description' => array(
            'title'       => __( 'Description', 'woocommerce' ),
            'type'        => 'textarea',
            'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce' ),
            'default'     => __( 'Pay with Bitcoin, Litecoin, or other altcoins via CoinPayments.net', 'woocommerce' )
         ),
         'merchant_id' => array(
            'title'       => __( 'Merchant ID', 'woocommerce' ),
            'type'        => 'text',
            'description' => __( 'Please enter your CoinPayments.net Merchant ID.', 'woocommerce' ),
            'default'     => ''
         ),
         'ipn_secret' => array(
            'title'       => __( 'IPN Secret', 'woocommerce' ),
            'type'        => 'text',
            'description' => __( 'Please enter your CoinPayments.net IPN Secret.', 'woocommerce' ),
            'default'     => ''
         ),
         'currency' => array(
            'title'       => __( 'Currency ID', 'woocommerce' ),
            'type'        => 'text',
            'description' => __( 'Please enter the Currency ID', 'woocommerce' ),
            'default'     => '5057'
         ),
         'simple_total' => array(
            'title'   => __( 'Compatibility Mode', 'woocommerce' ),
            'type'    => 'checkbox',
            'label'   => __( "This may be needed for compatibility with certain addons if the order total isn't correct.", 'woocommerce' ),
            'default' => ''
         ));
   }
		/*
		*	LEGACY FROM ORIGINAL
		*
		*	'send_shipping' => array(
		*					'title' => __( 'Collect Shipping Info?', 'woocommerce' ),
		*					'type' => 'checkbox',
		*					'label' => __( 'Enable Shipping Information on Checkout page', 'woocommerce' ),
		*					'default' => 'yes'
		*				),
		*	'allow_zero_confirm' => array(
		*					'title' => __( 'Enable 1st-confirm payments?', 'woocommerce' ),
		*					'type' => 'checkbox',
		*					'label' => __( '* WARNING * If this is selected orders will be marked as paid as soon as your buyer\'s payment is detected, but before it is fully confirmed. This can be dangerous if the payment never confirms and is only recommended for digital downloads.', 'woocommerce' ),
		*					'default' => ''
		*				),
		*	'invoice_prefix' => array(
		*					'title' => __( 'Invoice Prefix', 'woocommerce' ),
		*					'type' => 'text',
		*					'description' => __( 'Please enter a prefix for your invoice numbers. If you use your CoinPayments.net account for multiple stores ensure this prefix is unique.', 'woocommerce' ),
		*					'default' => 'WC-',
		*					'desc_tip'      => true,
		*				),
		*
		*
		*
		*	'testing' => array(
		*					'title' => __( 'Gateway Testing', 'woocommerce' ),
		*					'type' => 'title',
		*					'description' => '',
		*				),
		*	'debug_email' => array(
		*					'title' => __( 'Debug Email', 'woocommerce' ),
		*					'type' => 'email',
		*					'default' => '',
		*					'description' => __( 'Send copies of invalid IPNs to this email address.', 'woocommerce' ),
		*				)
		*/

   /*
    *	Generate Payment Modal
    */
   function generate_coinpayments_form($order_id) {
      $order       = wc_get_order($order_id);
      $merchant_id = $this->merchant_id;
      $currency    = $this->currency;
      $amount      = $order->get_total()*100; //Total Parse requires *100 for accurate order price

      return '<script src="https://orion-api-testnet.starhermit.com/static/js/checkout.js"></script>
      <div id="cps-button-container-1"></div>
      <script type="text/javascript">
      var amount = "'.$amount.'";
      var merchant_id = "'.$merchant_id.'";
      var currency = "'.$currency.'";
      CoinPayments.Button({
         createInvoice: function (data, actions) {
            return actions.invoice.create({
               clientId: merchant_id,
               amount:
               {
                  currencyId: currency,
                  value: amount
               }
            });
         }
      }).render("cps-button-container-1");</script>';

   }


   /*
    * Process the payment and return the result
    */
   function process_payment( $order_id ) {
      $order          = wc_get_order( $order_id );
      return array(
         'result' 	=> 'success',
         'redirect'     => $order->get_checkout_payment_url(true)		
      );
   }

   /*
    * Output for the order received page.
    */
   function receipt_page( $order ) {
      echo '<p>'.__( 'Thank you for your order, please click the button below to pay with CoinPayments.net.', 'woocommerce' ).'</p>';
      echo $this->generate_coinpayments_form( $order );
   }

   /*
    * Check CoinPayments.net IPN validity
    */
   function check_ipn_request_is_valid() {
      global $woocommerce;
      $order = false;
      $error_msg = "Unknown error";
      $auth_ok = false;
      if (isset($_POST['ipn_mode']) && $_POST['ipn_mode'] == 'hmac') {
         if (isset($_SERVER['HTTP_HMAC']) && !empty($_SERVER['HTTP_HMAC'])) {
            $request = file_get_contents('php://input');
            if ($request !== FALSE && !empty($request)) {
               if (isset($_POST['merchant']) && $_POST['merchant'] == trim($this->merchant_id)) {
                  $hmac = hash_hmac("sha512", $request, trim($this->ipn_secret));
                  if ($hmac == $_SERVER['HTTP_HMAC']) {
                     $auth_ok = true;
                  } else {
                     $error_msg = 'HMAC signature does not match';
                  }
               } else {
                  $error_msg = 'No or incorrect Merchant ID passed';
               }
            } else {
               $error_msg = 'Error reading POST data';
            }
         } else {
            $error_msg = 'No HMAC signature sent.';
         }
      } else {
         $error_msg = "Unknown IPN verification method.";
      }
      if ($auth_ok) {
         if (!empty($_POST['invoice']) && !empty($_POST['custom'])) {
	    $order = $this->get_coinpayments_order( $_POST );
	 }
         if ($order !== FALSE) {
            if ($_POST['ipn_type'] == "button" || $_POST['ipn_type'] == "simple") {
               if ($_POST['merchant'] == $this->merchant_id) {
                  if ($_POST['currency1'] == $order->get_currency()) {
                     if ($_POST['amount1'] >= $order->get_total()) {
                        print "IPN check OK\n";
			return true;
                     } else {
                        $error_msg = "Amount received is less than the total!";
                     }
                  } else {
                     $error_msg = "Original currency doesn't match!";
                  }
               } else {
                  $error_msg = "Merchant ID doesn't match!";
               }
            } else {
               $error_msg = "ipn_type != button or simple";
            }
         } else {
            $error_msg = "Could not find order info for order: ".$_POST['invoice'];
         }
      }
      $report = "Error Message: ".$error_msg."\n\n";
      $report .= "POST Fields\n\n";
      foreach ($_POST as $key => $value) {
         $report .= $key.'='.$value."\n";
      }
      if ($order) {
         $order->update_status('on-hold', sprintf( __( 'CoinPayments.net IPN Error: %s', 'woocommerce' ), $error_msg ) );
      }
      if (!empty($this->debug_email)) { mail($this->debug_email, "CoinPayments.net Invalid IPN", $report); }
      mail(get_option( 'admin_email' ), sprintf( __( 'CoinPayments.net Invalid IPN', 'woocommerce' ), $error_msg ), $report );
      die('IPN Error: '.$error_msg);
      return false;
      }

   /*
    * Successful Payment!
    */
   function successful_request( $posted ) {
      global $woocommerce;
      $posted = stripslashes_deep( $posted );

      // Custom holds post ID
      if (!empty($_POST['invoice']) && !empty($_POST['custom'])) {
         $order = $this->get_coinpayments_order( $posted );
         if ($order === FALSE) {
            die("IPN Error: Could not find order info for order: ".$_POST['invoice']);
         }
         $this->log->add( 'coinpayments', 'Order #'.$order->get_id().' payment status: ' . $posted['status_text'] );
         $order->add_order_note('CoinPayments.net Payment Status: '.$posted['status_text']);
         if ( $order->get_status() != 'completed' && get_post_meta( $order->get_id(), 'CoinPayments payment complete', true ) != 'Yes' ) {
            // no need to update status if it's already done
            if ( ! empty( $posted['txn_id'] ) )
               update_post_meta( $order->get_id(), 'Transaction ID', $posted['txn_id'] );
               if ( ! empty( $posted['first_name'] ) )
                  update_post_meta( $order->get_id(), 'Payer first name', $posted['first_name'] );
                     if ( ! empty( $posted['last_name'] ) )
             	        update_post_meta( $order->get_id(), 'Payer last name', $posted['last_name'] );
                        if ( ! empty( $posted['email'] ) )
             	           update_post_meta( $order->get_id(), 'Payer email', $posted['email'] );
                              if ($posted['status'] >= 100 || $posted['status'] == 2 || ($this->allow_zero_confirm && $posted['status'] >= 0 && $posted['received_confirms'] > 0 && $posted['received_amount'] >= $posted['amount2'])) {
                                 print "Marking complete\n";
				 update_post_meta( $order->get_id(), 'CoinPayments payment complete', 'Yes' );
             	                 $order->payment_complete();
                              } else if ($posted['status'] < 0) {
                                  print "Marking cancelled\n";
                                  $order->update_status('cancelled', 'CoinPayments.net Payment cancelled/timed out: '.$posted['status_text']);
				  mail( get_option( 'admin_email' ), sprintf( __( 'Payment for order %s cancelled/timed out', 'woocommerce' ), $order->get_order_number() ), $posted['status_text'] );
                              } else {
                                  print "Marking pending\n";
                                  $order->update_status('pending', 'CoinPayments.net Payment pending: '.$posted['status_text']);					}
                              }
	                   die("IPN OK");
	                   }
	                }

   /*
    * Check for CoinPayments IPN Response
    *
    */
   function check_ipn_response() {
      @ob_clean();
      if ( ! empty( $_POST ) && $this->check_ipn_request_is_valid() ) {
         $this->successful_request($_POST);
      } else {
      wp_die( "CoinPayments.net IPN Request Failure" );
      }
   }

   /*
    * get_coinpayments_order function.
    */
   function get_coinpayments_order( $posted ) {
      $custom = maybe_unserialize( stripslashes_deep($posted['custom']) );
      // Backwards comp for IPN requests
      if ( is_numeric( $custom ) ) {
         $order_id = (int) $custom;
         $order_key = $posted['invoice'];
      } elseif( is_string( $custom ) ) {
         $order_id = (int) str_replace( $this->invoice_prefix, '', $custom );
         $order_key = $custom;
      } else {
         list( $order_id, $order_key ) = $custom;
      }
      $order = wc_get_order( $order_id );
      if ($order === FALSE) {
      // We have an invalid $order_id, probably because invoice_prefix has changed
         $order_id 	= wc_get_order_id_by_order_key( $order_key );
         $order 		= wc_get_order( $order_id );
      }
      // Validate key
      if ($order === FALSE || $order->get_order_key() !== $order_key ) {
         return FALSE;
      }
      return $order;
   }
   }
}