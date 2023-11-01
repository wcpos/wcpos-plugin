<?php

namespace WCPOS\WooCommercePOS\Tests\API;

use Automattic\WooCommerce\RestApi\UnitTests\Helpers\ProductHelper;
use Ramsey\Uuid\Uuid;
use WCPOS\WooCommercePOS\API\Products_Controller;
use WP_Test_Spy_REST_Server;

/**
 * @internal
 *
 * @coversNothing
 */
class Test_Products_Controller extends WCPOS_REST_Unit_Test_Case {
	public function setup(): void {
		parent::setUp();
		$this->endpoint = new Products_Controller();
	}

	public function tearDown(): void {
		parent::tearDown();
	}

	public function test_namespace_property(): void {
		$namespace = $this->get_reflected_property_value('namespace');

		$this->assertEquals('wcpos/v1', $namespace );
	}

	public function test_rest_base(): void {
		$rest_base = $this->get_reflected_property_value('rest_base');

		$this->assertEquals('products', $rest_base);
	}

	/**
	 * Test route registration.
	 */
	public function test_register_routes(): void {
		$routes = $this->server->get_routes();
		$this->assertArrayHasKey( '/wcpos/v1/products', $routes );
		$this->assertArrayHasKey( '/wcpos/v1/products/(?P<id>[\d]+)', $routes );
		$this->assertArrayHasKey( '/wcpos/v1/products/batch', $routes );
	}

	/**
	 * Get all expected fields.
	 */
	public function get_expected_response_fields() {
		return array(
			'id',
			'name',
			'slug',
			'permalink',
			'date_created',
			'date_created_gmt',
			'date_modified',
			'date_modified_gmt',
			'type',
			'status',
			'featured',
			'catalog_visibility',
			'description',
			'short_description',
			'sku',
			'price',
			'regular_price',
			'sale_price',
			'date_on_sale_from',
			'date_on_sale_from_gmt',
			'date_on_sale_to',
			'date_on_sale_to_gmt',
			'price_html',
			'on_sale',
			'purchasable',
			'total_sales',
			'virtual',
			'downloadable',
			'downloads',
			'download_limit',
			'download_expiry',
			'external_url',
			'button_text',
			'tax_status',
			'tax_class',
			'manage_stock',
			'stock_quantity',
			'stock_status',
			'backorders',
			'backorders_allowed',
			'backordered',
			'low_stock_amount',
			'sold_individually',
			'weight',
			'dimensions',
			'shipping_required',
			'shipping_taxable',
			'shipping_class',
			'shipping_class_id',
			'reviews_allowed',
			'average_rating',
			'rating_count',
			'related_ids',
			'upsell_ids',
			'cross_sell_ids',
			'parent_id',
			'purchase_note',
			'categories',
			'tags',
			'images',
			'has_options',
			'attributes',
			'default_attributes',
			'variations',
			'grouped_products',
			'menu_order',
			'meta_data',
			'post_password',
			// Added by WCPOS.
			'barcode',
		);
	}

	public function test_product_api_get_all_fields(): void {
		$expected_response_fields = $this->get_expected_response_fields();

		$product  = ProductHelper::create_simple_product();
		$request  = $this->wp_rest_get_request( '/wcpos/v1/products/' . $product->get_id() );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
		$response_fields = array_keys( $response->get_data() );
		$this->assertEmpty( array_diff( $expected_response_fields, $response_fields ), 'These fields were expected but not present in WCPOS API response: ' . print_r( array_diff( $expected_response_fields, $response_fields ), true ) );
		$this->assertEmpty( array_diff( $response_fields, $expected_response_fields ), 'These fields were not expected in the WCPOS API response: ' . print_r( array_diff( $response_fields, $expected_response_fields ), true ) );
	}

	public function test_product_api_schema(): void {
		$schema = $this->endpoint->get_item_schema();

		$this->assertArrayHasKey( 'barcode', $schema['properties'] );
	}

