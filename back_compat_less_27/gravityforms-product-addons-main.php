<?php

if ( is_woocommerce_active() ) {

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
		 * @return WC_GFPA_Main
		 */
		public static function instance() {
			if ( self::$instance == null ) {
				self::$instance = new WC_GFPA_Main();
			}

			return self::$instance;
		}

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


			// Filters for price display
			add_filter( 'woocommerce_grouped_price_html', array( $this, 'get_price_html' ), 10, 2 );


			add_filter( 'woocommerce_variation_price_html', array( $this, 'get_price_html' ), 10, 2 );
			add_filter( 'woocommerce_variation_sale_price_html', array( $this, 'get_price_html' ), 10, 2 );

			add_filter( 'woocommerce_variable_price_html', array( $this, 'get_price_html' ), 10, 2 );
			add_filter( 'woocommerce_variable_sale_price_html', array( $this, 'get_price_html' ), 10, 2 );
			add_filter( 'woocommerce_variable_empty_price_html', array( $this, 'get_price_html' ), 10, 2 );
			add_filter( 'woocommerce_variable_free_sale_price_html', array( $this, 'get_free_price_html' ), 10, 2 );
			add_filter( 'woocommerce_variable_free_price_html', array( $this, 'get_free_price_html' ), 10, 2 );

			add_filter( 'woocommerce_sale_price_html', array( $this, 'get_price_html' ), 10, 2 );
			add_filter( 'woocommerce_price_html', array( $this, 'get_price_html' ), 10, 2 );
			add_filter( 'woocommerce_empty_price_html', array( $this, 'get_price_html' ), 10, 2 );

			add_filter( 'woocommerce_free_sale_price_html', array( $this, 'get_free_price_html' ), 10, 2 );
			add_filter( 'woocommerce_free_price_html', array( $this, 'get_free_price_html' ), 10, 2 );

			//Modify Add to Cart Buttons
			add_action( 'init', array( $this, 'get_gravity_products' ) );

			//Register the admin controller. 
			require 'admin/gravityforms-product-addons-admin.php';
			WC_GFPA_Admin_Controller::register();

			require 'inc/gravityforms-product-addons-order.php';
			WC_GFPA_Order::register();


			require 'inc/gravityforms-product-addons-cart.php';
			require 'inc/gravityforms-product-addons-entry.php';
			require 'inc/gravityforms-product-addons-display.php';

			WC_GFPA_Cart::register();
			WC_GFPA_Display::register();
			WC_GFPA_Entry::register();
		}

		function on_woocommerce_before_add_to_cart_form() {
			$product = wc_get_product();
			if ( $product->is_type( 'variable' ) ) {
				// Addon display
				if ( WC_GFPA_Compatibility::is_wc_version_gte_2_4() ) {
					if ( apply_filters( 'woocommerce_gforms_use_template_back_compatibility', get_option( 'woocommerce_gforms_use_template_back_compatibility', false ) ) ) {
						add_action( 'woocommerce_before_add_to_cart_button', array(
							$this,
							'woocommerce_gravityform'
						), 10 );
					} else {
						//Use the new 2.4 hook
						add_action( 'woocommerce_single_variation', array( $this, 'woocommerce_gravityform' ), 11 );
						add_action( 'wc_cvo_after_single_variation', array( $this, 'woocommerce_gravityform' ), 9 );
					}
				} else {
					add_action( 'catalog_visibility_after_alternate_add_to_cart_button', array(
						$this,
						'woocommerce_gravityform'
					), 10 );
					add_action( 'woocommerce_before_add_to_cart_button', array(
						$this,
						'woocommerce_gravityform'
					), 10 );
				}
			} else {
				add_action( 'woocommerce_before_add_to_cart_button', array( $this, 'woocommerce_gravityform' ), 10 );
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

		function woocommerce_gravityform() {
			global $post, $woocommerce;

			include_once( 'gravityforms-product-addons-form.php' );

			$gravity_form_data = $this->get_gravity_form_data( $post->ID );

			if ( is_array( $gravity_form_data ) && $gravity_form_data['id'] ) {
				$product = null;
				if ( function_exists( 'get_product' ) ) {
					$product = get_product( $post->ID );
				} else {
					$product = new WC_Product( $post->ID );
				}

				$product_form = new woocommerce_gravityforms_product_form( $gravity_form_data['id'], $post->ID );
				$product_form->get_form( $gravity_form_data );

				$add_to_cart_value = '';
				if ( $product->is_type( 'variable' ) ) :
					$add_to_cart_value = 'variation';
				elseif ( $product->has_child() ) :
					$add_to_cart_value = 'group';
				else :
					$add_to_cart_value = $product->get_id();
				endif;

				if ( ! function_exists( 'get_product' ) ) {
					//1.x only
					$woocommerce->nonce_field( 'add_to_cart' );
					echo '<input type="hidden" name="add-to-cart" value="' . $add_to_cart_value . '" />';
				} else {
					echo '<input type="hidden" name="add-to-cart" value="' . $post->ID . '" />';
				}
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

							$prices[ $_product->get_id() ]   = $_product->get_display_price();
							$suffixes[ $_product->get_id() ] = $_product->get_price_suffix();
							$use_ajax[ $_product->get_id() ] = apply_filters( 'woocommerce_gforms_use_ajax', isset( $gravity_form_data['use_ajax'] ) ? ( $gravity_form_data['use_ajax'] == 'yes' ) : false );
							if ( $_product->has_child() ) {
								foreach ( $_product->get_children() as $variation_id ) {
									$variation               = $_product->get_child( $variation_id );
									$prices[ $variation_id ] = $variation->get_display_price();
								}
							}
						}
					}
				}
			}


			if ( $enqueue ) {
				$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
				if ( WC_GFPA_Compatibility::is_wc_version_gte_2_5() ) {
					wp_register_script( 'accounting', WC()->plugin_url() . '/assets/js/accounting/accounting' . $suffix . '.js', array( 'jquery' ), '0.4.2' );
				} else {
					wp_register_script( 'accounting', WC()->plugin_url() . '/assets/js/admin/accounting' . $suffix . '.js', array( 'jquery' ), '0.4.2' );
				}

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

		function woocommerce_gravityform_enqueue_scripts() {
			global $post, $product;

			if ( is_product() ) {
				$gravity_form_data = $this->get_gravity_form_data( $post->ID );
				if ( $gravity_form_data && is_array( $gravity_form_data ) ) {

					wp_enqueue_style( 'wc-gravityforms-product-addons', WC_GFPA_Main::plugin_url() . '/assets/css/frontend.css', null );


					gravity_form_enqueue_scripts( $gravity_form_data['id'], false );

					$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

					if ( WC_GFPA_Compatibility::is_wc_version_gte_2_5() ) {
						wp_register_script( 'accounting', WC()->plugin_url() . '/assets/js/accounting/accounting' . $suffix . '.js', array( 'jquery' ), '0.4.2' );
					} else {
						wp_register_script( 'accounting', WC()->plugin_url() . '/assets/js/admin/accounting' . $suffix . '.js', array( 'jquery' ), '0.4.2' );
					}

					wp_enqueue_script( 'wc-gravityforms-product-addons', WC_GFPA_Main::plugin_url() . '/assets/js/gravityforms-product-addons.js', array(
						'jquery',
						'accounting'
					), false );

					$_product = wc_get_product();
					$prices   = array(
						$_product->get_id() => $_product->get_display_price(),
					);

					if ( $_product->has_child() ) {
						foreach ( $_product->get_children() as $variation_id ) {
							$variation               = $_product->get_child( $variation_id );
							$prices[ $variation_id ] = $variation->get_display_price();
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
						'price_suffix'                 => array( $_product->get_id() => $_product->get_price_suffix() ),
						'use_ajax'                     => array( $_product->get_id() => apply_filters( 'woocommerce_gforms_use_ajax', isset( $gravity_form_data['use_ajax'] ) ? ( $gravity_form_data['use_ajax'] == 'yes' ) : false ) )
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
								$prices[ $product->get_id() ] = $product->get_display_price();

								if ( $product->has_child() ) {
									foreach ( $product->get_children() as $variation_id ) {
										$variation               = $product->get_child( $variation_id );
										$prices[ $variation_id ] = $variation->get_display_price();
									}
								}
							}
						}
					}

					if ( $enqueue ) {
						wp_enqueue_style( 'wc-gravityforms-product-addons', WC_GFPA_Main::plugin_url() . '/assets/css/frontend.css', null );

						$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

						if ( WC_GFPA_Compatibility::is_wc_version_gte_2_5() ) {
							wp_register_script( 'accounting', WC()->plugin_url() . '/assets/js/accounting/accounting' . $suffix . '.js', array( 'jquery' ), '0.4.2' );
						} else {
							wp_register_script( 'accounting', WC()->plugin_url() . '/assets/js/admin/accounting' . $suffix . '.js', array( 'jquery' ), '0.4.2' );
						}

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

		function get_free_price_html( $html, $_product ) {
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

		function get_formatted_price( $price ) {
			return woocommerce_price( $price );
		}



		/** Helper functions ***************************************************** */

		/**
		 * Get the plugin url.
		 *
		 * @access public
		 * @return string
		 */
		public static function plugin_url() {
			return wc_gfpa_get_plugin_url() . '/back_compat_less_27';
		}

		public function get_gravity_form_data( $post_id ) {
			$gravity_form_data = get_post_meta($post_id, '_gravity_form_data', true);
			return apply_filters( 'woocommerce_gforms_get_product_form_data', $gravity_form_data, $post_id );
		}

	}

	/**
	 * The instance of the plugin.
	 * @return WC_GFPA_Main
	 */
	function wc_gfpa() {
		return WC_GFPA_Main::instance();
	}

	WC_GFPA_Main::register();
}