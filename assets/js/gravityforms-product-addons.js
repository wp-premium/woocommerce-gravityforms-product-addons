var ajax_price_req;
//See the gravity forms documentation for this function. 
function gform_product_total(formId, total) {
	console.log(wc_gravityforms_params);
	
	if (wc_gravityforms_params.use_ajax) {
		return update_dynamic_price_ajax(total, formId);
	} else {
		return update_dynamic_price(total, formId);
	}

}

function update_dynamic_price(gform_total) {
	var product_id = jQuery("#product_id").val();
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
		) + wc_gravityforms_params.price_suffix
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

	var product_id = jQuery("#product_id").val();
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

	var opts = "product_id=" + jQuery("#product_id").val() + "&variation_id=" + jQuery("input[name=variation_id]").val();
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

jQuery(document).ready(function ($) {
	if (window.gravityforms_params) {


		if (gravityforms_params.previous_page === 0 && ($('.woocommerce-message').length)) {
			window.location.hash = '';
		}
		;


		$("form.cart").attr('action', '');
		$('form.cart').attr('id', 'gform_' + gravityforms_params.form_id);

		$('body').delegate('form.cart', 'found_variation', function () {
			try {
				gf_apply_rules(gravityforms_params.form_id, ["0"]);
			} catch (err) {
			}
			gformCalculateTotalPrice(gravityforms_params.form_id);
		});



		$('button[type=submit]', 'form.cart').attr('id', 'gform_submit_button_' + gravityforms_params.form_id).addClass('button gform_button');


		if (gravityforms_params.next_page != 0) {

			$('button[type=submit]', 'form.cart').remove();
			$('div.quantity').remove();
			$('#wl-wrapper').hide();

		} else {

		}

		$('.gform_next_button', 'form.cart').attr('onclick', '');
		$('.gform_next_button', 'form.cart').click(function (event) {
			window.location.hash = '#_form_' + gravityforms_params.form_id;
			
			$('form.cart').attr('action', window.location.hash);
			
			$("#gform_target_page_number_" + gravityforms_params.form_id).val(gravityforms_params.next_page);
			$("form.cart").trigger("submit", [true]);

		});

		$('.gform_previous_button', 'form.cart').click(function (event) {
			$("#gform_target_page_number_" + gravityforms_params.form_id).val(gravityforms_params.previous_page);
			
			window.location.hash = '#_form_' + gravityforms_params.form_id;
			$('form.cart').attr('action', window.location.hash);
			
			$("form.cart").trigger("submit", [true]);
		});
	}
});