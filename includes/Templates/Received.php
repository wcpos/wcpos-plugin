<?php
/**
 *
 *
 * @package    WCPOS\WooCommercePOS\Templates\Received
 * @author   Paul Kilmurray <paul@kilbot.com>
 * @link     http://wcpos.com
 */

namespace WCPOS\WooCommercePOS\Templates;

use Exception;
use WCPOS\WooCommercePOS\Server;

class Received {
	/**
	 * @var int
	 */
	private $order_id;

	public function __construct( int $order_id ) {
		$this->order_id = $order_id;
	}

	/**
	 *
	 */
	public function get_template() {
		try {
			// get order
			$order = wc_get_order( $this->order_id );

			// Order or receipt url is invalid.
			if ( ! $order ) {
				wp_die( esc_html__( 'Sorry, this order is invalid.', 'woocommerce-pos' ) );
			}

			//
			if ( ! $order->is_paid() ) {
				wp_die( esc_html__( 'Sorry, this order has not been paid.', 'woocommerce-pos' ) );
			}

			$server     = new Server();
			$order_json = $server->wp_rest_request( '/wc/v3/orders/' . $this->order_id );

			// @TODO - display message for errors

			include woocommerce_pos_locate_template( 'received.php' );
			exit;

		} catch ( Exception $e ) {
			wc_print_notice( $e->getMessage(), 'error' );
		}
	}
}
