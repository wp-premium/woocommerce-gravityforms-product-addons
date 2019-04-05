<?php


if ( defined( 'DOING_AJAX' ) ) {
	include 'gravityforms-product-addons-ajax.php';
}

class WC_GFPA_Main {

	/**
	 *
	 * @var WC_GFPA_Main
	 */
	private static $instance;

	public static function register() {
		if ( self::$instance == null ) {
			self::$instance = new WC_GFPA_Main();
		}
	}

	/**
	 * Gets the single instance of the plugin.
	 *
	 * @return WC_GFPA_Main
	 */
	public static function instance() {
		if ( self::$instance == null ) {
			self::$instance = new WC_GFPA_Main();
		}

		return self::$instance;
	}

	public $assets_version = '3.3.1';

	public $gravity_products = array();

	public function __construct() {

		add_action( 'wp_head', array( $this, 'on_wp_head' ) );

		// Enqueue Gravity Forms Scripts
		add_action( 'wp_enqueue_scripts', array( $this, 'woocommerce_gravityform_enqueue_scripts' ), 99 );
		add_action( 'wc_quick_view_enqueue_scripts', array( $this, 'wc_quick_view_enqueue_scripts' ), 99 );
		//Bind the form
		add_action( 'woocommerce_before_add_to_cart_form', array(
			$this,
			'on_woocommerce_before_add_to_cart_form'
		) );


		add_action( 'woocommerce_bv_before_add_to_cart_button', array(
			$this,
			'woocommerce_gravityform_bulk_variations'
		) );


		// Filters for price display
		add_filter( 'woocommerce_grouped_price_html', array( $this, 'get_price_html' ), 999, 2 );


		add_filter( 'woocommerce_variation_price_html', array( $this, 'get_price_html' ), 999, 2 );
		add_filter( 'woocommerce_variation_sale_price_html', array( $this, 'get_price_html' ), 999, 2 );

		//add_filter( 'woocommerce_variable_price_html', array( $this, 'get_price_html' ), 10, 2 );
		//add_filter( 'woocommerce_variable_sale_price_html', array( $this, 'get_price_html' ), 10, 2 );
		//add_filter( 'woocommerce_variable_empty_price_html', array( $this, 'get_price_html' ), 10, 2 );
		//add_filter( 'woocommerce_variable_free_sale_price_html', array( $this, 'get_free_price_html' ), 10, 2 );
		//add_filter( 'woocommerce_variable_free_price_html', array( $this, 'get_free_price_html' ), 10, 2 );

		add_filter( 'woocommerce_sale_price_html', array( $this, 'get_price_html' ), 999, 2 );
		add_filter( 'woocommerce_price_html', array( $this, 'get_price_html' ), 999, 2 );
		add_filter( 'woocommerce_get_price_html', array( $this, 'get_price_html' ), 999, 2 );
		add_filter( 'woocommerce_empty_price_html', array( $this, 'get_price_html' ), 999, 2 );

		add_filter( 'woocommerce_free_sale_price_html', array( $this, 'get_free_price_html' ), 999, 2 );
		add_filter( 'woocommerce_free_price_html', array( $this, 'get_free_price_html' ), 999, 2 );

		//Modify Add to Cart Buttons
		add_action( 'init', array( $this, 'get_gravity_products' ) );

		//Register the admin controller.
		require 'admin/gravityforms-product-addons-admin.php';
		WC_GFPA_Admin_Controller::register();

		require 'inc/gravityforms-product-addons-order.php';
		WC_GFPA_Order::register();

		require 'inc/gravityforms-product-addons-bulk-variations.php';
		require 'inc/gravityforms-product-addons-cart.php';
		require 'inc/gravityforms-product-addons-cart-edit.php';
		require 'inc/gravityforms-product-addons-reorder.php';
		require 'inc/gravityforms-product-addons-entry.php';
		require 'inc/gravityforms-product-addons-stock.php';
		require 'inc/gravityforms-product-addons-display.php';
		require 'inc/gravityforms-product-addons-field-values.php';

		WC_GFPA_Cart::register();
		WC_GFPA_Cart_Edit::register();
		WC_GFPA_Reorder::register();
		WC_GFPA_Display::register();
		WC_GFPA_FieldValues::register();
		WC_GFPA_Stock::register();

		add_action( 'init', array( $this, 'on_init' ) );
	}