	public function test_product_api_get_all_ids(): void {
		$product1  = ProductHelper::create_simple_product();
		$product2  = ProductHelper::create_simple_product();
		$request   = $this->wp_rest_get_request( '/wcpos/v1/products' );
		$request->set_param( 'posts_per_page', -1 );
		$request->set_param( 'fields', array('id') );

		$response = $this->server->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$ids  = wp_list_pluck( $data, 'id' );

		$this->assertEqualsCanonicalizing( array( $product1->get_id(), $product2->get_id() ), $ids );
	}

	/**
	 * Each product needs a UUID.
	 */
	public function test_product_response_contains_uuid_meta_data(): void {
		$product  = ProductHelper::create_simple_product();
		$request  = $this->wp_rest_get_request( '/wcpos/v1/products/' . $product->get_id() );
		$response = $this->server->dispatch($request);

		$data = $response->get_data();

		$this->assertEquals(200, $response->get_status());

		$found      = false;
		$uuid_value = '';
		$count      = 0;

		// Look for the _woocommerce_pos_uuid key in meta_data
		foreach ($data['meta_data'] as $meta) {
			if ('_woocommerce_pos_uuid' === $meta['key']) {
				$count++;
				$uuid_value = $meta['value'];
			}
		}

		$this->assertEquals(1, $count, 'There should only be one _woocommerce_pos_uuid.');
		$this->assertTrue(Uuid::isValid($uuid_value), 'The UUID value is not valid.');
	}

	/**
	 * Barcode.
	 */
	public function test_product_get_barcode(): void {
		add_filter( 'woocommerce_pos_general_settings', function() {
			return array(
				'barcode_field' => 'foo',
			);
		});

		$product  = ProductHelper::create_simple_product();
		$product->update_meta_data( 'foo', 'bar' );
		$this->assertEquals( 'bar', $this->endpoint->wcpos_get_barcode( $product ) );
	}


	public function test_product_response_contains_barcode(): void {
		add_filter( 'woocommerce_pos_general_settings', function() {
			return array(
				'barcode_field' => '_some_field',
			);
		});

		$product  = ProductHelper::create_simple_product();
		$product->update_meta_data( '_some_field', 'some_string' );
		$product->save_meta_data();
		$request  = $this->wp_rest_get_request( '/wcpos/v1/products/' . $product->get_id() );
		$response = $this->server->dispatch($request);
	
		$data = $response->get_data();
	
		$this->assertEquals(200, $response->get_status());
			
		$this->assertEquals( 'some_string', $data['barcode'] );
	}

	public function test_product_update_barcode(): void {
		add_filter( 'woocommerce_pos_general_settings', function() {
			return array(
				'barcode_field' => 'barcode',
			);
		});

		$product  = ProductHelper::create_simple_product( array( 'sku' => 'sku-12345' ) );
		$request  = $this->wp_rest_patch_request( '/wcpos/v1/products/' . $product->get_id() );
		$request->set_body_params( array(
			'barcode' => 'foo-12345',
		) );
		$response = $this->server->dispatch($request);
	
		$data = $response->get_data();
	
		$this->assertEquals(200, $response->get_status());
			
		$this->assertEquals( 'foo-12345', $data['barcode'] );
	}

	/**
	 * Orderby.
	 */
	public function test_product_orderby_sku(): void {
		$product1  = ProductHelper::create_simple_product( array( 'sku' => '987654321' ) );
		$product2  = ProductHelper::create_simple_product( array( 'sku' => 'zeta' ) );
		$product3  = ProductHelper::create_simple_product( array( 'sku' => '123456789' ) );
		$product4  = ProductHelper::create_simple_product( array( 'sku' => 'alpha' ) );
		$request   = $this->wp_rest_get_request( '/wcpos/v1/products' );
		$request->set_query_params( array( 'orderby' => 'sku', 'order' => 'asc' ) );
		$response     = rest_get_server()->dispatch( $request );
		$data         = $response->get_data();
		$skus         = wp_list_pluck( $data, 'sku' );

		$this->assertEquals( $skus, array( '123456789', '987654321', 'alpha', 'zeta' ) );

		// reverse order
		$request->set_query_params( array( 'orderby' => 'sku', 'order' => 'desc' ) );
		$response     = rest_get_server()->dispatch( $request );
		$data         = $response->get_data();
		$skus         = wp_list_pluck( $data, 'sku' );

		$this->assertEquals( $skus, array( 'zeta', 'alpha', '987654321', '123456789' ) );
	}

