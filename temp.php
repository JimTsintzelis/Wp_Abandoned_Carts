<?php
/*
Plugin Name: Abandoned Carts
Description: Saves abandoned cart information and user data in the wp_abandoned_carts table.
Version: 1.0
Author: Your Name
*/




// Register activation hook
register_activation_hook(__FILE__, 'abandoned_carts_activate');

// Activation callback
function abandoned_carts_activate() {
    // Create the custom table for abandoned carts
    global $wpdb;
    $table_name = $wpdb->prefix . 'abandoned_carts';

    // Check if the table already exists
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        // Create the table
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE $table_name (
            id INT(11) NOT NULL AUTO_INCREMENT,
            user_id INT(11) NOT NULL,
            cart_contents LONGTEXT NOT NULL,
            email VARCHAR(255) NOT NULL,
            first_name VARCHAR(100),
            last_name VARCHAR(100),
            subscription_status TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL,
            category varchar(255) NOT NULL,
            subcategory varchar(255) NOT NULL,
            best_selling_product int(11) DEFAULT 0,
            product_rating float DEFAULT 0,
            is_subscribed TINYINT(1) NOT NULL,
            subject VARCHAR(255) NOT NULL,
            template_content TEXT NOT NULL,
            PRIMARY KEY (id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}

// Hook into WooCommerce add to cart action to capture abandoned cart data
add_action('woocommerce_add_to_cart', 'capture_abandoned_cart_data', 10, 6);

// Capture abandoned cart data
function capture_abandoned_cart_data($cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data) {


    // Retrieve relevant cart data using WooCommerce functions
    $cart_contents = array();
    foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {

        

        $product = $cart_item['data'];
        $cart_contents[] = array(
            'product_id' => $product_id,
            'name' => $product->get_name(),
            'quantity' => $cart_item['quantity'],
            'price' => $product->get_price(),
            'image_url' => $image_url,
            'subtotal' => wc_price($cart_item['line_subtotal'])
        );
    }


    
    $customer = WC()->customer;
    $user_id = $customer->get_id();
    $email = $customer->get_email();
    $first_name = $customer->get_first_name();
    $last_name = $customer->get_last_name();

    // Serialize and store the cart contents in the custom table
    global $wpdb;
    $table_name = $wpdb->prefix . 'abandoned_carts';
    $wpdb->insert($table_name, array(
        'user_id' => $user_id,
        'cart_contents' => maybe_serialize($cart_contents),
        'email' => $email,
        'first_name' => $first_name,
        'last_name' => $last_name,
        'created_at' => current_time('mysql')
    ));
}





// Hook into WooCommerce remove cart item action to delete abandoned cart data
add_action('woocommerce_remove_cart_item', 'delete_abandoned_cart_item', 10, 2);

// Delete abandoned cart data when cart item is removed
function delete_abandoned_cart_item($cart_item_key, $cart) {
    // Retrieve user ID from the current customer
    $customer = WC()->customer;
    $user_id = $customer->get_id();

    // Delete abandoned cart entries containing the removed item from the custom table
    global $wpdb;
    $table_name = $wpdb->prefix . 'abandoned_carts';
    $cart_item_name = $cart->get_cart()[$cart_item_key]['data']->get_name();

    // Use a wildcard (%) to match any cart_contents containing the removed item
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM $table_name WHERE user_id = %d AND cart_contents LIKE %s",
            $user_id,
            '%' . $wpdb->esc_like($cart_item_name) . '%'
        )
    );
}


// Hook into WooCommerce order status completed action to delete abandoned cart data
add_action('woocommerce_order_status_completed', 'delete_abandoned_cart_data');

// Delete abandoned cart data
function delete_abandoned_cart_data($order_id) {
    // Retrieve user ID from the completed order
    $order = wc_get_order($order_id);
    $user_id = $order->get_user_id();

    // Delete abandoned cart entries for the user from the custom table
    global $wpdb;
    $table_name = $wpdb->prefix . 'abandoned_carts';
    $wpdb->delete($table_name, array('user_id' => $user_id));
}





function abandoned_carts_plugin_init() {
    // Enqueue plugin scripts and styles
  

    // Register plugin menus or settings
    
    

    // Other initialization code...
}




// Get abandoned cart contents
function get_abandoned_cart_contents($user_id) {
    $table_name = $wpdb->prefix . 'abandoned_carts';
    $abandoned_cart = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE user_id = %d", $user_id));
    if ($abandoned_cart) {
        return maybe_unserialize($abandoned_cart->cart_contents);
    }
    return array();
}