	public function on_init() {
		WC_GFPA_Entry::register();
	}

	public function on_woocommerce_before_add_to_cart_form() {
		$product = wc_get_product( get_the_ID() );

		if ( empty( $product ) ) {
			return;
		}

		if ( $product->is_type( 'variable' ) ) {
			// Addon display

			if ( apply_filters( 'woocommerce_gforms_use_template_back_compatibility', get_option( 'woocommerce_gforms_use_template_back_compatibility', false ) ) ) {
				add_action( 'woocommerce_before_add_to_cart_button', array(
					$this,
					'woocommerce_gravityform'
				), 10 );
			} else {

				$hook = apply_filters( 'woocommerce_gforms_form_output_hook', 'woocommerce_single_variation', $product );

				//Use the new 2.4+ hook
				add_action( $hook, array( $this, 'woocommerce_gravityform' ), 11 );
				add_action( 'wc_cvo_after_single_variation', array( $this, 'woocommerce_gravityform' ), 9 );
			}

		} else {
			$hook = apply_filters( 'woocommerce_gforms_form_output_hook', 'woocommerce_before_add_to_cart_button', $product );
			add_action( $hook, array( $this, 'woocommerce_gravityform' ), 10 );
		}
	}

	public function on_wp_head() {
		echo '<style type="text/css">';
		echo 'dd ul.bulleted {  float:none;clear:both; }';
		echo '</style>';
	}

	public function get_gravity_products() {
		global $wpdb;
		$metakey                = '_gravity_form_data';
		$this->gravity_products = $wpdb->get_col( $wpdb->prepare( "SELECT post_id FROM $wpdb->postmeta WHERE meta_key=%s", $metakey ) );
	}

	/* ----------------------------------------------------------------------------------- */
	/* Product Form Functions */
	/* ----------------------------------------------------------------------------------- */

	public function woocommerce_gravityform() {
		global $post, $woocommerce;

		include_once( 'gravityforms-product-addons-form.php' );

		$gravity_form_data = $this->get_gravity_form_data( $post->ID );

		if ( is_array( $gravity_form_data ) && $gravity_form_data['id'] ) {
			$product = wc_get_product( $post->ID );

			$product_form = new woocommerce_gravityforms_product_form( $gravity_form_data['id'], $post->ID );
			$product_form->get_form( $gravity_form_data );

			echo '<input type="hidden" name="add-to-cart" value="' . esc_attr( $product->get_id() ) . '" />';

		}
		echo '<div class="clear"></div>';
	}

	public function woocommerce_gravityform_bulk_variations() {
		global $post, $woocommerce;

		include_once( 'gravityforms-product-addons-form.php' );

		$gravity_form_data = $this->get_gravity_form_data( $post->ID, 'bulk' );
		if ( is_array( $gravity_form_data ) && $gravity_form_data['id'] ) {
			$product = wc_get_product( $post->ID );

			$form_id                                     = isset( $gravity_form_data['bulk_id'] ) ? $gravity_form_data['bulk_id'] : $gravity_form_data['id'];
			$product_form                                = new woocommerce_gravityforms_product_form( $form_id, $post->ID );
			$gravity_form_data['disable_label_subtotal'] = 'yes';
			$gravity_form_data['disable_label_total']    = 'yes';

			$product_form->get_form( $gravity_form_data );
		}
		echo '<div class="clear"></div>';
	}