	public function test_product_orderby_barcode(): void {
		add_filter( 'woocommerce_pos_general_settings', function() {
			return array(
				'barcode_field' => '_barcode',
			);
		});

		$product1  = ProductHelper::create_simple_product();
		$product1->update_meta_data( '_barcode', 'alpha' );
		$product1->save_meta_data();

		$product2  = ProductHelper::create_simple_product();
		$product2->update_meta_data( '_barcode', 'zeta' );
		$product2->save_meta_data();

		$request  = $this->wp_rest_get_request( '/wcpos/v1/products' );
		$request->set_query_params( array( 'orderby' => 'barcode', 'order' => 'asc' ) );
		$response         = rest_get_server()->dispatch( $request );
		$data             = $response->get_data();
		$barcodes         = wp_list_pluck( $data, 'barcode' );

		$this->assertEquals( $barcodes, array( 'alpha', 'zeta' ) );

		// reverse order
		$request->set_query_params( array( 'orderby' => 'barcode', 'order' => 'desc' ) );
		$response         = rest_get_server()->dispatch( $request );
		$data             = $response->get_data();
		$barcodes         = wp_list_pluck( $data, 'barcode' );

		$this->assertEquals( $barcodes, array( 'zeta', 'alpha' ) );
	}

	public function test_product_orderby_stock_status(): void {
		$product1  = ProductHelper::create_simple_product( array( 'stock_status' => 'instock' ) );
		$product2  = ProductHelper::create_simple_product( array( 'stock_status' => 'outofstock' ) );
		$request   = $this->wp_rest_get_request( '/wcpos/v1/products' );
		$request->set_query_params( array( 'orderby' => 'stock_status', 'order' => 'asc' ) );
		$response     = rest_get_server()->dispatch( $request );
		$data         = $response->get_data();
		$skus         = wp_list_pluck( $data, 'stock_status' );

		$this->assertEquals( $skus, array( 'instock', 'outofstock' ) );

		// reverse order
		$request->set_query_params( array( 'orderby' => 'stock_status', 'order' => 'desc' ) );
		$response     = rest_get_server()->dispatch( $request );
		$data         = $response->get_data();
		$skus         = wp_list_pluck( $data, 'stock_status' );

		$this->assertEquals( $skus, array( 'outofstock', 'instock' ) );
	}

	public function test_product_orderby_stock_quantity(): void {
		$product1  = ProductHelper::create_simple_product( array( 'stock_quantity' => 1, 'manage_stock' => true ) );
		$product2  = ProductHelper::create_simple_product( array( 'stock_quantity' => 2, 'manage_stock' => true ) );
		$product3  = ProductHelper::create_simple_product( array( 'stock_quantity' => null, 'manage_stock' => true ) );
		$product4  = ProductHelper::create_simple_product( array( 'stock_quantity' => 0, 'manage_stock' => true ) );
		$product5  = ProductHelper::create_simple_product( array( 'stock_quantity' => -1, 'manage_stock' => true ) );
		$product6  = ProductHelper::create_simple_product();
		$request   = $this->wp_rest_get_request( '/wcpos/v1/products' );
		$request->set_query_params( array( 'orderby' => 'stock_quantity', 'order' => 'asc' ) );
		$response     = rest_get_server()->dispatch( $request );
		$data         = $response->get_data();
		$skus         = wp_list_pluck( $data, 'stock_quantity' );

		$this->assertEquals( $skus, array( -1, 0, 1, 2, null, null ) );

		// reverse order
		$request->set_query_params( array( 'orderby' => 'stock_quantity', 'order' => 'desc' ) );
		$response     = rest_get_server()->dispatch( $request );
		$data         = $response->get_data();
		$skus         = wp_list_pluck( $data, 'stock_quantity' );

		$this->assertEquals( $skus, array( 2, 1, 0 , -1, null, null ) );
	}

