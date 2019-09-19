<?php

/**
 * This class helps us to override structured data from WooCommerce.
 * Helpful when the base price of the product is 0, or when you want google to be aware of the pricing options in your form.
 * Class WC_GFPA_Entry
 */
class WC_GFPA_Structured_Data {

	private static $instance;

	public static function register() {
		if ( self::$instance == null ) {
			self::$instance = new WC_GFPA_Structured_Data();
		}
	}

	private function __construct() {

		add_filter( 'woocommerce_structured_data_product_offer', array(
			$this,
			'modify_woocommerce_structured_data_product_offer'
		), 10, 2 );

	}

	/**
	 * @param $markup  string
	 * @param $product \WC_Product
	 *
	 * @return string The markup to use for structured data offers.
	 */
	public function modify_woocommerce_structured_data_product_offer( $markup, $product ) {

		$the_id = $product->get_id();
		if ( $product->is_type( 'variation' ) ) {
			$the_id = $product->get_parent_id();
		}
		$gravity_form_data = WC_GFPA_Main::instance()->get_gravity_form_data( $the_id );
		if ( empty( $gravity_form_data ) || ! isset( $gravity_form_data['structured_data_override'] ) ) {
			return $markup;
		}

		if ( $gravity_form_data['structured_data_override'] !== 'yes' ) {
			return $markup;
		}

		$low_price  = $gravity_form_data['structured_data_low_price'];
		$high_price = $gravity_form_data['structured_data_high_price'];
		$operation  = $gravity_form_data['structured_data_override_type'];

		//Check if there is actually anything to do.
		if ( empty( $low_price ) && empty( $high_price ) && $operation == 'append' ) {
			return $markup;
		}

		$new_offer = $this->get_markup( $markup, $gravity_form_data, $product );

		return $new_offer;
	}

