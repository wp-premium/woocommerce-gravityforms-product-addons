<?php
/**
 * WC Dependency Checker
 *
 * Checks if WooCommerce is enabled
 */
class WC_GFPA_Bulk_Variations_Check {

	private static $active_plugins;

	public static function init() {

		self::$active_plugins = (array) get_option( 'active_plugins', array() );

		if ( is_multisite() )
			self::$active_plugins = array_merge( self::$active_plugins, get_site_option( 'active_sitewide_plugins', array() ) );
	}

	public static function woocommerce_bulk_variations_active_check() {

		if ( ! self::$active_plugins ) self::init();

		return in_array( 'woocommerce-bulk-variations/woocommerce-bulk-variations.php', self::$active_plugins ) || array_key_exists( 'woocommerce-bulk-variations/woocommerce-bulk-variations.php', self::$active_plugins );
	}

}


