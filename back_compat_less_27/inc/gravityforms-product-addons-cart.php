<?php

class WC_GFPA_Cart {

	private static $instance;

	public static function register() {
		if ( self::$instance == null ) {
			self::$instance = new WC_GFPA_Cart;
		}
	}

	private function __construct() {
		// Filters for cart actions

		add_filter( 'woocommerce_add_cart_item_data', array( $this, 'add_cart_item_data' ), 10, 2 );
		add_filter( 'woocommerce_get_cart_item_from_session', array( $this, 'get_cart_item_from_session' ), 10, 2 );
		add_filter( 'woocommerce_get_item_data', array( $this, 'get_item_data' ), 10, 2 );
		add_filter( 'woocommerce_add_cart_item', array( $this, 'add_cart_item' ), 10, 1 );

		add_action( 'woocommerce_add_order_item_meta', array( $this, 'order_item_meta' ), 10, 2 );
		add_filter( 'woocommerce_add_to_cart_validation', array( $this, 'add_to_cart_validation' ), 99, 3 );

		//Order Again
		add_filter( 'woocommerce_order_again_cart_item_data', array(
			$this,
			'on_get_order_again_cart_item_data'
		), 10, 3 );
	}

	//Helper function, used when an item is added to the cart as well as when an item is restored from session.
	public function add_cart_item( $cart_item ) {
		global $woocommerce;

		// Adjust price if required based on the gravity form data
		if ( isset( $cart_item['_gravity_form_lead'] ) && isset( $cart_item['_gravity_form_data'] ) ) {
			//Gravity forms generates errors and warnings.  To prevent these from conflicting with other things, we are going to disable warnings and errors.
			$err_level = error_reporting();
			error_reporting( 0 );

			$gravity_form_data = $cart_item['_gravity_form_data'];
			$form_meta         = RGFormsModel::get_form_meta( $gravity_form_data['id'] );

			if ( empty( $form_meta ) ) {
				$_product = $cart_item['data'];
				$woocommerce->add_error( $_product->get_title() . __( ' is invalid.  Please remove and try readding to the cart', 'wc_gf_addons' ) );

				return $cart_item;
			}

			$lead = $cart_item['_gravity_form_lead'];

			$products = array();
			$total    = 0;

			$lead['id'] = uniqid() . time() . rand();

			$products = $this->get_product_fields( $form_meta, $lead );
			if ( ! empty( $products["products"] ) ) {

				foreach ( $products["products"] as $product ) {
					$price = GFCommon::to_number( $product["price"] );
					if ( is_array( rgar( $product, "options" ) ) ) {
						$count = sizeof( $product["options"] );
						$index = 1;
						foreach ( $product["options"] as $option ) {
							$price += GFCommon::to_number( $option["price"] );
							$class = $index == $count ? " class='lastitem'" : "";
							$index ++;
						}
					}
					$subtotal = floatval( $product["quantity"] ) * $price;
					$total += $subtotal;
				}

				$total += floatval( $products["shipping"]["price"] );
			}

			$cart_item['data']->adjust_price( $total );

			error_reporting( $err_level );
		}


		return $cart_item;
	}

