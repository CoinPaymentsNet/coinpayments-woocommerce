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
         'invoice_prefix' => array(
            'title' => __( 'Invoice Prefix', 'woocommerce' ),
            'type' => 'text',
            'description' => __( 'Please enter a prefix for your invoice numbers. If you use your CoinPayments.net account for multiple stores ensure this prefix is unique.', 'woocommerce' ),
            'default' => 'WC-',
            'desc_tip'      => true,
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
    *	Generate Payment Modal
    */
   function generate_coinpayments_form($order_id) {
      $order       = wc_get_order($order_id);
      $merchant_id = $this->merchant_id;
      $currency    = $this->currency;
      $amount      = $order->get_total()*100; //Total Parse requires *100 for accurate order price
      $invoiceId   = $this->invoice_prefix . $order->get_order_number();
      $description = serialize( array( $order->get_id(), $order->get_order_key() ) );

	//NOTE RECTANGULAR CONTAINER ON BUTTON DIV NEEDED FOR CSS CONFLICT

      return '<script src="https://alpha-api.coinpayments.net/static/js/checkout.js"></script>
      <div style="width:500px;height:100px;border:3px solid #000;"><div id="cps-button-container-1"></div></div>
      <script type="text/javascript">
      var amount = "'.$amount.'";
      var merchant_id = "'.$merchant_id.'";
      var currency = "'.$currency.'";
      var invoice_Id = "'.$invoiceId.'";
      var Description = "'.$description.'";
      CoinPayments.Button({
         createInvoice: function (data, actions) {
            return actions.invoice.create({
               clientId: merchant_id,
               invoiceId: invoice_Id,
               description: Description, 
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
   * Validate IPN
   */
   function check_ipn_request_is_valid($post, $request) {
      global $woocommerce;
      $order = false;
      $error_msg = "Unknown error";

      $auth_ok = true; // !!!!!!!!WARNING TESTING MODE MUST BE: false; FOR PRODUCTION!!!!!!!

      if (isset($_SERVER['HTTP_X_COINPAYMENTS_SIGNATURE']) && !empty($_SERVER['HTTP_X_COINPAYMENTS_SIGNATURE'])) {
         if ($request !== FALSE && !empty($request)) {
            $hmac = hash_hmac("sha512", $request, trim($this->ipn_secret));
            $signature = base64_encode($hmac);
            if ($signature == $_SERVER['HTTP_X_COINPAYMENTS_SIGNATURE']) {
               $auth_ok = true;
            } else {
               $error_msg = 'HMAC signature does not match';
               }
         } else {
            $error_msg = 'Error reading POST data';
            }
      } else {
         $error_msg = 'No Signature Sent.';
         }
      if ($auth_ok) {
         if (!empty($post->invoice->invoiceId) && !empty($post->invoice->description)) {
            $order = $this->get_coinpayments_order( $post );
            }
         if ($order !== FALSE) {

         //IPN Vetted Successfully !!!!!!!!WARNING!!!!!!!! : Missing Currency & Value verification of v1.0
            return true;

         } else {
            $error_msg = "Could not find order info for order: ".$post->invoice->invoiceId;
            }
      }
      $report = "Error Message: ".$error_msg."\n\n";
      $report .= "POST Fields\n\n";
      $report .= $request;
      if ($order) {
         $order->update_status('on-hold', sprintf( __( 'CoinPayments.net IPN Error: %s', 'woocommerce' ), $error_msg ) );
      }
      if (!empty($this->debug_email)) { mail($this->debug_email, "CoinPayments.net Invalid IPN", $report); }
      mail(get_option( 'admin_email' ), sprintf( __( 'CoinPayments.net Invalid IPN', 'woocommerce' ), $error_msg ), $report );
      die('IPN Error: '.$error_msg);
      return false;
   }

  /*
   * Update Completed Payment
   */
   function successful_request( $post ) {
      global $woocommerce;
      $post = stripslashes_deep( $post );

      // Custom holds post ID
      if (!empty($post->invoice->invoiceId) && !empty($post->invoice->description)) {
         $order = $this->get_coinpayments_order( $post );
         if ($order === FALSE) {die("IPN Error: Could not find order info for order: ".$post->invoice->invoiceId);}
         $this->log->add( 'coinpayments', 'Order #'.$order->get_id().' payment status: ' . $post->invoice->status );
         $order->add_order_note('CoinPayments.net Payment Status: '.$post->invoice->status);
         if ( $order->get_status() != 'completed' && get_post_meta( $order->get_id(), 'CoinPayments payment complete', true ) != 'Yes' ) {
            if ( ! empty( $post->invoice->id ) )
               update_post_meta( $order->get_id(), 'Transaction ID', $post->invoice->id );
            if ( ! empty( $post->invoice->shipping->fullName ) )
               update_post_meta( $order->get_id(), 'Payer Full Name', $post->invoice->shipping->fullName );
            if ($post->invoice->status == 'Complete') {
               print "Marking complete\n";
               update_post_meta( $order->get_id(), 'CoinPayments payment complete', 'Yes' );
               $order->payment_complete();
            } else if ($post->invoice->status == 'Cancelled') {
               print "Marking cancelled\n";
               $order->update_status('cancelled', 'CoinPayments.net Payment cancelled/timed out: '.$post->invoice->status);
               mail( get_option( 'admin_email' ), sprintf( __( 'Payment for order %s cancelled/timed out', 'woocommerce' ), $order->get_order_number() ), $posted['status_text'] );
            } else {
               print "Marking pending\n";
               $order->update_status('pending', 'CoinPayments.net Payment pending: '.$post->invoice->status);
            }
            die("IPN OK");
         }
      }

  /*
   * Receive IPN
   */
   function check_ipn_response() {
      @ob_clean();
      $request = file_get_contents('php://input');
      $post = json_decode($request);
      if ( ! empty( $_POST ) && $this->check_ipn_request_is_valid($post, $request) ) {
         $this->successful_request($post);
      } else {
         wp_die( "CoinPayments.net IPN Request Failure" );
      }
   }

  /*
   * Retrieve Order for IPN
   */
   function get_coinpayments_order( $post ) {
      $custom = maybe_unserialize( stripslashes_deep($post->invoice->description) );

      // Backwards comp for IPN requests
      if ( is_numeric( $custom ) ) {
         $order_id = (int) $custom;
         $order_key = $post->invoice->invoiceId;
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
         $order 	= wc_get_order( $order_id );
      }

      // Validate key
      if ($order === FALSE || $order->get_order_key() !== $order_key ) {
         return FALSE;
      }
      return $order;
   }
}
}