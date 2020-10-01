(function($) {

    $('form.cart').on('found_variation', function(e, variation) {

        console.log(variation.display_price);

    });


})(jQuery);



(function($){

    var $element;
    $(document).ready(function(){
        $element = $('#input_64_1');
    });

    var form_id = 64;
    var $form = undefined;
    $(document).on('wc_variation_form', function (e) {
        if ($form === undefined) {
            $form = $(e.target);

            $form.on('found_variation', function (e, variation) {
                $element.val(variation.display_price).trigger('keyup');

                try {
                    gf_apply_rules(form_id, ["0"]);
                } catch (err) {

                }

                gformCalculateTotalPrice(form_id);


            });
        }
    });

})(jQuery);
