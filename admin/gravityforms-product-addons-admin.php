<?php

class WC_GFPA_Admin_Controller {

	private static $instance;

	public static function register() {
		if ( self::$instance == null ) {
			self::$instance = new WC_GFPA_Admin_Controller;
		}
	}

	private function __construct() {

		add_action( 'admin_enqueue_scripts', array( $this, 'on_admin_enqueue_scripts' ), 100 );
		add_action( 'admin_notices', array( $this, 'admin_install_notices' ) );

		add_action( 'woocommerce_process_product_meta', array( $this, 'process_meta_box' ), 1, 2 );
		add_action( 'admin_notices', array( $this, 'on_admin_notices' ) );


		add_action( 'woocommerce_product_write_panel_tabs', array( $this, 'add_tab' ) );
		add_action( 'woocommerce_product_data_panels', array( $this, 'render_panel' ) );
		add_action( 'woocommerce_process_product_meta', array( $this, 'process_meta_box' ), 1, 2 );

		add_action( 'wp_ajax_wc_gravityforms_get_form_data', array( $this, 'on_wc_gravityforms_get_form_data' ) );

	}

	public function on_admin_enqueue_scripts() {
		wp_enqueue_style( 'woocommerce_gravityforms_product_addons_css', plugins_url( basename( dirname( dirname( __FILE__ ) ) ) ) . '/assets/css/admin.css' );

		$params = array(
			'nonce'                 => wp_create_nonce( 'wc_gravityforms_get_products' ),
			'text_edit_form'        => __( 'Edit ', 'wc_gf_addons' ),
			'url_edit_form'         => sprintf( '%s/admin.php?page=gf_edit_forms&id=FORMID', get_admin_url() ),
			'duplicate_form_notice' => __( 'The singular and the bulk form can not be the same form. Make a duplicate of your singular form if need be. ', 'wc_gf_addons' ),
			'product_id'            => get_the_ID()
		);

		wp_enqueue_script( 'woocommerce_gravityforms_product_addons_js', plugins_url( basename( dirname( dirname( __FILE__ ) ) ) ) . '/assets/js/admin.js', array(
			'jquery',
			'jquery-blockui'
		), wc_gfpa()->assets_version );

		wp_localize_script( 'woocommerce_gravityforms_product_addons_js', 'wc_gf_addons', $params );
	}


	public function admin_install_notices() {
		if ( ! class_exists( 'RGForms' ) ) {
			?>
            <div id="message" class="updated woocommerce-error wc-connect">
                <div class="squeezer">
                    <h4><?php _e( '<strong>Gravity Forms Not Found</strong> &#8211; The Gravity Forms Plugin is required to build and manage the forms for your products.', 'wc_gf_addons' ); ?></h4>
                    <p class="submit">
                        <a href="https://www.gravityforms.com/"
                           class="button-primary"><?php _e( 'Get Gravity Forms', 'wc_gf_addons' ); ?></a>
                    </p>
                </div>
            </div>
			<?php
		}


	}

	public function on_admin_notices() {

		if ( is_admin() ) {
			if ( is_plugin_active( 'gravity-forms-duplicate-prevention/gravityforms-duplicateprevention.php' ) || is_plugin_active_for_network( 'gravity-forms-duplicate-prevention/gravityforms-duplicateprevention.php' ) ) {
				?>
                <div id="message" class="error woocommerce-error wc-connect">
                    <div class="squeezer">
                        <h4><?php printf( __( '<strong>Gravity Forms Duplicate Prevention Active</strong></h4><p>The <strong>Gravity Forms Product Addon Extension</strong> can not function properly if this additional plugin is active.  Please <a href="%s">disable</a> it for proper functionality of the extension.</p>', 'wc_gf_addons' ), $this->na_action_link( 'gravity-forms-duplicate-prevention/gravityforms-duplicateprevention.php', 'deactivate' ) ); ?></h4>
                    </div>
                </div>
				<?php
			}
		}
	}

	/**
	 * Get activation or deactivation link of a plugin
	 *
	 * @param string $plugin plugin file name
	 * @param string $action action to perform. activate or deactivate
	 *
	 * @return string $url action url
	 */
	private function na_action_link( $plugin, $action = 'activate' ) {
		if ( strpos( $plugin, '/' ) ) {
			$plugin = str_replace( '\/', '%2F', $plugin );
		}
		$url                = sprintf( admin_url( 'plugins.php?action=' . $action . '&plugin=%s&plugin_status=all&paged=1&s' ), $plugin );
		$_REQUEST['plugin'] = $plugin;
		$url                = wp_nonce_url( $url, $action . '-plugin_' . $plugin );

		return $url;
	}


	public function add_tab() {
		?>
        <li class="gravityforms_addons_tab gravityforms_addons">
        <a href="#gravityforms_addons_data"><span><?php _e( 'Gravity Forms', 'wc_gf_addons' ); ?></span></a></li><?php
	}

	/**
	 * Add product panel.
	 */
	public function render_panel() {
		global $post;
		$product = wc_get_product( $post );
		include( dirname( __FILE__ ) . '/views/html-gravityforms-addons-wc-metabox.php' );
	}