// Add a top-level menu item for the plugin
function abandoned_carts_plugin_menu() {
    add_menu_page(
        'Abandoned Carts Plugin',
        'Abandoned Carts',
        'manage_options',
        'abandoned-carts-plugin',
        'abandoned_carts_plugin_dashboard',
        'dashicons-cart',
        30
    );

    // Add sub-menu items
    add_submenu_page(
        'abandoned-carts-plugin',
        'Abandoned Orders',
        'Abandoned Orders',
        'manage_options',
        'abandoned-orders',
        'abandoned_carts_page'
    );

    add_submenu_page(
        'abandoned-carts-plugin',
        'Email Template',
        'Email Template',
        'manage_options',
        'email-template',
        'email_template_page'
    );

    add_submenu_page(
        'abandoned-carts-plugin',
        'Recovered Orders',
        'Recovered Orders',
        'manage_options',
        'recovered-orders',
        'recovered_orders_page'
    );

    // Add sub-menu items
    add_submenu_page(
        'abandoned-carts-plugin',
        'Order Analytics',
        'Order Analytics',
        'manage_options',
        'order-analytics',
        'order_analytics_page'
    );
}


// Callback for the plugin dashboard page
function abandoned_carts_plugin_dashboard() {
    // Output your dashboard page content here
    echo '<h1>Abandoned Carts Plugin Dashboard</h1>';
    echo '<p>Welcome to the Abandoned Carts Plugin dashboard page.</p>';
    // Add any other content or functionality you need for the plugin dashboard
}

// Abandoned carts page callback
function abandoned_carts_page() {

     // Check if a template has been selected and saved
    if (isset($_POST['save_template'])) {
        $selected_template = isset($_POST['email_template']) ? sanitize_text_field($_POST['email_template']) : '';
        $template_content = isset($_POST['email_content']) ? sanitize_textarea_field($_POST['email_content']) : '';

        // Save the template content to the database or any other storage
        // Here, you can implement your logic to save the template content
    }


       global $wpdb;
    $table_name = $wpdb->prefix . 'abandoned_carts';

    // Retrieve abandoned cart data from the custom table
    $abandoned_carts = $wpdb->get_results("SELECT * FROM $table_name");

    // Output the abandoned cart data
    echo '<div class="wrap">';
    echo '<h1>Abandoned Carts</h1>';

    // Abandoned cart data table
    echo '<form method="post" action="">'; // Add form element for checkbox selection
    echo '<table class="widefat">';
    echo '<thead><tr><th></th><th>User ID</th><th>Email</th><th>First Name</th><th>Last Name</th><th>Category</th><th>Subcategory</th><th>Cart Contents</th><th>Abandoned Datetime</th><th>Subscription Status</th></tr></thead>';
    echo '<tbody>';

    foreach ($abandoned_carts as $abandoned_cart) {
        $user_id = $abandoned_cart->user_id;
        $email = $abandoned_cart->email;
        $first_name = $abandoned_cart->first_name;
        $last_name = $abandoned_cart->last_name;
        $category = $abandoned_cart->category; // Added category column
        $subcategory = $abandoned_cart->subcategory; // Added subcategory column
        $cart_contents = maybe_unserialize($abandoned_cart->cart_contents);
        $abandoned_datetime = $abandoned_cart->created_at;

        // Get subscription status
        $subscription_status = get_user_meta($user_id, 'abandoned_cart_unsubscribed', true);
        $subscription_status = $subscription_status ? 'Unsubscribed' : 'Subscribed';

        echo '<tr>';
        echo '<td><input type="checkbox" name="selected_carts[]" value="' . $user_id . '"></td>'; // Add the checkbox here
        echo '<td>' . $user_id . '</td>';
        echo '<td>' . $email . '</td>';
        echo '<td>' . $first_name . '</td>';
        echo '<td>' . $last_name . '</td>';
        echo '<td>' . $category . '</td>'; // Display category
        echo '<td>' . $subcategory . '</td>'; // Display subcategory
        echo '<td>';
        echo '<ul>';
        foreach ($cart_contents as $cart_item) {
            echo '<li>' . $cart_item['name'] . ' - Qty: ' . $cart_item['quantity'] . '</li>';
        }
        echo '</ul>';
        echo '</td>';
        echo '<td>' . $abandoned_datetime . '</td>';
        echo '<td>' . $subscription_status . '</td>';
        echo '</tr>';
    }

    echo '</tbody>';
    echo '</table>';

   
    
    // Display the "Send Template" button
    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
    echo '<input type="hidden" name="action" value="send_abandoned_cart_email">';
    echo '<input type="hidden" name="email_content" value="' . esc_attr($default_template) . '">';
    echo '<button type="submit" name="send_email" class="button">Send Email to Selected Users</button>';
    echo '</form>';
}     



