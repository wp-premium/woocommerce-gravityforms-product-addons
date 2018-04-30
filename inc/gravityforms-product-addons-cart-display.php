<?php


class WC_GFPA_Cart_Display {

	private static $instance;

	public static function register() {
		if ( self::$instance == null ) {
			self::$instance = new WC_GFPA_Cart_Display;
		}
	}

	private $_other_data;

	private function __construct() {
		add_filter( 'woocommerce_get_item_data', array( $this, 'on_get_item_data' ), 10, 2 );
		add_action( 'woocommerce_before_cart', array( $this, 'on_woocommerce_before_cart' ) );

	}

	public function on_wc_get_template( $located, $template_name, $args ) {

		if ( $template_name == 'cart/cart-item-data.php' ) {
			remove_filter( 'wc_get_template', array( $this, 'on_wc_get_template' ), 9999, 3 );
			$located = wc_locate_template( $template_name, '', WC_GFPA_Main::plugin_path() . '/templates/' );
		}

		return $located;
	}

	public function on_woocommerce_after_template_part( $template_name, $template_path, $located, $args ) {

		if ( $template_name == 'cart/cart-item-data.php' ) {

			if ( ! empty( $this->_other_data['fields'] ) ) {
				wc_get_template( 'cart/entry-item-data.php', array( 'item_data' => $this->_other_data['fields'] ), WC_GFPA_Main::plugin_path() . '/templates/' );
			}

			if ( ! empty( $this->_other_data['products'] ) ) {
				foreach ( $this->_other_data['products'] as $item_data ) {
					wc_get_template( 'cart/entry-item-data.php', array( 'item_data' => $this->_other_data['fields'] ), WC_GFPA_Main::plugin_path() . '/templates/' );
				}
			}
		}

	}

	public function on_woocommerce_before_cart() {
		require_once( GFCommon::get_base_path() . '/entry_detail.php' );
	}

