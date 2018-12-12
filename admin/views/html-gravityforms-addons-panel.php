<div id="gravityforms_addons_data" class="panel woocommerce_options_panel wc-metaboxes-wrapper hidden">

	<?php if ( !$product->is_type('external') && $product->get_status() == 'publish' && ! $product->is_purchasable() ) : ?>
		<div style="margin:5px 0 15px;border-left:4px solid red;padding:1px 12px;box-shadow: 0 1px 1px 0 rgba(0,0,0,.1);">
			<p>You must set a price for the product before the gravity form will be visible. Set the price to 0 if you are performing all price calculations with the attached Gravity Form.</p>
		</div>
	<?php endif; ?>

	<h4><?php _e( 'General', 'wc_gf_addons' ); ?></h4>
	<?php
	$gravity_form_data = $product->get_meta( '_gravity_form_data', true );
	$gravityform       = null;
	if ( is_array( $gravity_form_data ) && isset( $gravity_form_data['id'] ) && is_numeric( $gravity_form_data['id'] ) ) {

		$form_meta = RGFormsModel::get_form_meta( $gravity_form_data['id'] );

		if ( ! empty( $form_meta ) ) {
			$gravityform = RGFormsModel::get_form( $gravity_form_data['id'] );
		}
	}
	?>
	<div class="options_group">
		<p class="form-field">
			<label for="gravityform-id"><strong><?php _e( 'Choose Form', 'wc_gf_addons' ); ?></strong></label>
			<?php
			echo '<select id="gravityform-id" name="gravityform-id"><option value="">' . __( 'None', 'wc_gf_addons' ) . '</option>';
			foreach ( RGFormsModel::get_forms() as $form ) {
				echo '<option ' . selected( $form->id, ( isset( $gravity_form_data['id'] ) ? $gravity_form_data['id'] : 0 ) ) . ' value="' . esc_attr( $form->id ) . '">' . wptexturize( $form->title ) . '</option>';
			}
			echo '</select>';
			?>
			<?php if ( ! empty( $gravityform ) && is_object( $gravityform ) ) : ?>
				<span class="edit_form_link">
                    <a target="_blank" href="<?php printf( '%s/admin.php?page=gf_edit_forms&id=%d', get_admin_url(), $gravityform->id ); ?>" class="edit_gravityform"><?php _e('Edit', 'wc_gf_addons'); ?> <?php echo $gravityform->title; ?> Gravity Form</a>
                </span>
			<?php else: ?>
				<span class="edit_form_link">
                    <a target="_blank" href="<?php printf( '%s/admin.php?page=gf_edit_forms', get_admin_url()); ?>" class="edit_gravityform"><?php _e('Browse all your forms', 'wc_gf_addons'); ?></a>
                </span>
			<?php endif; ?>
		</p>

		<?php
		woocommerce_wp_checkbox( array(
			'id'    => 'gravityform-display_title',
			'label' => __( 'Display Title', 'wc_gf_addons' ),
			'value' => isset( $gravity_form_data['display_title'] ) && $gravity_form_data['display_title'] ? 'yes' : ''
		) );

		woocommerce_wp_checkbox( array(
			'id'    => 'gravityform-display_description',
			'label' => __( 'Display Description', 'wc_gf_addons' ),
			'value' => isset( $gravity_form_data['display_description'] ) && $gravity_form_data['display_description'] ? 'yes' : ''
		) );;
		?>
	</div>


	<div id="multipage_forms_data" class="gforms-panel options_group" <?php echo empty( $gravity_form_data['id'] ) ? "style='display:none;'" : ''; ?>>
		<h4><?php _e( 'Multipage Forms', 'wc_gf_addons' ); ?></h4>
		<?php
		woocommerce_wp_checkbox( array(
			'id'    => 'gravityform-disable_anchor',
			'label' => __( 'Disable Gravity Forms Anchors', 'wc_gf_addons' ),
			'value' => isset( $gravity_form_data['disable_anchor'] ) ? $gravity_form_data['disable_anchor'] : ''
		) );
		?>
	</div>

	<div id="price_labels_data" class="gforms-panel options_group" <?php echo empty( $gravity_form_data['id'] ) ? "style='display:none;'" : ''; ?>>
		<h4><?php _e( 'Price Labels', 'wc_gf_addons' ); ?></h4>

		<?php

		woocommerce_wp_checkbox( array(
			'id'    => 'gravityform-disable_woocommerce_price',
			'label' => __( 'Remove WooCommerce Price?', 'wc_gf_addons' ),
			'value' => isset( $gravity_form_data['disable_woocommerce_price'] ) ? $gravity_form_data['disable_woocommerce_price'] : ''
		) );

		woocommerce_wp_text_input( array(
			'id'          => 'gravityform-price-before',
			'label'       => __( 'Price Before', 'wc_gf_addons' ),
			'value'       => isset( $gravity_form_data['price_before'] ) ? $gravity_form_data['price_before'] : '',
			'placeholder' => __( 'Base Price:', 'wc_gf_addons' ),
			'description' => __( 'Enter text you would like printed before the price of the product.', 'wc_gf_addons' )
		) );

		woocommerce_wp_text_input( array(
			'id'          => 'gravityform-price-after',
			'label'       => __( 'Price After', 'wc_gf_addons' ),
			'value'       => isset( $gravity_form_data['price_after'] ) ? $gravity_form_data['price_after'] : '',
			'placeholder' => '',
			'description' => __( 'Enter text you would like printed after the price of the product.', 'wc_gf_addons' )
		) );

		?>

	</div>

	<div id="total_labels_data" class="gforms-panel options_group" <?php echo empty( $gravity_form_data['id'] ) ? "style='display:none;'" : ''; ?>>
		<h4><?php _e( 'Total Calculations', 'wc_gf_addons' ); ?></h4>
		<?php

		if ( class_exists( 'WC_Dynamic_Pricing' ) ) {

			woocommerce_wp_select(
				array(
					'id'          => 'gravityform_use_ajax',
					'label'       => __( 'Enable Dynamic Pricing?', 'wc_gf_addons' ),
					'value'       => isset( $gravity_form_data['use_ajax'] ) ? $gravity_form_data['use_ajax'] : '',
					'options'     => array( 'no' => 'No', 'yes' => 'Yes' ),
					'description' => __( 'Enable Dynamic Pricing calculations if you are using Dynamic Pricing to modify the price of this product.', 'wc_gf_addons' )
				)
			);

		}


		woocommerce_wp_checkbox( array(
			'id'    => 'gravityform-disable_calculations',
			'label' => __( 'Disable Calculations?', 'wc_gf_addons' ),
			'value' => isset( $gravity_form_data['disable_calculations'] ) ? $gravity_form_data['disable_calculations'] : ''
		) );


		woocommerce_wp_checkbox( array(
			'id'    => 'gravityform-disable_label_subtotal',
			'label' => __( 'Disable Subtotal?', 'wc_gf_addons' ),
			'value' => isset( $gravity_form_data['disable_label_subtotal'] ) ? $gravity_form_data['disable_label_subtotal'] : ''
		) );

		woocommerce_wp_text_input( array(
			'id'          => 'gravityform-label_subtotal',
			'label'       => __( 'Subtotal Label', 'wc_gf_addons' ),
			'value'       => isset( $gravity_form_data['label_subtotal'] ) && ! empty( $gravity_form_data['label_subtotal'] ) ? $gravity_form_data['label_subtotal'] : 'Subtotal',
			'placeholder' => __( 'Subtotal', 'wc_gf_addons' ),
			'description' => __( 'Enter "Subtotal" label to display on for single products.', 'wc_gf_addons' )
		) );

		woocommerce_wp_checkbox( array(
			'id'    => 'gravityform-disable_label_options',
			'label' => __( 'Disable Options Label?', 'wc_gf_addons' ),
			'value' => isset( $gravity_form_data['disable_label_options'] ) ? $gravity_form_data['disable_label_options'] : ''
		) );

		woocommerce_wp_text_input( array(
			'id'          => 'gravityform-label_options',
			'label'       => __( 'Options Label', 'wc_gf_addons' ),
			'value'       => isset( $gravity_form_data['label_options'] ) && ! empty( $gravity_form_data['label_options'] ) ? $gravity_form_data['label_options'] : 'Options',
			'placeholder' => __( 'Options', 'wc_gf_addons' ),
			'description' => __( 'Enter the "Options" label to display for single products.', 'wc_gf_addons' )
		) );

		woocommerce_wp_checkbox( array(
			'id'    => 'gravityform-disable_label_total',
			'label' => __( 'Disable Total Label?', 'wc_gf_addons' ),
			'value' => isset( $gravity_form_data['disable_label_total'] ) ? $gravity_form_data['disable_label_total'] : ''
		) );

		woocommerce_wp_text_input( array(
			'id'          => 'gravityform-label_total',
			'label'       => __( 'Total Label', 'wc_gf_addons' ),
			'value'       => isset( $gravity_form_data['label_total'] ) && ! empty( $gravity_form_data['label_total'] ) ? $gravity_form_data['label_total'] : 'Total',
			'placeholder' => __( 'Total', 'wc_gf_addons' ),
			'description' => __( 'Enter the "Total" label to display for single products.', 'wc_gf_addons' )
		) );

		?>
	</div>
</div>