<?php

class WC_GFPA_Admin_Controller {

	private static $instance;

	public static function register() {
		if ( self::$instance == null ) {
			self::$instance = new WC_GFPA_Admin_Controller;
		}
	}

	private function __construct() {
		add_action( 'admin_notices', array($this, 'admin_install_notices') );
		add_action( 'add_meta_boxes', array($this, 'add_meta_box') );
		add_action( 'woocommerce_process_product_meta', array($this, 'process_meta_box'), 1, 2 );
	}

	public function admin_install_notices() {
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

	public function add_meta_box() {
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
				    'placeholder' => '', 'description' => __( 'Enter text you would like printed after the price of the product.', 'wc_gf_addons' )) );
				?>
			</div>
		</div>

		<div id="total_labels_data" class="gforms-panel panel woocommerce_options_panel" <?php echo empty( $gravity_form_data['id'] ) ? "style='display:none;'" : ''; ?>>
			<h4><?php _e( 'Total Calculations', 'wc_gf_addons' ); ?></h4>
			<?php
			echo '<div class="options_group">';
			if ( class_exists( 'WC_Dynamic_Pricing' ) ) {
				woocommerce_wp_select(
					array(
					    'id' => 'gravityform_use_ajax',
					    'label' => __( 'Enable Dynamic Pricing?', 'wc_gf_addons' ),
					    'value' => isset( $gravity_form_data['use_ajax'] ) ? $gravity_form_data['use_ajax'] : '',
					    'options' => array('no' => 'No', 'yes' => 'Yes'),
					    'description' => __( 'Enable Dynamic Pricing calculations if you are using Dynamic Pricing to modify the price of this product.', 'wc_gf_addons' )
					)
				);
			}
			echo '</div>';
			echo '<div class="options_group">';
			woocommerce_wp_checkbox( array(
			    'id' => 'gravityform-disable_calculations',
			    'label' => __( 'Disable Calculations?', 'wc_gf_addons' ),
			    'value' => isset( $gravity_form_data['disable_calculations'] ) ? $gravity_form_data['disable_calculations'] : '') );
			echo '</div>';

			echo '<div class="options_group">';
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

	public function process_meta_box( $post_id, $post ) {
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
			    'label_total' => $_POST['gravityform-label_total'],
			    'use_ajax' => isset( $_POST['gravityform_use_ajax'] ) ? $_POST['gravityform_use_ajax'] : 'no'
			);
			update_post_meta( $post_id, '_gravity_form_data', $gravity_form_data );
		} else {
			delete_post_meta( $post_id, '_gravity_form_data' );
		}
	}

}