	public function on_get_item_data( $other_data, $cart_item ) {
		if ( isset( $cart_item['_gravity_form_lead'] ) && isset( $cart_item['_gravity_form_data'] ) ) {
			$this->_other_data = array(
				'fields'   => array(),
				'products' => array()
			);

			$display_empty_fields = false;
			$gravity_form_data    = $cart_item['_gravity_form_data'];
			$form                 = RGFormsModel::get_form_meta( $gravity_form_data['id'] );
			$lead                 = $cart_item['_gravity_form_lead'];

			$count              = 0;
			$field_count        = sizeof( $form['fields'] );
			$has_product_fields = false;
			foreach ( $form['fields'] as $field ) {

				$content = $value = '';

				switch ( $field->get_input_type() ) {
					case 'section' :
					case 'captcha':
					case 'html':
					case 'password':
					case 'page':
						// Ignore captcha, html, password, page field.
						break;

					default :
						// Ignore product fields as they will be grouped together at the end of the grid.
						if ( GFCommon::is_product_field( $field->type ) ) {
							$has_product_fields = true;
							continue;
						}


						$this->_other_data['fields'][] = $this->get_form_field_data( $form, $lead, $field );

						break;
				}


			}


			$products = array();
			if ( $has_product_fields ) {

				$products = $this->get_product_fields( $gravity_form_data, $form, $lead );
				if ( ! empty( $products['products'] ) ) {

					$product_count = 0;
					$total         = 0;
					foreach ( $products['products'] as $product_id => $product ) {

						$this->_other_data['products'][ $product_id ] = array();

						$product_type = $product['field']->get_input_type();

						if ( $product_type != 'hiddenproduct' ) {
							$this->_other_data['products'][ $product_id ]['product'] = array(
								'key'     => $product['name'],
								'name'    => $product['name'],
								'display' => ' ',
								'value'   => $product['price'] * $product['quantity'],
								'hidden'  => $product_type == 'hiddenproduct',
								'product' => $product
							);
						}

						$has_options  = false;
						$option_total = 0;
						if ( is_array( rgar( $product, 'options' ) ) ) {
							$has_options = true;
							foreach ( $product['options'] as $option ) {

								$option_total += GFCommon::to_number( $option['price'] );

								$this->_other_data['products'][ $product_id ][] = array(
									'key'     => $option['field_label'],
									'name'    => $option['field_label'],
									'display' => $option['option_name'],
									'value'   => $option['price'],
									'hidden'  => false
								);

							}
						}

						$price = GFCommon::to_number( $product['price'] ) + GFCommon::to_number( $option_total );
						if ( $product['calculation_type'] == 'one-time-fee' ) {
							$subtotal                                              = $price;
							$this->_other_data['products'][ $product_id ]['total'] = array(
								'key'     => $product['total_label'],
								'name'    => $product['total_label'],
								'display' => GFCommon::to_money( $subtotal ) . ' ( x ' . '1' . ' )',
								'value'   => GFCommon::to_money( $subtotal ) . ' ( x ' . '1' . ' )',
								'hidden'  => false
							);
						} else {
							$subtotal                                              = floatval( $product['quantity'] ) * $price;
							$show                                                  = (bool) ( $product_type != 'hiddenproduct' | $has_options );
							$show                                                  = apply_filters( 'woocommerce_gforms_show_product_total', $show, $product );
							$this->_other_data['products'][ $product_id ]['total'] = array(
								'key'     => $product['total_label'],
								'name'    => $product['total_label'],
								'display' => GFCommon::to_money( $subtotal ) . ' ( x ' . $cart_item['quantity'] . ' )',
								'value'   => GFCommon::to_money( $subtotal ) . ' ( x ' . $cart_item['quantity'] . ' )',
								'hidden'  => !$show
							);

						}

						$total += $subtotal;
						$product_count ++;
					}
				}

			}


			if ( $total ) {

				$price                       = isset( $gravity_form_data['regular_product_price'] ) ? $gravity_form_data['regular_product_price'] : false;
				$this->_other_data['totals'] = array();
				if ( $price !== false ) {
					if ( $gravity_form_data['disable_label_subtotal'] != 'yes' ) {
						$this->_other_data['totals']['subtotal'] = array(
							'name'  => $gravity_form_data['label_subtotal'],
							'value' => wc_price( $price )
						);
					}

					if ( $gravity_form_data['disable_label_subtotal'] != 'yes' ) {
						$this->_other_data['totals']['options'] = array(
							'name'  => $gravity_form_data['label_options'],
							'value' => wc_price( $total )
						);
					}

					if ( $gravity_form_data['disable_label_total'] != 'yes' ) {
						$this->_other_data['totals']['options'] = array(
							'name'  => $gravity_form_data['label_total'],
							'value' => wc_price( $total + $price )
						);
					}
				}

			}

			if ( ! empty( $this->_other_data['fields'] ) || ! empty( $this->_other_data['products'] ) ) {
				add_filter( 'wc_get_template', array( $this, 'on_wc_get_template' ), 9999, 3 );
				$other_data[] = array(
					'key'   => 'gf-entry-details',
					'value' => $this->_other_data
				);
			}


		}

		return $other_data;
	}


	protected
	function get_product_fields(
		$gravity_form_data, $form, $lead, $use_choice_text = false, $use_admin_label = false
	) {

		$gform    = new WC_GFPA_GForm( $form, $lead );
		$products = $gform->get_product_fields( $gravity_form_data );

		return $products;

	}


	protected
	function get_form_field_data(
		$form_meta, $lead, $field
	) {
		$value   = RGFormsModel::get_lead_field_value( $lead, $field );
		$arr_var = ( is_array( $value ) ) ? implode( '', $value ) : '-';

		if ( ! empty( $value ) && ! empty( $arr_var ) ) {
			$display_value = GFCommon::get_lead_field_display( $field, $value, isset( $lead["currency"] ) ? $lead["currency"] : false, false );

			$display_value = apply_filters( "gform_entry_field_value", $display_value, $field, $lead, $form_meta );

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

			$cart_item_data = apply_filters( "woocommerce_gforms_get_item_data", array(
				'key'     => $prefix . $display_title,
				'name'    => $prefix . $display_title,
				'display' => $display_text,
				'value'   => $display_value,
				'hidden'  => $hidden
			), $field, $lead, $form_meta );

			return $cart_item_data;
		} else {
			return null;
		}
	}

}