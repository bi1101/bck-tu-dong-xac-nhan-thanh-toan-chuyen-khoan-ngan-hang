<?php
/** Run with: php tests/order-confirmation-detection.php */
namespace Automattic\WooCommerce\Blocks\Payments\Integrations {
	abstract class AbstractPaymentMethodType {}
}
namespace {
	define( 'ABSPATH', __DIR__ );
	function wp_is_block_theme() { return $GLOBALS['block_theme']; }
	function is_wc_endpoint_url( $endpoint ) { return $GLOBALS['order_received'] && 'order-received' === $endpoint; }
	require dirname( __DIR__ ) . '/inc/class-mpay-blocks.php';
	foreach ( array( false, true ) as $block_theme ) {
		foreach ( array( false, true ) as $order_received ) {
			if ( Mpay_Blocks::is_block_order_confirmation() !== ( $block_theme && $order_received ) ) {
				throw new \RuntimeException( 'Incorrect order-confirmation renderer selection.' );
			}
		}
	}
	echo "Order confirmation detection: 4 cases passed.\n";
}
