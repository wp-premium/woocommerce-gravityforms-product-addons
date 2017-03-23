var ajax_price_req;
//See the gravity forms documentation for this function. 
function gform_product_total(formId, total) {

    var product_id = jQuery("input[name=product_id]").val();

    if (wc_gravityforms_params.use_ajax[product_id]) {
        return update_dynamic_price_ajax(total, formId);
    } else {
        return update_dynamic_price(total, formId);
    }

}

function get_gravity_forms_price(formId) {

    if (!_gformPriceFields[formId])
        return;

    var price = 0;

    _anyProductSelected = false; //Will be used by gformCalculateProductPrice().
    for (var i = 0; i < _gformPriceFields[formId].length; i++) {
        price += gformCalculateProductPrice(formId, _gformPriceFields[formId][i]);
    }

    //add shipping price if a product has been selected
    if (_anyProductSelected) {
        //shipping price
        var shipping = gformGetShippingPrice(formId)
        price += shipping;
    }

    //gform_product_total filter. Allows uers to perform custom price calculation
    if (window["gform_product_total"]) {
        price = window["gform_product_total"](formId, price);
    }

    price = gform.applyFilters('gform_product_total', price, formId);
    return price;
}


function update_dynamic_price(gform_total) {
    var product_id = jQuery("input[name=product_id]").val();
    var variation_id = jQuery("input[name=variation_id]").val();


    if (product_id || variation_id) {
        var the_id = 0;
        if (variation_id) {
            the_id = variation_id;
        } else {
            the_id = product_id;
        }

        var base_price = wc_gravityforms_params.prices[the_id];
        jQuery('.formattedBasePrice').html(accounting.formatMoney(base_price, {
                symbol: wc_gravityforms_params.currency_format_symbol,
                decimal: wc_gravityforms_params.currency_format_decimal_sep,
                thousand: wc_gravityforms_params.currency_format_thousand_sep,
                precision: wc_gravityforms_params.currency_format_num_decimals,
                format: wc_gravityforms_params.currency_format
            }
        ));

        jQuery('.formattedVariationTotal').html(accounting.formatMoney(gform_total, {
                symbol: wc_gravityforms_params.currency_format_symbol,
                decimal: wc_gravityforms_params.currency_format_decimal_sep,
                thousand: wc_gravityforms_params.currency_format_thousand_sep,
                precision: wc_gravityforms_params.currency_format_num_decimals,
                format: wc_gravityforms_params.currency_format
            }
        ));

        jQuery('.formattedTotalPrice').html(accounting.formatMoney(parseFloat(base_price) + parseFloat(gform_total), {
                    symbol: wc_gravityforms_params.currency_format_symbol,
                    decimal: wc_gravityforms_params.currency_format_decimal_sep,
                    thousand: wc_gravityforms_params.currency_format_thousand_sep,
                    precision: wc_gravityforms_params.currency_format_num_decimals,
                    format: wc_gravityforms_params.currency_format
                }
            ) + wc_gravityforms_params.price_suffix[product_id]
        );
    }

    return gform_total;
}

function update_dynamic_price_ajax(gform_total) {
    jQuery('div.product_totals').block({
        message: null,
        overlayCSS: {
            background: '#fff',
            opacity: 0.6
        }
    });

    var product_id = jQuery("input[name=product_id]").val();
    var variation_id = jQuery("input[name=variation_id]").val();

    var the_id = 0;
    if (variation_id) {
        the_id = variation_id;
    } else {
        the_id = product_id;
    }

    var base = wc_gravityforms_params.prices[the_id];

    if (ajax_price_req) {
        ajax_price_req.abort();
    }

    var opts = "product_id=" + product_id + "&variation_id=" + variation_id
    opts += '&action=get_updated_price&gform_total=' + gform_total;

    ajax_price_req = jQuery.ajax({
        type: "POST",
        url: woocommerce_params.ajax_url,
        data: opts,
        dataType: 'json',
        success: function (response) {
            jQuery('.formattedBasePrice').html((response.formattedBasePrice));
            jQuery('.formattedVariationTotal').html(response.formattedVariationTotal);
            jQuery('.formattedTotalPrice').html(response.formattedTotalPrice);

            jQuery('div.product_totals').unblock();
        }
    });
    return gform_total;
}


(function ($) {
    $.fn.wc_gravity_form = function () {
        var $form = this;

        var form_id = $form.find("input[name=wc_gforms_form_id]").val();

        if (form_id) {

            var next_page = $form.find("input[name=wc_gforms_next_page]").val();
            var previous_page = $form.find("input[name=wc_gforms_previous_page]").val();

            $form.attr('action', '');
            $form.attr('id', 'gform_' + form_id);

            $form.on('found_variation', function (variation) {
                try {
                    gf_apply_rules(form_id, ["0"]);
                } catch (err) {
                }

                gformCalculateTotalPrice(form_id);
            });


            $('button[type=submit]', $form).attr('id', 'gform_submit_button_' + form_id).addClass('button gform_button');

            if (next_page != 0) {
                $('button[type=submit]', $form).remove();
                $('div.quantity', $form).remove();
                $('#wl-wrapper', $form).hide();
            }

            $('.gform_next_button', $form).attr('onclick', '');

            $('.gform_next_button', $form).click(function (event) {
                window.location.hash = '#_form_' + form_id;
                $form.attr('action', window.location.hash);
                $("#gform_target_page_number_" + form_id, $form).val(next_page);
                $form.trigger("submit", [true]);
            });

            $('.gform_previous_button', $form).click(function (event) {
                $("#gform_target_page_number_" + form_id, $form).val(previous_page);
                window.location.hash = '#_form_' + form_id
                $form.attr('action', window.location.hash);
                $form.trigger("submit", [true]);
            });
        }
    };


    $(document).on('wc_variation_form', function (e) {
        var $form = $(this);
        $form.wc_gravity_form();
    });

    $(document).ready(function (e) {
        $('form.cart').each(function (index, form) {
            var $form = $(form);
            $form.wc_gravity_form();
        });
    });


    $(document).on('quick-view-displayed', function () {
        console.log('quick view displayed');
        setTimeout(function () {

            $.globalEval($('.quick-view-content').find('script').text());
            $('.quick-view-content').find('form').each(function (i, form) {
                $(form).wc_gravity_form();
            })

        }, 0);
    });

})(jQuery);