	public function wc_quick_view_enqueue_scripts() {
		global $wp_query, $post;

		$enqueue  = false;
		$prices   = array();
		$suffixes = array();
		$use_ajax = array();

		$product_ids = array();

		if ( $post && preg_match_all( '/\[products +.*?((ids=.+?)|(name=.+?))\]/is', $post->post_content, $matches, PREG_SET_ORDER ) ) {
			$ajax = false;
			foreach ( $matches as $match ) {
				//parsing shortcode attributes
				$attr       = shortcode_parse_atts( $match[1] );
				$product_id = isset( $attr['ids'] ) ? $attr['ids'] : false;
				if ( ! empty( $product_id ) ) {
					$product_ids = array_merge( $product_ids, array_map( 'trim', explode( ',', $product_id ) ) );
				}
			}
		} elseif ( $wp_query && ! empty( $wp_query->posts ) ) {
			$product_ids = wp_list_pluck( $wp_query->posts, 'ID' );
		}

		if ( ! empty( $product_ids ) ) {
			foreach ( $product_ids as $post_id ) {
				$_product = wc_get_product( $post_id );
				if ( $_product ) {
					$enqueue           = true;
					$gravity_form_data = $this->get_gravity_form_data( $post_id );
					if ( $gravity_form_data && is_array( $gravity_form_data ) ) {
						gravity_form_enqueue_scripts( $gravity_form_data['id'], false );

						if ( isset( $gravity_form_data['bulk_id'] ) && ! empty( $gravity_form_data['bulk_id'] ) ) {
							gravity_form_enqueue_scripts( $gravity_form_data['bulk_id'], false );
						}

						$prices[ $_product->get_id() ]   = wc_get_price_to_display( $_product );
						$suffixes[ $_product->get_id() ] = $_product->get_price_suffix();
						$use_ajax[ $_product->get_id() ] = apply_filters( 'woocommerce_gforms_use_ajax', isset( $gravity_form_data['use_ajax'] ) ? ( $gravity_form_data['use_ajax'] == 'yes' ) : false );
						if ( $_product->has_child() ) {
							foreach ( $_product->get_children() as $variation_id ) {
								$variation               = wc_get_product( $variation_id );
								$prices[ $variation_id ] = wc_get_price_to_display( $variation );
							}
						}
					}
				}
			}
		}


		if ( $enqueue ) {
			$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
			wp_register_script( 'accounting', WC()->plugin_url() . '/assets/js/accounting/accounting' . $suffix . '.js', array( 'jquery' ), '0.4.2' );

			wp_enqueue_script( 'wc-gravityforms-product-addons', WC_GFPA_Main::plugin_url() . '/assets/js/gravityforms-product-addons.js', array(
				'jquery',
				'accounting'
			), true );

			// Accounting
			wp_localize_script( 'accounting', 'accounting_params', array(
				'mon_decimal_point' => wc_get_price_decimal_separator()
			) );

			$wc_gravityforms_params = array(
				'currency_format_num_decimals' => wc_get_price_decimals(),
				'currency_format_symbol'       => get_woocommerce_currency_symbol(),
				'currency_format_decimal_sep'  => esc_attr( wc_get_price_decimal_separator() ),
				'currency_format_thousand_sep' => esc_attr( wc_get_price_thousand_separator() ),
				'currency_format'              => esc_attr( str_replace( array( '%1$s', '%2$s' ), array(
					'%s',
					'%v'
				), get_woocommerce_price_format() ) ), // For accounting JS
				'prices'                       => $prices,
				'price_suffix'                 => $suffixes,
				'use_ajax'                     => $use_ajax,
			);

			wp_localize_script( 'wc-gravityforms-product-addons', 'wc_gravityforms_params', $wc_gravityforms_params );
		}

	}