	public function process_meta_box( $post_id, $post ) {

		// Save gravity form as serialised array
		if ( isset( $_POST['gravityform-id'] ) && ! empty( $_POST['gravityform-id'] ) ) {

			$product = wc_get_product( $post );

			$gravity_form_data = array(
				'id'                              => $_POST['gravityform-id'],
				'bulk_id'                         => isset( $_POST['gravityform-bulk-id'] ) ? $_POST['gravityform-bulk-id'] : 0,
				'display_title'                   => isset( $_POST['gravityform-display_title'] ) ? true : false,
				'display_description'             => isset( $_POST['gravityform-display_description'] ) ? true : false,
				'disable_woocommerce_price'       => isset( $_POST['gravityform-disable_woocommerce_price'] ) ? 'yes' : 'no',
				'price_before'                    => $_POST['gravityform-price-before'],
				'price_after'                     => $_POST['gravityform-price-after'],
				'disable_calculations'            => isset( $_POST['gravityform-disable_calculations'] ) ? 'yes' : 'no',
				'disable_label_subtotal'          => isset( $_POST['gravityform-disable_label_subtotal'] ) ? 'yes' : 'no',
				'disable_label_options'           => isset( $_POST['gravityform-disable_label_options'] ) ? 'yes' : 'no',
				'disable_label_total'             => isset( $_POST['gravityform-disable_label_total'] ) ? 'yes' : 'no',
				'disable_anchor'                  => isset( $_POST['gravityform-disable_anchor'] ) ? 'yes' : 'no',
				'label_subtotal'                  => $_POST['gravityform-label_subtotal'],
				'label_options'                   => $_POST['gravityform-label_options'],
				'label_total'                     => $_POST['gravityform-label_total'],
				'use_ajax'                        => isset( $_POST['gravityform_use_ajax'] ) ? $_POST['gravityform_use_ajax'] : 'no',
				'enable_cart_edit'                => isset( $_POST['gravityform-enable_cart_edit'] ) ? $_POST['gravityform-enable_cart_edit'] : 'no',
				'enable_cart_edit_remove'         => isset( $_POST['gravityform-enable_cart_edit_remove'] ) ? $_POST['gravityform-enable_cart_edit_remove'] : 'yes',
				'keep_cart_entries'               => isset( $_POST['gravityform-keep_cart_entries'] ) ? 'yes' : 'no',
				'send_notifications'              => isset( $_POST['gravityform-send_notifications'] ) ? $_POST['gravityform-send_notifications'] : 'no',
				'enable_cart_quantity_management' => isset( $_POST['gravityform-enable_cart_quantity_management'] ) ? $_POST['gravityform-enable_cart_quantity_management'] : 'no',
				'cart_quantity_field'             => isset( $_POST['gravityform-cart_quantity_field'] ) ? $_POST['gravityform-cart_quantity_field'] : '',
				'update_payment_details'          => isset( $_POST['gravityform-update_payment_details'] ) ? 'yes' : 'no',
				'display_totals_location'         => isset( $_POST['gravityform-display_totals_location'] ) ? $_POST['gravityform-display_totals_location'] : 'after',
			);

			$product->update_meta_data( '_gravity_form_data', $gravity_form_data );
			$product->save_meta_data();
		} else {
			$product = wc_get_product( $post );
			$product->delete_meta_data( '_gravity_form_data' );
			$product->save_meta_data();
		}
	}


	/** Ajax Handling */
	public function on_wc_gravityforms_get_form_data() {
		check_ajax_referer( 'wc_gravityforms_get_products', 'wc_gravityforms_security' );

		$form_id = isset( $_POST['form_id'] ) ? $_POST['form_id'] : 0;
		if ( empty( $form_id ) ) {
			wp_send_json_error( array(
				'status'  => 'error',
				'message' => __( 'No Form ID', 'wc_gf_addons' ),
			) );
			die();
		}

		$product_id = isset( $_POST['product_id'] ) ? $_POST['product_id'] : 0;

		$selected_field = '';
		if ( $product_id ) {
			$gravity_form_data = wc_gfpa()->get_gravity_form_data( $product_id );

			if ( $gravity_form_data && isset( $gravity_form_data['enable_cart_quantity_management'] ) ) {

				if ( isset( $gravity_form_data['cart_quantity_field'] ) ) {
					$selected_field = $gravity_form_data['cart_quantity_field'];
				}

			}

		}


		$form   = GFAPI::get_form( $form_id );
		$fields = GFAPI::get_fields_by_type( $form, array( 'quantity', 'number', 'singleproduct', ), true );

		if ( $fields ) {
			$options = array();
			foreach ( $fields as $field ) {
				if ( $field['disableQuantity'] !== true ) {
					$options[ $field['id'] ] = $field['label'];
				}
			}


			ob_start();
			woocommerce_wp_select(
				array(
					'id'          => 'gravityform-cart_quantity_field',
					'label'       => __( 'Quantity Field', 'wc_gf_addons' ),
					'value'       => $selected_field,
					'options'     => $options,
					'description' => __( 'A field to use to control cart item quantity.', 'wc_gf_addons' )
				)
			);

			$markup = ob_get_clean();
		} else {
			$markup = '<p class="form-field">' . __( 'No suitable quantity fields found.', 'wc_gf_addons' ) . '</p>';
		}

		/*
		$markup = '<select name="gravityform-cart_quantity_field" id="gravityform-cart_quantity_field">';

		foreach ( $fields as $field ) {
			$markup .= '<option ' . selected( $field['id'], $selected_field, false ) . ' value="' . $field['id'] . '">' . $field['label'] . '</option>';
		}

		$markup .= '</select>';
        */

		$response = array(
			'status'  => 'success',
			'message' => '',
			'markup'  => $markup
		);

		wp_send_json_success( $response );
		die();
	}


}
