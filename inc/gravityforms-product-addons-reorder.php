<?php


class WC_GFPA_Reorder {

	private static $instance;

	public static function register() {
		if ( self::$instance == null ) {
			self::$instance = new WC_GFPA_Reorder;
		}
	}


	protected function __construct() {

		//Order Again

		add_filter( 'woocommerce_order_again_cart_item_data', array(
			$this,
			'on_get_order_again_cart_item_data'
		), 10, 3 );

		add_action( 'wcs_before_renewal_setup_cart_subscriptions', array(
			$this,
			'on_wcs_before_renewal_setup_cart_subscriptions'
		) );
		add_action( 'wcs_after_renewal_setup_cart_subscriptions', array(
			$this,
			'on_wcs_after_renewal_setup_cart_subscriptions'
		) );

	}

	public function on_wcs_before_renewal_setup_cart_subscriptions() {
		remove_filter( 'woocommerce_order_again_cart_item_data', array(
			$this,
			'on_get_order_again_cart_item_data'
		), 10 );
	}

	public function on_wcs_after_renewal_setup_cart_subscriptions() {
		add_filter( 'woocommerce_order_again_cart_item_data', array(
			$this,
			'on_get_order_again_cart_item_data'
		), 10, 3 );
	}

	public function add_to_cart_validation( $valid, $product_id, $quantity, $variation_id, $variations, $cart_item_data ) {

		$gravity_form_data = wc_gfpa()->get_gravity_form_data( $product_id );
		if ( isset( $cart_item_data['_gravity_form_lead'] ) ) {

			if ( empty( $gravity_form_data ) ) {
				return false;
			}

			if ( isset( $gravity_form_data['bulk_id'] ) ) {
				if ( $gravity_form_data['id'] != $cart_item_data['_gravity_form_lead']['form_id'] && $gravity_form_data['bulk_id'] != $cart_item_data['_gravity_form_lead']['form_id'] ) {
					return false;
				}
			} elseif ( $gravity_form_data['id'] != $cart_item_data['_gravity_form_lead']['form_id'] ) {
				return false;
			}

			$glead = $cart_item_data['_gravity_form_lead'];
			foreach ( $_POST as $key => $value ) {
				if ( strpos( $key, 'input_' ) === 0 ) {
					unset( $_POST[ $key ] );
				}
			}

			foreach ( $glead as $key => $value ) {
				$_POST[ 'input_' . str_replace( '.', '_', $key ) ] = $value;
			}

			$valid = $this->validate_entry( $cart_item_data['_gravity_form_lead']['form_id'], $glead );
		}


		remove_filter( 'woocommerce_add_to_cart_validation', array( $this, 'add_to_cart_validation' ), 99 );

		return $valid;
	}

	public function on_get_order_again_cart_item_data( $data, $item, $order ) {

		//Note regular add to cart validation is disabled in the gravityforms-product-addons-cart.php during reorder.
		add_filter( 'woocommerce_add_to_cart_validation', array( $this, 'add_to_cart_validation' ), 99, 6 );

		if ( isset( $data['subscription_resubscribe'] ) ) {
			return $data;
		}

		$history = isset( $item['gravity_forms_history'] ) ? maybe_unserialize( $item['gravity_forms_history'] ) : false;
		if ( ! $history ) {
			//Not sure why exactly WC strips out the leading _, let's check for it anyways
			$history = isset( $item['_gravity_forms_history'] ) ? maybe_unserialize( $item['_gravity_forms_history'] ) : false;
		}

		if ( $history ) {
			$glead = isset( $history['_gravity_form_lead'] ) ? $history['_gravity_form_lead'] : false;
			$gdata = isset( $history['_gravity_form_data'] ) ? $history['_gravity_form_data'] : false;

			if ( $glead && $gdata ) {
				$data['_gravity_form_lead'] = $glead;
				$data['_gravity_form_data'] = $gdata;
			}

			foreach ( $_POST as $key => $value ) {
				if ( strpos( $key, 'input_' ) === 0 ) {
					unset( $_POST[ $key ] );
				}
			}

			foreach ( $glead as $key => $value ) {
				$_POST[ 'input_' . str_replace( '.', '_', $key ) ] = $value;
			}

		}

		return $data;
	}


	public function validate_entry( $form_id, $field_values ) {

		$form     = RGFormsModel::get_form_meta( $form_id );
		$is_valid = true;

		if ( $form && $form['id'] == $form_id ) {
			foreach ( $form['fields'] as &$field ) {
				/* @var GF_Field $field */


				// don't validate adminOnly fields.
				if ( $field->is_administrative() ) {
					continue;
				}

				//ignore validation if field is hidden
				if ( RGFormsModel::is_field_hidden( $form, $field, $field_values, $field_values ) ) {
					$field->is_field_hidden = true;

					continue;
				}

				if ( $field->get_input_type() == 'fileupload' ) {
					continue;
				}


				$inputs = $field->get_entry_inputs();

				if ( is_array( $inputs ) ) {
					$value = array();
					foreach ( $inputs as $input ) {
						$v = '';

						if ( isset( $field_values[ strval( $input['id'] ) ] ) ) {
							$v = $field_values[ strval( $input['id'] ) ];
						}

						$value[ strval( $input['id'] ) ] = $v;
					}
				} else {
					$value = isset( $field_values[ $field->id ] ) ? $field_values[ $field->id ] : '';
				}


				$input_type = RGFormsModel::get_input_type( $field );

				//display error message if field is marked as required and the submitted value is empty
				if ( $field->isRequired && $field->is_value_submission_empty( $form_id ) ) {
					$field->failed_validation  = true;
					$field->validation_message = empty( $field->errorMessage ) ? __( 'This field is required.', 'gravityforms' ) : $field->errorMessage;
				} else {

				}

				$field->validate( $value, $form );

				$custom_validation_result = gf_apply_filters( array(
					'gform_field_validation',
					$form['id'],
					$field->id
				), array(
					'is_valid' => $field->failed_validation ? false : true,
					'message'  => $field->validation_message
				), $value, $form, $field );

				$field->failed_validation  = rgar( $custom_validation_result, 'is_valid' ) ? false : true;
				$field->validation_message = rgar( $custom_validation_result, 'message' );

				if ( $field->failed_validation ) {
					$is_valid = false;
				}
			}

			return $is_valid;
		} else {
			return false;
		}

	}

}