	//When the item is being added to the cart.
	public function add_cart_item_data( $cart_item_meta, $product_id ) {
		if ( ! isset( $_POST['gform_old_submit'] ) ) {
			return $cart_item_meta;
		}

		if ( isset( $cart_item_meta['_gravity_form_data'] ) && isset( $cart_item_meta['_gravity_form_lead'] ) ) {
			return $cart_item_meta;
		}

		$gravity_form_data                    = wc_gfpa()->get_gravity_form_data( $product_id );
		$cart_item_meta['_gravity_form_data'] = $gravity_form_data;

		if ( $gravity_form_data && is_array( $gravity_form_data ) && isset( $gravity_form_data['id'] ) && intval( $gravity_form_data['id'] ) > 0 ) {

			$form_id   = $gravity_form_data['id'];
			$form_meta = RGFormsModel::get_form_meta( $form_id );
			$form_meta = gf_apply_filters( array( 'gform_pre_render', $form_id ), $form_meta );

			//Gravity forms generates errors and warnings.  To prevent these from conflicting with other things, we are going to disable warnings and errors.
			$err_level = error_reporting();
			error_reporting( 0 );

			//MUST disable notifications manually.
			add_filter( 'gform_disable_user_notification_' . $form_id, array( $this, 'disable_notifications' ), 10, 3 );
			add_filter( 'gform_disable_admin_notification_' . $form_id, array(
				$this,
				'disable_notifications'
			), 10, 3 );
			add_filter( 'gform_disable_notification_' . $form_id, array( $this, 'disable_notifications' ), 10, 3 );

			add_filter( "gform_confirmation_" . $form_id, array( $this, "disable_confirmation" ), 10, 4 );

			if ( empty( $form_meta ) ) {
				return $cart_item_meta;
			}

			GFFormDisplay::$submission[ $form_id ] = null;
			require_once( GFCommon::get_base_path() . "/form_display.php" );
			$_POST['gform_submit'] = $_POST['gform_old_submit'];
			GFFormDisplay::process_form( $form_id );
			$_POST['gform_old_submit'] = $_POST['gform_submit'];
			unset( $_POST['gform_submit'] );

			$lead                                 = GFFormDisplay::$submission[ $form_id ]['lead'];
			$cart_item_meta['_gravity_form_lead'] = array(
				'form_id'    => $form_id,
				'source_url' => $lead['source_url'],
				'ip'         => $lead['ip']
			);

			foreach ( $form_meta['fields'] as $field ) {
				if ( isset( $field['displayOnly'] ) && $field['displayOnly'] ) {
					continue;
				}

				$value = $this->get_lead_field_value( $lead, $field );


				$inputs = $field instanceof GF_Field ? $field->get_entry_inputs() : rgar( $field, 'inputs' );
				if ( is_array( $inputs ) ) {
					//making sure values submitted are sent in the value even if
					//there isn't an input associated with it
					$lead_field_keys = array_keys( $lead );
					natsort( $lead_field_keys );
					foreach ( $lead_field_keys as $input_id ) {
						if ( is_numeric( $input_id ) && absint( $input_id ) == absint( $field->id ) ) {
							$cart_item_meta['_gravity_form_lead'][ strval( $input_id ) ] = $value[ strval( $input_id ) ];
						}
					}
				} else {
					$cart_item_meta['_gravity_form_lead'][ strval( $field['id'] ) ] = $value;
				}
			}

			if ( apply_filters( 'woocommerce_gravityforms_delete_entries', true ) ) {
				$this->delete_entry( $lead );
			}

			error_reporting( $err_level );
		}

		return $cart_item_meta;
	}

	public function get_cart_item_from_session( $cart_item, $values ) {

		if ( isset( $values['_gravity_form_data'] ) ) {
			$cart_item['_gravity_form_data'] = $values['_gravity_form_data'];
		}

		if ( isset( $values['_gravity_form_lead'] ) ) {
			$cart_item['_gravity_form_lead'] = $values['_gravity_form_lead'];
		}

		if ( isset( $cart_item['_gravity_form_lead'] ) && isset( $cart_item['_gravity_form_data'] ) ) {
			$this->add_cart_item( $cart_item );
		}

		return $cart_item;
	}

