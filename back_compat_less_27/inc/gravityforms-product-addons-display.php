<?php

class WC_GFPA_Display {

	private static $instance;

	public static function register() {
		if ( self::$instance == null ) {
			self::$instance = new WC_GFPA_Display;
		}
	}

	private function __construct() {		
		add_filter( 'add_to_cart_text', array( $this, 'get_add_to_cart_text'), 15 );
		add_filter( 'woocommerce_product_add_to_cart_text', array( $this, 'get_add_to_cart_text'), 15 );
		add_filter( 'woocommerce_add_to_cart_url', array( $this, 'get_add_to_cart_url' ), 10, 1 );
		add_filter( 'woocommerce_product_add_to_cart_url', array( $this, 'get_add_to_cart_url' ), 10, 1 );
		add_filter( 'woocommerce_product_supports', array( $this, 'ajax_add_to_cart_supports' ), 10, 3 );
	}

	
	public function get_add_to_cart_text( $text ) {
		global $product;

		if ( ! is_single( $product->id ) ) {
			if ( is_array( wc_gfpa()->gravity_products ) && in_array( $product->id, wc_gfpa()->gravity_products ) ) {
				$text = apply_filters( 'woocommerce_gforms_add_to_cart_text', __( 'Select options', 'woocommerce' ) );
			}
		}

		return $text;
	}
	
	
	public function get_add_to_cart_url( $url ) {
		global $product;

		if ( is_array( wc_gfpa()->gravity_products ) && in_array( $product->id, wc_gfpa()->gravity_products ) && (!isset( $_GET['wc-api'] ) || $_GET['wc-api'] !== 'WC_Quick_View' ) ) {
			$url = apply_filters( 'addons_add_to_cart_url', get_permalink( $product->id ) );
		}
		
		return $url;
	}
	
	
	/**
	 * Removes ajax-add-to-cart functionality in WC 2.5 when a product has required add-ons.
	 *
	 * @access public
	 * @param  boolean $supports
	 * @param  string  $feature
	 * @param  object  $product
	 * @return boolean
	 */
	public function ajax_add_to_cart_supports( $supports, $feature, $product ) {

		if ( 'ajax_add_to_cart' === $feature && is_array( wc_gfpa()->gravity_products ) && in_array( $product->id, wc_gfpa()->gravity_products ) ) {
			$supports = false;
		}

		return $supports;
	}

}