// Callback for the "Email Template" page
function email_template_page() {
    // Retrieve the template content and subject from the "abandoned_carts" table
    global $wpdb;
    $table_name = $wpdb->prefix . 'abandoned_carts';
    $template_row = $wpdb->get_row("SELECT template_content, subject FROM $table_name LIMIT 1");

    // Get the default template content and subject
    $default_template = "Salutation: Dear [customer_name],\n\n";
    $default_template .= "Body:\n";
    $default_template .= "We noticed that you have left some items in your shopping cart. Here are the details:\n\n";
    $default_template .= "Items:\n[cart_contents]\n\n";
    $default_template .= "Cart Link: [cart_link]\n\n";
    $default_template .= "Available Coupons:\n[coupon_list]\n\n";
    $default_template .= "Closing: Thanks for shopping from us!\n\n";
    $default_template .= "Signature: Pharm247\n[site_logo]";
    $default_subject = 'Abandoned Cart';

    // Apply filters for customizing the email template
    $template_content = apply_filters('abandoned_cart_email_content', $template_row->template_content ?? $default_template);
    $current_subject = $template_row->subject ?? $default_subject;

    // Update the template content and subject when the form is submitted
    if (isset($_POST['save_template'])) {
        $new_template_content = sanitize_textarea_field($_POST['email_content']);
        $new_subject = sanitize_text_field($_POST['email_subject']);

        // Update the "abandoned_carts" table with the new template content and subject
        $wpdb->update(
            $table_name,
            array(
                'template_content' => $new_template_content,
                'subject' => $new_subject
            ),
            array('ID' => 1)
        );

        // Display a success message or perform any other necessary actions
        echo '<div class="notice notice-success"><p>Template saved successfully.</p></div>';

        // Update the current template and subject variables
        $template_content = $new_template_content;
        $current_subject = $new_subject;
    }

    // Retrieve available coupons
    $available_coupons = get_available_coupons();

    // Output your "Email Template" page content here
    echo '<h1>Email Template Page</h1>';
    echo '<p>This is the Email Template page.</p>';

    // Display the template editing form
    echo '<h2>Email Template</h2>';
    echo '<form method="post" action="">';
    echo '<label for="email_subject">Subject:</label><br>';
    echo '<input type="text" name="email_subject" value="' . esc_attr($current_subject) . '"><br><br>';
    echo '<label for="email_content">Template:</label><br>';
    echo '<textarea name="email_content" rows="10" cols="50">' . esc_textarea($template_content) . '</textarea>';

    // Display the coupon selection checkboxes
    echo '<h2>Select Coupons</h2>';
    echo '<ul>';
    foreach ($available_coupons as $coupon) {
        $coupon_id = $coupon['id'];
        $coupon_code = $coupon['code'];
        $checked = strpos($template_content, $coupon_code) !== false ? 'checked' : '';
        echo '<li><input type="checkbox" name="selected_coupons[]" value="' . $coupon_id . '" ' . $checked . '>' . $coupon_code . '</li>';
    }
    echo '</ul>';

    echo '<input type="submit" name="save_template" value="Save Template">';
    echo '</form>';
}





// Get the available coupons
function get_available_coupons() {
    $coupons = array();
    $args = array(
        'post_type' => 'shop_coupon',
        'post_status' => 'publish',
        'posts_per_page' => -1
    );

    $query = new WP_Query($args);
    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $coupon_id = get_the_ID();
            $coupon_code = get_the_title();
            $coupons[] = array(
                'id' => $coupon_id,
                'code' => $coupon_code
            );
        }
    }
    wp_reset_postdata();

    return $coupons;
}

// Replace the coupon placeholders in the template with the selected coupons
function replace_coupon_placeholders($template, $selected_coupons) {
    $coupon_list = '';
    $coupons = get_available_coupons();
    foreach ($coupons as $coupon) {
        if (in_array($coupon['id'], $selected_coupons)) {
            $coupon_code = $coupon['code'];
            $coupon_list .= "- " . $coupon_code . "\n";
            $template = str_replace('[coupon_code]', $coupon_code, $template);
        }
    }

    $template = str_replace('[coupon_list]', $coupon_list, $template);
    return $template;
}



// Replace the cart items placeholders in the template with the actual cart items
function replace_cart_items_placeholders($template, $cart_items) {
    $cart_items_content = '';
    foreach ($cart_items as $cart_item) {
        $product_id = $cart_item['product_id'];
        $product_name = $cart_item['name'];
        $product_quantity = $cart_item['quantity'];

        $cart_items_content .= "- $product_name (Quantity: $product_quantity)\n";
    }

    $template = str_replace('[cart_items]', $cart_items_content, $template);
    return $template;
}

// Replace the cart link placeholder in the template with the actual cart link
function replace_cart_link_placeholder($template, $user_id) {
    $cart_link = wc_get_cart_url();
    if ($user_id) {
        $cart_link = add_query_arg('user', $user_id, $cart_link);
    }

    $template = str_replace('[cart_link]', $cart_link, $template);
    return $template;
}

