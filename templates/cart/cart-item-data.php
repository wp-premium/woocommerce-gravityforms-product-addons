<?php
/**
 * Cart item data (when outputting non-flat)
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/cart/cart-item-data.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see        https://docs.woocommerce.com/document/template-structure/
 * @author        WooThemes
 * @package    WooCommerce/Templates
 * @version    2.4.0
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<?php

$entry_data;
foreach ( $item_data as $index => $data ) :
	if ( $data['key'] == 'gf-entry-details' ) :
		$entry_data = $data['value'];
		unset( $item_data[ $index ] );
	endif;
endforeach;

?>

<?php

//Load the default WooCommerce cart item data to render variations or anything else which would normally use this
wc_get_template( 'cart/cart-item-data.php', array( 'item_data' => $item_data ) );

?>

<?php if ( $entry_data ): ?>

	<?php if ( isset( $entry_data['totals'] ) && isset( $entry_data['totals']['subtotal'] ) ): ?>
        <dl class="variation">
            <dt class=""><?php echo $entry_data['totals']['subtotal']['name']; ?>:</dt>
            <dd class=""><?php echo $entry_data['totals']['subtotal']['value']; ?></dd>
        </dl>
	<?php endif; ?>

	<?php if ( ! empty( $entry_data['fields'] ) ): ?>
        <dl class="variation">
			<?php foreach ( $entry_data['fields'] as $field ) : ?>
                <dt class="<?php echo sanitize_html_class( 'variation-' . $field['key'] ); ?>"><?php echo wp_kses_post( $field['key'] ); ?>:</dt>
                <dd class="<?php echo sanitize_html_class( 'variation-' . $field['key'] ); ?>"><?php echo wp_kses_post( wpautop( $field['display'] ) ); ?></dd>
			<?php endforeach; ?>
        </dl>
	<?php endif; ?>

	<?php foreach ( $entry_data['products'] as $product_id => $fields ): ?>

        <dl class="variation">
			<?php foreach ( $fields as $field_key => $field ) : ?>
				<?php if ( ! $field['hidden'] ) : ?>
                    <dt class="<?php echo sanitize_html_class( 'variation-' . $field['key'] ); ?>"><?php echo wp_kses_post( $field['key'] ); ?>:</dt>
                    <dd class="<?php echo sanitize_html_class( 'variation-' . $field['key'] ); ?>"><?php echo wp_kses_post( wpautop( $field['display'] ) ); ?></dd>
				<?php endif; ?>
			<?php endforeach; ?>
        </dl>
	<?php endforeach; ?>
<?php endif; ?>
