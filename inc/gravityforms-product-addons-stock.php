<?php

class WC_GFPA_Stock {

	private static $instance;

	public static function register() {
		if ( self::$instance == null ) {
			self::$instance = new WC_GFPA_Stock();
		}
	}

	protected function __construct() {
		add_filter( 'woocommerce_order_item_quantity', array( $this, 'maybe_set_order_item_quantity' ), 9999, 3 );
	}

	public function maybe_set_order_item_quantity( $quantity, $order, $order_item ) {

		$meta_data_items = $order_item->get_meta_data();

		foreach ( $meta_data_items as $meta ) {
			if ( $meta->key == '_gravity_forms_history' ) {

				$gravity_form_data = $meta->value['_gravity_form_data'];
				$lead              = $meta->value['_gravity_form_lead'];

				$form_meta = RGFormsModel::get_form_meta( $gravity_form_data['id'] );

				if ( empty( $form_meta ) ) {
					return $quantity;
				}

				$products = $this->get_product_fields( $form_meta, $lead );

				if ( isset( $gravity_form_data['enable_cart_quantity_management'] ) && $gravity_form_data['enable_cart_quantity_management'] == 'stock' ) {

					$field = isset( $gravity_form_data['cart_quantity_field'] ) ? $gravity_form_data['cart_quantity_field'] : false;

					if ( $field ) {
						if ( isset( $products['products'][ $field ] ) ) {
							$quantity = isset( $products['products'][ $field ] ) ? $products['products'][ $field ]['quantity'] : $quantity;
						} else {
							$quantity = isset( $lead[ $field ] ) ? $lead[ $field ] : $quantity;
						}

					}

				}
			}
		}

		return $quantity;

	}

	//TODO:  Abstract these out to a helper class.

	//Helper Functions
	protected function get_product_fields( $form, $lead, $use_choice_text = false, $use_admin_label = false ) {
		$products = array();

		foreach ( $form["fields"] as $field ) {
			$id         = $field["id"];
			$lead_value = $this->get_lead_field_value( $lead, $field );

			$quantity_field = GFCommon::get_product_fields_by_type( $form, array( "quantity" ), $id );
			$quantity       = sizeof( $quantity_field ) > 0 ? $this->get_lead_field_value( $lead, $quantity_field[0] ) : 1;

			switch ( $field["type"] ) {

				case "product" :

					//ignore products that have been hidden by conditional logic
					$is_hidden = $this->get_product_field_is_hidden( $form, $field, array(), $lead );
					if ( $is_hidden ) {
						break;
					}

					//if single product, get values from the multiple inputs
					if ( is_array( $lead_value ) ) {
						$product_quantity = sizeof( $quantity_field ) == 0 && ! rgar( $field, "disableQuantity" ) ? rgget( $id . ".3", $lead_value ) : $quantity;
						if ( empty( $product_quantity ) ) {
							break;
						}

						if ( ! rgget( $id, $products ) ) {
							$products[ $id ] = array();
						}

						$products[ $id ]["name"]     = $use_admin_label && ! rgempty( "adminLabel", $field ) ? $field["adminLabel"] : $lead_value[ $id . ".1" ];
						$products[ $id ]["price"]    = rgar( $lead_value, $id . ".2" );
						$products[ $id ]["quantity"] = $product_quantity;
					} else if ( ! empty( $lead_value ) ) {

						if ( empty( $quantity ) ) {
							break;
						}

						if ( ! rgar( $products, $id ) ) {
							$products[ $id ] = array();
						}

						if ( $field["inputType"] == "price" ) {
							$name  = $field["label"];
							$price = $lead_value;
						} else {
							list( $name, $price ) = explode( "|", $lead_value );
						}

						$products[ $id ]["name"]     = ! $use_choice_text ? $name : RGFormsModel::get_choice_text( $field, $name );
						$products[ $id ]["price"]    = $price;
						$products[ $id ]["quantity"] = $quantity;
						$products[ $id ]["options"]  = array();
					}

					if ( isset( $products[ $id ] ) ) {
						$options = GFCommon::get_product_fields_by_type( $form, array( "option" ), $id );
						foreach ( $options as $option ) {
							$option_value = $this->get_lead_field_value( $lead, $option );
							$option_label = empty( $option["adminLabel"] ) ? $option["label"] : $option["adminLabel"];
							if ( is_array( $option_value ) ) {
								foreach ( $option_value as $value ) {
									$option_info = GFCommon::get_option_info( $value, $option, $use_choice_text );
									if ( ! empty( $option_info ) ) {
										$products[ $id ]["options"][] = array(
											"field_label"  => rgar( $option, "label" ),
											"option_name"  => rgar( $option_info, "name" ),
											"option_label" => $option_label . ": " . rgar( $option_info, "name" ),
											"price"        => rgar( $option_info, "price" )
										);
									}
								}
							} else if ( ! empty( $option_value ) ) {
								$option_info                  = GFCommon::get_option_info( $option_value, $option, $use_choice_text );
								$products[ $id ]["options"][] = array(
									"field_label"  => rgar( $option, "label" ),
									"option_name"  => rgar( $option_info, "name" ),
									"option_label" => $option_label . ": " . rgar( $option_info, "name" ),
									"price"        => rgar( $option_info, "price" )
								);
							}
						}
					}
					break;
			}
		}

		$shipping_field = GFCommon::get_fields_by_type( $form, array( "shipping" ) );
		$shipping_price = $shipping_name = "";

		if ( ! empty( $shipping_field ) && ! RGFormsModel::is_field_hidden( $form, $shipping_field[0], array(), $lead ) ) {
			$shipping_price = $this->get_lead_field_value( $lead, $shipping_field[0] );
			$shipping_name  = $shipping_field[0]["label"];
			if ( $shipping_field[0]["inputType"] != "singleshipping" ) {
				list( $shipping_method, $shipping_price ) = explode( "|", $shipping_price );
				$shipping_name = $shipping_field[0]["label"] . " ($shipping_method)";
			}
		}

		$shipping_price = GFCommon::to_number( $shipping_price );

		$product_info = array(
			"products" => $products,
			"shipping" => array( "name" => $shipping_name, "price" => $shipping_price )
		);

		$product_info = apply_filters( "gform_product_info_{$form["id"]}", apply_filters( "gform_product_info", $product_info, $form, $lead ), $form, $lead );

		return $product_info;
	}