	public function get_item_data( $other_data, $cart_item ) {
		if ( isset( $cart_item['_gravity_form_lead'] ) && isset( $cart_item['_gravity_form_data'] ) ) {
			//Gravity forms generates errors and warnings.  To prevent these from conflicting with other things, we are going to disable warnings and errors.
			$err_level = error_reporting();
			error_reporting( 0 );

			$gravity_form_data = $cart_item['_gravity_form_data'];
			$form_meta         = RGFormsModel::get_form_meta( $gravity_form_data['id'] );
			$form_meta         = gf_apply_filters( array( 'gform_pre_render', $gravity_form_data['id'] ), $form_meta );
			if ( ! empty( $form_meta ) ) {

				$lead = $cart_item['_gravity_form_lead'];

				//$lead['id'] = uniqid() . time() . rand();

				$products       = $this->get_product_fields( $form_meta, $lead );
				$valid_products = array();
				foreach ( $products['products'] as $id => $product ) {
					if ( $product['quantity'] ) {
						$valid_products[] = $id;
					}
				}

				foreach ( $form_meta['fields'] as $field ) {

					if ( ( isset( $field['inputType'] ) && $field['inputType'] == 'hiddenproduct' ) || ( isset( $field['displayOnly'] ) && $field['displayOnly'] ) || ( isset( $field->cssClass ) && strpos( $field->cssClass, 'wc-gforms-hide-from-email-and-admin' ) !== false ) ) {
						continue;
					}

					if ( $field['type'] == 'product' ) {
						if ( ! in_array( $field['id'], $valid_products ) ) {
							continue;
						}
					}

					$value   = $this->get_lead_field_value( $lead, $field );
					$arr_var = ( is_array( $value ) ) ? implode( '', $value ) : '-';

					if ( ! empty( $value ) && ! empty( $arr_var ) ) {
						$display_value     = GFCommon::get_lead_field_display( $field, $value, isset( $lead["currency"] ) ? $lead["currency"] : false, false );
						$price_adjustement = false;
						$display_value     = apply_filters( "gform_entry_field_value", $display_value, $field, $lead, $form_meta );

						$display_text = GFCommon::get_lead_field_display( $field, $value, isset( $lead["currency"] ) ? $lead["currency"] : false, apply_filters( 'woocommerce_gforms_use_label_as_value', true, $value, $field, $lead, $form_meta ) );
						$display_text = apply_filters( "woocommerce_gforms_field_display_text", $display_text, $display_value, $field, $lead, $form_meta );

						if ( $field['type'] == 'product' ) {
							$prefix        = '';
							$display_title = GFCommon::get_label( $field );
							$display_text  = str_replace( $display_title . ',', '', $display_text );;
							$hidden = false;
						} else {


							$display_title = GFCommon::get_label( $field );

							$prefix         = '';
							$hidden         = $field['type'] == 'hidden';
							$display_hidden = apply_filters( "woocommerce_gforms_field_is_hidden", $hidden, $display_value, $display_title, $field, $lead, $form_meta );
							if ( $display_hidden ) {
								$prefix = $hidden ? '_' : '';
							}

							if ( ! $display_hidden && ( isset( $field->cssClass ) && strpos( $field->cssClass, 'wc-gforms-hide-from-email' ) !== false ) ) {
								$prefix        = '_gf_email_hidden_';
								$display_title = str_replace( '_gf_email_hidden_', '', $display_title );
								$hidden        = true;
							}
						}

						$other_data[] = array(
							'name'    => $prefix . $display_title,
							'display' => $display_text,
							'value'   => $display_value,
							'hidden'  => $hidden
						);
					}
				}
			}
			error_reporting( $err_level );
		}

		return $other_data;
	}

	public function add_to_cart_validation( $valid, $product_id, $quantity ) {
		global $woocommerce;

		if ( ! $valid ) {
			return false;
		}

		// Check if we need a gravity form!
		$gravity_form_data = wc_gfpa()->get_gravity_form_data( $product_id );

		if ( is_array( $gravity_form_data ) && $gravity_form_data['id'] && empty( $_POST['gform_form_id'] ) ) {
			return false;
		}

		if ( isset( $_POST['gform_form_id'] ) && is_numeric( $_POST['gform_form_id'] ) ) {
			$form_id = $_POST['gform_form_id'];

			//Gravity forms generates errors and warnings.  To prevent these from conflicting with other things, we are going to disable warnings and errors.
			$err_level = error_reporting();
			error_reporting( 0 );

			//MUST disable notifications manually.
			add_filter( 'gform_disable_user_notification_' . $form_id, array( $this, 'disable_notifications' ), 10, 3 );
			add_filter( 'gform_disable_admin_notification_' . $form_id, array(
				$this,
				'disable_notifications'
			), 10, 3 );
			add_filter( 'gform_disable_notification_' . $form_id, array( $this, 'disable_notifications' ), 10, 3 );

			add_filter( "gform_confirmation_" . $form_id, array( $this, "disable_confirmation" ), 10, 4 );

			require_once( GFCommon::get_base_path() . "/form_display.php" );

			$_POST['gform_submit'] = $_POST['gform_old_submit'];

			GFFormDisplay::process_form( $form_id );
			$_POST['gform_old_submit'] = $_POST['gform_submit'];
			unset( $_POST['gform_submit'] );

			if ( ! GFFormDisplay::$submission[ $form_id ]['is_valid'] ) {
				return false;
			}

			if ( GFFormDisplay::$submission[ $form_id ]['page_number'] != 0 ) {
				return false;
			}

			$this->delete_entry( GFFormDisplay::$submission[ $form_id ]['lead'] );
			error_reporting( $err_level );
		}

		return $valid;
	}