// Replace the unsubscribe button placeholder in the template with the actual unsubscribe button
function replace_unsubscribe_button_placeholder($template, $user_id) {
    $unsubscribe_url = esc_url(add_query_arg('action', 'unsubscribe_abandoned_cart', admin_url('admin-post.php')));
    $unsubscribe_url = esc_url(add_query_arg('user', $user_id, $unsubscribe_url));
    $unsubscribe_button = '<a href="' . $unsubscribe_url . '">I am not interested</a>';

    $template = str_replace('[unsubscribe_button]', $unsubscribe_button, $template);
    return $template;
}




// Unsubscribe from abandoned cart emails
function unsubscribe_abandoned_cart() {
    if (isset($_GET['user'])) {
        $user_id = intval($_GET['user']);

        // Update the is_subscribed value in the abandoned_carts table
        global $wpdb;
        $table_name = $wpdb->prefix . 'abandoned_carts';
        $wpdb->update(
            $table_name,
            array('is_subscribed' => 0),
            array('user_id' => $user_id)
        );

        // Redirect the user to a confirmation page or perform any other necessary actions
        wp_redirect(home_url('/unsubscribe-confirmation'));
        exit;
    }
}
add_action('admin_post_unsubscribe_abandoned_cart', 'unsubscribe_abandoned_cart');












// Callback for the "RECOVERED ORDERS" page
function recovered_orders_page() {
    // Output your "Recovered Orders" page content here
    echo '<h1>Recovered Orders Page</h1>';
    echo '<p>This is the Recovered Orders page.</p>';
    // Add any other content or functionality you need for the Recovered Orders page
}
// Callback for the "Order Analytics" page
function order_analytics_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'abandoned_carts';

    // Retrieve order analytics data from the custom table
    $order_analytics = $wpdb->get_results("SELECT * FROM $table_name");

    // Output the order analytics data
    echo '<div class="wrap">';
    echo '<h1>Order Analytics</h1>';

    // Display category filter dropdown
    $categories = get_categories(); // Retrieve all categories
    echo '<select id="category-filter">';
    echo '<option value="all">All Categories</option>';
    foreach ($categories as $category) {
        echo '<option value="' . $category->term_id . '">' . $category->name . '</option>';
    }
    echo '</select>';

    // Display order analytics data table
    echo '<table class="widefat">';
    echo '<thead><tr><th>Category</th><th>Product</th><th>Best Seller</th><th>Rating</th></tr></thead>';
    echo '<tbody>';

    foreach ($order_analytics as $order) {
        $category = $order->category;
        $product = $order->product;
        $best_seller = $order->best_seller;
        $rating = $order->product_rating;

        echo '<tr>';
        echo '<td>' . $category . '</td>';
        echo '<td>' . $product . '</td>';
        echo '<td>' . $best_seller . '</td>';
        echo '<td>' . $rating . '</td>';
        echo '</tr>';
    }

    echo '</tbody>';
    echo '</table>';
    echo '</div>';
}



// Hook into the admin menu creation
add_action('admin_menu', 'abandoned_carts_plugin_menu');

// Send abandoned cart email to selected users using PHPMailer
function send_abandoned_cart_email() {
    if (isset($_POST['send_email'])) {
        require_once 'path/to/PHPMailer/PHPMailerAutoload.php'; // Adjust the path to PHPMailer library

        $selected_carts = isset($_POST['selected_carts']) ? $_POST['selected_carts'] : array();
        $template_content = get_option('abandoned_cart_email_template', '');

        foreach ($selected_carts as $user_id) {
            $user_email = get_user_meta($user_id, 'billing_email', true);
            $email_subject = 'Abandoned Cart Reminder';
            $email_content = apply_filters('abandoned_cart_email_content', $template_content, $user_id);

            $mail = new PHPMailer();
            $mail->isSMTP();
            $mail->Host = 'your-smtp-host';
            $mail->SMTPAuth = true;
            $mail->Username = 'your-smtp-username';
            $mail->Password = 'your-smtp-password';
            $mail->SMTPSecure = 'tls';
            $mail->Port = 587;
            $mail->setFrom('from@example.com', 'Your Name');
            $mail->addAddress($user_email);
            $mail->Subject = $email_subject;
            $mail->Body = $email_content;

            if ($mail->send()) {
                // Email sent successfully
                // You can perform any additional actions here
            } else {
                // Failed to send email
                // You can handle the error here
            }
        }

        // Redirect the user to a confirmation page or perform any other necessary actions
        wp_redirect(admin_url('admin.php?page=abandoned_carts&email_sent=1'));
        exit;
    }
}
add_action('admin_post_send_abandoned_cart_email', 'send_abandoned_cart_email');





// Initialize the abandoned carts plugin
add_action('init', 'abandoned_carts_plugin_init');
