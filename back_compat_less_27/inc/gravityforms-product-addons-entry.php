<?php

/**
 * This class helps us link us a Gravity Form entry to a WooCommerce order.
 * The data is displayed when viewing an entry and when exporting the Gravity Form data.
 * Class WC_GFPA_Entry
 */
class WC_GFPA_Entry {

	private static $instance;

	public static function register() {
		if ( self::$instance == null ) {
			self::$instance = new WC_GFPA_Entry();
		}
	}

	private $_order_items = array();

	private function __construct() {
		add_filter( 'gform_entry_detail_meta_boxes', array( $this, 'add_custom_entry_metabox' ), 10, 3 );
		add_action( 'woocommerce_order_add_product', array( $this, 'record_current_order_id' ), 10, 2 );
		add_action( 'woocommerce_gforms_create_entry', array( $this, 'create_entry' ), 10, 3 );

		add_filter( 'gform_export_fields', array( $this, 'add_wc_order_fields' ), 10, 1 );
		add_filter( 'gform_export_field_value', array( $this, 'export_wc_order_fields' ), 10, 4 );

		add_action( 'gform_entry_detail_content_before', array( $this, 'entry_detail_screen_notice' ), 10, 2 );
	}


	public function record_current_order_id( $order_id, $order_item_id ) {
		$this->_order_items[ $order_item_id ] = $order_id;
	}

	public function create_entry( $order_item_id, $form_id, $lead_data ) {
		unset( $lead_data['lead_id'] );
		$entry_id = GFAPI::add_entry( $lead_data );
		if ( $entry_id && ! is_wp_error( $entry_id ) ) {
			gform_update_meta( $entry_id, 'woocommerce_order_number', $this->_order_items[ $order_item_id ], $form_id );
			gform_update_meta( $entry_id, 'woocommerce_order_item_number', $order_item_id, $form_id );

			$new_history = wc_get_order_item_meta($order_item_id, '_gravity_forms_history');
			$new_history['_gravity_form_linked_entry_id'] = $entry_id;
			wc_update_order_item_meta($order_item_id, '_gravity_forms_history', $new_history);
		}
	}

	//Entry admin screen

	public function entry_detail_screen_notice( $form, $lead ) {
		$order_id = gform_get_meta( $lead['id'], 'woocommerce_order_number' );
		if ($order_id) {
			$the_order = wc_get_order($order_id);

			echo '<div style="padding: 20px;background:#fff;border:1px solid #e5e5e5;margin: 5px 1px 2px 1px">';
				echo '<h3>' . __('WooCommerce Order Item', 'wc_gf_addons') . '</h3>';
			if ($the_order) {
				echo '<p>';
				echo sprintf( _x( 'This entry was created as part WooCommerce Order %s', 'Order number', 'woocommerce' ), '<a href="' . admin_url( 'post.php?post=' . absint( $order_id ) . '&action=edit' ) . '" class="row-title"><strong>#' . esc_attr( $the_order->get_order_number() ) . '</strong></a>' );
				echo '<br />';
				echo '<br />';
				echo '<em>' . __( 'Any changes made on this entry directly will not be reflected on the actual order.', 'wc_gf_addons' ) . '</em>';
				echo '</p>';
			} else {
				echo '<p>';
				echo sprintf( _x( 'This entry was created as part WooCommerce Order %s', 'Order number', 'woocommerce' ), $order_id );
				echo '<br />';
				echo '<br />';
				echo '<em>' . __( 'The WooCommerce order no longer exists.', 'wc_gf_addons' ) . '</em>';
				echo '</p>';
			}
			echo '</div>';
		}
	}

	public function add_custom_entry_metabox( $meta_boxes, $entry, $form ) {
		$order_id  = gform_get_meta( $entry['id'], 'woocommerce_order_number' );
		$the_order = wc_get_order( $order_id );
		if ( $the_order ) {
			$meta_boxes['notes'] = array(
				'title'    => esc_html__( 'WooCommerce', 'woocommerce' ),
				'callback' => array( $this, 'render_custom_metabox' ),
				'context'  => 'side',
			);
		}

		return $meta_boxes;
	}