	public function order_item_meta( $item_id, $cart_item ) {
		if ( function_exists( 'woocommerce_add_order_item_meta' ) ) {

			if ( isset( $cart_item['_gravity_form_lead'] ) && isset( $cart_item['_gravity_form_data'] ) ) {

				wc_add_order_item_meta( $item_id, '_gravity_forms_history', array(
						'_gravity_form_lead' => $cart_item['_gravity_form_lead'],
						'_gravity_form_data' => $cart_item['_gravity_form_data']
					)
				);

				//Gravity forms generates errors and warnings.  To prevent these from conflicting with other things, we are going to disable warnings and errors.
				$err_level = error_reporting();
				error_reporting( 0 );

				$gravity_form_data = $cart_item['_gravity_form_data'];
				$form_meta         = RGFormsModel::get_form_meta( $gravity_form_data['id'] );
				$form_meta         = gf_apply_filters( array(
					'gform_pre_render',
					$gravity_form_data['id']
				), $form_meta );
				if ( ! empty( $form_meta ) ) {
					$lead = $cart_item['_gravity_form_lead'];
					//We reset the lead id to disable caching of the gravity form value by gravity forms.
					//This cache causes issues with multipule cart line items each with their own form.
					$lead['id'] = uniqid() . time() . rand();

					$products       = $this->get_product_fields( $form_meta, $lead );
					$valid_products = array();
					foreach ( $products['products'] as $id => $product ) {
						if ( ! isset( $product['quantity'] ) ) {

						} elseif ( $product['quantity'] ) {
							$valid_products[] = $id;
						}
					}

					foreach ( $form_meta['fields'] as $field ) {

						if ( ( isset( $field['inputType'] ) && $field['inputType'] == 'hiddenproduct' ) || ( isset( $field['displayOnly'] ) && $field['displayOnly'] )
						     || ( isset( $field->cssClass ) && strpos( $field->cssClass, 'wc-gforms-hide-from-email-and-admin' ) ) !== false
						) {
							continue;
						}

						if ( $field['type'] == 'product' ) {
							if ( ! in_array( $field['id'], $valid_products ) ) {
								continue;
							}
						}

						$value = $this->get_lead_field_value( $lead, $field );
						$arr_var = ( is_array( $value ) ) ? implode( '', $value ) : '-';

						if ( ! empty( $value ) && ! empty( $arr_var ) ) {
							try {
								$strip_html = true;
								if ( $field['type'] == 'fileupload' && isset( $lead[ $field['id'] ] ) ) {
									$strip_html = false;
									$dv         = $lead[ $field['id'] ];
									$files      = json_decode( $dv );

									if ( empty( $files ) ) {
										$files = array( $dv );
									}

									$display_value = '';

									$sep = '';
									foreach ( $files as $file ) {
										$display_value .= $sep . '<a href="' . $file . '">' . $file . '</a>';
										$sep = ', ';
									}
								} else {

									if ( $field['type'] == 'address' ) {
										$display_value = implode( ', ', array_filter( $value ) );
									} else {
										$display_value = GFCommon::get_lead_field_display( $field, $value, isset( $lead["currency"] ) ? $lead["currency"] : false, apply_filters( 'woocommerce_gforms_use_label_as_value', true, $value, $field, $lead, $form_meta ) );
									}

									$price_adjustement = false;
									$display_value     = apply_filters( "gform_entry_field_value", $display_value, $field, $lead, $form_meta );


								}

								$display_title = GFCommon::get_label( $field );
								$display_title = apply_filters( "woocommerce_gforms_order_meta_title", $display_title, $field, $lead, $form_meta, $item_id, $cart_item );
								$display_value = apply_filters( "woocommerce_gforms_order_meta_value", $display_value, $field, $lead, $form_meta, $item_id, $cart_item );

								if ( apply_filters( 'woocommerce_gforms_strip_meta_html', $strip_html, $display_value, $field, $lead, $form_meta, $item_id, $cart_item ) ) {
									if ( strstr( $display_value, '<li>' ) ) {
										$display_value = str_replace( '<li>', '', $display_value );
										$display_value = explode( '</li>', $display_value );
										$display_value = trim( strip_tags( implode( ', ', $display_value ) ) );
										$display_value = trim( $display_value, ',' );
									}

									$display_value = strip_tags( wp_kses( $display_value, '' ) );
								}

								$display_text  = GFCommon::get_lead_field_display( $field, $value, isset( $lead["currency"] ) ? $lead["currency"] : false, false );
								$display_value = apply_filters( "woocommerce_gforms_field_display_text", $display_value, $display_text, $field, $lead, $form_meta );

								$prefix         = '';
								$hidden         = $field['type'] == 'hidden';
								$display_hidden = apply_filters( "woocommerce_gforms_field_is_hidden", $hidden, $display_value, $display_title, $field, $lead, $form_meta );
								if ( $display_hidden ) {
									$prefix = $hidden ? '_' : '';
								}

								if ( ! $display_hidden && ( isset( $field->cssClass ) && strpos( $field->cssClass, 'wc-gforms-hide-from-email' ) !== false ) ) {
									$prefix        = '_gf_email_hidden_';
									$display_title = str_replace( '_gf_email_hidden_', '', $display_title );
								}

								if ( $field['type'] == 'product' ) {
									$prefix        = '';
									$display_title = GFCommon::get_label( $field );
									$display_value = str_replace( $display_title . ',', '', $display_text );;
								}

								wc_add_order_item_meta( $item_id, $prefix . $display_title, $display_value );
							} catch ( Exception $e ) {

							}
						}
					}
					do_action( 'woocommerce_gforms_create_entry', $item_id, $gravity_form_data['id'], $lead );
				}
				error_reporting( $err_level );
			}
		}
	}

