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

<div id="gravityforms_addons_data" class="panel woocommerce_options_panel wc-metaboxes-wrapper hidden">

	<?php if ( ! $product->is_type( 'external' ) && $product->get_status() == 'publish' && ! $product->is_purchasable() ) : ?>
        <div style="margin:5px 0 15px;border-left:4px solid red;padding:1px 12px;box-shadow: 0 1px 1px 0 rgba(0,0,0,.1);">
            <p>You must set a price for the product before the gravity form will be visible. Set the price to 0 if you
                are performing all price calculations with the attached Gravity Form.</p>
        </div>
	<?php endif; ?>


    <div class="options_group">
        <p class="form-field">
            <label for="gravityform-id"><?php _e( 'Choose Form', 'wc_gf_addons' ); ?></label>
			<?php
			echo '<select id="gravityform-id" name="gravityform-id"><option value="">' . __( 'None', 'wc_gf_addons' ) . '</option>';
			foreach ( RGFormsModel::get_forms() as $form ) {
				echo '<option ' . selected( $form->id, ( isset( $gravity_form_data['id'] ) ? $gravity_form_data['id'] : 0 ) ) . ' value="' . esc_attr( $form->id ) . '">' . wptexturize( $form->title ) . '</option>';
			}
			echo '</select>';
			?>
			<?php if ( ! empty( $gravityform ) && is_object( $gravityform ) ) : ?>
                <span class="edit_form_link">
                    <a target="_blank"
                       href="<?php printf( '%s/admin.php?page=gf_edit_forms&id=%d', get_admin_url(), $gravityform->id ); ?>"
                       class="edit_gravityform"><?php _e( 'Edit', 'wc_gf_addons' ); ?> <?php echo $gravityform->title; ?>
                        Gravity Form</a>
                        </span>
			<?php else: ?>
                <span class="edit_form_link">
                            <a target="_blank"
                               href="<?php printf( '%s/admin.php?page=gf_edit_forms', get_admin_url() ); ?>"
                               class="edit_gravityform"><?php _e( 'Browse all your forms', 'wc_gf_addons' ); ?></a>
                        </span>
			<?php endif; ?>
        </p>

		<?php if ( WC_GFPA_Bulk_Variations_Check::woocommerce_bulk_variations_active_check() && $product->is_type( 'variable' ) ): ?>
			<?php $axis_attributes = $product->get_variation_attributes(); //Attributes configured on this product already.
			if ( count( $axis_attributes ) === 2 ) : ?>
				<?php

				$bulk_gravityform = null;
				if ( is_array( $gravity_form_data ) && isset( $gravity_form_data['bulk_id'] ) && is_numeric( $gravity_form_data['bulk_id'] ) ) {
					$bulk_form_meta = RGFormsModel::get_form_meta( $gravity_form_data['bulk_id'] );
					if ( ! empty( $form_meta ) ) {
						$bulk_gravityform = RGFormsModel::get_form( $gravity_form_data['bulk_id'] );
					}
				}

				?>

                <p class="form-field bulk-variation-form-field">
                    <label for="gravityform-bulk-id"><?php _e( 'Choose Bulk Form', 'wc_gf_addons' ); ?></label>
					<?php
					echo '<select id="gravityform-bulk-id" name="gravityform-bulk-id"><option value="">' . __( 'None', 'wc_gf_addons' ) . '</option>';
					foreach ( RGFormsModel::get_forms() as $form ) {
						echo '<option ' . selected( $form->id, ( isset( $gravity_form_data['bulk_id'] ) ? $gravity_form_data['bulk_id'] : 0 ) ) . ' value="' . esc_attr( $form->id ) . '">' . wptexturize( $form->title ) . '</option>';
					}
					echo '</select>';
					?>
					<?php if ( ! empty( $bulk_gravityform ) && is_object( $bulk_gravityform ) ) : ?>
                        <span class="edit_bulk_form_link">
                    <a target="_blank"
                       href="<?php printf( '%s/admin.php?page=gf_edit_forms&id=%d', get_admin_url(), $bulk_gravityform->id ); ?>"
                       class="edit_gravityform"><?php _e( 'Edit', 'wc_gf_addons' ); ?> <?php echo $bulk_gravityform->title; ?>
                        Gravity Form</a>
                        </span>
					<?php else: ?>
                        <span class="edit_bulk_form_link">
                            <a target="_blank"
                               href="<?php printf( '%s/admin.php?page=gf_edit_forms', get_admin_url() ); ?>"
                               class="edit_gravityform"><?php _e( 'Browse all your forms', 'wc_gf_addons' ); ?></a>
                        </span>
					<?php endif; ?>
                </p>
			<?php endif; ?>
		<?php endif; ?>

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


    <div class="gforms-panel" style="<?php echo empty( $gravityform ) ? 'display:none;' : 'display:block'; ?>">
        <div class="wc-product-data-metabox">

            <div class="wc-product-data-metabox-group">
                <div class="wc-product-data-metabox-group-title"><?php _e( 'Form Display', 'wc_gf_addons' ); ?></div>

                <div class="wc-product-data-metabox-group-field">
                    <div class="wc-product-data-metabox-group-field-title">
                        <a href="javascript:;"><?php _e( 'Multi Page Forms', 'wc_gf_addons' ); ?></a></div>

                    <div class="wc-product-data-metabox-group-field-content" style="display:none;">

                        <div id="multipage_forms_data"
                             class="gforms-panel options_group" <?php echo empty( $gravity_form_data['id'] ) ? "style='display:none;'" : ''; ?>>
                            <h4><?php _e( 'Multipage Forms', 'wc_gf_addons' ); ?></h4>
							<?php
							woocommerce_wp_checkbox( array(
								'id'    => 'gravityform-disable_anchor',
								'label' => __( 'Disable Gravity Forms Anchors', 'wc_gf_addons' ),
								'value' => isset( $gravity_form_data['disable_anchor'] ) ? $gravity_form_data['disable_anchor'] : ''
							) );
							?>
                        </div>

                    </div>

                </div>


                <div class="wc-product-data-metabox-group-field">
                    <div class="wc-product-data-metabox-group-field-title">
                        <a href="javascript:;"><?php _e( 'Price Labels', 'wc_gf_addons' ); ?></a>
                    </div>

                    <div class="wc-product-data-metabox-group-field-content" style="display:none;">
                        <div id="price_labels_data"
                             class="gforms-panel options_group" <?php echo empty( $gravity_form_data['id'] ) ? "style='display:none;'" : ''; ?>>


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
                    </div>
                </div>


                <div class="wc-product-data-metabox-group-field">
                    <div class="wc-product-data-metabox-group-field-title">
                        <a href="javascript:;"><?php _e( 'Total Calculations', 'wc_gf_addons' ); ?></a>
                    </div>

                    <div class="wc-product-data-metabox-group-field-content" style="display:none;">

                        <div id="total_labels_data"
                             class="gforms-panel options_group" <?php echo empty( $gravity_form_data['id'] ) ? "style='display:none;'" : ''; ?>>
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
								'value'       => isset( $gravity_form_data['label_subtotal'] ) && ! empty( $gravity_form_data['label_subtotal'] ) ? $gravity_form_data['label_subtotal'] : __( 'Subtotal', 'wc_gf_addons' ),
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
								'value'       => isset( $gravity_form_data['label_options'] ) && ! empty( $gravity_form_data['label_options'] ) ? $gravity_form_data['label_options'] : __( 'Options', 'wc_gf_addons' ),
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
								'value'       => isset( $gravity_form_data['label_total'] ) && ! empty( $gravity_form_data['label_total'] ) ? $gravity_form_data['label_total'] : __( 'Total', 'wc_gf_addons' ),
								'placeholder' => __( 'Total', 'wc_gf_addons' ),
								'description' => __( 'Enter the "Total" label to display for single products.', 'wc_gf_addons' )
							) );

							woocommerce_wp_select(
								array(
									'id'          => 'gravityform-display_totals_location',
									'label'       => __( 'Totals Section Placement', 'wc_gf_addons' ),
									'value'       => isset( $gravity_form_data['display_totals_location'] ) ? $gravity_form_data['display_totals_location'] : 'after',
									'std'         => 'after',
									'default'     => 'after',
									'options'     => array( 'after' => 'After Form', 'before' => 'Before Form' ),
									'description' => __( 'Choose where to display the total calculations.  After form is the default.  .', 'wc_gf_addons' )
								)
							);

							?>
                        </div>
                    </div>
                </div>

                <div class="wc-product-data-metabox-group-field">
                    <div class="wc-product-data-metabox-group-field-title">
                        <a href="javascript:;"><?php _e( 'Entries and Notifications', 'wc_gf_addons' ); ?></a>
                    </div>

                    <div class="wc-product-data-metabox-group-field-content" style="display:none;">

                        <div id="entries_and_notifications_options_data"
                             class="gforms-panel options_group" <?php echo empty( $gravity_form_data['id'] ) ? "style='display:none;'" : ''; ?>>

							<?php

							woocommerce_wp_checkbox( array(
								'id'          => 'gravityform-keep_cart_entries',
								'label'       => __( 'Keep Cart Entries?', 'wc_gf_addons' ),
								'value'       => isset( $gravity_form_data['keep_cart_entries'] ) ? $gravity_form_data['keep_cart_entries'] : 'no',
								'description' => __( 'Entries are created when the order is placed. Check this to keep the copy when the item is added to the cart.', 'wc_gf_addons' )
							) );

							?>

							<?php

							woocommerce_wp_checkbox( array(
								'id'          => 'gravityform-update_payment_details',
								'label'       => __( 'Update Payment and Transaction Details?', 'wc_gf_addons' ),
								'value'       => isset( $gravity_form_data['update_payment_details'] ) ? $gravity_form_data['update_payment_details'] : 'no',
								'description' => __( 'Automatically update the entry transaction details based on the WooCommerce Order.', 'wc_gf_addons' )
							) );

							?>

							<?php

							woocommerce_wp_checkbox( array(
								'id'          => 'gravityform-send_notifications',
								'label'       => __( 'Send Notifications?', 'wc_gf_addons' ),
								'value'       => isset( $gravity_form_data['send_notifications'] ) ? $gravity_form_data['send_notifications'] : 'no',
								'description' => __( 'Send any form notifications configured when an item is ordered.', 'wc_gf_addons' )
							) );

							?>


                        </div>
                    </div>
                </div>


                <div class="wc-product-data-metabox-group-field">
                    <div class="wc-product-data-metabox-group-field-title">
                        <a href="javascript:;"><?php _e( 'Advanced Options', 'wc_gf_addons' ); ?></a>
                    </div>

                    <div class="wc-product-data-metabox-group-field-content" style="display:none;">

                        <div class="gforms-panel options_group" <?php echo empty( $gravity_form_data['id'] ) ? "style='display:none;'" : ''; ?>>
                            <div class="wc-product-data-metabox-option-group-label">
								<?php _e( 'Cart Options', 'wc_gf_addons' ); ?>
                            </div>

							<?php

							woocommerce_wp_checkbox( array(
								'id'    => 'gravityform-enable_cart_edit',
								'label' => __( 'Enable Cart Edit?', 'wc_gf_addons' ),
								'value' => isset( $gravity_form_data['enable_cart_edit'] ) ? $gravity_form_data['enable_cart_edit'] : 'no'
							) );

							?>

							<?php

							woocommerce_wp_select( array(
								'id'          => 'gravityform-enable_cart_edit_remove',
								'label'       => __( 'Replace Modified Items?', 'wc_gf_addons' ),
								'value'       => isset( $gravity_form_data['enable_cart_edit_remove'] ) ? $gravity_form_data['enable_cart_edit_remove'] : 'yes',
								'options'     => array(
									'yes' => __( 'Yes:  Replace modified items in the cart.' ),
									'no'  => __( 'No:  Add additional items to the cart.' )
								),
								'description' => __( 'When items are modified choose to remove the modified item if different or to keep in the cart.  If keeping the items, additional items will be added to the cart with the modified data.  If replacing the items, the original cart item will be replaced with the updated submission data.', 'wc_gf_addons' )

							) );

							?>

                        </div>


                        <div class="gforms-panel options_group" <?php echo empty( $gravity_form_data['id'] ) ? "style='display:none;'" : ''; ?>>
                            <div class="wc-product-data-metabox-option-group-label">
								<?php _e( 'Quantity / Stock Options', 'wc_gf_addons' ); ?>
                            </div>

							<?php

							woocommerce_wp_select( array(
								'id'          => 'gravityform-enable_cart_quantity_management',
								'label'       => __( 'Enable Quantity Management?', 'wc_gf_addons' ),
								'value'       => isset( $gravity_form_data['enable_cart_quantity_management'] ) ? $gravity_form_data['enable_cart_quantity_management'] : 'no',
								'options'     => array(
									'no'    => __( 'No' ),
									'yes'   => __( 'Yes - Set Cart Item Quantity' ),
									'stock' => __( 'Yes - Set Order Item Stock Quantity' )
								),
								'description' => __( 'Control the cart item or stock reduction quantity with a Gravity Form field.', 'wc_gf_addons' )

							) );

							?>

                            <div id="gravityform-quantity-field">
								<?php

								if ( isset( $gravity_form_data['enable_cart_quantity_management'] ) && $gravity_form_data['enable_cart_quantity_management'] != 'no' ) :

									if ( $gravity_form_data['id'] ) {
										$form   = GFAPI::get_form( $gravity_form_data['id'] );
										$fields = GFAPI::get_fields_by_type( $form, array(
											'quantity',
											'number',
											'singleproduct',
										), true );

										$options = array();
										foreach ( $fields as $field ) {
											if ( $field['disableQuantity'] !== true ) {
												$options[ $field['id'] ] = $field['label'];
											}
										}
									} else {
										$options = array();
									}

									woocommerce_wp_select(
										array(
											'id'          => 'gravityform-cart_quantity_field',
											'label'       => __( 'Quantity Field', 'wc_gf_addons' ),
											'value'       => isset( $gravity_form_data['cart_quantity_field'] ) ? $gravity_form_data['cart_quantity_field'] : '',
											'options'     => $options,
											'description' => __( 'A field to use to control cart item quantity.', 'wc_gf_addons' )
										)
									);

								endif;
								?>
                            </div>

                        </div>
                    </div>
                </div>


            </div>

        </div>
    </div>
</div>
