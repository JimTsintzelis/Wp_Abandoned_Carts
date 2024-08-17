jQuery(document).ready(function($) {
    $.ajax({
        url: ajax_object.ajax_url,
        method: 'POST',
        data: {
            action: 'fetch_cart_data'
        },
        success: function(response) {
            console.log('Initial Cart Product IDs:', response.initial_product_ids);
            console.log('Current Cart Data:', response.current_cart_data);
        }
    });
});