	/**
	 * Decimal quantities.
	 */
	public function test_product_decimal_stock_quantity_schema(): void {
		$schema = $this->endpoint->get_item_schema();
		$this->assertEquals( 'integer', $schema['properties']['stock_quantity']['type'] );

		add_filter( 'woocommerce_pos_general_settings', function() {
			return array(
				'decimal_qty' => true,
			);
		});
		$schema = $this->endpoint->get_item_schema();
		$this->assertEquals( 'string', $schema['properties']['stock_quantity']['type'] );
	}

	public function test_product_response_with_decimal_quantities(): void {
		add_filter( 'woocommerce_pos_general_settings', function() {
			return array(
				'decimal_qty' => true,
			);
		});
		remove_filter('woocommerce_stock_amount', 'intval');
		add_filter( 'woocommerce_stock_amount', 'floatval' );

		$product  = ProductHelper::create_simple_product();
		$product->set_manage_stock( true );
		$product->set_stock_quantity( 1.5 );
		$product->save();

		$request  = $this->wp_rest_get_request( '/wcpos/v1/products/' . $product->get_id() );
		$response = $this->server->dispatch($request);
	
		$data = $response->get_data();
	
		$this->assertEquals(200, $response->get_status());
			
		$this->assertEquals( 1.5, $data['stock_quantity'] );
	}

	// @TODO - this works in the POS, but not in the tests, I have no idea why
	public function test_product_update_decimal_quantities(): void {
		add_filter( 'woocommerce_pos_general_settings', function() {
			return array(
				'decimal_qty' => true,
			);
		});
		remove_filter('woocommerce_stock_amount', 'intval');
		add_filter( 'woocommerce_stock_amount', 'floatval' );

		$product  = ProductHelper::create_simple_product();
		$product->set_manage_stock( true );
		$product->save();

		$request  = $this->wp_rest_patch_request( '/wcpos/v1/products/' . $product->get_id() );
		$request->set_body_params( array(
			'stock_quantity' => '3.85',
		) );
		// $server   = rest_get_server(); // re-init the server, so that params are re-read??
		$wp_rest_server = new WP_Test_Spy_REST_Server();
		$response       = $wp_rest_server->dispatch($request);
	
		$data = $response->get_data();
	
		$this->assertEquals(200, $response->get_status());
			
		$this->assertEquals( 3.85, $data['stock_quantity'] );
	}

	public function test_product_orderby_decimal_stock_quantity(): void {
		add_filter( 'woocommerce_pos_general_settings', function() {
			return array(
				'decimal_qty' => true,
			);
		});
		remove_filter('woocommerce_stock_amount', 'intval');
		add_filter( 'woocommerce_stock_amount', 'floatval' );

		$product1  = ProductHelper::create_simple_product( array( 'stock_quantity' => '11.2', 'manage_stock' => true ) );
		$product2  = ProductHelper::create_simple_product( array( 'stock_quantity' => '3.5', 'manage_stock' => true ) );
		$request   = $this->wp_rest_get_request( '/wcpos/v1/products' );
		$request->set_query_params( array( 'orderby' => 'stock_quantity', 'order' => 'asc' ) );
		$response     = rest_get_server()->dispatch( $request );
		$data         = $response->get_data();
		$skus         = wp_list_pluck( $data, 'stock_quantity' );

		$this->assertEquals( $skus, array( 3.5, 11.2 ) );

		// reverse order
		$request->set_query_params( array( 'orderby' => 'stock_quantity', 'order' => 'desc' ) );
		$response     = rest_get_server()->dispatch( $request );
		$data         = $response->get_data();
		$skus         = wp_list_pluck( $data, 'stock_quantity' );

		$this->assertEquals( $skus, array( 11.2, 3.5 ) );
	}

