<?php


class WC_GFPA_GForm {

	private $form;
	private $lead;

	public function __construct( $form, $lead = null ) {

		if ( is_numeric( $form ) ) {
			$this->form = RGFormsModel::get_form_meta( $form );
		} else {
			$this->form = $form;
		}

		$this->lead = $lead;
	}

	public function get_product_fields( $gravity_form_data, $use_choice_text = false, $use_admin_label = false ) {
		$products = array();


		foreach ( $this->form["fields"] as $field ) {
			$id         = $field["id"];
			$lead_value = $this->get_lead_field_value( $field );

			$quantity_field = GFCommon::get_product_fields_by_type( $this->form, array( "quantity" ), $id );
			$quantity       = sizeof( $quantity_field ) > 0 ? $this->get_lead_field_value( $quantity_field[0] ) : 1;

			switch ( $field["type"] ) {

				case "product" :

					//ignore products that have been hidden by conditional logic
					$is_hidden = $this->get_product_field_is_hidden( $field, array() );
					if ( $is_hidden ) {
						continue;
					}

					//if single product, get values from the multiple inputs
					if ( is_array( $lead_value ) ) {
						$product_quantity = sizeof( $quantity_field ) == 0 && ! rgar( $field, "disableQuantity" ) ? rgget( $id . ".3", $lead_value ) : $quantity;
						if ( empty( $product_quantity ) ) {
							continue;
						}

						if ( ! rgget( $id, $products ) ) {
							$products[ $id ] = array();
						}

						$products[ $id ]['id']       = $id;
						$products[ $id ]["name"]     = $use_admin_label && ! rgempty( "adminLabel", $field ) ? $field["adminLabel"] : $lead_value[ $id . ".1" ];
						$products[ $id ]["price"]    = rgar( $lead_value, $id . ".2" );
						$products[ $id ]["quantity"] = $product_quantity;
						$products[ $id ]['field']    = $field;
					} else if ( ! empty( $lead_value ) ) {

						if ( empty( $quantity ) ) {
							continue;
						}

						if ( ! rgar( $products, $id ) ) {
							$products[ $id ] = array();
						}

						if ( $field["inputType"] == "price" ) {
							$name  = $field["label"];
							$price = 0;
						} else {
							list( $name, $price ) = explode( "|", $lead_value );
						}

						$products[ $id ]['id']       = $id;
						$products[ $id ]["name"]     = $field['label'];
						$products[ $id ]["price"]    = $price;
						$products[ $id ]["quantity"] = $quantity;
						$products[ $id ]["options"]  = array();
						$products[ $id ]['field']    = $field;
					} else {
						if ( ! rgar( $products, $id ) ) {
							$products[ $id ] = array();
						}

						$name  = $field["label"];
						$price = $lead_value;


						$products[ $id ]['id']       = $id;
						$products[ $id ]["name"]     = $field['label'];
						$products[ $id ]["price"]    = $price;
						$products[ $id ]["quantity"] = $quantity;
						$products[ $id ]["options"]  = array();
						$products[ $id ]['field']    = $field;
					}

					if ( isset( $products[ $id ] ) ) {

						$config = isset( $gravity_form_data['product_configuration'][ $this->form['id'] . '_' . $id ] ) ? $gravity_form_data['product_configuration'][ $this->form['id'] . '_' . $id ] : false;


						$one_time_fee                        = ( $config && isset( $config['calculation_type'] ) && $config['calculation_type'] == 'one-time-fee' ) ? 'one-time-fee' : 'standard';
						$one_time_fee                        = apply_filters( 'woocommerce_gravityforms_is_product_one_time_fee', $one_time_fee, $field, $products[ $id ], $gravity_form_data );
						$products[ $id ]['calculation_type'] = $one_time_fee;

						$total_label                    = ( $config && isset( $config['total_label'] ) ) ? $config['total_label'] : __( 'Total', 'wc_gf_addons' );
						$products[ $id ]['total_label'] = $total_label;

						$product_page_calculation_type                    = ( $config && isset( $config['product_page_calculation_type'] ) ) ? $config['product_page_calculation_type'] : 'combine';
						$products[ $id ]['product_page_calculation_type'] = $product_page_calculation_type;

						$options_label                    = ( $config && isset( $config['options_label'] ) ) ? $config['options_label'] : __( 'Options', 'wc_gf_addons' );
						$products[ $id ]['options_label'] = $options_label;

						$options = GFCommon::get_product_fields_by_type( $this->form, array( "option" ), $id );
						foreach ( $options as $option ) {
							$option_value = $this->get_lead_field_value( $option );
							$option_label = empty( $option["adminLabel"] ) ? $option["label"] : $option["adminLabel"];
							if ( is_array( $option_value ) ) {
								foreach ( $option_value as $value ) {
									$option_info = GFCommon::get_option_info( $value, $option, $use_choice_text );
									if ( ! empty( $option_info ) ) {
										$products[ $id ]["options"][] = array(
											"field_id"     => rgar( $option, "id" ),
											"field_label"  => rgar( $option, "label" ),
											"option_name"  => rgar( $option_info, "name" ),
											"option_label" => $option_label . ": " . rgar( $option_info, "name" ),
											"option_value" => $option_value,
											"price"        => rgar( $option_info, "price" )
										);
									}
								}
							} else if ( ! empty( $option_value ) ) {
								$option_info                  = GFCommon::get_option_info( $option_value, $option, $use_choice_text );
								$products[ $id ]["options"][] = array(
									"field_id"     => rgar( $option, "id" ),
									"field_label"  => $option_label,
									"option_name"  => rgar( $option_info, "name" ),
									"option_label" => $option_label . ": " . rgar( $option_info, "name" ),
									"option_value" => $option_value,
									"price"        => rgar( $option_info, "price" )
								);
							}
						}
					}
					break;
			}
		}

		$shipping_field = GFCommon::get_fields_by_type( $this->form, array( "shipping" ) );
		$shipping_price = $shipping_name = "";

		if ( ! empty( $shipping_field ) && ! RGFormsModel::is_field_hidden( $this->form, $shipping_field[0], array(), $this->lead ) ) {
			$shipping_price = $this->get_lead_field_value( $shipping_field[0] );
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

		$product_info = apply_filters( "gform_product_info_{$this->form["id"]}", apply_filters( "gform_product_info", $product_info, $this->form, $this->lead ), $this->form, $this->lead );

		return $product_info;
	}

	public function get_product_field_is_hidden( $field, $field_values ) {

		if ( empty( $field ) ) {
			return false;
		}

		$section         = RGFormsModel::get_section( $this->form, $field->id );
		$section_display = $this->get_field_display( $section, $field_values );

		//if section is hidden, hide field no matter what. if section is visible, see if field is supposed to be visible
		if ( $section_display == 'hide' ) {
			$display = 'hide';
		} else if ( RGFormsModel::is_page_hidden( $this->form, $field->pageNumber, $field_values, $this->lead ) ) {
			$display = 'hide';
		} else {
			$display = $this->get_field_display( $field, $field_values );

			return $display == 'hide';
		}

		return $display == 'hide';

	}

	public function get_field_display( $field, $field_values ) {

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
			$source_field   = RGFormsModel::get_field( $this->form, $rule['fieldId'] );
			$field_value    = empty( $this->lead ) ? RGFormsModel::get_field_value( $source_field, $field_values ) : RGFormsModel::get_lead_field_value( $this->lead, $source_field );
			$is_value_match = RGFormsModel::is_value_match( $field_value, $rule['value'], $rule['operator'], $source_field, $rule, $this->form );

			if ( $is_value_match ) {
				$match_count ++;
			}
		}

		$do_action = ( $logic['logicType'] == 'all' && $match_count == sizeof( $logic['rules'] ) ) || ( $logic['logicType'] == 'any' && $match_count > 0 );
		$is_hidden = ( $do_action && $logic['actionType'] == 'hide' ) || ( ! $do_action && $logic['actionType'] == 'show' );

		return $is_hidden ? 'hide' : 'show';
	}


	/**
	 * @param $field GF_Field
	 *
	 * @return array|bool|mixed|string|void
	 */
	public function get_lead_field_value( $field ) {

		if ( empty( $this->lead ) ) {
			return null;
		}

		return RGFormsModel::get_lead_field_value( $this->lead, $field );
	}

}