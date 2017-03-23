<?php


class WC_GFPA_Order {

	private static $instance;

	public static function register() {
		if ( self::$instance == null ) {
			self::$instance = new WC_GFPA_Order();
		}
	}

	public function __construct() {
		add_action( 'woocommerce_after_order_itemmeta', array( $this, 'custom_order_item_meta' ), 10, 3 );
	}

	public function custom_order_item_meta( $item_id, $item, $product ) {

		if ( is_object( $item ) ) {

			$meta_to_display = array();
			$meta_data_items = $item->get_meta_data();

			foreach ( $meta_data_items as $meta ) {
				if ( strpos( $meta->key, '_gf_email_hidden_' ) === 0 ) {
					$meta->display_key = str_replace( '_gf_email_hidden_', '', $meta->display_key );

					$meta->key     = rawurldecode( (string) $meta->key );
					$meta->value   = rawurldecode( (string) $meta->value );
					$attribute_key = str_replace( 'attribute_', '', $meta->key );
					$display_key   = wc_attribute_label( $attribute_key, is_callable( array(
						$this,
						'get_product'
					) ) ? $this->get_product() : false );
					$display_value = $meta->value;

					if ( taxonomy_exists( $attribute_key ) ) {
						$term = get_term_by( 'slug', $meta->value, $attribute_key );
						if ( ! is_wp_error( $term ) && is_object( $term ) && $term->name ) {
							$display_value = $term->name;
						}
					}

					$meta_to_display[ $meta->id ] = (object) array(
						'key'           => $meta->key,
						'value'         => $meta->value,
						'display_key'   => apply_filters( 'woocommerce_order_item_display_meta_key', $display_key ),
						'display_value' => apply_filters( 'woocommerce_order_item_display_meta_value', wpautop( make_clickable( $display_value ) ) ),
					);
				}

				if ( $meta->key == '_gravity_forms_history' ) {
					$entry_id = isset( $meta->value['_gravity_form_linked_entry_id'] ) ? $meta->value['_gravity_form_linked_entry_id'] : false;

					if ( $entry_id ) {

						$entry = GFAPI::get_entry( $entry_id );
						if ( $entry && !is_wp_error($entry) ) {
							$display_value = '<a href="' . admin_url( 'admin.php?page=gf_entries&view=entry&id=' . $entry['form_id'] . '&lid=' . $entry_id ) . '">' . __( 'View', 'wc_gf_addons' ) . '</a>';
							$meta_to_display[ 'gravity_form_entry_' . $entry_id ] = (object) array(
								'key'           => 'gravity_form_entry',
								'value'         => $entry_id,
								'display_key'   => apply_filters( 'woocommerce_order_item_display_meta_key', 'Form Entry' ),
								'display_value' => apply_filters( 'woocommerce_order_item_display_meta_value', wpautop( make_clickable( $display_value ) ) ),
							);
						}
					}

				}
			}

			if ( ! empty( $meta_to_display ) ) {
				include( 'html-order-item-meta.php' );
			}
		}

	}
}