	private function get_markup( $markup_offer, $gravity_form_data, $product ) {
		$price_valid_until = date( 'Y-12-31', current_time( 'timestamp', true ) + YEAR_IN_SECONDS );

		$low_price  = $gravity_form_data['structured_data_low_price'];
		$high_price = $gravity_form_data['structured_data_high_price'];
		if ( empty( $high_price ) ) {
			$high_price = $low_price;
		}

		if ( empty( $low_price ) ) {
			$low_price = $high_price;
		}

		$currency = get_woocommerce_currency();

		if ( $product->is_type( 'variable' ) ) {
			$lowest  = $product->get_variation_price( 'min', false );
			$highest = $product->get_variation_price( 'max', false );

			if ( $low_price === $high_price && $lowest === $highest ) {
				$new_price = $lowest;
				if ( $gravity_form_data['structured_data_override_type'] === 'overwrite' ) {
					$new_price = $low_price;
				} elseif ( $gravity_form_data['structured_data_override_type'] === 'append' ) {
					$new_price = $lowest + $low_price;
				}

				$markup_offer['price']                       = wc_format_decimal( $new_price, wc_get_price_decimals() );
				$markup_offer['priceSpecification']['price'] = wc_format_decimal( $new_price, wc_get_price_decimals() );
			} elseif ( $low_price === $high_price && $lowest != $highest ) {

				if ( $gravity_form_data['structured_data_override_type'] === 'overwrite' ) {
					//This is probably an extreme edge case where the variation prices are different but the user wants to completely override them with a single price.
					unset( $markup_offer['lowPrice'] );
					unset( $markup_offer['highPrice'] );

					$markup_offer['@type']              = 'Offer';
					$markup_offer['price']              = wc_format_decimal( $low_price, wc_get_price_decimals() );
					$markup_offer['priceValidUntil']    = $price_valid_until;
					$markup_offer['priceSpecification'] = array(
						'price'                 => wc_format_decimal( $low_price, wc_get_price_decimals() ),
						'priceCurrency'         => $currency,
						'valueAddedTaxIncluded' => wc_prices_include_tax() ? 'true' : 'false',
					);
				} elseif ( $gravity_form_data['structured_data_override_type'] === 'append' ) {
					$new_low_price  = $low_price + $lowest;
					$new_high_price = $high_price + $highest;
					unset( $markup_offer['price'] );
					unset( $markup_offer['priceSpecification'] );
					$markup_offer['@type']     = 'AggregateOffer';
					$markup_offer['lowPrice']  = wc_format_decimal( $new_low_price, wc_get_price_decimals() );
					$markup_offer['highPrice'] = wc_format_decimal( $new_high_price, wc_get_price_decimals() );
				}
			} elseif ( $low_price !== $high_price ) {
				if ( $gravity_form_data['structured_data_override_type'] === 'append' ) {
					$new_low_price  = $low_price + $lowest;
					$new_high_price = $high_price + $highest;
					unset( $markup_offer['price'] );
					unset( $markup_offer['priceSpecification'] );
					$markup_offer['@type']     = 'AggregateOffer';
					$markup_offer['lowPrice']  = wc_format_decimal( $new_low_price, wc_get_price_decimals() );
					$markup_offer['highPrice'] = wc_format_decimal( $new_high_price, wc_get_price_decimals() );
				} elseif ( $gravity_form_data['structured_data_override_type'] === 'overwrite' ) {
					$new_low_price  = $low_price;
					$new_high_price = $high_price;
					unset( $markup_offer['price'] );
					unset( $markup_offer['priceSpecification'] );
					$markup_offer['@type']     = 'AggregateOffer';
					$markup_offer['lowPrice']  = wc_format_decimal( $new_low_price, wc_get_price_decimals() );
					$markup_offer['highPrice'] = wc_format_decimal( $new_high_price, wc_get_price_decimals() );
				}
			}
		} else {
			if ( $product->is_on_sale() && $product->get_date_on_sale_to() ) {
				$price_valid_until = date( 'Y-m-d', $product->get_date_on_sale_to()->getTimestamp() );
			}

			if ( $low_price === $high_price ) {

				if ( $gravity_form_data['structured_data_override_type'] === 'overwrite' ) {
					$markup_offer['@type']              = 'Offer';
					$markup_offer['price']              = wc_format_decimal( $low_price, wc_get_price_decimals() );
					$markup_offer['priceValidUntil']    = $price_valid_until;
					$markup_offer['priceSpecification'] = array(
						'price'                 => wc_format_decimal( $low_price, wc_get_price_decimals() ),
						'priceCurrency'         => $currency,
						'valueAddedTaxIncluded' => wc_prices_include_tax() ? 'true' : 'false',
					);
				} elseif ( $gravity_form_data['structured_data_override_type'] === 'append' ) {
					$new_low_price                      = $low_price + $product->get_price();
					$markup_offer['@type']              = 'Offer';
					$markup_offer['price']              = wc_format_decimal( $new_low_price, wc_get_price_decimals() );
					$markup_offer['priceValidUntil']    = $price_valid_until;
					$markup_offer['priceSpecification'] = array(
						'price'                 => wc_format_decimal( $new_low_price, wc_get_price_decimals() ),
						'priceCurrency'         => $currency,
						'valueAddedTaxIncluded' => wc_prices_include_tax() ? 'true' : 'false',
					);
				}
			} elseif ( $low_price !== $high_price ) {
				if ( $gravity_form_data['structured_data_override_type'] === 'append' ) {
					$base_price     = $product->get_price();
					$new_low_price  = $low_price + $base_price;
					$new_high_price = $high_price + $base_price;
					unset( $markup_offer['price'] );
					unset( $markup_offer['priceSpecification'] );
					$markup_offer['@type']     = 'AggregateOffer';
					$markup_offer['lowPrice']  = wc_format_decimal( $new_low_price, wc_get_price_decimals() );
					$markup_offer['highPrice'] = wc_format_decimal( $new_high_price, wc_get_price_decimals() );
				} elseif ( $gravity_form_data['structured_data_override_type'] === 'overwrite' ) {
					$new_low_price  = $low_price;
					$new_high_price = $high_price;
					unset( $markup_offer['price'] );
					unset( $markup_offer['priceSpecification'] );
					$markup_offer['@type']     = 'AggregateOffer';
					$markup_offer['lowPrice']  = wc_format_decimal( $new_low_price, wc_get_price_decimals() );
					$markup_offer['highPrice'] = wc_format_decimal( $new_high_price, wc_get_price_decimals() );
				}
			}
		}

		return $markup_offer;
	}

}
