<?php
// unsubscribe-handler.php
require_once($_SERVER['DOCUMENT_ROOT'].'/wp-load.php');

if (isset($_GET['user_id'])) {
    $user_id = intval($_GET['user_id']);
    
    // Perform security checks here as necessary

    // Update user meta to mark as unsubscribed
    update_user_meta($user_id, 'abandoned_cart_unsubscribed', true);

    // Optional: Additional logic to update the is_abandoned column

    // Redirect to a confirmation page or display a message
    wp_redirect(site_url('/unsubscribe-confirmation')); // Make sure you create this page
    exit;
}
?>
