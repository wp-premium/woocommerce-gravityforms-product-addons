<div class="wc-product-data-metabox-group-field">
    <div class="wc-product-data-metabox-group-field-title">
        <a href="javascript:;"><?php echo esc_html($product['name']) ?> - <?php echo esc_html($product['id']); ?></a></div>

    <div class="wc-product-data-metabox-group-field-content" style="display:none;">


        <div class="options_group">

	        <?php

	        woocommerce_wp_select(
		        array(
			        'id'          => 'gravityform_product_configuration[' . $gravity_form_data['id'] . '_' . $product['id'] . '][product_page_calculation_type]',
			        'label'       => __( 'Product Page Options Calculation Type', 'wc_gf_addons' ),
			        'value'       => isset( $gravity_form_data['product_configuration'][$gravity_form_data['id'] . '_' . $product['id']]['product_page_calculation_type'] ) ? $gravity_form_data['product_configuration'][$gravity_form_data['id'] . '_' . $product['id']]['product_page_calculation_type'] : 'combine',
			        'options'     => array( 'combine' => 'Combine - Display as a single Options label', 'separate' => 'Separate - Display as separate options label' ),
			        'description' => __( "Choose how to display the Options Calculation on the single product page.  Default:  Standard", 'wc_gf_addons' )
		        )
	        );

	        ?>

	        <?php

            $default_label_options = isset( $gravity_form_data['label_options'] ) && !empty( $gravity_form_data['label_options'] ) ? $gravity_form_data['label_options'] : __('Options', 'wc_gf_addons');
	        woocommerce_wp_text_input(
		        array(
			        'id'          => 'gravityform_product_configuration[' . $gravity_form_data['id'] . '_' . $product['id'] . '][options_label]',
			        'label'       => __( 'Product Page Options Total Label', 'wc_gf_addons' ),
			        'value'       => isset( $gravity_form_data['product_configuration'][$gravity_form_data['id'] . '_' . $product['id']]['options_label'] ) ? $gravity_form_data['product_configuration'][$gravity_form_data['id'] . '_' . $product['id']]['options_label'] : $default_label_options,
			        'description' => __( "The label to display this products options on the single product page", 'wc_gf_addons' )
		        )
	        );

	        ?>


            <?php

            woocommerce_wp_select(
	            array(
		            'id'          => 'gravityform_product_configuration[' . $gravity_form_data['id'] . '_' . $product['id'] . '][calculation_type]',
		            'label'       => __( 'Calculation Type', 'wc_gf_addons' ),
		            'value'       => isset( $gravity_form_data['product_configuration'][$gravity_form_data['id'] . '_' . $product['id']]['calculation_type'] ) ? $gravity_form_data['product_configuration'][$gravity_form_data['id'] . '_' . $product['id']]['calculation_type'] : 'standard',
		            'options'     => array( 'standard' => 'Standard', 'one-time-fee' => 'One Time Fee' ),
		            'description' => __( "Choose if the product's price is added once to the cart item or is based on the cart item quantity", 'wc_gf_addons' )
	            )
            );

            ?>

            <?php

            woocommerce_wp_text_input(
	            array(
		            'id'          => 'gravityform_product_configuration[' . $gravity_form_data['id'] . '_' . $product['id'] . '][total_label]',
		            'label'       => __( 'Cart Total Label', 'wc_gf_addons' ),
		            'value'       => isset( $gravity_form_data['product_configuration'][$gravity_form_data['id'] . '_' . $product['id']]['total_label'] ) ? $gravity_form_data['product_configuration'][$gravity_form_data['id'] . '_' . $product['id']]['total_label'] : __('Total', 'wc_gf_addons'),
		            'description' => __( "The label to display this products price in the cart", 'wc_gf_addons' )
	            )
            );

            ?>

        </div>


    </div>
</div>
