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
			$meta_data_items = $item->get_formatted_meta_data( '' );
			if ( $meta_data_items ) {
				$meta_to_display = array();
				foreach ( $meta_data_items as $meta_data_item ) {
					if ( strpos( $meta_data_item->key, '_gf_email_hidden_' ) === 0 ) {
						$meta_data_item->display_key = str_replace( '_gf_email_hidden_', '', $meta_data_item->display_key );
						$meta_to_display[]           = $meta_data_item;
					}

					if ( $meta_data_item->key == '_gravity_forms_history' ) {
						$entry_id = isset( $meta_data_item['_gravity_form_linked_entry_id'] ) ? $meta_data_item['_gravity_form_linked_entry_id'] : false;

						if ( $entry_id ) {

							$entry = GFAPI::get_entry( $entry_id );
							if ( $entry && !is_wp_error($entry) ) {
								$display_value                                        = '<a href="' . admin_url( 'admin.php?page=gf_entries&view=entry&id=' . $entry['form_id'] . '&lid=' . $entry_id ) . '">' . __( 'View', 'wc_gf_addons' ) . '</a>';
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
			}
		} else {
			$meta_data_items = $item['item_meta'];
			if ( $meta_data_items ) {
				$meta_to_display = array();
				foreach ( $meta_data_items as $meta_key => $meta_data_item ) {

					if ( $meta_key == '_gravity_forms_history' ) {
						$meta_data_item = unserialize($meta_data_item[0]);
						$entry_id = isset( $meta_data_item['_gravity_form_linked_entry_id'] ) ? $meta_data_item['_gravity_form_linked_entry_id'] : false;

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
			}
		}


		if ( ! empty( $meta_to_display ) ) {
			include( 'html-order-item-meta.php' );
		}
	}
}