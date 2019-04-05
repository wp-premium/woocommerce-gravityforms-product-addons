<?php

class WC_GFPA_Cart {

	private static $instance;

	public static function register() {
		if ( self::$instance == null ) {
			self::$instance = new WC_GFPA_Cart;
		}
	}

	private $removed_captcha = false;

	//Keep track of if we have already added the gravity form data to an order item.
	private $meta_added = array();

	private function __construct() {
		// Filters for cart actions

		add_filter( 'woocommerce_add_cart_item_data', array( $this, 'add_cart_item_data' ), 10, 3 );
		add_filter( 'woocommerce_get_cart_item_from_session', array( $this, 'get_cart_item_from_session' ), 10, 2 );
		add_filter( 'woocommerce_get_item_data', array( $this, 'get_item_data' ), 10, 2 );
		add_filter( 'woocommerce_add_cart_item', array( $this, 'add_cart_item' ), 10, 1 );

		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'order_item_meta' ), 10, 3 );
		add_filter( 'woocommerce_add_to_cart_validation', array( $this, 'add_to_cart_validation' ), 99, 3 );

		//Order Again
		add_filter( 'woocommerce_order_again_cart_item_data', array(
			$this,
			'on_get_order_again_cart_item_data'
		), 10, 3 );

	}

	//Helper function, used when an item is added to the cart as well as when an item is restored from session.
	public function add_cart_item( $cart_item, $restoring_session = false ) {
		global $woocommerce;

		// Adjust price if required based on the gravity form data
		if ( isset( $cart_item['_gravity_form_lead'] ) && isset( $cart_item['_gravity_form_data'] ) ) {
			//Gravity forms generates errors and warnings.  To prevent these from conflicting with other things, we are going to disable warnings and errors.
			$err_level = error_reporting();
			error_reporting( 0 );

			$gravity_form_data = $cart_item['_gravity_form_data'];
			$form_meta         = RGFormsModel::get_form_meta( $gravity_form_data['id'] );

			if ( empty( $form_meta ) ) {
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
					$total    += $subtotal;
				}

				$total += floatval( $products["shipping"]["price"] );
			}

			$total = apply_filters('woocommerce_gforms_get_cart_item_total', $total, $cart_item);

			$price = $cart_item['data']->get_price( 'edit' );
			$price += (float) $total;
			$cart_item['data']->set_price( $price );
			$cart_item['_gform_total'] = $total;
			error_reporting( $err_level );

			if ( $restoring_session === false ) {
				if ( isset( $gravity_form_data['enable_cart_quantity_management'] ) && $gravity_form_data['enable_cart_quantity_management'] == 'yes' ) {

					$field = isset( $gravity_form_data['cart_quantity_field'] ) ? $gravity_form_data['cart_quantity_field'] : false;

					if ( $field ) {
						if ( isset( $products['products'][ $field ] ) ) {
							$quantity = isset( $products['products'][ $field ] ) ? $products['products'][ $field ]['quantity'] : $cart_item['quantity'];
						} else {
							$quantity = isset( $lead[ $field ] ) ? $lead[ $field ] : $cart_item['quantity'];
						}

						$cart_item['quantity'] = $quantity;
					}

				}
			}

		}


		return $cart_item;
	}

	//When the item is being added to the cart.
	public function add_cart_item_data( $cart_item_meta, $product_id, $variation_id = null ) {
		if ( ! isset( $_POST['gform_old_submit'] ) ) {
			return $cart_item_meta;
		}

		if ( isset( $cart_item_meta['_gravity_form_data'] ) && isset( $cart_item_meta['_gravity_form_lead'] ) ) {
			return $cart_item_meta;
		}

		$context                              = ( isset( $_POST['add-variations-to-cart'] ) && $_POST['add-variations-to-cart'] ) ? 'bulk' : 'single';
		$gravity_form_data                    = wc_gfpa()->get_gravity_form_data( $product_id, $context );
		$cart_item_meta['_gravity_form_data'] = $gravity_form_data;

		if ( $gravity_form_data && is_array( $gravity_form_data ) && isset( $gravity_form_data['id'] ) && intval( $gravity_form_data['id'] ) > 0 ) {

			if ( ! class_exists( 'GFFormDisplay' ) ) {
				require_once( GFCommon::get_base_path() . "/form_display.php" );
			}

			$form_id   = $gravity_form_data['id'];
			$form_meta = RGFormsModel::get_form_meta( $form_id );
			$form_meta = gf_apply_filters( array( 'gform_pre_render', $form_id ), $form_meta );

			//Gravity forms generates errors and warnings.  To prevent these from conflicting with other things, we are going to disable warnings and errors.
			$err_level = error_reporting();
			error_reporting( 0 );

			//MUST disable notifications manually.
			add_filter( 'gform_disable_notification', array( $this, 'disable_notifications' ), 999, 3 );

			add_filter( 'gform_disable_user_notification', array( $this, 'disable_notifications', 999, 3 ) );
			add_filter( 'gform_disable_user_notification_' . $form_id, array(
				$this,
				'disable_notifications'
			), 999, 3 );

			add_filter( 'gform_disable_admin_notification' . $form_id, array(
				$this,
				'disable_notifications'
			), 10, 3 );


			add_filter( 'gform_disable_admin_notification_' . $form_id, array(
				$this,
				'disable_notifications'
			), 10, 3 );


			add_filter( 'gform_disable_notification_' . $form_id, array( $this, 'disable_notifications' ), 999, 3 );

			add_filter( "gform_confirmation_" . $form_id, array( $this, "disable_confirmation" ), 999, 4 );

			if ( empty( $form_meta ) ) {
				return $cart_item_meta;
			}

			$delete_cart_entries = isset( $gravity_form_data['keep_cart_entries'] ) && $gravity_form_data['keep_cart_entries'] == 'yes' ? false : true;

			if ( apply_filters( 'woocommerce_gravityforms_delete_entries', $delete_cart_entries ) ) {
				//We are going to delete this entry, so let's remove all after submission hooks.
				//Remove all post_submission hooks so data does not get sent to feeds such as Zapier
				$this->disable_gform_after_submission_hooks( $form_id );
			} else {
				//Entry will not be deleted, so add the hooks back in so they will be fired when the form is processed by GForms
				$this->enable_gform_after_submission_hooks( $form_id );
			}

			GFCommon::log_debug( __METHOD__ . "(): [woocommerce-gravityforms-product-addons] Processing Add to Cart #{$form_id}." );
			GFFormDisplay::$submission = array();
			require_once( GFCommon::get_base_path() . "/form_display.php" );
			$_POST['gform_submit'] = $_POST['gform_old_submit'];


			add_filter( 'gform_pre_process_' . $form_id, array( $this, 'on_gform_pre_process' ) );
			GFFormDisplay::process_form( $form_id );
			remove_filter( 'gform_pre_process_' . $form_id, array( $this, 'on_gform_pre_process' ) );


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

					foreach ( $inputs as $input ) {
						if ( is_numeric( $input_id ) && absint( $input_id ) == absint( $field->id ) ) {
							$cart_item_meta['_gravity_form_lead'][ strval( $input['id'] ) ] = apply_filters( 'wcgf_gform_input_value', $cart_item_meta['_gravity_form_lead'][ strval( $input_id ) ], $product_id, $variation_id, $field, $input );
						}
					}

				} else {
					$cart_item_meta['_gravity_form_lead'][ strval( $field['id'] ) ] = apply_filters( 'wcgf_gform_field_value', $value, $product_id, $variation_id, $field );
				}
			}

			if ( apply_filters( 'woocommerce_gravityforms_delete_entries', $delete_cart_entries ) ) {
				GFCommon::log_debug( __METHOD__ . "(): [woocommerce-gravityforms-product-addons] Add to Cart - Deleting Entry #{$lead['id']}." );
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
			$this->add_cart_item( $cart_item, true );
		}

		return $cart_item;
	}

	public function get_item_data( $other_data, $cart_item ) {
		if ( isset( $cart_item['_gravity_form_lead'] ) && isset( $cart_item['_gravity_form_data'] ) ) {
			//Gravity forms generates errors and warnings.  To prevent these from conflicting with other things, we are going to disable warnings and errors.
			$err_level = error_reporting();
			error_reporting( 0 );

			$gravity_form_data = $cart_item['_gravity_form_data'];

			//Ensure GFFormDisplay exists in case developers use hooks that expect it to.
			if ( ! class_exists( 'GFFormDisplay' ) ) {
				require_once( GFCommon::get_base_path() . "/form_display.php" );
			}

			$form_meta = RGFormsModel::get_form_meta( $gravity_form_data['id'] );
			$form_meta = gf_apply_filters( array( 'gform_pre_render', $gravity_form_data['id'] ), $form_meta );
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

					if ( $value === '0' || ( ! empty( $value ) && ! empty( $arr_var ) ) ) {
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
							$hidden         = $field['type'] == 'hidden' || ( isset( $field['visibility'] ) && $field['visibility'] == 'hidden' );
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

						$cart_item_data = apply_filters( "woocommerce_gforms_get_item_data", array(
							'name'    => $prefix . $display_title,
							'display' => $display_text,
							'value'   => $display_value,
							'hidden'  => $hidden
						), $field, $lead, $form_meta );


						$other_data[] = $cart_item_data;
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
		$context           = ( isset( $_POST['add-variations-to-cart'] ) && $_POST['add-variations-to-cart'] ) ? 'bulk' : 'single';
		$gravity_form_data = wc_gfpa()->get_gravity_form_data( $product_id, $context );

		if ( is_array( $gravity_form_data ) && $gravity_form_data['id'] && empty( $_POST['gform_form_id'] ) ) {
			return false;
		}

		if ( $gravity_form_data && is_array( $gravity_form_data ) && isset( $gravity_form_data['id'] ) && intval( $gravity_form_data['id'] ) > 0 && isset( $_POST['gform_form_id'] ) && is_numeric( $_POST['gform_form_id'] ) ) {

			if ( ! class_exists( 'GFFormDisplay' ) ) {
				require_once( GFCommon::get_base_path() . "/form_display.php" );
			}

			$form_id = $_POST['gform_form_id'];

			//Gravity forms generates errors and warnings.  To prevent these from conflicting with other things, we are going to disable warnings and errors.
			$err_level = error_reporting();
			error_reporting( 0 );

			//MUST disable notifications manually.

			add_filter( 'gform_disable_notification', array( $this, 'disable_notifications' ), 999, 3 );

			add_filter( 'gform_disable_user_notification', array( $this, 'disable_notifications', 999, 3 ) );
			add_filter( 'gform_disable_user_notification_' . $form_id, array(
				$this,
				'disable_notifications'
			), 999, 3 );

			add_filter( 'gform_disable_admin_notification' . $form_id, array(
				$this,
				'disable_notifications'
			), 10, 3 );


			add_filter( 'gform_disable_admin_notification_' . $form_id, array(
				$this,
				'disable_notifications'
			), 10, 3 );


			add_filter( 'gform_disable_notification_' . $form_id, array( $this, 'disable_notifications' ), 999, 3 );

			add_filter( "gform_confirmation_" . $form_id, array( $this, "disable_confirmation" ), 999, 4 );

			//Remove all post_submission hooks so data does not get sent to feeds such as Zapier
			$this->disable_gform_after_submission_hooks( $form_id );

			GFFormDisplay::$submission = array();

			require_once( GFCommon::get_base_path() . "/form_display.php" );

			$_POST['gform_submit'] = $_POST['gform_old_submit'];

			GFCommon::log_debug( __METHOD__ . "(): [woocommerce-gravityforms-product-addons] Processing Add to Cart Validation #{$form_id}." );
			GFFormDisplay::process_form( $form_id );
			$_POST['gform_old_submit'] = $_POST['gform_submit'];
			unset( $_POST['gform_submit'] );

			if ( ! GFFormDisplay::$submission[ $form_id ]['is_valid'] ) {
				return false;
			}

			if ( GFFormDisplay::$submission[ $form_id ]['page_number'] != 0 ) {
				return false;
			}
			$lead = GFFormDisplay::$submission[ $form_id ]['lead'];

			GFCommon::log_debug( __METHOD__ . "(): [woocommerce-gravityforms-product-addons] Add to Cart Validation - Deleting Entry #{$lead['id']}." );

			$this->delete_entry( GFFormDisplay::$submission[ $form_id ]['lead'] );
			error_reporting( $err_level );
		}

		return $valid;
	}

	/**
	 * @param $item \WC_Order_Item
	 * @param $cart_item_key
	 * @param $cart_item
	 */
	public function order_item_meta( $item, $cart_item_key, $cart_item ) {
		if ( function_exists( 'woocommerce_add_order_item_meta' ) ) {

			$cart_item_debug = print_r( $cart_item, true );
			GFCommon::log_debug( "Gravity Forms Add Order Item Meta: (#{$cart_item_key}) - Data (#{$cart_item_debug})" );

			if ( isset( $cart_item['_gravity_form_lead'] ) && isset( $cart_item['_gravity_form_data'] ) ) {

				$item_id = $item->get_id();

				$history = $item->get_meta('_gravity_forms_history');
				if ($history) {
					GFCommon::log_debug( "Gravity Forms Meta Data Already Added: Order Item ID(#{$item_id})" );
					GFCommon::log_debug( "Gravity Forms Skipping: Order Item ID(#{$item_id})" );
					return;
				}

				GFCommon::log_debug( "Gravity Forms Add Order Item Meta: Order Item ID(#{$item_id})" );

				$item->add_meta_data( '_gravity_forms_history', array(
						'_gravity_form_lead'          => $cart_item['_gravity_form_lead'],
						'_gravity_form_data'          => $cart_item['_gravity_form_data'],
						'_gravity_form_cart_item_key' => $cart_item_key
					)
				);

				//Gravity forms generates errors and warnings.  To prevent these from conflicting with other things, we are going to disable warnings and errors.
				$err_level = error_reporting();
				error_reporting( 0 );

				$gravity_form_data = $cart_item['_gravity_form_data'];

				//Ensure GFFormDisplay exists in case developers use hooks that expect it to.
				if ( ! class_exists( 'GFFormDisplay' ) ) {
					require_once( GFCommon::get_base_path() . "/form_display.php" );
				}

				$form_meta = RGFormsModel::get_form_meta( $gravity_form_data['id'] );
				$form_meta = gf_apply_filters( array(
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
							$field_debug_string = print_r( $field, true );
							GFCommon::log_debug( "Gravity Forms Add Order Item Meta: Skipping (#{$field_debug_string})" );
							continue;
						}

						if ( $field['type'] == 'product' ) {
							if ( ! in_array( $field['id'], $valid_products ) ) {
								GFCommon::log_debug( "Gravity Forms Add Order Item Meta: Skipping Non-Valid Product(#{$field['id']})" );
								continue;
							}
						}

						$value   = $this->get_lead_field_value( $lead, $field );
						$arr_var = ( is_array( $value ) ) ? implode( '', $value ) : '-';

						if ( $value === '0' || ( ! empty( $value ) && ! empty( $arr_var ) ) ) {
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
										$name = basename( $file );
										if ( empty( $name ) ) {
											$name = $file;
										}
										$display_value .= $sep . '<a href="' . $file . '">' . $name . '</a>';
										$sep           = ', ';
									}
								} else {

									if ( $field['type'] == 'address' ) {
										$display_value = implode( ', ', array_filter( $value ) );
									} else {
										$display_value = GFCommon::get_lead_field_display( $field, $value, isset( $lead["currency"] ) ? $lead["currency"] : false, apply_filters( 'woocommerce_gforms_use_label_as_value', true, $value, $field, $lead, $form_meta ) );
									}

									$price_adjustement = false;
									$display_value     = apply_filters( "gform_entry_field_value", $display_value, $field, $lead, $form_meta );

									if ( strpos( $display_value, '<img' ) !== false ) {
										$strip_html = false;
									}
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

								if ( empty( $prefix ) && empty( $display_title ) ) {
									$display_title = $field['id'] . ' -';
								}
								$value_debug_string = $prefix . $display_title . ' - Value:' . $display_value;
								GFCommon::log_debug( "Gravity Forms Add Order Item Meta:(#{$value_debug_string})" );

								$order_item_meta = array(
									'name'  => $prefix . $display_title,
									'value' => $display_value
								);

								$order_item_meta = apply_filters( "woocommerce_gforms_order_item_meta", $order_item_meta, $field, $lead, $form_meta, $item_id, $cart_item );

								$item->add_meta_data( $order_item_meta['name'], $order_item_meta['value'] );
							} catch ( Exception $e ) {
								$e_debug_string = $e->getMessage();
								GFCommon::log_debug( "Gravity Forms Add Order Item Meta Exception:(#{$e_debug_string})" );
							}
						}
					}
				}
				error_reporting( $err_level );
			}
		}
	}

	public function on_get_order_again_cart_item_data( $data, $item, $order ) {
		//disable validation
		remove_filter( 'woocommerce_add_to_cart_validation', array( $this, 'add_to_cart_validation' ), 99, 3 );

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

	//Use a custom delete function so we don't delete files that are uploaded.
	private function delete_entry( $entry ) {
		global $wpdb;

		if ( version_compare( GFFormsModel::get_database_version(), '2.3-dev-1', '<' ) ) {
			$this->delete_entry_legacy( $entry );

			return;
		}

		$entry_id = $entry['id'];
		GFCommon::log_debug( __METHOD__ . "(): [woocommerce-gravityforms-product-addons] Deleting entry #{$entry_id}." );

		/**
		 * Fires before a lead is deleted
		 *
		 * @param $lead_id
		 *
		 * @deprecated
		 * @see gform_delete_entry
		 */
		do_action( 'gform_delete_lead', $entry_id );

		$entry_table           = GFFormsModel::get_entry_table_name();
		$entry_notes_table     = GFFormsModel::get_entry_notes_table_name();
		$entry_meta_table_name = GFFormsModel::get_entry_meta_table_name();

		// Delete from entry meta
		$sql = $wpdb->prepare( "DELETE FROM $entry_meta_table_name WHERE entry_id=%d", $entry_id );
		$wpdb->query( $sql );

		// Delete from lead notes
		$sql = $wpdb->prepare( "DELETE FROM $entry_notes_table WHERE entry_id=%d", $entry_id );
		$wpdb->query( $sql );


		// Delete from entry table
		$sql = $wpdb->prepare( "DELETE FROM $entry_table WHERE id=%d", $entry_id );
		$wpdb->query( $sql );
	}

	private function delete_entry_legacy( $entry ) {
		global $wpdb;

		$lead_id = $entry['id'];

		GFCommon::log_debug( __METHOD__ . "(): [woocommerce-gravityforms-product-addons] Deleting legacy entry #{$lead_id}." );

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


	private function disable_gform_after_submission_hooks( $form_id ) {
		global $wp_filter, $wp_actions;
		$tag = 'gform_after_submission';
		if ( ! isset( $this->_wp_filters[ $tag ] ) ) {
			if ( isset( $wp_filter[ $tag ] ) ) {
				$this->_wp_filters[ $tag ] = $wp_filter[ $tag ];
				unset( $wp_filter[ $tag ] );
			}
		}
		$tag = "gform_after_submission_{$form_id}";
		if ( ! isset( $this->_wp_filters[ $tag ] ) ) {
			if ( isset( $wp_filter[ $tag ] ) ) {
				$this->_wp_filters[ $tag ] = $wp_filter[ $tag ];
				unset( $wp_filter[ $tag ] );
			}
		}
		$tag = 'gform_entry_post_save';
		if ( ! isset( $this->_wp_filters[ $tag ] ) ) {
			if ( isset( $wp_filter[ $tag ] ) ) {
				$this->_wp_filters[ $tag ] = $wp_filter[ $tag ];
				unset( $wp_filter[ $tag ] );
			}
		}
		$tag = "gform_entry_post_save_{$form_id}";
		if ( ! isset( $this->_wp_filters[ $tag ] ) ) {
			if ( isset( $wp_filter[ $tag ] ) ) {
				$this->_wp_filters[ $tag ] = $wp_filter[ $tag ];
				unset( $wp_filter[ $tag ] );
			}
		}

	}

	private function enable_gform_after_submission_hooks( $form_id ) {
		global $wp_filter;
		$tag = 'gform_after_submission';
		if ( isset( $this->_wp_filters[ $tag ] ) ) {
			$wp_filter[ $tag ] = $this->_wp_filters[ $tag ];
		}
		$tag = "gform_after_submission_{$form_id}";
		if ( isset( $this->_wp_filters[ $tag ] ) ) {
			$wp_filter[ $tag ] = $this->_wp_filters[ $tag ];
		}
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


	public function on_gform_pre_process( $form ) {

		$captcha_id = null;
		if ( isset( $form['fields'] ) ) {
			foreach ( $form['fields'] as $index => $field ) {
				if ( isset( $field['type'] ) && $field['type'] == 'captcha' ) {
					$captcha_id = $index;

					$this->removed_captcha = array(
						'index' => $index,
						'field' => $field
					);

				}
			}
		}

		if ( $captcha_id !== null ) {
			unset( $form['fields'][ $captcha_id ] );
		}

		return $form;
	}

}
