<?php

add_action('wp_ajax_nopriv_get_updated_price', 'woocommerce_gravityforms_get_updated_price');
add_action('wp_ajax_get_updated_price', 'woocommerce_gravityforms_get_updated_price');

function woocommerce_gravityforms_get_updated_price() {
    global $woocommerce;
    header('Cache-Control: no-cache, must-revalidate');
    header('Content-type: application/json');

    $variation_id = isset($_POST['variation_id']) ? $_POST['variation_id'] : '';
    $product_id = isset($_POST['product_id']) ? $_POST['product_id'] : 0;
    $gform_total = isset($_POST['gform_total']) ? $_POST['gform_total'] : 0;

    $product_data = null;
    if (function_exists('get_product')) {
        $product_data = get_product($variation_id > 0 ? $variation_id : $product_id);
    } else {
        if ($variation_id > 0) :
            $product_data = new WC_Product_Variation($variation_id);
        else :
            $product_data = new WC_Product($product_id);
        endif;
    }
    
    $discount_price = false;
    $gforms_discount_price = false;
    $base_price = $product_data->get_display_price();

    if (class_exists('WC_Dynamic_Pricing')) {
        $working_price = $base_price;
        $dynamic_pricing = WC_Dynamic_Pricing::instance();
        foreach ($dynamic_pricing->modules as $module) {
            if ($module->module_type == 'simple') {
                //Make sure we are using the price that was just discounted.
                $working_price = $discount_price ? $discount_price : $base_price;
                $working_price = $module->get_product_working_price($working_price, $product_data);
                if (floatval($working_price)) {
                    $discount_price = $module->get_discounted_price_for_shop($product_data, $working_price);
                }
            }
        }
        
        $gforms_base_price = $base_price + $gform_total;
        $gforms_working_price = $base_price + $gform_total;
        foreach ($dynamic_pricing->modules as $module) {
            if ($module->module_type == 'simple') {
                //Make sure we are using the price that was just discounted.
                $gforms_working_price = $gforms_discount_price ? $gforms_discount_price : $gforms_base_price;
                $gforms_working_price = $module->get_product_working_price($gforms_working_price, $product_data);
                if (floatval($gforms_working_price)) {
                    $gforms_discount_price = $module->get_discounted_price_for_shop($product_data, $gforms_working_price);
                }
            }
        }
    }

    $price = $discount_price ? $discount_price : $base_price;
    $gform_final_total = $gforms_discount_price ? $gforms_discount_price : $price + $gform_total;
    
    $result = array(
        'formattedBasePrice' => apply_filters('woocommerce_gform_base_price', wc_price($price), $product_data),
        'formattedTotalPrice' => apply_filters('woocommerce_gform_total_price', wc_price($gform_final_total), $product_data),
        'formattedVariationTotal' => apply_filters('woocommerce_gform_variation_total_price', wc_price($gform_total), $product_data)
    );

    echo json_encode($result);
    die();
}