<?php
/*
  Plugin Name: WooCommerce - Gravity Forms Product Add-Ons
  Plugin URI: http://woothemes.com/products/gravity-forms-add-ons/
  Description: Allows you to use Gravity Forms on individual WooCommerce products. Requires the Gravity Forms plugin to work. Requires WooCommerce 2.3 or higher
  Version: 2.10.5
  Author: WooThemes
  Author URI: http://woothemes.com/
  Developer: Lucas Stark
  Developer URI: http://lucasstark.com/
  Requires at least: 3.1
  Tested up to: 4.4.1

  Copyright: © 2009-2016 Lucas Stark.
  License: GNU General Public License v3.0
  License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

/**
 * Required functions
 */
if ( !function_exists( 'woothemes_queue_update' ) )
	require_once( 'woo-includes/woo-functions.php' );

/**
 * Plugin updates
 */
woothemes_queue_update( plugin_basename( __FILE__ ), 'a6ac0ab1a1536e3a357ccf24c0650ed0', '18633' );

if ( is_woocommerce_active() ) {

	load_plugin_textdomain( 'wc_gf_addons', null, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

	include 'compatibility.php';

	class woocommerce_gravityforms {

		var $settings;
		var $edits = array();
		var $gravity_products = array();
		var $shortcode_product_id = null;
		var $rendered_on_variation = false;
		var $rendered_form = false;

		public function __construct() {

			add_action( 'wp_head', array($this, 'on_wp_head') );

			add_action( 'init', array($this, 'on_init') );

			// Enqueue Gravity Forms Scripts
			add_action( 'wp_enqueue_scripts', array($this, 'woocommerce_gravityform_enqueue_scripts'), 99 );

			//Bind the form
			add_action( 'woocommerce_before_add_to_cart_form', array($this, 'on_woocommerce_before_add_to_cart_form') );
			add_action( 'catalog_visibility_after_alternate_add_to_cart_button', array($this, 'woocommerce_gravityform'), 10 );



			// Filters for price display
			add_filter( 'woocommerce_grouped_price_html', array($this, 'get_price_html'), 10, 2 );


			add_filter( 'woocommerce_variation_price_html', array($this, 'get_price_html'), 10, 2 );
			add_filter( 'woocommerce_variation_sale_price_html', array($this, 'get_price_html'), 10, 2 );

			add_filter( 'woocommerce_variable_price_html', array($this, 'get_price_html'), 10, 2 );
			add_filter( 'woocommerce_variable_sale_price_html', array($this, 'get_price_html'), 10, 2 );
			add_filter( 'woocommerce_variable_empty_price_html', array($this, 'get_price_html'), 10, 2 );
			add_filter( 'woocommerce_variable_free_sale_price_html', array($this, 'get_free_price_html'), 10, 2 );
			add_filter( 'woocommerce_variable_free_price_html', array($this, 'get_free_price_html'), 10, 2 );

			add_filter( 'woocommerce_sale_price_html', array($this, 'get_price_html'), 10, 2 );
			add_filter( 'woocommerce_price_html', array($this, 'get_price_html'), 10, 2 );
			add_filter( 'woocommerce_empty_price_html', array($this, 'get_price_html'), 10, 2 );

			add_filter( 'woocommerce_free_sale_price_html', array($this, 'get_free_price_html'), 10, 2 );
			add_filter( 'woocommerce_free_price_html', array($this, 'get_free_price_html'), 10, 2 );


			// Filters for cart actions

			add_filter( 'woocommerce_add_cart_item_data', array($this, 'add_cart_item_data'), 10, 2 );
			add_filter( 'woocommerce_get_cart_item_from_session', array($this, 'get_cart_item_from_session'), 10, 2 );
			add_filter( 'woocommerce_get_item_data', array($this, 'get_item_data'), 10, 2 );
			add_filter( 'woocommerce_add_cart_item', array($this, 'add_cart_item'), 10, 1 );
			add_action( 'woocommerce_order_item_meta', array($this, 'order_item_meta'), 10, 2 );

			// Add meta to order 2.0
			add_action( 'woocommerce_add_order_item_meta', array($this, 'order_item_meta_2'), 10, 2 );
			add_filter( 'woocommerce_add_to_cart_validation', array($this, 'add_to_cart_validation'), 99, 3 );


			//Order Again
			add_filter( 'woocommerce_order_again_cart_item_data', array($this, 'on_get_order_again_cart_item_data'), 10, 3 );


			// Write Panel
			add_action( 'add_meta_boxes', array($this, 'add_meta_box') );
			add_action( 'woocommerce_process_product_meta', array($this, 'process_meta_box'), 1, 2 );

			add_action( 'admin_notices', array($this, 'admin_install_notices') );


			//Modify Add to Cart Buttons
			add_action( 'init', array($this, 'get_gravity_products') );
		}

		function on_init() {
			if ( wc_is_21x() ) {
				add_filter( 'woocommerce_loop_add_to_cart_link', array($this, 'get_add_to_cart_link21'), 99, 2 );
			} else {
				add_filter( 'woocommerce_loop_add_to_cart_link', array($this, 'get_add_to_cart_link'), 99, 3 );
			}
		}

		function on_woocommerce_before_add_to_cart_form() {
			$product = wc_get_product();
			if ( $product->is_type( 'variable' ) ) {
				// Addon display
				if ( WC_GFPA_Compatibility::is_wc_version_gte_2_4() ) {
					if ( apply_filters( 'woocommerce_gforms_use_template_back_compatibility', get_option( 'woocommerce_gforms_use_template_back_compatibility', false ) ) ) {
						add_action( 'woocommerce_before_add_to_cart_button', array($this, 'woocommerce_gravityform'), 10 );
					} else {
						//Use the new 2.4 hook
						add_action( 'woocommerce_single_variation', array($this, 'woocommerce_gravityform'), 11 );
					}
				} else {
					add_action( 'woocommerce_before_add_to_cart_button', array($this, 'woocommerce_gravityform'), 10 );
				}
			} else {
				add_action( 'woocommerce_before_add_to_cart_button', array($this, 'woocommerce_gravityform'), 10 );
			}
		}

		function admin_install_notices() {
			if ( !class_exists( 'RGForms' ) ) {
				?>
				<div id="message" class="updated woocommerce-error wc-connect">
					<div class="squeezer">
						<h4><?php _e( '<strong>Gravity Forms Not Found</strong> &#8211; The Gravity Forms Plugin is required to build and manage the forms for your products.', 'wc_gf_addons' ); ?></h4>
						<p class="submit"><a href="http://www.gravityforms.com/" class="button-primary"><?php _e( 'Get Gravity Forms', 'wc_gf_addons' ); ?></a></p>
					</div>
				</div>
				<?php
			}
		}

		function on_wp_head() {
			echo '<style type="text/css">';
			echo 'dd ul.bulleted {  float:none;clear:both; }';
			echo '</style>';
		}

		function get_gravity_products() {
			global $wpdb;
			$metakey = '_gravity_form_data';
			$this->gravity_products = $wpdb->get_col( $wpdb->prepare( "SELECT post_id FROM $wpdb->postmeta WHERE meta_key=%s", $metakey ) );
		}

		function get_add_to_cart_link( $anchor, $product, $link ) {
			if ( is_array( $this->gravity_products ) && in_array( $product->id, $this->gravity_products ) ) {
				$link['url'] = apply_filters( 'variable_add_to_cart_url', get_permalink( $product->id ) );
				$link['label'] = apply_filters( 'gravityforms_add_to_cart_text', apply_filters( 'variable_add_to_cart_text', __( 'Select options', 'woocommerce' ) ) );
				return sprintf( '<a href="%s" rel="nofollow" data-product_id="%s" data-product_sku="%s" class="%s button product_type_%s">%s</a>', esc_url( $link['url'] ), esc_attr( $product->id ), esc_attr( $product->get_sku() ), esc_attr( $link['class'] ), esc_attr( 'variable' ), esc_html( $link['label'] ) );
			} else {
				return $anchor;
			}
		}

		function get_add_to_cart_link21( $link, $product ) {
			if ( is_array( $this->gravity_products ) && in_array( $product->id, $this->gravity_products ) ) {
				$label = $product->is_purchasable() && $product->is_in_stock() ? __( 'Select options', 'woocommerce' ) : __( 'Read More', 'woocommerce' );
				$label = apply_filters( 'gravityforms_add_to_cart_text', apply_filters( 'woocommerce_product_add_to_cart_text', $label, $product ) );

				return sprintf( '<a href="%s" rel="nofollow" data-product_id="%s" data-product_sku="%s" class="button add_to_cart_button product_type_%s">%s</a>', get_permalink( $product->id ), esc_attr( $product->id ), esc_attr( $product->get_sku() ), 'variable', esc_html( $label ) );
			} else {
				return $link;
			}
		}

		//Fix up any add to cart button that has a gravity form assoicated with the product.
		function on_wp_footer() {
			global $wpdb;
			$metakey = '_gravity_form_data';
			$product_ids = $wpdb->get_col( $wpdb->prepare( "SELECT post_id FROM $wpdb->postmeta WHERE meta_key=%s", $metakey ) );
			if ( is_array( $product_ids ) ) {
				$product_ids = array_flip( $product_ids );
				foreach ( $product_ids as $k => $v ) {
					$product_ids[$k] = get_permalink( $k );
				}
			}
			?>
			<script type="text/javascript">
				jQuery(document).ready(function ($) {
					var gravityform_products = <?php echo json_encode( $product_ids ); ?>;
					var label = "<?php echo apply_filters( 'gravityforms_add_to_cart_text', apply_filters( 'variable_add_to_cart_text', __( 'Select options', 'wc_gf_addons' ) ) ); ?>";
					$('.add_to_cart_button').each(function () {
						if ($(this).data('product_id') in gravityform_products) {
							$(this).text(label);
							$(this).click(function (event) {

								event.preventDefault();
								var product_id = $(this).data('product_id');

								window.location = gravityform_products[product_id];
								return false;
							});

						}
					});
				});
			</script>
			<?php
		}

		/* ----------------------------------------------------------------------------------- */
		/* Write Panel */
		/* ----------------------------------------------------------------------------------- */

		function add_meta_box() {
			global $post;
			add_meta_box( 'woocommerce-gravityforms-meta', __( 'Gravity Forms Product Add-Ons', 'wc_gf_addons' ), array($this, 'meta_box'), 'product', 'normal', 'default' );
		}

		function meta_box( $post ) {
			?>

			<script type="text/javascript">
				jQuery(document).ready(function ($) {
					$('#gravityform-id').change(function () {
						if ($(this).val() != '') {
							$('.gforms-panel').show();
						} else {
							$('.gforms-panel').hide();
						}
					})
				});
			</script>
			<div id="gravityforms_data" class="panel woocommerce_options_panel">
				<h4><?php _e( 'General', 'wc_gf_addons' ); ?></h4>
				<?php
				$gravity_form_data = get_post_meta( $post->ID, '_gravity_form_data', true );

				$gravityform = NULL;
				if ( is_array( $gravity_form_data ) && isset( $gravity_form_data['id'] ) && is_numeric( $gravity_form_data['id'] ) ) {

					$form_meta = RGFormsModel::get_form_meta( $gravity_form_data['id'] );

					if ( !empty( $form_meta ) ) {
						$gravityform = RGFormsModel::get_form( $gravity_form_data['id'] );
					}
				}
				?>
				<div class="options_group">
					<p class="form-field">
						<label for="gravityform-id"><?php _e( 'Choose Form', 'wc_gf_addons' ); ?></label>
						<?php
						echo '<select id="gravityform-id" name="gravityform-id"><option value="">' . __( 'None', 'wc_gf_addons' ) . '</option>';
						foreach ( RGFormsModel::get_forms() as $form ) {
							echo '<option ' . selected( $form->id, (isset( $gravity_form_data['id'] ) ? $gravity_form_data['id'] : 0 ) ) . ' value="' . esc_attr( $form->id ) . '">' . wptexturize( $form->title ) . '</option>';
						}
						echo '</select>';
						?>
					</p>

					<?php
					woocommerce_wp_checkbox( array(
					    'id' => 'gravityform-display_title',
					    'label' => __( 'Display Title', 'wc_gf_addons' ),
					    'value' => isset( $gravity_form_data['display_title'] ) && $gravity_form_data['display_title'] ? 'yes' : '') );

					woocommerce_wp_checkbox( array(
					    'id' => 'gravityform-display_description',
					    'label' => __( 'Display Description', 'wc_gf_addons' ),
					    'value' => isset( $gravity_form_data['display_description'] ) && $gravity_form_data['display_description'] ? 'yes' : '') );

					;
					?>
				</div>

				<div class="options_group" style="padding: 0 9px;">
					<?php if ( !empty( $gravityform ) && is_object( $gravityform ) ) : ?>
						<h4><a href="<?php printf( '%s/admin.php?page=gf_edit_forms&id=%d', get_admin_url(), $gravityform->id ) ?>" class="edit_gravityform">Edit <?php echo $gravityform->title; ?> Gravity Form</a></h4>
					<?php endif; ?>
				</div>
			</div>

			<div id="multipage_forms_data" class="gforms-panel panel woocommerce_options_panel" <?php echo empty( $gravity_form_data['id'] ) ? "style='display:none;'" : ''; ?>>
				<h4><?php _e( 'Multipage Forms', 'wc_gf_addons' ); ?></h4>
				<div class="options_group">
					<?php
					woocommerce_wp_checkbox( array(
					    'id' => 'gravityform-disable_anchor',
					    'label' => __( 'Disable Gravity Forms Anchors', 'wc_gf_addons' ),
					    'value' => isset( $gravity_form_data['disable_anchor'] ) ? $gravity_form_data['disable_anchor'] : '') );
					?>
				</div>
			</div>

			<div id="price_labels_data" class="gforms-panel panel woocommerce_options_panel" <?php echo empty( $gravity_form_data['id'] ) ? "style='display:none;'" : ''; ?>>
				<h4><?php _e( 'Price Labels', 'wc_gf_addons' ); ?></h4>
				<div class="options_group">
					<?php
					woocommerce_wp_checkbox( array(
					    'id' => 'gravityform-disable_woocommerce_price',
					    'label' => __( 'Remove WooCommerce Price?', 'wc_gf_addons' ),
					    'value' => isset( $gravity_form_data['disable_woocommerce_price'] ) ? $gravity_form_data['disable_woocommerce_price'] : '') );

					woocommerce_wp_text_input( array('id' => 'gravityform-price-before', 'label' => __( 'Price Before', 'wc_gf_addons' ),
					    'value' => isset( $gravity_form_data['price_before'] ) ? $gravity_form_data['price_before'] : '',
					    'placeholder' => __( 'Base Price:', 'wc_gf_addons' ), 'description' => __( 'Enter text you would like printed before the price of the product.', 'wc_gf_addons' )) );

					woocommerce_wp_text_input( array('id' => 'gravityform-price-after', 'label' => __( 'Price After', 'wc_gf_addons' ),
					    'value' => isset( $gravity_form_data['price_after'] ) ? $gravity_form_data['price_after'] : '',
					    'placeholder' => __( '', 'wc_gf_addons' ), 'description' => __( 'Enter text you would like printed after the price of the product.', 'wc_gf_addons' )) );
					?>
				</div>
			</div>

			<div id="total_labels_data" class="gforms-panel panel woocommerce_options_panel" <?php echo empty( $gravity_form_data['id'] ) ? "style='display:none;'" : ''; ?>>
				<h4><?php _e( 'Total Calculations', 'wc_gf_addons' ); ?></h4>
				<?php
				echo '<div class="options_group">';
				woocommerce_wp_checkbox( array(
				    'id' => 'gravityform-disable_calculations',
				    'label' => __( 'Disable Calculations?', 'wc_gf_addons' ),
				    'value' => isset( $gravity_form_data['disable_calculations'] ) ? $gravity_form_data['disable_calculations'] : '') );
				echo '</div><div class="options_group">';
				woocommerce_wp_checkbox( array(
				    'id' => 'gravityform-disable_label_subtotal',
				    'label' => __( 'Disable Subtotal?', 'wc_gf_addons' ),
				    'value' => isset( $gravity_form_data['disable_label_subtotal'] ) ? $gravity_form_data['disable_label_subtotal'] : '') );

				woocommerce_wp_text_input( array('id' => 'gravityform-label_subtotal', 'label' => __( 'Subtotal Label', 'wc_gf_addons' ),
				    'value' => isset( $gravity_form_data['label_subtotal'] ) && !empty( $gravity_form_data['label_subtotal'] ) ? $gravity_form_data['label_subtotal'] : 'Subtotal',
				    'placeholder' => __( 'Subtotal', 'wc_gf_addons' ), 'description' => __( 'Enter "Subtotal" label to display on for single products.', 'wc_gf_addons' )) );
				echo '</div><div class="options_group">';
				woocommerce_wp_checkbox( array(
				    'id' => 'gravityform-disable_label_options',
				    'label' => __( 'Disable Options Label?', 'wc_gf_addons' ),
				    'value' => isset( $gravity_form_data['disable_label_options'] ) ? $gravity_form_data['disable_label_options'] : '') );

				woocommerce_wp_text_input( array('id' => 'gravityform-label_options', 'label' => __( 'Options Label', 'wc_gf_addons' ),
				    'value' => isset( $gravity_form_data['label_options'] ) && !empty( $gravity_form_data['label_options'] ) ? $gravity_form_data['label_options'] : 'Options',
				    'placeholder' => __( 'Options', 'wc_gf_addons' ), 'description' => __( 'Enter the "Options" label to display for single products.', 'wc_gf_addons' )) );
				echo '</div><div class="options_group">';
				woocommerce_wp_checkbox( array(
				    'id' => 'gravityform-disable_label_total',
				    'label' => __( 'Disable Total Label?', 'wc_gf_addons' ),
				    'value' => isset( $gravity_form_data['disable_label_total'] ) ? $gravity_form_data['disable_label_total'] : '') );

				woocommerce_wp_text_input( array('id' => 'gravityform-label_total', 'label' => __( 'Total Label', 'wc_gf_addons' ),
				    'value' => isset( $gravity_form_data['label_total'] ) && !empty( $gravity_form_data['label_total'] ) ? $gravity_form_data['label_total'] : 'Total',
				    'placeholder' => __( 'Total', 'wc_gf_addons' ), 'description' => __( 'Enter the "Total" label to display for single products.', 'wc_gf_addons' )) );
				echo '</div>';
				?>
			</div>
			<?php
		}

		function process_meta_box( $post_id, $post ) {
			global $woocommerce_errors;


			// Save gravity form as serialised array
			if ( isset( $_POST['gravityform-id'] ) && !empty( $_POST['gravityform-id'] ) ) {

				$product = null;
				if ( function_exists( 'get_product' ) ) {
					$product = get_product( $post_id );
				} else {
					$product = new WC_Product( $post_id );
				}

				if ( $product->product_type != 'variable' && empty( $product->price ) && ($product->price != '0' || $product->price != '0.00') ) {
					$woocommerce_errors[] = __( 'You must set a price for the product before the gravity form will be visible.  Set the price to 0 if you are performing all price calculations with the attached Gravity Form.', 'woocommerce' );
				}

				$gravity_form_data = array(
				    'id' => $_POST['gravityform-id'],
				    'display_title' => isset( $_POST['gravityform-display_title'] ) ? true : false,
				    'display_description' => isset( $_POST['gravityform-display_description'] ) ? true : false,
				    'disable_woocommerce_price' => isset( $_POST['gravityform-disable_woocommerce_price'] ) ? 'yes' : 'no',
				    'price_before' => $_POST['gravityform-price-before'],
				    'price_after' => $_POST['gravityform-price-after'],
				    'disable_calculations' => isset( $_POST['gravityform-disable_calculations'] ) ? 'yes' : 'no',
				    'disable_label_subtotal' => isset( $_POST['gravityform-disable_label_subtotal'] ) ? 'yes' : 'no',
				    'disable_label_options' => isset( $_POST['gravityform-disable_label_options'] ) ? 'yes' : 'no',
				    'disable_label_total' => isset( $_POST['gravityform-disable_label_total'] ) ? 'yes' : 'no',
				    'disable_anchor' => isset( $_POST['gravityform-disable_anchor'] ) ? 'yes' : 'no',
				    'label_subtotal' => $_POST['gravityform-label_subtotal'],
				    'label_options' => $_POST['gravityform-label_options'],
				    'label_total' => $_POST['gravityform-label_total']
				);
				update_post_meta( $post_id, '_gravity_form_data', $gravity_form_data );
			} else {
				delete_post_meta( $post_id, '_gravity_form_data' );
			}
		}

		/* ----------------------------------------------------------------------------------- */
		/* Product Form Functions */
		/* ----------------------------------------------------------------------------------- */

		function woocommerce_gravityform() {
			global $post, $woocommerce;

			include_once( 'gravityforms-product-addons-form.php' );

			$gravity_form_data = get_post_meta( $post->ID, '_gravity_form_data', true );

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
					$add_to_cart_value = $product->id;
				endif;

				if ( !function_exists( 'get_product' ) ) {
					//1.x only
					$woocommerce->nonce_field( 'add_to_cart' );
					echo '<input type="hidden" name="add-to-cart" value="' . $add_to_cart_value . '" />';
				} else {
					echo '<input type="hidden" name="add-to-cart" value="' . $post->ID . '" />';
				}
			}
			echo '<div class="clear"></div>';
		}

		function woocommerce_gravityform_enqueue_scripts() {
			global $post;

			if ( is_product() ) {
				$gravity_form_data = get_post_meta( $post->ID, '_gravity_form_data', true );
				if ( $gravity_form_data && is_array( $gravity_form_data ) ) {
					//wp_enqueue_script("gforms_gravityforms", GFCommon::get_base_url() . "/js/gravityforms.js", array("jquery"), GFCommon::$version, false);

					gravity_form_enqueue_scripts( $gravity_form_data['id'], false );

					$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

					if ( WC_GFPA_Compatibility::is_wc_version_gte_2_5() ) {
						wp_register_script( 'accounting', WC()->plugin_url() . '/assets/js/accounting/accounting' . $suffix . '.js', array('jquery'), '0.4.2' );
					} else {
						wp_register_script( 'accounting', WC()->plugin_url() . '/assets/js/admin/accounting' . $suffix . '.js', array('jquery'), '0.4.2' );
					}

					wp_enqueue_script( 'wc-gravityforms-product-addons', woocommerce_gravityforms::plugin_url() . '/assets/js/gravityforms-product-addons.js', array('jquery', 'accounting'), true );

					$product = wc_get_product();
					$prices = array(
					    $product->id => $product->get_display_price(),
					);

					if ( $product->has_child() ) {
						foreach ( $product->get_children() as $variation_id ) {
							$variation = $product->get_child( $variation_id );
							$prices[$variation_id] = $variation->get_display_price();
						}
					}

					// Accounting
					wp_localize_script( 'accounting', 'accounting_params', array(
					    'mon_decimal_point' => wc_get_price_decimal_separator()
					) );

					$wc_gravityforms_params = array(
					    'currency_format_num_decimals' => wc_get_price_decimals(),
					    'currency_format_symbol' => get_woocommerce_currency_symbol(),
					    'currency_format_decimal_sep' => esc_attr( wc_get_price_decimal_separator() ),
					    'currency_format_thousand_sep' => esc_attr( wc_get_price_thousand_separator() ),
					    'currency_format' => esc_attr( str_replace( array('%1$s', '%2$s'), array('%s', '%v'), get_woocommerce_price_format() ) ), // For accounting JS
					    'prices' => $prices,
					    'price_suffix' => $product->get_price_suffix()
					);

					wp_localize_script( 'wc-gravityforms-product-addons', 'wc_gravityforms_params', $wc_gravityforms_params );
				}
			} elseif ( is_object( $post ) && isset( $post->post_content ) && !empty( $post->post_content ) ) {
				$enqueue = false;
				$forms = array();
				$prices = array();

				if ( preg_match_all( '/\[product_page[s]? +.*?((id=.+?)|(name=.+?))\]/is', $post->post_content, $matches, PREG_SET_ORDER ) ) {
					$ajax = false;
					foreach ( $matches as $match ) {
						//parsing shortcode attributes
						$attr = shortcode_parse_atts( $match[1] );
						$product_id = isset( $attr['id'] ) ? $attr['id'] : false;

						if ( !empty( $product_id ) ) {
							$gravity_form_data = get_post_meta( $product_id, '_gravity_form_data', true );
							if ( $gravity_form_data && is_array( $gravity_form_data ) ) {
								$enqueue = true;
								gravity_form_enqueue_scripts( $gravity_form_data['id'], false );

								$product = wc_get_product( $product_id );
								$prices[$product->id] = $product->get_display_price();

								if ( $product->has_child() ) {
									foreach ( $product->get_children() as $variation_id ) {
										$variation = $product->get_child( $variation_id );
										$prices[$variation_id] = $variation->get_display_price();
									}
								}
							}
						}
					}

					if ( $enqueue ) {

						$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

						if ( WC_GFPA_Compatibility::is_wc_version_gte_2_5() ) {
							wp_register_script( 'accounting', WC()->plugin_url() . '/assets/js/accounting/accounting' . $suffix . '.js', array('jquery'), '0.4.2' );
						} else {
							wp_register_script( 'accounting', WC()->plugin_url() . '/assets/js/admin/accounting' . $suffix . '.js', array('jquery'), '0.4.2' );
						}

						wp_enqueue_script( 'wc-gravityforms-product-addons', woocommerce_gravityforms::plugin_url() . '/assets/js/gravityforms-product-addons.js', array('jquery', 'accounting'), true );

						// Accounting
						wp_localize_script( 'accounting', 'accounting_params', array(
						    'mon_decimal_point' => wc_get_price_decimal_separator()
						) );

						$wc_gravityforms_params = array(
						    'currency_format_num_decimals' => wc_get_price_decimals(),
						    'currency_format_symbol' => get_woocommerce_currency_symbol(),
						    'currency_format_decimal_sep' => esc_attr( wc_get_price_decimal_separator() ),
						    'currency_format_thousand_sep' => esc_attr( wc_get_price_thousand_separator() ),
						    'currency_format' => esc_attr( str_replace( array('%1$s', '%2$s'), array('%s', '%v'), get_woocommerce_price_format() ) ), // For accounting JS
						    'prices' => $prices,
						    'price_suffix' => $product->get_price_suffix()
						);

						wp_localize_script( 'wc-gravityforms-product-addons', 'wc_gravityforms_params', $wc_gravityforms_params );
					}
				}
			}
		}

		function get_price_html( $html, $_product ) {
			$gravity_form_data = get_post_meta( $_product->id, '_gravity_form_data', true );
			if ( $gravity_form_data && is_array( $gravity_form_data ) ) {

				if ( isset( $gravity_form_data['disable_woocommerce_price'] ) && $gravity_form_data['disable_woocommerce_price'] == 'yes' ) {
					$html = '';
				}

				if ( isset( $gravity_form_data['price_before'] ) && !empty( $gravity_form_data['price_before'] ) ) {
					$html = '<span class="woocommerce-price-before">' . $gravity_form_data['price_before'] . ' </span>' . $html;
				}

				if ( isset( $gravity_form_data['price_after'] ) && !empty( $gravity_form_data['price_after'] ) ) {
					$html .= '<span class="woocommerce-price-after"> ' . $gravity_form_data['price_after'] . '</span>';
				}
			}
			return $html;
		}

		function get_free_price_html( $html, $_product ) {
			$gravity_form_data = get_post_meta( $_product->id, '_gravity_form_data', true );
			if ( $gravity_form_data && is_array( $gravity_form_data ) ) {

				if ( isset( $gravity_form_data['disable_woocommerce_price'] ) && $gravity_form_data['disable_woocommerce_price'] == 'yes' ) {
					$html = '';
				}

				if ( isset( $gravity_form_data['price_before'] ) && !empty( $gravity_form_data['price_before'] ) ) {
					$html = '<span class="woocommerce-price-before">' . $gravity_form_data['price_before'] . ' </span>' . $html;
				}

				if ( isset( $gravity_form_data['price_after'] ) && !empty( $gravity_form_data['price_after'] ) ) {
					$html .= '<span class="woocommerce-price-after"> ' . $gravity_form_data['price_after'] . '</span>';
				}
			}
			return $html;
		}

		function get_formatted_price( $price ) {
			return woocommerce_price( $price );
		}

		function disable_notifications( $disabled, $form, $lead ) {
			return true;
		}

		function disable_confirmation( $confirmation, $form, $lead, $ajax ) {
			if ( is_array( $confirmation ) && isset( $confirmation['redirect'] ) ) {
				return $confirmation;
			} else {
				return false;
			}
		}

		function add_to_cart_validation( $valid, $product_id, $quantity ) {
			global $woocommerce;

			if ( !$valid ) {
				return false;
			}

			// Check if we need a gravity form!
			$gravity_form_data = get_post_meta( $product_id, '_gravity_form_data', true );

			if ( is_array( $gravity_form_data ) && $gravity_form_data['id'] && empty( $_POST['gform_form_id'] ) )
				return false;

			if ( isset( $_POST['gform_form_id'] ) && is_numeric( $_POST['gform_form_id'] ) ) {
				$form_id = $_POST['gform_form_id'];

				//Gravity forms generates errors and warnings.  To prevent these from conflicting with other things, we are going to disable warnings and errors.
				$err_level = error_reporting();
				error_reporting( 0 );

				//MUST disable notifications manually.
				add_filter( 'gform_disable_user_notification_' . $form_id, array($this, 'disable_notifications'), 10, 3 );
				add_filter( 'gform_disable_admin_notification_' . $form_id, array($this, 'disable_notifications'), 10, 3 );
				add_filter( 'gform_disable_notification_' . $form_id, array($this, 'disable_notifications'), 10, 3 );

				add_filter( "gform_confirmation_" . $form_id, array($this, "disable_confirmation"), 10, 4 );

				require_once(GFCommon::get_base_path() . "/form_display.php");

				$_POST['gform_submit'] = $_POST['gform_old_submit'];

				GFFormDisplay::process_form( $form_id );
				$_POST['gform_old_submit'] = $_POST['gform_submit'];
				unset( $_POST['gform_submit'] );

				if ( !GFFormDisplay::$submission[$form_id]['is_valid'] ) {
					return false;
				}

				if ( GFFormDisplay::$submission[$form_id]['page_number'] != 0 ) {
					return false;
				}

				error_reporting( $err_level );
			}
			return $valid;
		}

		//When the item is being added to the cart.
		function add_cart_item_data( $cart_item_meta, $product_id ) {
			global $woocommerce;

			if ( isset( $cart_item_meta['_gravity_form_data'] ) && isset( $cart_item_meta['_gravity_form_lead'] ) ) {
				return $cart_item_meta;
			}

			$gravity_form_data = get_post_meta( $product_id, '_gravity_form_data', true );
			$cart_item_meta['_gravity_form_data'] = $gravity_form_data;

			if ( $gravity_form_data && is_array( $gravity_form_data ) && isset( $gravity_form_data['id'] ) && intval( $gravity_form_data['id'] ) > 0 ) {

				$form_id = $gravity_form_data['id'];
				$form_meta = RGFormsModel::get_form_meta( $form_id );

				//Gravity forms generates errors and warnings.  To prevent these from conflicting with other things, we are going to disable warnings and errors.
				$err_level = error_reporting();
				error_reporting( 0 );

				//MUST disable notifications manually.
				add_filter( 'gform_disable_user_notification_' . $form_id, array($this, 'disable_notifications'), 10, 3 );
				add_filter( 'gform_disable_admin_notification_' . $form_id, array($this, 'disable_notifications'), 10, 3 );
				add_filter( 'gform_disable_notification_' . $form_id, array($this, 'disable_notifications'), 10, 3 );

				add_filter( "gform_confirmation_" . $form_id, array($this, "disable_confirmation"), 10, 4 );

				if ( empty( $form_meta ) ) {
					return $cart_item_meta;
				}

				require_once(GFCommon::get_base_path() . "/form_display.php");
				$_POST['gform_submit'] = $_POST['gform_old_submit'];
				GFFormDisplay::process_form( $form_id );
				$_POST['gform_old_submit'] = $_POST['gform_submit'];
				unset( $_POST['gform_submit'] );

				$lead = GFFormDisplay::$submission[$form_id]['lead'];

				$cart_item_meta['_gravity_form_lead'] = array();

				foreach ( $form_meta['fields'] as $field ) {
					if ( isset( $field['displayOnly'] ) && $field['displayOnly'] ) {
						continue;
					}

					$value = RGFormsModel::get_lead_field_value( $lead, $field );


					$inputs = $field instanceof GF_Field ? $field->get_entry_inputs() : rgar( $field, 'inputs' );
					if ( is_array( $inputs ) ) {
						//making sure values submitted are sent in the value even if
						//there isn't an input associated with it
						$lead_field_keys = array_keys( $lead );
						natsort( $lead_field_keys );
						foreach ( $lead_field_keys as $input_id ) {
							if ( is_numeric( $input_id ) && absint( $input_id ) == absint( $field->id ) ) {
								$cart_item_meta['_gravity_form_lead'][strval( $input_id )] = $value[strval( $input_id )];
							}
						}
					} else {
						$cart_item_meta['_gravity_form_lead'][strval( $field['id'] )] = $value;
					}
				}

				if ( GFFormDisplay::$submission[$form_id]['is_valid'] ) {
					if ( is_wc_version_gte_2_3() ) {
						add_filter( 'woocommerce_add_to_cart_redirect', array($this, 'get_redirect_url'), 99 );
					} else {
						add_filter( 'add_to_cart_redirect', array($this, 'get_redirect_url'), 99 );
					}
					if ( get_option( 'woocommerce_cart_redirect_after_add' ) == 'yes' ) {
						$_SERVER['REQUEST_URI'] = esc_url_raw( add_query_arg( array('invalid' => 1) ) );
					}
				}

				error_reporting( $err_level );
			}

			return $cart_item_meta;
		}

		function get_cart_item_from_session( $cart_item, $values ) {

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

		function get_item_data( $other_data, $cart_item ) {
			if ( isset( $cart_item['_gravity_form_lead'] ) && isset( $cart_item['_gravity_form_data'] ) ) {
				//Gravity forms generates errors and warnings.  To prevent these from conflicting with other things, we are going to disable warnings and errors.
				$err_level = error_reporting();
				error_reporting( 0 );

				$gravity_form_data = $cart_item['_gravity_form_data'];
				$form_meta = RGFormsModel::get_form_meta( $gravity_form_data['id'] );

				if ( !empty( $form_meta ) ) {

					$lead = $cart_item['_gravity_form_lead'];
					$lead['id'] = uniqid() . time() . rand();

					$products = $this->get_product_fields( $form_meta, $lead );
					$valid_products = array();
					foreach ( $products['products'] as $id => $product ) {
						if ( $product['quantity'] ) {
							$valid_products[] = $id;
						}
					}

					foreach ( $form_meta['fields'] as $field ) {

						if ( $field['inputType'] == 'hiddenproduct' || $field['type'] == 'total' || (isset( $field['displayOnly'] ) && $field['displayOnly']) ) {
							continue;
						}

						if ( $field['type'] == 'product' ) {
							if ( !in_array( $field['id'], $valid_products ) ) {
								continue;
							}
						}

						$value = RGFormsModel::get_lead_field_value( $lead, $field );
						$arr_var = (is_array( $value )) ? implode( '', $value ) : '-';

						if ( !empty( $value ) && !empty( $arr_var ) ) {
							$display_value = GFCommon::get_lead_field_display( $field, $value, isset( $lead["currency"] ) ? $lead["currency"] : false, false );
							$price_adjustement = false;
							$display_value = apply_filters( "gform_entry_field_value", $display_value, $field, $lead, $form_meta );

							$display_text = GFCommon::get_lead_field_display( $field, $value, isset( $lead["currency"] ) ? $lead["currency"] : false, apply_filters( 'woocommerce_gforms_use_label_as_value', true, $value, $field, $lead, $form_meta ) );
							$display_text = apply_filters( "woocommerce_gforms_field_display_text", $display_text, $display_value, $field, $lead, $form_meta );

							$display_title = GFCommon::get_label( $field );

							$prefix = '';
							$hidden = $field['type'] == 'hidden';
							$display_hidden = apply_filters( "woocommerce_gforms_field_is_hidden", $hidden, $display_value, $display_title, $field, $lead, $form_meta );
							if ( $display_hidden ) {
								$prefix = $hidden ? '_' : '';
							}

							$other_data[] = array('name' => $prefix . $display_title, 'display' => $display_text, 'value' => $display_value, 'hidden' => $hidden);
						}
					}
				}

				error_reporting( $err_level );
			}

			return $other_data;
		}

		//Helper function, used when an item is added to the cart as well as when an item is restored from session.
		function add_cart_item( $cart_item ) {
			global $woocommerce;

			// Adjust price if required based on the gravity form data
			if ( isset( $cart_item['_gravity_form_lead'] ) && isset( $cart_item['_gravity_form_data'] ) ) {
				//Gravity forms generates errors and warnings.  To prevent these from conflicting with other things, we are going to disable warnings and errors.
				$err_level = error_reporting();
				error_reporting( 0 );

				$gravity_form_data = $cart_item['_gravity_form_data'];
				$form_meta = RGFormsModel::get_form_meta( $gravity_form_data['id'] );

				if ( empty( $form_meta ) ) {
					$_product = $cart_item['data'];
					$woocommerce->add_error( $_product->get_title() . __( ' is invalid.  Please remove and try readding to the cart', 'wc_gf_addons' ) );
					return $cart_item;
				}

				$lead = $cart_item['_gravity_form_lead'];

				$products = array();
				$total = 0;

				$lead['id'] = uniqid() . time() . rand();

				$products = $this->get_product_fields( $form_meta, $lead );
				if ( !empty( $products["products"] ) ) {

					foreach ( $products["products"] as $product ) {
						$price = GFCommon::to_number( $product["price"] );
						if ( is_array( rgar( $product, "options" ) ) ) {
							$count = sizeof( $product["options"] );
							$index = 1;
							foreach ( $product["options"] as $option ) {
								$price += GFCommon::to_number( $option["price"] );
								$class = $index == $count ? " class='lastitem'" : "";
								$index++;
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

		function order_item_meta( $item_meta, $cart_item ) {
			if ( isset( $cart_item['_gravity_form_lead'] ) && isset( $cart_item['_gravity_form_data'] ) ) {
				//Gravity forms generates errors and warnings.  To prevent these from conflicting with other things, we are going to disable warnings and errors.
				$err_level = error_reporting();
				error_reporting( 0 );

				$gravity_form_data = $cart_item['_gravity_form_data'];
				$form_meta = RGFormsModel::get_form_meta( $gravity_form_data['id'] );

				if ( !empty( $form_meta ) ) {
					$lead = $cart_item['_gravity_form_lead'];
					$lead['id'] = uniqid() . time() . rand();

					$products = $this->get_product_fields( $form_meta, $lead );
					$valid_products = array();
					foreach ( $products['products'] as $id => $product ) {
						if ( $product['quantity'] ) {
							$valid_products[] = $id;
						}
					}

					foreach ( $form_meta['fields'] as $field ) {

						if ( (isset( $field['inputType'] ) && $field['inputType'] == 'hiddenproduct') || (isset( $field['displayOnly'] ) && $field['displayOnly']) ) {
							continue;
						}

						if ( $field['type'] == 'product' ) {
							if ( !in_array( $field['id'], $valid_products ) ) {
								continue;
							}
						}

						$value = RGFormsModel::get_lead_field_value( $lead, $field );
						$arr_var = (is_array( $value )) ? implode( '', $value ) : '-';

						if ( !empty( $value ) && !empty( $arr_var ) ) {
							try {
								$display_value = GFCommon::get_lead_field_display( $field, $value, isset( $lead["currency"] ) ? $lead["currency"] : false, false );
								$display_text = GFCommon::get_lead_field_display( $field, $value, isset( $lead["currency"] ) ? $lead["currency"] : false, true );

								$price_adjustement = false;
								$display_value = apply_filters( "gform_entry_field_value", $display_value, $field, $lead, $form_meta );
								$display_title = GFCommon::get_label( $field );

								$display_title = apply_filters( "woocommerce_gforms_order_meta_title", $display_title, $field, $lead, $form_meta );
								$display_value = apply_filters( "woocommerce_gforms_order_meta_value", $display_value, $field, $lead, $form_meta );


								if ( apply_filters( 'woocommerce_gforms_strip_meta_html', true, $display_value, $field, $lead, $form_meta ) ) {
									if ( strstr( $display_value, '<li>' ) ) {
										$display_value = str_replace( '<li>', '', $display_value );
										$display_value = explode( '</li>', $display_value );
										$display_value = strip_tags( implode( ', ', $display_value ) );
										$display_value = substr( $display_value, 0, strlen( $display_value ) - 2 );
									}

									$display_value = strip_tags( $display_value );
								}

								$item_meta->add( $display_title, $display_value );
							} catch ( Exception $e ) {
								
							}
						}
					}
				}

				error_reporting( $err_level );
			}
		}

		public function order_item_meta_2( $item_id, $cart_item ) {
			if ( function_exists( 'woocommerce_add_order_item_meta' ) ) {

				if ( isset( $cart_item['_gravity_form_lead'] ) && isset( $cart_item['_gravity_form_data'] ) ) {

					woocommerce_add_order_item_meta( $item_id, '_gravity_forms_history', array(
					    '_gravity_form_lead' => $cart_item['_gravity_form_lead'],
					    '_gravity_form_data' => $cart_item['_gravity_form_data']
						)
					);

					//Gravity forms generates errors and warnings.  To prevent these from conflicting with other things, we are going to disable warnings and errors.
					$err_level = error_reporting();
					error_reporting( 0 );

					$gravity_form_data = $cart_item['_gravity_form_data'];
					$form_meta = RGFormsModel::get_form_meta( $gravity_form_data['id'] );

					if ( !empty( $form_meta ) ) {
						$lead = $cart_item['_gravity_form_lead'];
						$lead['id'] = uniqid() . time() . rand();

						$products = $this->get_product_fields( $form_meta, $lead );
						$valid_products = array();
						foreach ( $products['products'] as $id => $product ) {
							if ( !isset( $product['quantity'] ) ) {
								
							} elseif ( $product['quantity'] ) {
								$valid_products[] = $id;
							}
						}

						foreach ( $form_meta['fields'] as $field ) {

							if ( (isset( $field['inputType'] ) && $field['inputType'] == 'hiddenproduct') || (isset( $field['displayOnly'] ) && $field['displayOnly']) ) {
								continue;
							}

							if ( $field['type'] == 'product' ) {
								if ( !in_array( $field['id'], $valid_products ) ) {
									continue;
								}
							}

							$value = RGFormsModel::get_lead_field_value( $lead, $field );
							$arr_var = (is_array( $value )) ? implode( '', $value ) : '-';

							if ( !empty( $value ) && !empty( $arr_var ) ) {
								try {
									$strip_html = true;
									if ( $field['type'] == 'fileupload' && isset( $lead[$field['id']] ) ) {
										$strip_html = false;
										$dv = $lead[$field['id']];
										$files = json_decode( $dv );

										if ( empty( $files ) ) {
											$files = array($dv);
										}

										$display_value = '';

										$sep = '';
										foreach ( $files as $file ) {
											$display_value .= $sep . '<a href="' . $file . '">' . $file . '</a>';
											$sep = ', ';
										}
									} else {

										$display_value = GFCommon::get_lead_field_display( $field, $value, isset( $lead["currency"] ) ? $lead["currency"] : false, apply_filters( 'woocommerce_gforms_use_label_as_value', true, $value, $field, $lead, $form_meta ) );

										$price_adjustement = false;
										$display_value = apply_filters( "gform_entry_field_value", $display_value, $field, $lead, $form_meta );
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

									$display_text = GFCommon::get_lead_field_display( $field, $value, isset( $lead["currency"] ) ? $lead["currency"] : false, false );
									$display_value = apply_filters( "woocommerce_gforms_field_display_text", $display_value, $display_text, $field, $lead, $form_meta );

									$prefix = '';
									$hidden = $field['type'] == 'hidden';
									$display_hidden = apply_filters( "woocommerce_gforms_field_is_hidden", $hidden, $display_value, $display_title, $field, $lead, $form_meta );
									if ( $display_hidden ) {
										$prefix = $hidden ? '_' : '';
									}

									woocommerce_add_order_item_meta( $item_id, $prefix . $display_title, $display_value );
								} catch ( Exception $e ) {
									
								}
							}
						}
					}

					error_reporting( $err_level );
				}
			}
		}

		public function get_product_fields( $form, $lead, $use_choice_text = false, $use_admin_label = false ) {
			$products = array();


			foreach ( $form["fields"] as $field ) {
				$id = $field["id"];
				$lead_value = RGFormsModel::get_lead_field_value( $lead, $field );

				$quantity_field = GFCommon::get_product_fields_by_type( $form, array("quantity"), $id );
				$quantity = sizeof( $quantity_field ) > 0 ? RGFormsModel::get_lead_field_value( $lead, $quantity_field[0] ) : 1;

				switch ( $field["type"] ) {

					case "product" :

						//ignore products that have been hidden by conditional logic
						$is_hidden = RGFormsModel::is_field_hidden( $form, $field, array(), $lead );
						if ( $is_hidden )
							continue;

						//if single product, get values from the multiple inputs
						if ( is_array( $lead_value ) ) {
							$product_quantity = sizeof( $quantity_field ) == 0 && !rgar( $field, "disableQuantity" ) ? rgget( $id . ".3", $lead_value ) : $quantity;
							if ( empty( $product_quantity ) )
								continue;

							if ( !rgget( $id, $products ) )
								$products[$id] = array();

							$products[$id]["name"] = $use_admin_label && !rgempty( "adminLabel", $field ) ? $field["adminLabel"] : $lead_value[$id . ".1"];
							$products[$id]["price"] = rgar( $lead_value, $id . ".2" );
							$products[$id]["quantity"] = $product_quantity;
						}
						else if ( !empty( $lead_value ) ) {

							if ( empty( $quantity ) )
								continue;

							if ( !rgar( $products, $id ) )
								$products[$id] = array();

							if ( $field["inputType"] == "price" ) {
								$name = $field["label"];
								$price = $lead_value;
							} else {
								list($name, $price) = explode( "|", $lead_value );
							}

							$products[$id]["name"] = !$use_choice_text ? $name : RGFormsModel::get_choice_text( $field, $name );
							$products[$id]["price"] = $price;
							$products[$id]["quantity"] = $quantity;
							$products[$id]["options"] = array();
						}

						if ( isset( $products[$id] ) ) {
							$options = GFCommon::get_product_fields_by_type( $form, array("option"), $id );
							foreach ( $options as $option ) {
								$option_value = RGFormsModel::get_lead_field_value( $lead, $option );
								$option_label = empty( $option["adminLabel"] ) ? $option["label"] : $option["adminLabel"];
								if ( is_array( $option_value ) ) {
									foreach ( $option_value as $value ) {
										$option_info = GFCommon::get_option_info( $value, $option, $use_choice_text );
										if ( !empty( $option_info ) )
											$products[$id]["options"][] = array("field_label" => rgar( $option, "label" ), "option_name" => rgar( $option_info, "name" ), "option_label" => $option_label . ": " . rgar( $option_info, "name" ), "price" => rgar( $option_info, "price" ));
									}
								}
								else if ( !empty( $option_value ) ) {
									$option_info = GFCommon::get_option_info( $option_value, $option, $use_choice_text );
									$products[$id]["options"][] = array("field_label" => rgar( $option, "label" ), "option_name" => rgar( $option_info, "name" ), "option_label" => $option_label . ": " . rgar( $option_info, "name" ), "price" => rgar( $option_info, "price" ));
								}
							}
						}
						break;
				}
			}

			$shipping_field = GFCommon::get_fields_by_type( $form, array("shipping") );
			$shipping_price = $shipping_name = "";

			if ( !empty( $shipping_field ) && !RGFormsModel::is_field_hidden( $form, $shipping_field[0], array(), $lead ) ) {
				$shipping_price = RGFormsModel::get_lead_field_value( $lead, $shipping_field[0] );
				$shipping_name = $shipping_field[0]["label"];
				if ( $shipping_field[0]["inputType"] != "singleshipping" ) {
					list($shipping_method, $shipping_price) = explode( "|", $shipping_price );
					$shipping_name = $shipping_field[0]["label"] . " ($shipping_method)";
				}
			}

			$shipping_price = GFCommon::to_number( $shipping_price );

			$product_info = array("products" => $products, "shipping" => array("name" => $shipping_name, "price" => $shipping_price));

			$product_info = apply_filters( "gform_product_info_{$form["id"]}", apply_filters( "gform_product_info", $product_info, $form, $lead ), $form, $lead );
			return $product_info;
		}

		function get_redirect_url( $url ) {
			global $woocommerce;

			if ( !empty( $url ) ) {
				return $url;
			} elseif ( get_option( 'woocommerce_cart_redirect_after_add' ) == 'yes' && WC_GFPA_Compatibility::wc_error_count() == 0 ) {
				$url = $woocommerce->cart->get_cart_url();
			} else {
				$ref = false;
				if ( !empty( $_REQUEST['_wp_http_referer'] ) )
					$ref = $_REQUEST['_wp_http_referer'];
				else if ( !empty( $_SERVER['HTTP_REFERER'] ) )
					$ref = $_SERVER['HTTP_REFERER'];

				$url = $ref ? $ref : $url;
			}

			return $url;
		}

		public function on_get_order_again_cart_item_data( $data, $item, $order ) {

			//disable validation
			remove_filter( 'woocommerce_add_to_cart_validation', array($this, 'add_to_cart_validation'), 99, 3 );

			$history = isset( $item['gravity_forms_history'] ) ? maybe_unserialize( $item['gravity_forms_history'] ) : false;
			if ( !$history ) {
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

	}

	$woocommerce_gravityforms = new woocommerce_gravityforms();
}