	public function woocommerce_gravityform_enqueue_scripts() {
		global $post;


		if ( is_product() ) {
			$product           = wc_get_product( get_the_ID() );
			$gravity_form_data = $this->get_gravity_form_data( $post->ID );
			if ( $gravity_form_data && is_array( $gravity_form_data ) ) {
				wp_enqueue_style( 'wc-gravityforms-product-addons', WC_GFPA_Main::plugin_url() . '/assets/css/frontend.css', null );

				gravity_form_enqueue_scripts( $gravity_form_data['id'], false );
				if ( isset( $gravity_form_data['bulk_id'] ) && ! empty( $gravity_form_data['bulk_id'] ) ) {
					gravity_form_enqueue_scripts( $gravity_form_data['bulk_id'], false );
				}

				$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

				if ( WC_GFPA_Compatibility::is_wc_version_gte_2_5() ) {
					wp_register_script( 'accounting', WC()->plugin_url() . '/assets/js/accounting/accounting' . $suffix . '.js', array( 'jquery' ), '0.4.2' );
				} else {
					wp_register_script( 'accounting', WC()->plugin_url() . '/assets/js/admin/accounting' . $suffix . '.js', array( 'jquery' ), '0.4.2' );
				}

				wp_enqueue_script( 'wc-gravityforms-product-addons', WC_GFPA_Main::plugin_url() . '/assets/js/gravityforms-product-addons.js', array(
					'jquery',
					'accounting'
				), '3.2.4', true );

				$prices = array(
					$product->get_id() => wc_get_price_to_display( $product ),
				);

				if ( $product->has_child() ) {
					foreach ( $product->get_children() as $variation_id ) {
						$variation               = wc_get_product( $variation_id );
						$prices[ $variation_id ] = wc_get_price_to_display( $variation );
					}
				}

				// Accounting
				wp_localize_script( 'accounting', 'accounting_params', array(
					'mon_decimal_point' => wc_get_price_decimal_separator()
				) );

				$wc_gravityforms_params = array(
					'currency_format_num_decimals' => wc_get_price_decimals(),
					'currency_format_symbol'       => get_woocommerce_currency_symbol(),
					'currency_format_decimal_sep'  => esc_attr( wc_get_price_decimal_separator() ),
					'currency_format_thousand_sep' => esc_attr( wc_get_price_thousand_separator() ),
					'currency_format'              => esc_attr( str_replace( array( '%1$s', '%2$s' ), array(
						'%s',
						'%v'
					), get_woocommerce_price_format() ) ), // For accounting JS
					'prices'                       => $prices,
					'price_suffix'                 => array( $product->get_id() => $product->get_price_suffix() ),
					'use_ajax'                     => array( $product->get_id() => apply_filters( 'woocommerce_gforms_use_ajax', isset( $gravity_form_data['use_ajax'] ) ? ( $gravity_form_data['use_ajax'] == 'yes' ) : false ) )
				);

				wp_localize_script( 'wc-gravityforms-product-addons', 'wc_gravityforms_params', $wc_gravityforms_params );
			}
		} elseif ( is_object( $post ) && isset( $post->post_content ) && ! empty( $post->post_content ) ) {
			$enqueue = false;
			$forms   = array();
			$prices  = array();

			if ( preg_match_all( '/\[product_page[s]? +.*?((id=.+?)|(name=.+?))\]/is', $post->post_content, $matches, PREG_SET_ORDER ) ) {
				$ajax = false;
				foreach ( $matches as $match ) {
					//parsing shortcode attributes
					$attr       = shortcode_parse_atts( $match[1] );
					$product_id = isset( $attr['id'] ) ? $attr['id'] : false;

					if ( ! empty( $product_id ) ) {
						$gravity_form_data = $this->get_gravity_form_data( $product_id );

						if ( $gravity_form_data && is_array( $gravity_form_data ) ) {
							$enqueue = true;
							gravity_form_enqueue_scripts( $gravity_form_data['id'], false );

							$product                      = wc_get_product( $product_id );
							$prices[ $product->get_id() ] = wc_get_price_to_display( $product );

							if ( $product->has_child() ) {
								foreach ( $product->get_children() as $variation_id ) {
									$variation               = wc_get_product( $variation_id );
									$prices[ $variation_id ] = wc_get_price_to_display( $variation );
								}
							}
						}
					}
				}

				if ( $enqueue ) {

					wp_enqueue_style( 'wc-gravityforms-product-addons', WC_GFPA_Main::plugin_url() . '/assets/css/frontend.css', null );


					$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
					wp_register_script( 'accounting', WC()->plugin_url() . '/assets/js/accounting/accounting' . $suffix . '.js', array( 'jquery' ), '0.4.2' );

					wp_enqueue_script( 'wc-gravityforms-product-addons', WC_GFPA_Main::plugin_url() . '/assets/js/gravityforms-product-addons.js', array(
						'jquery',
						'accounting'
					), '3.2.5' );

					// Accounting
					wp_localize_script( 'accounting', 'accounting_params', array(
						'mon_decimal_point' => wc_get_price_decimal_separator()
					) );

					$wc_gravityforms_params = array(
						'currency_format_num_decimals' => wc_get_price_decimals(),
						'currency_format_symbol'       => get_woocommerce_currency_symbol(),
						'currency_format_decimal_sep'  => esc_attr( wc_get_price_decimal_separator() ),
						'currency_format_thousand_sep' => esc_attr( wc_get_price_thousand_separator() ),
						'currency_format'              => esc_attr( str_replace( array(
							'%1$s',
							'%2$s'
						), array( '%s', '%v' ), get_woocommerce_price_format() ) ), // For accounting JS
						'prices'                       => $prices,
						'price_suffix'                 => array( $product->get_id() => $product->get_price_suffix() ),
						'use_ajax'                     => array( $product->get_id() => apply_filters( 'woocommerce_gforms_use_ajax', isset( $gravity_form_data['use_ajax'] ) ? ( $gravity_form_data['use_ajax'] == 'yes' ) : false ) )

					);

					wp_localize_script( 'wc-gravityforms-product-addons', 'wc_gravityforms_params', $wc_gravityforms_params );
				}
			}
		}
	}

