<?php

class WC_GFPA_Cart_Edit {

	private static $instance;

	public static function register() {
		if ( self::$instance == null ) {
			self::$instance = new WC_GFPA_Cart_Edit;
		}
	}


	private function __construct() {

		add_filter( 'woocommerce_cart_item_permalink', array(
			$this,
			'on_get_woocommerce_cart_item_permalink'
		), 99, 3 );

		add_filter( 'gform_pre_render', array( $this, 'on_gform_pre_render' ), 99, 1 );

		add_action( 'woocommerce_add_to_cart', array( $this, 'on_woocommerce_add_to_cart' ), 99, 6 );
	}


	public function on_get_woocommerce_cart_item_permalink( $permalink, $cart_item, $cart_item_key ) {

		if ( isset( $cart_item['_gravity_form_lead'] ) && isset( $cart_item['_gravity_form_data'] ) ) {

			if ( isset( $cart_item['_gravity_form_lead'] ) && isset( $cart_item['_gravity_form_data'] ) ) {

				if ( $cart_item['data']->get_type() == 'variation' ) {
					$p = wc_get_product( $cart_item['data']->get_parent_id() );
				} else {
					$p = $cart_item['data'];
				}


				$gravity_form_data = wc_gfpa()->get_gravity_form_data( $p->get_id() );

				if ( isset( $gravity_form_data['enable_cart_edit'] ) && $gravity_form_data['enable_cart_edit'] !== 'no' ) {
					$permalink = add_query_arg( array( 'wc_gforms_cart_item_key' => $cart_item_key ), $permalink );
				}
			}
		}

		return $permalink;
	}

	public function on_gform_pre_render( $form ) {

		if ( isset( $_GET['wc_gforms_cart_item_key'] ) && empty( $_POST ) ) {


			$cart_item_key = $_GET['wc_gforms_cart_item_key'];
			$cart_item     = WC()->cart->get_cart_item( $cart_item_key );

			if ( empty( $cart_item ) ) {
				return $form;
			}

			if ( isset( $cart_item['_gravity_form_lead'] ) && isset( $cart_item['_gravity_form_data'] ) ) {
				$entry    = $cart_item['_gravity_form_lead'];
				$entry_id = false;
				$form     = $this->gfee_pre_render( $form, $entry, $entry_id, array() );

			}


		}

		return $form;


	}


	public function gfee_pre_render( $form, $entry, $entry_id, $exclude_fields = array() ) {

		if ( ! $entry ) {
			return $form;
		}


		foreach ( $form['fields'] as &$field ) {
			if ( in_array( $field['id'], $exclude_fields ) ) {
				$field['cssClass']   = 'gform_hidden';
				$field['isRequired'] = false;
			} else {
				$value = null;
				if ( $field['type'] == 'checkbox' || ( $field['type'] == 'option' && $field['inputType'] == 'checkbox' ) ) { // handle checkbox fields
					// only pull the field values from the entry that match the form field we are evaluating
					$field_values = array();

					foreach ( $entry as $key => $value ) {
						$entry_key = explode( '.', $key );
						if ( $entry_key[0] == $field['id'] ) {
							$v              = explode( '|', $value );
							$field_values[] = $v[0];
						}
					}
					foreach ( $field->choices as &$choice ) {
						$choice['isSelected'] = ( in_array( $choice['value'], $field_values, true ) ) ? true : '';
					}
				} elseif ( is_array( $field->inputs ) ) { // handle other multi-input fields (address, name, time, etc.)

					// for time field, parse entry string to get individual parts of time string
					if ( $field['type'] == 'time' ) {
						// separate time string from entry into individual parts
						list( $HH, $time_end_part ) = explode( ':', $entry[ strval( $field['id'] ) ] );
						list( $MM, $AMPM ) = explode( ' ', $time_end_part );
						// save the time parts into individual array elements within the entry for our loop
						$entry[ $field['id'] . '.1' ] = $HH;
						$entry[ $field['id'] . '.2' ] = $MM;
						$entry[ $field['id'] . '.3' ] = $AMPM;
					}

					// loop each field input and set the default value from the entry
					foreach ( $field->inputs as $key => &$input ) {
						$value = '';
						if ( isset( $entry[ strval( $input['id'] ) ] ) ) {
							$value = $entry[ strval( $input['id'] ) ];
						} elseif ( isset( $entry[ $field['id'] ] ) ) {
							$value = $entry[ $field['id'] ];
						} elseif ( isset( $entry[ $field['id'] . '1' ] ) ) {
							$value = $entry[ $field['id'] ] . '.1';
						}

						$input['defaultValue'] = $value;
					}
				} else { // handle remaining single input fields
					if ( isset( $entry[ $field['id'] ] ) ) {
						$value = $entry[ $field['id'] ];
					}
				}

				// if we have a value for the field from the provided entry, set the default value for the field
				if ( ! empty( $value ) ) {
					$field['defaultValue'] = $value;
				}
			}
		}

		return $form;
	}


	/**
	 * Removes the modified item from the cart if settings allow for it.
	 *
	 * @param $cart_item_key
	 * @param $cart_item
	 */
	public function on_woocommerce_add_to_cart( $cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data ) {

		if ( isset( $_POST['wc_gforms_previous_cart_item_key'] ) ) {
			if ( $cart_item_key != $_POST['wc_gforms_previous_cart_item_key'] ) {
				if ( isset( $cart_item_data['_gravity_form_lead'] ) && isset( $cart_item_data['_gravity_form_data'] ) ) {

					$gravity_form_data = wc_gfpa()->get_gravity_form_data( $product_id );

					if ( isset( $gravity_form_data['enable_cart_edit_remove'] ) && $gravity_form_data['enable_cart_edit_remove'] !== 'no' ) {
						WC()->cart->remove_cart_item( $_POST['wc_gforms_previous_cart_item_key'] );
					}
				}
			}
		}

	}


}