	public function on_get_order_again_cart_item_data( $data, $item, $order ) {

		//disable validation
		remove_filter( 'woocommerce_add_to_cart_validation', array( $this, 'add_to_cart_validation' ), 99, 3 );

		$history = isset( $item['gravity_forms_history'] ) ? maybe_unserialize( $item['gravity_forms_history'] ) : false;
		if ( ! $history ) {
			//Not sure why exactly WC strips out the leading _, let's check for it anyways
			isset( $item['_gravity_forms_history'] ) ? maybe_unserialize( $item['_gravity_forms_history'] ) : false;
		}

		if ( $history ) {
			$glead = isset( $history['_gravity_form_lead'] ) ? $history['_gravity_form_lead'] : false;
			$gdata = isset( $history['_gravity_form_data'] ) ? $history['_gravity_form_data'] : false;

			if ( $glead && $gdata ) {
				$data['_gravity_form_lead'] = $glead;
				$data['_gravity_form_data'] = $gdata;
			}
		}

		return $data;
	}

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
					$is_hidden = RGFormsModel::is_field_hidden( $form, $field, array(), $lead );
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

						$products[ $id ]["name"]     = $use_admin_label && ! rgempty( "adminLabel", $field ) ? $field["adminLabel"] : $lead_value[ $id . ".1" ];
						$products[ $id ]["price"]    = rgar( $lead_value, $id . ".2" );
						$products[ $id ]["quantity"] = $product_quantity;
					} else if ( ! empty( $lead_value ) ) {

						if ( empty( $quantity ) ) {
							continue;
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

	/**
	 * @param $lead
	 * @param $field GF_Field
	 *
	 * @return array|bool|mixed|string|void
	 */
	private function get_lead_field_value( $lead, $field ) {
		return RGFormsModel::get_lead_field_value( $lead, $field );
	}

	private function delete_entry( $entry ) {
		global $wpdb;
		$lead_id = $entry['id'];

		GFCommon::log_debug( __METHOD__ . "(): Deleting entry #{$lead_id}." );

		/**
		 * Fires before a lead is deleted
		 *
		 * @param $lead_id
		 *
		 * @deprecated
		 * @see gform_delete_entry
		 */
		do_action( 'gform_delete_lead', $lead_id );

		$lead_table        = GFFormsModel::get_lead_table_name();
		$lead_notes_table  = GFFormsModel::get_lead_notes_table_name();
		$lead_detail_table = GFFormsModel::get_lead_details_table_name();


		//Delete from lead details
		$sql = $wpdb->prepare( "DELETE FROM $lead_detail_table WHERE lead_id=%d", $lead_id );
		$wpdb->query( $sql );

		//Delete from lead notes
		$sql = $wpdb->prepare( "DELETE FROM $lead_notes_table WHERE lead_id=%d", $lead_id );
		$wpdb->query( $sql );

		//Delete from lead meta
		gform_delete_meta( $lead_id );

		//Delete from lead
		$sql = $wpdb->prepare( "DELETE FROM $lead_table WHERE id=%d", $lead_id );
		$wpdb->query( $sql );
	}

	/**
	 * Disable gravity forms notifications for the form.
	 *
	 * @param type $disabled
	 * @param type $form
	 * @param type $lead
	 *
	 * @return boolean
	 */
	public function disable_notifications( $disabled, $form, $lead ) {
		return true;
	}


	/**
	 * Disable any type of confirmations for the form.
	 *
	 * @param type $confirmation
	 * @param type $form
	 * @param type $lead
	 * @param type $ajax
	 *
	 * @return boolean
	 */
	public function disable_confirmation( $confirmation, $form, $lead, $ajax ) {
		if ( is_array( $confirmation ) && isset( $confirmation['redirect'] ) ) {
			return $confirmation;
		} else {
			return false;
		}
	}

}