	protected function get_product_field_is_hidden( $form, $field, $field_values, $lead = null ) {

		if ( empty( $field ) ) {
			return false;
		}

		$section         = RGFormsModel::get_section( $form, $field->id );
		$section_display = $this->get_field_display( $form, $section, $field_values, $lead );

		//if section is hidden, hide field no matter what. if section is visible, see if field is supposed to be visible
		if ( $section_display == 'hide' ) {
			$display = 'hide';
		} else if ( RGFormsModel::is_page_hidden( $form, $field->pageNumber, $field_values, $lead ) ) {
			$display = 'hide';
		} else {
			$display = $this->get_field_display( $form, $field, $field_values, $lead );

			return $display == 'hide';
		}

		return $display == 'hide';

	}

	protected function get_field_display( $form, $field, $field_values, $lead = null ) {

		if ( empty( $field ) ) {
			return 'show';
		}

		$logic = $field->conditionalLogic;

		//if this field does not have any conditional logic associated with it, it won't be hidden
		if ( empty( $logic ) ) {
			return 'show';
		}

		$match_count = 0;
		foreach ( $logic['rules'] as $rule ) {
			$source_field   = RGFormsModel::get_field( $form, $rule['fieldId'] );
			$field_value    = empty( $lead ) ? RGFormsModel::get_field_value( $source_field, $field_values ) : RGFormsModel::get_lead_field_value( $lead, $source_field );
			$is_value_match = RGFormsModel::is_value_match( $field_value, $rule['value'], $rule['operator'], $source_field, $rule, $form );

			if ( $is_value_match ) {
				$match_count ++;
			}
		}

		$do_action = ( $logic['logicType'] == 'all' && $match_count == sizeof( $logic['rules'] ) ) || ( $logic['logicType'] == 'any' && $match_count > 0 );
		$is_hidden = ( $do_action && $logic['actionType'] == 'hide' ) || ( ! $do_action && $logic['actionType'] == 'show' );

		return $is_hidden ? 'hide' : 'show';
	}


	/**
	 * @param $lead
	 * @param $field GF_Field
	 *
	 * @return array|bool|mixed|string|void
	 */
	private function get_lead_field_value( $lead, $field ) {
		return RGFormsModel::get_lead_field_value( $lead, $field );
	}

}