	public function render_custom_metabox( $args, $metabox ) {
		$form  = $args['form'];
		$entry = $args['entry'];
		$mode  = $args['mode'];

		$order_id      = gform_get_meta( $entry['id'], 'woocommerce_order_number' );
		$order_item_id = gform_get_meta( $entry['id'], 'woocommerce_order_item_number' );
		$the_order     = wc_get_order( $order_id );

		if ( $the_order ) {
			echo '<div>';
			if ( current_user_can( 'manage_woocommerce' ) ) {

				if ( $the_order->user_id ) {
					$user_info = get_userdata( $the_order->user_id );
				}

				if ( ! empty( $user_info ) ) {

					$username = '<a href="user-edit.php?user_id=' . absint( $user_info->ID ) . '">';

					if ( $user_info->first_name || $user_info->last_name ) {
						$username .= esc_html( sprintf( _x( '%1$s %2$s', 'full name', 'woocommerce' ), ucfirst( $user_info->first_name ), ucfirst( $user_info->last_name ) ) );
					} else {
						$username .= esc_html( ucfirst( $user_info->display_name ) );
					}

					$username .= '</a>';

				} else {
					if ( $the_order->billing_first_name || $the_order->billing_last_name ) {
						$username = trim( sprintf( _x( '%1$s %2$s', 'full name', 'woocommerce' ), $the_order->billing_first_name, $the_order->billing_last_name ) );
					} else if ( $the_order->billing_company ) {
						$username = trim( $the_order->billing_company );
					} else {
						$username = __( 'Guest', 'woocommerce' );
					}
				}

				$order_items = $the_order->get_items();

				echo '<p><strong>' . __( 'Order', 'wc_gf_addons' ) . ': </strong>' . sprintf( _x( '%s by %s', 'Order number by X', 'woocommerce' ), '<a href="' . admin_url( 'post.php?post=' . absint( $order_id ) . '&action=edit' ) . '" class="row-title"><strong>#' . esc_attr( $the_order->get_order_number() ) . '</strong></a>', $username );

				if ( isset( $order_items[ $order_item_id ] ) ) {
					echo '<p><strong>' . __( 'Product', 'wc_gf_addons' ) . ': </strong>' . '<a href="' . admin_url( 'post.php?post=' . absint( $order_items[ $order_item_id ]['product_id'] ) . '&action=edit' ) . '">' . $order_items[ $order_item_id ]['name'] . '</a></p>';
				}

			}

			echo '</div>';
		}


	}


	//Export management
	public function add_wc_order_fields( $form ) {
		array_push( $form['fields'], array(
			'id'    => 'woocommerce_order_number',
			'label' => __( 'WooCommerce Order Number', 'wc_gf_addons' )
		) );

		array_push( $form['fields'], array(
			'id'    => 'woocommerce_order_item_number',
			'label' => __( 'WooCommerce Order Item Line Number', 'wc_gf_addons' )
		) );

		array_push( $form['fields'], array(
			'id'    => 'woocommerce_order_item_product_name',
			'label' => __( 'WooCommerce Order Item Product Name', 'wc_gf_addons' )
		) );

		array_push( $form['fields'], array(
			'id'    => 'woocommerce_order_item_product_id',
			'label' => __( 'WooCommerce Order Item Product ID', 'wc_gf_addons' )
		) );

		return $form;
	}


	public function export_wc_order_fields( $value, $form_id, $field_id, $entry ) {

		switch ( $field_id ) {
			case 'woocommerce_order_number' :
				$order_id = gform_get_meta( $entry['id'], 'woocommerce_order_number' );
				$value    = empty( $order_id ) ? '' : $order_id;
				break;
			case 'woocommerce_order_item_number' :
				$order_item_id = gform_get_meta( $entry['id'], 'woocommerce_order_item_number' );
				$value         = empty( $order_item_id ) ? '' : $order_item_id;
				break;
			case 'woocommerce_order_item_product_name' :
				$value         = '';
				$order_id      = gform_get_meta( $entry['id'], 'woocommerce_order_number' );
				$order_item_id = gform_get_meta( $entry['id'], 'woocommerce_order_item_number' );
				$the_order     = wc_get_order( $order_id );
				if ( $the_order ) {
					$order_items = $the_order->get_items();
					if ( isset( $order_items[ $order_item_id ] ) ) {
						$value = $order_items[ $order_item_id ]['name'];
					}
				}
				break;
			case 'woocommerce_order_item_product_id' :
				$value         = '';
				$order_id      = gform_get_meta( $entry['id'], 'woocommerce_order_number' );
				$order_item_id = gform_get_meta( $entry['id'], 'woocommerce_order_item_number' );
				$the_order     = wc_get_order( $order_id );
				if ( $the_order ) {
					$order_items = $the_order->get_items();
					if ( isset( $order_items[ $order_item_id ] ) ) {
						$value = $order_items[ $order_item_id ]['product_id'];
					}
				}
				break;
		}

		return $value;
	}
}