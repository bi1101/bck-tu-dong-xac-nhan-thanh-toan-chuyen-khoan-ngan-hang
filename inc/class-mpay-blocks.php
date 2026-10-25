<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** WooCommerce Blocks integration for every gateway supplied by BCK. */
final class Mpay_Blocks {
	public static function register() {
		if ( ! class_exists( 'Automattic\\WooCommerce\\Blocks\\Payments\\Integrations\\AbstractPaymentMethodType' ) ) {
			return;
		}

		add_action( 'woocommerce_blocks_payment_method_type_registration', array( __CLASS__, 'register_payment_methods' ) );
		self::register_order_confirmation_block();
		add_filter( 'hooked_block_types', array( __CLASS__, 'hook_order_confirmation_block' ), 10, 4 );
	}

	public static function register_payment_methods( $registry ) {
		foreach ( WC()->payment_gateways()->payment_gateways() as $gateway ) {
			if ( $gateway instanceof WC_Base_Mpay ) {
				$registry->register( new Mpay_Blocks_Payment_Method( $gateway ) );
			}
		}
	}

	public static function register_order_confirmation_block() {
		register_block_type(
			MPAY_DIR . 'blocks/order-confirmation',
			array( 'render_callback' => array( __CLASS__, 'render_order_confirmation' ) )
		);
	}

	public static function hook_order_confirmation_block( $blocks, $position, $anchor, $context ) {
		if ( 'after' === $position && 'woocommerce/order-confirmation-summary' === $anchor ) {
			$blocks[] = 'bck/order-confirmation';
		}
		return array_values( array_unique( $blocks ) );
	}

	public static function render_order_confirmation() {
		$order_id = absint( get_query_var( 'order-received' ) );
		$order    = $order_id ? wc_get_order( $order_id ) : false;
		$key      = isset( $_GET['key'] ) ? wc_clean( wp_unslash( $_GET['key'] ) ) : '';

		if ( ! $order || ! $key || ! hash_equals( (string) $order->get_order_key(), (string) $key ) ) {
			return '';
		}

		$gateway = WC()->payment_gateways()->payment_gateways()[ $order->get_payment_method() ] ?? null;
		if ( ! $gateway instanceof WC_Base_Mpay || ! method_exists( $gateway, 'render_block_payment_details' ) ) {
			return '';
		}

		$output = $gateway->render_block_payment_details( $order_id );
		// The legacy renderer contains an inline polling script. Block markup is hydrated by React,
		// so keep the server-rendered instructions and attach a block-safe poller instead.
		$output = preg_replace( '#<script\b[^>]*>.*?</script>#is', '', $output );
		$handle = 'bck-block-order-status';
		$settings = MpayPayment::get_settings();
		$expected = isset( $settings['order_status']['order_status_after_paid'] ) ? $settings['order_status']['order_status_after_paid'] : 'wc-completed';
		if ( ! wp_script_is( $handle, 'registered' ) ) {
			wp_register_script( $handle, '', array(), '2.0.1', true );
			wp_add_inline_script( $handle, '(function(){var root=document.querySelector(".bck-block-order-confirmation");if(!root)return;var expected=' . wp_json_encode( $expected ) . ';setInterval(function(){fetch(' . wp_json_encode( admin_url( 'admin-ajax.php?action=fetch_order_status_hpc&order_id=' . absint( $order_id ) ) ) . ').then(function(r){return r.text();}).then(function(status){if(status===expected)window.location.reload();});},3000);}());' );
		}
		wp_enqueue_script( $handle );

		return '<div class="bck-block-order-confirmation">' . $output . '</div>';
	}

	public static function is_block_order_confirmation() {
		return function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'order-received' ) && function_exists( 'has_block' ) && has_block( 'woocommerce/checkout', wc_get_page_id( 'checkout' ) );
	}
}

final class Mpay_Blocks_Payment_Method extends Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType {
	private $gateway;

	public function __construct( $gateway ) {
		$this->gateway = $gateway;
		$this->name    = $gateway->id;
	}

	public function initialize() {
		$this->settings = get_option( 'woocommerce_' . $this->name . '_settings', array() );
	}

	public function is_active() {
		return 'yes' === $this->gateway->enabled && $this->gateway->is_available();
	}

	public function get_payment_method_script_handles() {
		$handle = 'bck-block-payment-methods';
		wp_register_script( $handle, MPAY_URL . 'assets/js/blocks-payment.js', array( 'wc-blocks-registry', 'wc-settings', 'wc-sanitize', 'wp-element', 'wp-html-entities' ), '2.0.1', true );
		wp_add_inline_script( $handle, 'window.BCK_BLOCK_GATEWAYS = window.BCK_BLOCK_GATEWAYS || []; window.BCK_BLOCK_GATEWAYS.push(' . wp_json_encode( $this->name ) . ');', 'before' );
		return array( $handle );
	}

	public function get_payment_method_data() {
		return array(
			'title'       => $this->gateway->title,
			'description' => $this->gateway->description,
			'icon'        => $this->gateway->icon,
			'supports'    => $this->gateway->supports,
		);
	}
}
