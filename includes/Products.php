<?php

/**
 * POS Product Class.
 *
 * @author Paul Kilmurray <paul@kilbot.com>
 *
 * @see    https://wcpos.com
 */

namespace WCPOS\WooCommercePOS;

class Products {
	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'woocommerce_product_set_stock', array( $this, 'product_set_stock' ) );
		add_action( 'woocommerce_variation_set_stock', array( $this, 'product_set_stock' ) );
		add_action( 'pre_get_posts', array( $this, 'hide_pos_products' ) );
//		add_filter( 'woocommerce_get_product_subcategories_args', array( $this, 'filter_category_count_exclude_pos_only' ) );

		/**
		 * Allow decimal quantities for products and variations.
		 *
		 * @TODO - this will affect the online store aswell, should I make it POS only?
		 */
		$allow_decimal_quantities = woocommerce_pos_get_settings( 'general', 'decimal_qty' );
		if ( \is_bool( $allow_decimal_quantities ) && $allow_decimal_quantities ) {
			remove_filter( 'woocommerce_stock_amount', 'intval' );
			add_filter( 'woocommerce_stock_amount', 'floatval' );
		}
	}

	/**
	 * Bump modified date on stock change
	 * - variation->id = parent id.
	 *
	 * @param $product
	 */
	public function product_set_stock( $product ): void {
		$post_modified     = current_time( 'mysql' );
		$post_modified_gmt = current_time( 'mysql', 1 );
		wp_update_post(array(
			'ID'                => $product->get_id(),
			'post_modified'     => $post_modified,
			'post_modified_gmt' => $post_modified_gmt,
		));
	}

	/**
	 * Hide POS Only products from the shop and category pages.
	 *
	 * @TODO - this should be improved so that admin users can see the product, but get a message
	 *
	 * @param mixed $query
	 */
	public function hide_pos_products( $query ): void {
		if ( ! is_admin() && $query->is_main_query() && ( 'product' === $query->get( 'post_type' ) || $query->is_tax( 'product_cat' ) ) ) {
			$pos_only_products = woocommerce_pos_get_settings( 'general', 'pos_only_products' );

			if ( $pos_only_products ) {
				$meta_query = $query->get( 'meta_query' );
				if ( ! \is_array( $meta_query ) ) {
					$meta_query = array();
				}
				$meta_query['relation'] = 'OR';
				$meta_query[]           = array(
					'key'     => '_pos_visibility',
					'value'   => 'pos_only',
					'compare' => '!=',
				);
				$meta_query[] = array(
					'key'     => '_pos_visibility',
					'compare' => 'NOT EXISTS',
				);
				$query->set( 'meta_query', $meta_query );
			}
		}
	}

	/**
	 * Filter category count to exclude pos_only products.
	 *
	 * @param array $args The arguments for getting product subcategories.
	 *
	 * @return array The modified arguments.
	 */
	public function filter_category_count_exclude_pos_only( $args ) {
		if ( ! is_admin() && \function_exists( 'woocommerce_pos_get_settings' ) ) {
			$pos_only_products = woocommerce_pos_get_settings( 'general', 'pos_only_products' );

			if ( $pos_only_products ) {
				$meta_query = $args['meta_query'] ?? array();

				$meta_query['relation'] = 'OR';
				$meta_query[]           = array(
					'key'     => '_pos_visibility',
					'value'   => 'pos_only',
					'compare' => '!=',
				);
				$meta_query[] = array(
					'key'     => '_pos_visibility',
					'compare' => 'NOT EXISTS',
				);

				$args['meta_query'] = $meta_query;
			}
		}

		return $args;
	}
}
