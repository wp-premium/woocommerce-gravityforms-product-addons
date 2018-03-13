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

			if ( apply_filters( 'woocommerce_gravityforms_create_entries', true ) ) {
				self::$instance = new WC_GFPA_Entry();
			}
		}
	}

	private $_resuming_orders = array();

	private function __construct() {

		add_filter( 'gform_entry_detail_meta_boxes', array( $this, 'add_custom_entry_metabox' ), 10, 3 );


		add_filter( 'gform_export_fields', array( $this, 'add_wc_order_fields' ), 10, 1 );
		add_filter( 'gform_export_field_value', array( $this, 'export_wc_order_fields' ), 10, 4 );

		add_action( 'gform_entry_detail_content_before', array( $this, 'entry_detail_screen_notice' ), 10, 2 );

		add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'create_entries' ), 10, 2 );
		add_action( 'woocommerce_resume_order', array( $this, 'on_woocommerce_resume_order' ), 10, 1 );
	}

	/**
	 * Fired after the order is created.
	 *
	 * @param $order_id
	 * @param $data
	 */
	public function create_entries( $order_id, $data ) {

		$the_order   = wc_get_order( $order_id );
		$order_items = $the_order->get_items();

		foreach ( $order_items as $order_item ) {
			$gravity_forms_history = null;

			$meta_data = $order_item->get_meta_data();
			if ( WC_GFPA_Compatibility::is_wc_version_gte_3_2() ) {

				foreach ( $meta_data as $meta_data_item ) {
					$d = $meta_data_item->get_data();
					if ( $d['key'] == '_gravity_forms_history' ) {
						$gravity_forms_history = array( $meta_data_item );
						break;
					}
				}

			} else {
				$gravity_forms_history = wp_list_filter( $meta_data, array( 'key' => '_gravity_forms_history' ) );
			}

			$entry_id = false;
			if ( $gravity_forms_history ) {
				$gravity_forms_history_value = array_pop( $gravity_forms_history );

				//Cart item key is set when the order is first created.
				$cart_item_key = isset( $gravity_forms_history_value->value['_gravity_form_cart_item_key'] ) ? $gravity_forms_history_value->value['_gravity_form_cart_item_key'] : false;

				if ( $cart_item_key ) {
					//Check to see if we are resuming an order and if so if we've tracked this entry id already.
					$entry_id = isset( $this->_resuming_orders[ $order_id ][ $cart_item_key ] ) ? $this->_resuming_orders[ $order_id ][ $cart_item_key ] : false;
				}

				$form_data = $gravity_forms_history_value->value['_gravity_form_data'];
				$lead_data = $gravity_forms_history_value->value['_gravity_form_lead'];

				if ( empty( $entry_id ) ) {
					unset( $lead_data['lead_id'] );
					$entry_id = GFAPI::add_entry( $lead_data );

					if ( $entry_id && ! is_wp_error( $entry_id ) ) {

						$form  = GFAPI::get_form( $form_data['id'] );
						$entry = GFAPI::get_entry( $entry_id );

						if ( $entry ) {

							//Send notifications unless disabled

							if ( apply_filters( 'woocommerce_gravityforms_send_entry_notifications_on_order_submission', true, $form ) ) {
								GFAPI::send_notifications( $form, $entry, 'form_submission', $entry );
							}

							/**
							 * Fired after an entry is created
							 *
							 * @param array $lead The Entry object
							 * @param array $form The Form object
							 */
							do_action( 'gform_entry_created', $entry, $form );
							$entry = gf_apply_filters( array( 'gform_entry_post_save', $form['id'] ), $entry, $form );

								gf_do_action( array( 'gform_after_submission', $form_data['id'] ), $entry, $form );


						}
					}
				}

				if ( $entry_id && ! is_wp_error( $entry_id ) ) {
					gform_update_meta( $entry_id, 'woocommerce_order_number', $order_item->get_order_id(), $lead_data['form_id'] );
					gform_update_meta( $entry_id, 'woocommerce_order_item_number', $order_item->get_id(), $lead_data['form_id'] );

					$new_history = array(
						'_gravity_form_cart_item_key'   => $cart_item_key,
						'_gravity_form_linked_entry_id' => $entry_id,
						'_gravity_form_lead'            => $gravity_forms_history_value->value['_gravity_form_lead'],
						'_gravity_form_data'            => $gravity_forms_history_value->value['_gravity_form_data']
					);

					wc_update_order_item_meta( $order_item->get_id(), '_gravity_forms_history', $new_history );
				}
			}
		}
	}

	/**
	 * If we are resuming an order we need to keep track of our entry's which were already created.
	 * We do that by storing the cart_item_key which was added to the gravity forms history when the order was created the first time
	 *
	 * We use these stored values so we can get the entry id which was already created for the order line item.
	 *
	 * We can't use the order item id to track because it get's deleted and a new one created, so we use the cart_item_key as the unique hash.
	 *
	 * @since 3.2.5
	 *
	 * @param int $order_id The woocommerce order id being resumed.
	 */
	public function on_woocommerce_resume_order( $order_id ) {

		$the_order   = wc_get_order( $order_id );
		$order_items = $the_order->get_items();

		foreach ( $order_items as $order_item ) {

			$meta_data = $order_item->get_meta_data();
			if ( WC_GFPA_Compatibility::is_wc_version_gte_3_2() ) {

				foreach ( $meta_data as $meta_data_item ) {
					$d = $meta_data_item->get_data();
					if ( $d['key'] == '_gravity_forms_history' ) {
						$gravity_forms_history = array( $meta_data_item );
						break;
					}
				}

			} else {
				$gravity_forms_history = wp_list_filter( $meta_data, array( 'key' => '_gravity_forms_history' ) );
			}

			if ( $gravity_forms_history ) {

				$gravity_forms_history_value = array_pop( $gravity_forms_history );
				$cart_item_key               = isset( $gravity_forms_history_value->value['_gravity_form_cart_item_key'] ) ? $gravity_forms_history_value->value['_gravity_form_cart_item_key'] : false;

				$entry_id                                              = isset( $gravity_forms_history_value->value['_gravity_form_linked_entry_id'] ) ? $gravity_forms_history_value->value['_gravity_form_linked_entry_id'] : false;
				$this->_resuming_orders[ $order_id ][ $cart_item_key ] = $entry_id;
			}
		}
	}


	/**
	 * @param $order_item WC_Order_Item_Product
	 * @param $form_id
	 * @param $lead_data
	 */
	public function create_entry( $order_item, $form_id, $lead_data ) {
		unset( $lead_data['lead_id'] );
		$entry_id = GFAPI::add_entry( $lead_data );
		if ( $entry_id && ! is_wp_error( $entry_id ) ) {
			gform_update_meta( $entry_id, 'woocommerce_order_number', $order_item->get_order_id(), $form_id );
			gform_update_meta( $entry_id, 'woocommerce_order_item_number', $order_item->get_id(), $form_id );
		}
	}

	//Entry admin screen

	public function entry_detail_screen_notice( $form, $lead ) {
		$order_id = gform_get_meta( $lead['id'], 'woocommerce_order_number' );
		if ( $order_id ) {
			$the_order = wc_get_order( $order_id );

			echo '<div style="padding: 20px;background:#fff;border:1px solid #e5e5e5;margin: 5px 1px 2px 1px">';
			echo '<h3>' . __( 'WooCommerce Order Item', 'wc_gf_addons' ) . '</h3>';
			if ( $the_order ) {
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

				if ( $the_order->get_user_id() ) {
					$user_info = get_userdata( $the_order->get_user_id() );
				}

				if ( ! empty( $user_info ) ) {

					$username = '<a href="user-edit.php?user_id=' . absint( $user_info->ID ) . '">';

					if ( $user_info->first_name || $user_info->last_name ) {
						/* translators: 1: first name 2: last name */
						$username .= esc_html( sprintf( _x( '%1$s %2$s', 'full name', 'woocommerce' ), ucfirst( $user_info->first_name ), ucfirst( $user_info->last_name ) ) );
					} else {
						$username .= esc_html( ucfirst( $user_info->display_name ) );
					}

					$username .= '</a>';

				} else {
					if ( $the_order->get_billing_first_name() || $the_order->get_billing_last_name() ) {
						/* translators: 1: first name 2: last name */
						$username = trim( sprintf( _x( '%1$s %2$s', 'full name', 'woocommerce' ), $the_order->get_billing_first_name(), $the_order->get_billing_last_name() ) );
					} elseif ( $the_order->get_billing_company() ) {
						$username = trim( $the_order->get_billing_company() );
					} else {
						$username = __( 'Guest', 'woocommerce' );
					}
				}

				echo '<p><strong>' . __( 'Order', 'wc_gf_addons' ) . ': </strong>' . sprintf( _x( '%s by %s', 'Order number by X', 'woocommerce' ), '<a href="' . admin_url( 'post.php?post=' . absint( $order_id ) . '&action=edit' ) . '" class="row-title"><strong>#' . esc_attr( $the_order->get_order_number() ) . '</strong></a>', $username );

				$order_item = $the_order->get_item( $order_item_id );

				if ( $order_item ) {
					echo '<p><strong>' . __( 'Product', 'wc_gf_addons' ) . ': </strong>' . '<a href="' . admin_url( 'post.php?post=' . absint( $order_item->get_product_id() ) . '&action=edit' ) . '">' . $order_item->get_name() . '</a></p>';
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