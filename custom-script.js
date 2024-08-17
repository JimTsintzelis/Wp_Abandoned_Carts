
jQuery(document).ready(function($) {
    if (typeof myScriptVars !== 'undefined' && myScriptVars.productIDs) {
        console.log('Product IDs at coupon:', myScriptVars.productIDs);
    } else {
        console.log('No product IDs found');
    }
});