	/**
	 * @param            $html
	 * @param WC_Product $_product
	 *
	 * @return string
	 */
	public function get_price_html( $html, $_product ) {
		$gravity_form_data = $this->get_gravity_form_data( $_product->get_id() );
		if ( $gravity_form_data && is_array( $gravity_form_data ) ) {

			if ( isset( $gravity_form_data['disable_woocommerce_price'] ) && $gravity_form_data['disable_woocommerce_price'] == 'yes' ) {
				$html = '';
			}

			if ( isset( $gravity_form_data['price_before'] ) && ! empty( $gravity_form_data['price_before'] ) ) {
				$html = '<span class="woocommerce-price-before">' . $gravity_form_data['price_before'] . ' </span>' . $html;
			}

			if ( isset( $gravity_form_data['price_after'] ) && ! empty( $gravity_form_data['price_after'] ) ) {
				$html .= '<span class="woocommerce-price-after"> ' . $gravity_form_data['price_after'] . '</span>';
			}
		}

		return $html;
	}

	/**
	 * @param            $html
	 * @param WC_Product $_product
	 *
	 * @return string
	 */
	public function get_free_price_html( $html, $_product ) {
		$gravity_form_data = $this->get_gravity_form_data( $_product->get_id() );
		if ( $gravity_form_data && is_array( $gravity_form_data ) ) {

			if ( isset( $gravity_form_data['disable_woocommerce_price'] ) && $gravity_form_data['disable_woocommerce_price'] == 'yes' ) {
				$html = '';
			}

			if ( isset( $gravity_form_data['price_before'] ) && ! empty( $gravity_form_data['price_before'] ) ) {
				$html = '<span class="woocommerce-price-before">' . $gravity_form_data['price_before'] . ' </span>' . $html;
			}

			if ( isset( $gravity_form_data['price_after'] ) && ! empty( $gravity_form_data['price_after'] ) ) {
				$html .= '<span class="woocommerce-price-after"> ' . $gravity_form_data['price_after'] . '</span>';
			}
		}

		return $html;
	}

	public function get_formatted_price( $price ) {
		return wc_price( $price );
	}



	/** Helper functions ***************************************************** */

	/**
	 * Get the plugin url.
	 *
	 * @access public
	 * @return string
	 */
	public static function plugin_url() {
		return plugins_url( basename( plugin_dir_path( __FILE__ ) ), basename( __FILE__ ) );
	}

	public function get_gravity_form_data( $post_id, $context = 'single' ) {
		$product = wc_get_product( $post_id );
		$data    = false;
		if ( $product ) {
			$data = $product->get_meta( '_gravity_form_data' );
		}

		$data = apply_filters( 'woocommerce_gforms_get_product_form_data', $data, $post_id, $context );
		if ($data) {
			if ( $context == 'bulk' ) {
				$data['id'] = isset( $data['bulk_id'] ) ? $data['bulk_id'] : $data['id'];
			}
		}

		return $data;
	}

}

/**
 * The instance of the plugin.
 *
 * @return WC_GFPA_Main
 */
function wc_gfpa() {
	return WC_GFPA_Main::instance();
}

WC_GFPA_Main::register();