	public function test_product_search(): void {
		add_filter( 'woocommerce_pos_general_settings', function() {
			return array(
				'barcode_field' => '_barcode',
			);
		});

		$random_title       = wp_generate_password(12, false);
		$random_sku         = wp_generate_password(8, false);
		$random_barcode     = wp_generate_password(10, false);
		$random_description = 'A string containing ' . $random_title . ' and ' . $random_sku . ' and ' . $random_barcode;

		$product1  = ProductHelper::create_simple_product( array( 'description' => $random_description ) );
		$product2  = ProductHelper::create_simple_product( array( 'description' => $random_description, 'name' => 'Foo ' . $random_title . ' bar' ) );
		$product3  = ProductHelper::create_simple_product( array( 'description' => $random_description, 'sku' => 'foo-' . $random_sku . '-bar' ) );
		$product4  = ProductHelper::create_simple_product( array( 'description' => $random_description ) );
		$product4->update_meta_data( '_barcode', 'foo-' . $random_barcode . '-bar' );
		$product4->save_meta_data();

		$request   = $this->wp_rest_get_request( '/wcpos/v1/products' );

		// empty search
		$request->set_query_params( array( 'search' => '' ) );
		$response     = rest_get_server()->dispatch( $request );
		$data         = $response->get_data();
		$this->assertEquals(200, $response->get_status());
		$this->assertEquals( 4, \count( $data ) );

		// search for title
		$request->set_query_params( array( 'search' => $random_title ) );
		$response     = rest_get_server()->dispatch( $request );
		$data         = $response->get_data();
		$this->assertEquals(200, $response->get_status());
		$this->assertEquals( 1, \count( $data ) );
		$this->assertEquals( $product2->get_id(), $data[0]['id'] );

		// search for sku
		$request->set_query_params( array( 'search' => $random_sku ) );
		$response     = rest_get_server()->dispatch( $request );
		$data         = $response->get_data();
		$this->assertEquals(200, $response->get_status());
		$this->assertEquals( 1, \count( $data ) );
		$this->assertEquals( $product3->get_id(), $data[0]['id'] );

		// search for barcode
		$request->set_query_params( array( 'search' => $random_barcode ) );
		$response     = rest_get_server()->dispatch( $request );
		$data         = $response->get_data();
		$this->assertEquals(200, $response->get_status());
		$this->assertEquals( 1, \count( $data ) );
		$this->assertEquals( $product4->get_id(), $data[0]['id'] );
	}

	/**
	 * Online Only products.
	 */
	public function test_pos_only_products(): void {
		add_filter( 'woocommerce_pos_general_settings', function() {
			return array(
				'pos_only_products' => true,
			);
		});

		// online only
		$product1  = ProductHelper::create_simple_product();
		$product1->update_meta_data( '_pos_visibility', 'online_only' );
		$product1->save_meta_data();
		
		// both
		$product2  = ProductHelper::create_simple_product();
		$product2->update_meta_data( '_pos_visibility', '' );
		$product2->save_meta_data();

		// pos only
		$product3  = ProductHelper::create_simple_product();
		$product3->update_meta_data( '_pos_visibility', 'pos_only' );
		$product3->save_meta_data();
	
		// new product
		$product4	 = ProductHelper::create_simple_product();

		// test get all ids
		$request  = $this->wp_rest_get_request( '/wcpos/v1/products' );
		$request->set_param( 'posts_per_page', -1 );
		$request->set_param( 'fields', array('id') );
		$response     = rest_get_server()->dispatch( $request );
		$data         = $response->get_data();
		$this->assertEquals(200, $response->get_status());
		$this->assertEquals( 3, \count( $data ) );
		$ids         = wp_list_pluck( $data, 'id' );

		$this->assertEqualsCanonicalizing( array( $product2->get_id(), $product3->get_id(), $product4->get_id() ), $ids );

		// test products response
		$request      = $this->wp_rest_get_request( '/wcpos/v1/products' );
		$response     = rest_get_server()->dispatch( $request );
		$data         = $response->get_data();
		$this->assertEquals(200, $response->get_status());
		$this->assertEquals( 3, \count( $data ) );
		$ids         = wp_list_pluck( $data, 'id' );

		$this->assertEqualsCanonicalizing( array( $product2->get_id(), $product3->get_id(), $product4->get_id() ), $ids );
	}
}
