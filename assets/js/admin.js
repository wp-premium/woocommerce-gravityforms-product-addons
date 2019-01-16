/*! WooCommerce Gravity Forms Product Addons
 * https://www.elementstark.com
 * Copyright (c) 2018;
 * Licensed GPLv2+
 */
(function ($) {

    $(document).ready(function () {

        $('#gravityform-id').change(function () {
            if ($(this).val() !== '') {
                $('.gforms-panel').show();
                $('.bulk-variation-form-field').show();

                $('.edit_form_link a').show()
                    .text(wc_gf_addons.text_edit_form + " " + $(this).find('option:selected').text())
                    .attr('href', wc_gf_addons.url_edit_form.replace('FORMID', $(this).val()));

                if ($('#gravityform-enable_cart_quantity_management').val() == 'yes' || $('#gravityform-enable_cart_quantity_management').val()  == 'stock' ) {
                    getFormData($(this).val());
                } else {
                    $('#gravityform-quantity-field').hide();
                }


            } else {
                $('.edit_form_link a').hide();

                $('.gforms-panel').hide();
                $('.bulk-variation-form-field').hide();
            }
        });

        $('#gravityform-bulk-id').change(function (e) {

            if ($(this).val() !== '' && $(this).val() == $('#gravityform-id').val()) {
                e.preventDefault();
                alert(wc_gf_addons.duplicate_form_notice);
                return false;
            }

            if ($(this).val() !== '') {

                $('.edit_bulk_form_link a').show()
                    .text(wc_gf_addons.text_edit_form + " " + $(this).find('option:selected').text())
                    .attr('href', wc_gf_addons.url_edit_form.replace('FORMID', $(this).val()));


            } else {
                $('.edit_bulk_form_link a').hide();
            }
        });

        $('#gravityform-enable_cart_quantity_management').change(function () {

            if ( $(this).val() == 'yes' || $(this).val() == 'stock' ) {
                getFormData($('#gravityform-id').val());
            } else {
                $('#gravityform-quantity-field').hide();
            }

        });

    });


    var $xhr = null;

    function getFormData($form_id) {

        if ($xhr) {
            $xhr.abort();
        }

        var data = {
            action: 'wc_gravityforms_get_form_data',
            wc_gravityforms_security: wc_gf_addons.nonce,
            form_id: $form_id,
            product_id: wc_gf_addons.product_id
        };

        $('#quantity_options_data').block({
            message: null,
            overlayCSS: {
                background: '#fff',
                opacity: 0.6
            }
        });


        $xhr = $.post(ajaxurl, data, function (response) {

            $('#quantity_options_data').unblock();

            $('#gravityform-quantity-field').show();
            $('#gravityform-quantity-field').html(response.data.markup);
        });

    }


})(jQuery);
;
(function ($) {


    var WCProductDataMetaBoxField = function ($field) {
        this.$field = $field;
        this.$content = $field.find('.wc-product-data-metabox-group-field-content');

        this.$field.on('click', '.wc-product-data-metabox-group-field-title', this.onFieldClicked.bind(this));
    };

    WCProductDataMetaBoxField.prototype.unload = function () {
        this.$field.off('click');
    };


    WCProductDataMetaBoxField.prototype.onFieldClicked = function (event) {
        event.preventDefault();
        event.stopPropagation();

        var self = this;

        if (this.$field.hasClass('open')) {
            this.$content.slideUp();
            this.$field.removeClass('open');
        } else {
            this.$field.addClass('open');
            this.$content.slideDown();
        }

    };


    /**
     * Function to call wc_variation_form on jquery selector.
     */
    $.fn.wc_product_data_metabox_field = function () {
        new WCProductDataMetaBoxField(this);
        return this;
    };


    $(function () {
        $('.wc-product-data-metabox-group-field').each(function () {
            $(this).wc_product_data_metabox_field();
        });
    });


})(jQuery);
