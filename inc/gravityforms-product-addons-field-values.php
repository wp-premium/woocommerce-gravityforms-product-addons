<?php


class WC_GFPA_FieldValues {
	private static $instance;

	public static function register() {
		if ( self::$instance == null ) {
			self::$instance = new WC_GFPA_FieldValues();
		}
	}


	public function __construct() {
		add_filter( 'wcgf_gform_field_value', array( $this, 'fill_field_value' ), 10, 4 );
		add_filter( 'wcgf_gform_input_value', array( $this, 'fill_input_value' ), 10, 5 );
	}

	public function fill_field_value( $value, $product_id, $variation_id, $field ) {

		if ( $field->allowsPrepopulate ) {
			$value = $this->fill_value( $product_id, $variation_id, $value, $field->inputName );
		}

		return $value;

	}

	public function fill_input_value( $value, $product_id, $variation_id, $field, $input ) {

		if ( $field->allowsPrepopulate ) {
			$value = $this->fill_value( $product_id, $variation_id, $value, rgar( $input, 'name' ) );
		}

		return $value;

	}

	private function fill_value( $product_id, $variation_id, $value, $name ) {


		switch ( $name ) {
			case 'wcgf_product_id':
				$product = wc_get_product( $product_id );
				if ( $product ) {
					$value = $product->get_id();
				}
				break;
			case 'wcgf_product_sku':
				$product = wc_get_product( $product_id );
				if ( $product ) {
					$value = $product->get_sku();
				}
				break;
			case 'wcgf_product_name':
				$product = wc_get_product( $product_id );
				if ( $product ) {
					$value = $product->get_name();
				}
				break;
			case 'wcgf_variation_id':
				if ( ! empty( $variation_id ) ) {
					$product = wc_get_product( $variation_id );
					if ( $product ) {
						$value = $product->get_id();
					}
				}
				break;
			case 'wcgf_variation_sku':
				if ( ! empty( $variation_id ) ) {
					$product = wc_get_product( $variation_id );
					if ( $product ) {
						$value = $product->get_sku();
					}
				}
				break;
			case 'wcgf_variation_name':
				if ( ! empty( $variation_id ) ) {
					$product = wc_get_product( $variation_id );
					if ( $product ) {
						$value = $product->get_name();
					}
				}
				break;
			default:
				break;
		}


		return $value;


	}

}