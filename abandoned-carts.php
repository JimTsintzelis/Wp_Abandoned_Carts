<?php 
/*

 Plugin Name: Abandoned Carts
 Plugin URI: http://www.mywebsite.com/abandoned-carts 
 Description: Recovering abandoned carts from sending e-mail with coupons to the users.
 Version: 1.0 
 Author: Dimitris Tsintzelis
 Author URI: http://www.mywebsite.com 
 License: GPLv3
License URI:https://www.gnu.org/licenses/gpl-3.0.html#license-text

Text Domain: AbandonedCarts
*/


// Register activation hook
register_activation_hook(__FILE__, 'abandoned_carts_activate');

function enqueue_abandoned_carts_scripts() {
    wp_enqueue_style('abandoned-carts', plugins_url('/abandoned-carts.css', __FILE__));
    wp_enqueue_script('abandoned-carts', plugins_url('/abandoned-carts.js', __FILE__), array('jquery'), null, true);
}

add_action('admin_enqueue_scripts', 'enqueue_abandoned_carts_scripts');



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
            last_updated DATETIME NOT NULL,  
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

register_activation_hook(__FILE__, 'abandoned_carts_activate');

function create_cart_click_log_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'cart_click_log';

    // Check if the table already exists
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        // SQL to create the new table
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE $table_name (
            click_id BIGINT(20) NOT NULL AUTO_INCREMENT,
            user_id MEDIUMINT(9) DEFAULT NULL,
            clicked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (click_id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}

// Hook the function to the plugin activation hook
register_activation_hook(__FILE__, 'create_cart_click_log_table');


function create_email_log_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'email_log';

    // Check if the table already exists
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        // SQL to create the new table
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE $table_name (
            id MEDIUMINT(9) NOT NULL AUTO_INCREMENT,
            user_id MEDIUMINT(9) NOT NULL,
            email VARCHAR(255) NOT NULL,
            status VARCHAR(100) NOT NULL,
            time DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}

// Hook the function to the plugin activation hook
register_activation_hook(__FILE__, 'create_email_log_table');


function create_unsubscribe_log_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'unsubscribe_log';

    // Check if the table already exists
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        // SQL to create the new table
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE $table_name (
            id MEDIUMINT(9) NOT NULL AUTO_INCREMENT,
            user_id MEDIUMINT(9) NOT NULL,
            email VARCHAR(255) NOT NULL,
            time DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}

// Hook the function to the plugin activation hook
register_activation_hook(__FILE__, 'create_unsubscribe_log_table');


function your_plugin_admin_init() {
    register_setting('your-settings-group', 'abandoned_cart_threshold', 'intval');
}
add_action('admin_init', 'your_plugin_admin_init');



// Hook into WooCommerce add to cart action to capture abandoned cart data
add_action('woocommerce_add_to_cart', 'capture_abandoned_cart_data', 10, 6);

function capture_abandoned_cart_data($cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data) {
    // Retrieve relevant cart data using WooCommerce functions
    $cart_contents = array();
    foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
        $product = $cart_item['data'];
        $cart_contents[] = array(
            'product_id' => $cart_item['product_id'],
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

    // Generate a unique token for the cart
    $cart_token = md5(uniqid($user_id . '_', true));

    // Serialize and store the cart contents in the custom table
    global $wpdb;
    $table_name = $wpdb->prefix . 'abandoned_carts';

    $current_time = current_time('mysql');
    
    // Check if the user already has an abandoned cart
    $existing_abandoned_cart = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $table_name WHERE user_id = %d AND is_abandoned = 0",
        $user_id
    ));
    
    // If a cart already exists, update it, otherwise create a new entry
    if ($existing_abandoned_cart) {
        $wpdb->update(
            $table_name,
            array(
                'cart_contents' => maybe_serialize($cart_contents),
                'cart_token' => $cart_token, // Update the cart token
                'email' => $email,
                'first_name' => $first_name,
                'last_name' => $last_name,
                'created_at' => current_time('mysql'),
                'is_abandoned' => 0,
                'last_updated' => $current_time,
            ),
            array('id' => $existing_abandoned_cart)
        );
    } else {
        $wpdb->insert($table_name, array(
            'user_id' => $user_id,
            'cart_contents' => maybe_serialize($cart_contents),
            'cart_token' => $cart_token, // Store the cart token
            'email' => $email,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'created_at' => current_time('mysql'),
            'is_abandoned' => 0,
            'last_updated' => $current_time,
        ));
    }
}

add_action('check_abandoned_carts_hook', 'check_and_update_abandoned_carts');

function check_and_update_abandoned_carts() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'abandoned_carts';
    $current_time = current_time('timestamp');
    $threshold_minutes = get_option('abandoned_cart_threshold', 60); // Default to 60 minutes if not set
    $threshold_time = $current_time - ($threshold_minutes * 60);

    $carts = $wpdb->get_results(
        "SELECT id, last_updated FROM $table_name WHERE is_abandoned = 0",
        ARRAY_A
    );

    foreach ($carts as $cart) {
        if (strtotime($cart['last_updated']) < $threshold_time) {
            $wpdb->update(
                $table_name,
                array('is_abandoned' => 1),
                array('id' => $cart['id'])
            );
        }
    }
}


add_filter('cron_schedules', 'add_every_minute_cron_schedule');
function add_every_minute_cron_schedule($schedules) {
    $schedules['every_minute'] = array(
        'interval' => 60,
        'display'  => __('Every Minute')
    );
    return $schedules;
}

function my_plugin_schedule_abandoned_cart_check() {
    if (!wp_next_scheduled('check_abandoned_carts_hook')) {
        wp_clear_scheduled_hook('check_abandoned_carts_hook'); // Clear out any other schedules to avoid duplication
        wp_schedule_event(time(), 'every_minute', 'check_abandoned_carts_hook');
    }
}
add_action('init', 'my_plugin_schedule_abandoned_cart_check');


// Hook into WooCommerce remove cart item action to update abandoned cart data
add_action('woocommerce_remove_cart_item', 'update_abandoned_cart_after_removal', 10, 2);


function abandoned_carts_settings_page() {
    ?>
    <div class="wrap">
        <h1>Abandoned Cart Settings</h1>
        <form method="post" action="options.php">
            <?php settings_fields('your-settings-group'); ?>
            <?php do_settings_sections('your-settings-group'); ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row" style="padding: 20px;">Abandoned Cart Threshold (minutes):</th>
                    <td><input type="number" name="abandoned_cart_threshold" value="<?php echo esc_attr(get_option('abandoned_cart_threshold', 60)); ?>" /></td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}


// Update abandoned cart data when a cart item is removed
function update_abandoned_cart_after_removal($cart_item_key, $cart) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'abandoned_carts';
    $customer = WC()->customer;
    $user_id = $customer->get_id();
    
    // Find the abandoned cart entry for the user
    $abandoned_cart = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT id, cart_contents FROM $table_name WHERE user_id = %d AND is_abandoned = 1",
            $user_id
        ),
        ARRAY_A
    );
    
    if ($abandoned_cart) {
        // Deserialize the cart contents to update them
        $cart_contents = maybe_unserialize($abandoned_cart['cart_contents']);
        
        // Remove the item from the array
        foreach ($cart_contents as $index => $item) {
            if ($item['product_id'] == $cart->get_cart()[$cart_item_key]['product_id']) {
                unset($cart_contents[$index]);
                break; // Stop the loop after removing the item
            }
        }
        
        // Re-index the array to prevent serialization issues
        $cart_contents = array_values($cart_contents);

        // Update the abandoned cart entry with the new cart contents
        $wpdb->update(
            $table_name,
            array('cart_contents' => maybe_serialize($cart_contents)),
            array('id' => $abandoned_cart['id'])
        );
    }
}




// Hook into WooCommerce update cart action to update abandoned cart data
add_action('woocommerce_update_cart_action_cart_updated', 'update_abandoned_cart_data', 20, 1);

function update_abandoned_cart_data($cart_updated) {

    if ($cart_updated) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'abandoned_carts';
        $customer = WC()->customer;
        $user_id = $customer->get_id();

        $cart_contents = array();
        foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
            $product = $cart_item['data'];
            $cart_contents[] = array(
                'product_id' => $product->get_id(),
                'name' => $product->get_name(),
                'quantity' => $cart_item['quantity'],
                'price' => wc_get_price_to_display($product),
            );
        }

        $abandoned_cart_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM $table_name WHERE user_id = %d",
                $user_id
            )
        );

        if ($abandoned_cart_id) {
            $wpdb->update(
                $table_name,
                array('cart_contents' => maybe_serialize($cart_contents)),
                array('id' => $abandoned_cart_id)
            );
        } 
        
        else {
            $wpdb->insert(
                $table_name,
                array(
                    'user_id' => $user_id,
                    'cart_contents' => maybe_serialize($cart_contents),
                    'email' => $customer->get_email(),
                    'first_name' => $customer->get_first_name(),
                    'last_name' => $customer->get_last_name(),
                    'created_at' => current_time('mysql'),
                )
            );
        }
    }
}





// Hook into WooCommerce order status completed action to delete abandoned cart data
add_action('woocommerce_order_status_completed', 'delete_abandoned_cart_data', 10, 1);


function delete_abandoned_cart_data($order_id) {
    $order = wc_get_order($order_id);
    $user_id = $order->get_user_id();

    if (!$user_id) {
        error_log('Order completed for guest user: ' . $order_id);
        return;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'abandoned_carts';
    $result = $wpdb->delete($table_name, array('user_id' => $user_id));

    if (false === $result) {
        error_log('Failed to delete abandoned cart data for user ID: ' . $user_id);
    } else {
        error_log('Abandoned cart data deleted for user ID: ' . $user_id);
    }
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
        'abandoned_carts_page',
        'dashicons-cart',
        30
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


   // Assuming this is inside your admin_menu setup function
add_submenu_page(
    'abandoned-carts-plugin',       // The slug of the parent menu
    'Settings',                     // The title of the settings page
    'Settings',                     // The text for the menu item
    'manage_options',               // The capability required to access this menu item
    'abandoned-carts-settings',     // The slug for this submenu item
    'abandoned_carts_settings_page' // The function that will render the settings page
);

}



// Callback for the plugin dashboard page
function abandoned_carts_plugin_dashboard() {
    // Output your dashboard page content here
    echo '<h1>Abandoned Carts Plugin Dashboard</h1>';
    echo '<p>Welcome to the Abandoned Carts Plugin dashboard page.</p>';
    // Add any other content or functionality you need for the plugin dashboard
}



function abandoned_carts_page() {
    handle_template_save();
    display_abandoned_carts();
}

function handle_template_save() {
    if (isset($_POST['save_template'])) {
        $selected_template = isset($_POST['email_template']) ? sanitize_text_field($_POST['email_template']) : '';
        $template_content = isset($_POST['email_content']) ? sanitize_textarea_field($_POST['email_content']) : '';

        // Implement your logic to save the template content
    }
}


function display_abandoned_carts() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'abandoned_carts';
    $abandoned_carts = $wpdb->get_results("SELECT * FROM $table_name where is_abandoned ='1'");
    $threshold_minutes = get_option('abandoned_cart_threshold', 60); // Default to 60 if not set
    ?>


    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/lottie-web/5.7.4/lottie.min.js"></script>

    <div class="wrap">
        <h1 class="mb-4">Abandoned Carts</h1>

        <p> Abandoned Cart Threshold: <?php echo esc_html($threshold_minutes);?> minutes</p>

        <table class="table table-striped">
            <thead>
                <tr>
                    <th scope="col"></th>
                    <th scope="col">User ID</th>
                    <th scope="col">Email</th>
                    <th scope="col">First Name</th>
                    <th scope="col">Last Name</th>
                    <th scope="col">Cart Contents</th>
                    <th scope="col">Abandoned Datetime</th>
                    <th scope="col">Subscription Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($abandoned_carts as $abandoned_cart): 
                    $cart_json = json_encode(maybe_unserialize($abandoned_cart->cart_contents));
                ?>
                    <tr class="cart-row" data-cart='<?php echo esc_attr($cart_json); ?>'>
                        <td><input type="checkbox" name="selected_carts[]" value="<?php echo esc_attr($abandoned_cart->user_id); ?>"></td>
                        <td><?php echo esc_html($abandoned_cart->user_id); ?></td>
                        <td><?php echo esc_html($abandoned_cart->email); ?></td>
                        <td><?php echo esc_html($abandoned_cart->first_name); ?></td>
                        <td><?php echo esc_html($abandoned_cart->last_name); ?></td>
                        <td><button class="btn btn-primary view-cart-button"><i class="fa fa-shopping-cart"></i></button></td>
                        <td><?php echo esc_html($abandoned_cart->created_at); ?></td>
                        <td><?php echo esc_html($abandoned_cart->is_subscribed ? 'Subscribed' : 'Unsubscribed'); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div id="modal" class="modal">
                <div class="modal-content" id="modal-content">
                    <!-- Cart details will be populated here -->
                </div>
                <button class="modal-quit-button" id="quit-button">Quit</button>
                <span class="close" id="close-modal">&times;</span>
            </div>

            <button id="send_email_button" class="btn" type="button" style="background-color: #009688; color: white; margin-top: 20px; border: none; padding: 10px 20px; border-radius: 5px; font-weight: bold; text-transform: uppercase; transition: background-color 0.3s ease;">
                Send Email
            </button>

<script type="text/javascript">
        jQuery(document).ready(function($) {
            $('#send_email_button').on('click', function(e) {
                e.preventDefault();
                var selectedUsers = [];
                $('input[name="selected_carts[]"]:checked').each(function() {
                    selectedUsers.push($(this).val());
                });

                if (selectedUsers.length > 0) {
                    var queryString = selectedUsers.join(',');
                    var emailTemplatePageUrl = 'admin.php?page=email-template&user_ids=' + encodeURIComponent(queryString);
                    window.location.href = emailTemplatePageUrl;
                } else {
                    alert('Please select at least one user.');
                }
            });
        });
        </script>
    </div>
    <?php
}



    wp_localize_script('my-custom-script', 'abandonedCartData', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'user_emails' => $user_emails
    ));


function enqueue_bootstrap($hook) {
    if ('edit.php' !== $hook) {
        return;
    }

    wp_enqueue_style('bootstrap-css', 'https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css');
    wp_enqueue_script('bootstrap-js', 'https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.bundle.min.js', array('jquery'), null, true);
}


function enqueue_select2() {
    wp_enqueue_style('select2-css', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css');
    wp_enqueue_script('select2-js', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js', array('jquery'), null, true);
}

add_action('admin_enqueue_scripts', 'enqueue_select2');
add_action('admin_enqueue_scripts', 'enqueue_bootstrap');

function enqueue_bootstrap_scripts() {
    wp_enqueue_script('bootstrap-js', 'https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js', array('jquery'), null, true);
}

add_action('admin_enqueue_scripts', 'enqueue_bootstrap_scripts');

function enqueue_font_awesome() {
    wp_enqueue_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css');
}
add_action('admin_enqueue_scripts', 'enqueue_font_awesome');

function enqueue_google_fonts() {
    wp_enqueue_style('google-fonts', 'https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap');
}
add_action('admin_enqueue_scripts', 'enqueue_google_fonts');

function enqueue_jquery_migrate() {
    wp_enqueue_script('jquery-migrate', 'https://code.jquery.com/jquery-migrate-3.3.2.min.js', array('jquery'), null, true);
}

add_action('admin_enqueue_scripts', 'enqueue_jquery_migrate');



function email_template_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }

    $user_ids = isset($_GET['user_ids']) ? explode(',', $_GET['user_ids']) : array();
    global $wpdb;
    $table_name = $wpdb->prefix . 'abandoned_carts';

    // Generic email template
    $email_content = "Dear [user_name],\n\nWe noticed that you have left some items in your shopping cart:\n\n[cart_contents]\n[coupons] \n\nPlease click on the link below to return to your cart and complete your purchase:\n[restore_cart_url]\n\nUnsubscribe from future emails:\n ";

    $coupons = get_available_coupons();
    enqueue_select2();

    echo '<style>
    .email-template-container { background-color: #f9f9f9; padding: 30px; border-radius: 8px; margin-top: 20px; margin-right: 20px; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);}
    .form-group { margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between; }
    .form-label { display: block; font-weight: bold; margin-bottom: 10px; }
    .form-control { border-radius: 4px; border: 1px solid #ccc; padding: 10px; width: calc(100% - 50px); /* Adjusted width to account for button */box-sizing: border-box; }
    .btn-custom { background-color: #007bff; color: white; border: none; border-radius: 50px; padding: 8px 16px; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 14px;}
    .btn-custom:hover { background-color: #0056b3;}
    #email_content { height: 300px; resize: vertical; }
    .button-group {text-align: right; }
    .input-group {display: flex; align-items: center; gap: 10px; width: 100%;}
    .select2-container--default .select2-selection--multiple { /* If you decide to use Select2 in the future */ }
    .select2-container--default .select2-selection--multiple .select2-selection__choice { /* If you decide to use Select2 in the future */ }
    .select2-container--default .select2-selection--multiple .select2-selection__choice__remove { /* If you decide to use Select2 in the future */ }
    </style>
    ';?>

    <div id="successNotification" style="display:none;">
        <div class="tick-icon">&#10004;</div>
        <p>Email sent successfully!</p>
    </div>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">

    <div class="email-template-container">

            <div class="form-group">
                <label for="coupon_selector" class="form-label">Select coupons (optional):</label>
                <div class="input-group">
                    <select id="coupon_selector" name="coupon_selector[]" multiple class="form-control">
                        <?php foreach ($coupons as $coupon): ?>
                            <option value="<?php echo esc_attr($coupon['code']); ?>">
                                <?php echo esc_html($coupon['code']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button id="update_template" class="btn btn-custom">
                        <i class="fas fa-sync-alt"></i>
                    </button>
                </div>
            </div>

        <div class="email-content">
            <label for="email_content" class="form-label">Email Content:</label>
            <textarea id="email_content" class="form-control" rows="20" cols="180" class="form-control"><?php echo esc_textarea($email_content); ?></textarea>
        </div>

        <button id="send_email" class="btn btn-custom" style="border-radius: 5px; margin-top: 10px; ">Send Email</button>
    </div>


    <script type="text/javascript">
        jQuery(document).ready(function($) {

        var sendEmailButton = $('#send_email');

        var couponSelector = $('#coupon_selector');
        var emailContentTextarea = $('#email_content');
        var updateTemplateButton = $('#update_template');
        var couponTextPlaceholder = "[coupon_codes]"; 

        function showNotification(message) {
            var notification = $('<div />', {
                'class': 'coupon-notification',
                'text': message,
                'css': {
                    'position': 'fixed',
                    'top': '50px',
                    'right': '50px',
                    'background': 'rgba(99, 121, 178, 0.8)',
                    'color': '#ffffff',
                    'padding': '10px 20px',
                    'border-radius': '5px',
                    'box-shadow': '0 4px 6px rgba(0, 0, 0, 0.1)',
                    'z-index': '10000',
                    'display': 'none'
                }
            }).appendTo('body');

            notification.fadeIn(500).delay(3000).fadeOut(500, function() {
                $(this).remove();
            });
        }

        updateTemplateButton.on('click', function() {
            var selectedCoupons = couponSelector.val();
            var couponText = selectedCoupons.length > 0 ? "Applied coupons: " + selectedCoupons.join(', ') : "No coupons selected";
            var emailContentWithCoupons = emailContentTextarea.val().replace(couponTextPlaceholder, couponText);
            emailContentTextarea.val(emailContentWithCoupons);
            emailContentTextarea.val(emailContentTextarea.val().replace(couponText, couponTextPlaceholder));
            showNotification(couponText);
            $(this).html('<i class="fas fa-sync-alt"></i>');
        });

            sendEmailButton.on('click', function() {
                var emailContent = emailContentTextarea.val();
                var selectedCoupons = couponSelector.val().join(',');

                <?php foreach ($user_ids as $user_id): ?>
                $.post(ajaxurl, {
                    action: 'send_abandoned_cart_email',
                    user_id: <?php echo $user_id; ?>,
                    coupon_codes: selectedCoupons,
                    email_content: emailContent
                }, function(response) {

        

                    console.log('Response:', response);
                    // Show the Bootstrap modal
                    $('#successNotification').fadeIn();

                    // Automatically close the modal after 3 seconds
                    setTimeout(function() {
                        $('#successNotification').fadeOut();
                    }, 3000);

                }).fail(function(jqXHR, textStatus, errorThrown) {
                    console.error('AJAX error:', textStatus, 'Error:', errorThrown);
                });
                <?php endforeach; ?>
            });
        });
    </script>
    <?php
}


add_action('wp_ajax_send_abandoned_cart_email', 'send_abandoned_cart_email_callback');

function send_abandoned_cart_email_callback() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'abandoned_carts';

    $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
    $coupon_codes = isset($_POST['coupon_codes']) ? sanitize_text_field($_POST['coupon_codes']) : ''; // Multiple codes expected
    $generic_email_content = isset($_POST['email_content']) ? $_POST['email_content'] : '';

    $user_info = $wpdb->get_row($wpdb->prepare("SELECT first_name, last_name, email FROM $table_name WHERE user_id = %d", $user_id));
    $cart = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE user_id = %d", $user_id));

    if ($user_info) {
        $cart_contents = maybe_unserialize($cart->cart_contents);
        $items_list = '<table cellspacing="0" cellpadding="10" border="0" style="width: 100%; border-collapse: collapse;">';
        foreach ($cart_contents as $item) {
            $plain_price = wp_strip_all_tags(wc_price($item['price']));
            $items_list .= "<tr style='border-bottom: 1px solid #ddd;'>
                                <td style='padding: 10px; border-bottom: 1px solid #eee;'>{$item['name']}</td>
                                <td style='padding: 10px; border-bottom: 1px solid #eee;'>Quantity: {$item['quantity']}</td>
                                <td style='padding: 10px; border-bottom: 1px solid #eee;'>Price: {$plain_price}</td>
                            </tr>";
        }
        $items_list .= '</table>';

        $timestamp = time();
        $restore_cart_url = add_query_arg(
            array(
                'restore_cart_token' => $cart->cart_token,
                'apply_coupons' => $coupon_codes,
                'click_id' => $timestamp // Unique identifier for the click
            ),
            wc_get_cart_url()
        );

        $coupon_codes = isset($_POST['coupon_codes']) ? sanitize_text_field($_POST['coupon_codes']) : '';
        $couponText = '';
        
        if (!empty($coupon_codes)) {
            $couponText = "Use the following coupon codes for discounts on your next purchase: " . $coupon_codes . "";
        }
     
        $local_image_url = 'http://localhost/Pharm247/wp-content/uploads/2023/05/apivita-frequent-use-sampouan-kathim-chrisis-500ml-vuH0hw-1.webp';

        $email_content .= "\n\nTo unsubscribe from future emails, please click here: [unsubscribe_url]\n\n";
        $unsubscribe_url = site_url('/unsubscribe-handler.php?user_id=' . $user_id);
        $email_content = str_replace('[unsubscribe_url]', $unsubscribe_url, $email_content);
        

        $unsubscribe_url = admin_url('admin-post.php?action=unsubscribe_abandoned_cart&user=' . $user_id);
        $unsubscribe_link = "<a href='" . esc_url($unsubscribe_url) . "' style='background-color: #F85252; color: white; padding: 10px 15px; text-decoration: none; border-radius: 5px; display: inline-block;'>Unsubscribe</a>";
        $restore_cart_button = "<a href='" . esc_url($restore_cart_url) . "' style='background-color: #E6C99B; padding: 10px 15px; margin-top: 5px; text-decoration: none; border-radius: 5px; display: inline-block; color: white; text-align: center;'>Restore Your Cart</a>";

        $personalized_content = str_replace('[user_name]', $user_info->first_name . ' ' . $user_info->last_name, $generic_email_content);
        $personalized_content = str_replace('[cart_contents]', $items_list, $personalized_content);
        
        if (!empty($couponText)) {
            $personalized_content = str_replace('[coupons]',$couponText,$personalized_content);
        }
        else {  $personalized_content = str_replace('[coupons]'," ", $personalized_content); }

        $personalized_content = str_replace('[restore_cart_url]', $restore_cart_button, $personalized_content);
        $personalized_content .= "<p>" . $unsubscribe_link . "</p>";

        $to = sanitize_email($user_info->email);
        $subject = 'Complete Your Purchase at Our Store';
        $headers = array('Content-Type: text/html; charset=UTF-8');

        $sent = wp_mail($to, $subject, nl2br($personalized_content), $headers);
        echo $sent ? 'Email sent successfully to ' . $user_info->email : 'Failed to send email to ' . $user_info->email;


        if ($sent) {
            global $wpdb;
            $wpdb->insert(
                $wpdb->prefix . 'email_log',
                array(
                    'user_id' => $user_id,
                    'email' => $to,
                    'status' => 'sent'
                )
            );
        }
        else { echo 'Failed to send email to ' . $user_info->email; }
            
    } 
    
    else { echo 'User or cart data not found for user ID ' . $user_id; }
    wp_die();
}



function apply_coupon_codes_from_url() {

    global $wpdb;

    if (is_cart() && !empty($_GET['apply_coupons'])) {

        WC()->cart->remove_coupons();

        WC()->session->__unset('applied_coupons_from_url');

        $coupons = explode(',', sanitize_text_field($_GET['apply_coupons']));
        $session_applied_coupons = array(); // Reset the session applied coupons array

        foreach ($coupons as $coupon_code) {
            if (!WC()->cart->has_discount($coupon_code)) {
                WC()->cart->add_discount($coupon_code);
                $session_applied_coupons[] = $coupon_code; // Add the applied coupon to the session array
            }
        }

        WC()->session->set('applied_coupons_from_url', $session_applied_coupons);
    
        if (!WC()->session->get('initial_product_ids')) {
            $initial_product_ids = array();
            foreach (WC()->cart->get_cart() as $cart_item) {
                $initial_product_ids[] = $cart_item['product_id'];
            }
            WC()->session->set('initial_product_ids', $initial_product_ids);
        }

        if (!empty($_GET['click_id'])) {

            $click_id = sanitize_text_field($_GET['click_id']);
            $user_id = get_current_user_id(); // Get current user ID, if available

            // Set a session variable to mark that the cart has been restored
            WC()->session->set('restored_from_email', true);

            // Debug: Print the click_id for testing
            //echo '<div>Click ID: ' . esc_html($click_id) . '</div>';
            //echo '<div>User ID: ' . esc_html($user_id) . '</div>';

            $wpdb->insert(
                $wpdb->prefix . 'cart_click_log',
                array(
                    'click_id' => $click_id,
                    'user_id' => $user_id ? $user_id : null
                ),
                array('%s','%d')
            );
    
            if ($wpdb->last_error) {
                echo 'Database error: ' . $wpdb->last_error; // For debugging
            }

        }
        else {echo 'no click';}
    }
}
add_action('wp', 'apply_coupon_codes_from_url');



function mark_order_as_restored_from_email($order_id) {
    if (WC()->session->get('restored_from_email')) {
        update_post_meta($order_id, 'restored_from_email', true);
        WC()->session->__unset('restored_from_email');
    }
}
add_action('woocommerce_checkout_update_order_meta', 'mark_order_as_restored_from_email');



function fetch_cart_data_ajax_handler() {
    // Fetch the initial product IDs stored in the session
    $initial_product_ids = WC()->session->get('initial_product_ids', array());

    // Fetch the current cart contents
    $current_cart_data = array();
    foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
        $product = $cart_item['data'];
        $current_cart_data[] = array(
            'product_id' => $cart_item['product_id'],
            'name' => $product->get_name(),
            'quantity' => $cart_item['quantity'],
            'price' => $product->get_price(),
            // ... other data ...
        );
    }

    // Send both sets of data to JavaScript
    wp_send_json(array(
        'initial_product_ids' => $initial_product_ids,
        'current_cart_data' => $current_cart_data
    ));
}

add_action('wp_ajax_fetch_cart_data', 'fetch_cart_data_ajax_handler');
add_action('wp_ajax_nopriv_fetch_cart_data', 'fetch_cart_data_ajax_handler');


function my_enqueue_custom_script() {
    wp_enqueue_script('my-custom-script', plugins_url('/custom-script.js', __FILE__), array('jquery'), null, true);
}
add_action('wp_enqueue_scripts', 'my_enqueue_custom_script');


function my_enqueue_cart_logger_script() {
    wp_enqueue_script('my-cart-logger-script', plugins_url('/custom-cart-logger.js', __FILE__), array('jquery'), null, true);
    
    // Provide the AJAX URL to the script
    wp_localize_script('my-cart-logger-script', 'ajax_object', array('ajax_url' => admin_url('admin-ajax.php')));
}
add_action('wp_enqueue_scripts', 'my_enqueue_cart_logger_script');





function is_product_eligible_for_discount($product_id) {
    $initial_product_ids = WC()->session->get('initial_product_ids', array());
    return in_array($product_id, $initial_product_ids);
}


add_filter('woocommerce_coupon_get_discount_amount', 'customize_coupon_discount_amount', 10, 5);
function customize_coupon_discount_amount($discount, $discounting_amount, $cart_item, $single, $coupon) {
    if (is_product_eligible_for_discount($cart_item['product_id'])) {
        // Calculate discount as usual
        return $discount;
    } else {
        // No discount for this item
        return 0;
    }
}


function detect_coupon_removal($coupon_code) {
    error_log('Coupon removed: ' . $coupon_code);
    $session_applied_coupons = WC()->session->get('applied_coupons_from_url', array());
    echo  $session_applied_coupons;
    // Check if the removed coupon is in the session's applied coupons array
    if (in_array($coupon_code, $session_applied_coupons)) {
        // Remove the coupon from the array
        $session_applied_coupons = array_diff($session_applied_coupons, array($coupon_code));

        // Update the session with the modified array
        WC()->session->set('applied_coupons_from_url', $session_applied_coupons);
    }
}

add_action('woocommerce_removed_coupon', 'detect_coupon_removal');


function clear_coupon_session_data() {
    WC()->session->__unset('applied_coupons_from_url');
}



add_action('woocommerce_checkout_order_processed', 'clear_coupon_session_data');

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



function unsubscribe_abandoned_cart() {
    if (isset($_GET['user'])) {
        global $wpdb;
        $user_id = intval($_GET['user']);

        // Retrieve the user's email
        $user_info = get_userdata($user_id);
        $user_email = $user_info->user_email;

        // Update the is_subscribed value
        $table_name = $wpdb->prefix . 'abandoned_carts';
        $wpdb->update(
            $table_name,
            array('is_subscribed' => 0),
            array('user_id' => $user_id)
        );

        // Log the unsubscription event
        $log_table_name = $wpdb->prefix . 'unsubscribe_log';
        $wpdb->insert(
            $log_table_name,
            array(
                'user_id' => $user_id,
                'email' => $user_email
            )
        );

        // Redirect to confirmation page
        wp_redirect(home_url('/unsubscribe-confirmation'));
        exit;
    }
}
add_action('admin_post_unsubscribe_abandoned_cart', 'unsubscribe_abandoned_cart');
add_action('admin_post_nopriv_unsubscribe_abandoned_cart', 'unsubscribe_abandoned_cart');



function my_theme_enqueue_styles() {
    wp_enqueue_style('bootstrap-css', 'https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css');
}
add_action('wp_enqueue_scripts', 'my_theme_enqueue_styles');


function recovered_orders_page() {
    global $wpdb;

    // Get email log data
    $email_logs = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "email_log");
    $total_emails_sent = count($email_logs);
    ?>
    <div class="container mt-5">
        <h2 class="mb-5">Recovery Emails</h2>
        
        <div class="alert alert-primary rounded-pill text-center">Total Emails Sent: <?php echo $total_emails_sent; ?></div>

        <div class="table-responsive">
            <table class="table table-hover">
                <thead class="thead-light">
                    <tr><th>User ID</th><th>Email</th><th>Status</th><th>Time</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($email_logs as $log): ?>
                        <tr>
                            <td><?php echo esc_html($log->user_id); ?></td>
                            <td><?php echo esc_html($log->email); ?></td>
                            <td><?php echo esc_html($log->status); ?></td>
                            <td><?php echo esc_html($log->time); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <style>
        .container {
            margin-top: 20px;
            margin-left: 10px;
            margin-right: 20px;
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.1);
        }

        .table-hover tbody tr:hover {
            background-color: #e9ecef;
        }

        .alert-primary {
            background-color: #009688; 
            border-color: #00796b;
            color: #fff;
            padding: 10px; 
            font-size: 1rem; 
            font-weight: bold; 
            border-radius: 5px; 
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2); 
        }

        .table-responsive {
        max-height: 250px; 
        overflow-y: auto; 
        -ms-overflow-style: -ms-autohiding-scrollbar; 
        }
    
        .table-responsive thead th {
            position: sticky;
            top: 0;
            background: #f8f9fa;
        }

        .table-responsive::-webkit-scrollbar {
            display: none;
        }

        .table-responsive {
            -ms-overflow-style: none;  
            scrollbar-width: none; 
        }

        .order-status-counts .badge {
            background-color: #009688;
            font-weight: bold; 
            color: white;
            margin-right: 10px;
            padding: 5px;
            border-radius: 5px;
            font-size: 0.8rem;
        }

        .subscription-status-counts .badge {
        margin-right: 10px;
        padding: 5px;
        }

        .badge-success {
            font-weight: bold; 
            border-radius: 5px;
            background-color: #75D6A7;
        }

        .badge-danger {
            font-weight: bold; 
            border-radius: 5px;
            background-color: #F35656;
        }

        .clicks-counter {
        background-color: #e9ecef;
        padding: 10px;
        border-radius: 5px;
        margin-bottom: 20px;
    }
    .clicks-counter strong {
        color: #333;
    }

    </style>
    <?php


    $args = array(
        'limit' => 10, // You can adjust the limit as needed
    );

    $orders = wc_get_orders($args);
    //echo '<pre>Number of Orders: ' . count($orders) . '</pre>';

    foreach ($orders as $order) {
        $status = $order->get_status();
        if (!isset($status_counts[$status])) {
            $status_counts[$status] = 0;
        }
        $status_counts[$status]++;
    }
    
    // Debugging: Output status counts
    //echo '<pre>Status Counts: ' . print_r($status_counts, true) . '</pre>';

        // Fetch and display unsubscribe log
        $unsubscribe_logs = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}unsubscribe_log");

        // Fetch subscription status counts
        $subscribed_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}abandoned_carts WHERE is_subscribed = 1");
        $unsubscribed_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}abandoned_carts WHERE is_subscribed = 0");

        $click_logs = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}cart_click_log");
        $unique_clicks_count = $wpdb->get_var("SELECT COUNT(DISTINCT click_id) FROM {$wpdb->prefix}cart_click_log");


        
    ?>
    <div class="container mt-5">
        <h2 class="mb-5">WooCommerce Orders</h2>

        <div class="order-status-counts mb-4">
            <?php foreach ($status_counts as $status => $count): ?>
                <span class="badge badge-secondary"><?php echo ucfirst($status) . ': ' . $count; ?></span>
            <?php endforeach; ?>
        </div>

        <div class="table-responsive">
            <table class="table table-hover">
            <thead class="thead-light">
                <tr>
                    <th>Order ID</th>
                    <th>Date</th>
                    <th>Status</th>
                    <th>Total</th>
                    <th>Customer</th>
                    <th>Coupons Used</th>
                    <th>Restored from Email</th> <!-- New Column -->
                </tr>
            </thead>

            <tbody>
                <?php foreach ($orders as $order): 
                    // Check if the order was restored from an email
                    $restored_from_email = get_post_meta($order->get_id(), 'restored_from_email', true);
                    ?>
                    <tr>
                        <td><?php echo esc_html($order->get_id()); ?></td>
                        <td><?php echo esc_html($order->get_date_created()->date('Y-m-d H:i:s')); ?></td>
                        <td><?php echo esc_html(wc_get_order_status_name($order->get_status())); ?></td>
                        <td><?php echo wc_price($order->get_total()); ?></td>
                        <td><?php echo esc_html($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()); ?></td>
                        <td>
                            <?php 
                            $coupons = $order->get_used_coupons();
                            echo esc_html(implode(', ', $coupons));
                            ?>
                        </td>
                        <td><?php echo $restored_from_email ? 'Yes' : 'No'; ?></td> <!-- Display Restored from Email status -->
                    </tr>
                <?php endforeach; ?>
            </tbody>

            </table>
        </div>
    </div>



    <div class="container mt-5">

        <h2 class="mb-5">Unsubscribe Log</h2>
        <div class="subscription-status-counts mb-4">
            <span class="badge badge-success">Subscribed: <?php echo $subscribed_count; ?></span>
            <span class="badge badge-danger">Unsubscribed: <?php echo $unsubscribed_count; ?></span>
        </div>


        <div class="table-responsive">
            <table class="table table-hover">
                <thead class="thead-light">
                    <tr><th>User ID</th><th>Email</th><th>Time</th></tr>
                </thead>
                <tbody>
                    <?php
                    $unsubscribe_logs = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}unsubscribe_log");
                    foreach ($unsubscribe_logs as $log): ?>
                        <tr>
                            <td><?php echo esc_html($log->user_id); ?></td>
                            <td><?php echo esc_html($log->email); ?></td>
                            <td><?php echo esc_html($log->time); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>



    <div class="container mt-5">

        <h2 class="mb-5">User Interaction with Recovery Emails</h2>
        <div class="clicks-counter mb-3">
            <strong>Total Unique Clicks:</strong> <?php echo $unique_clicks_count; ?>
        </div>

            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="thead-light">
                        <tr>
                            <th>User ID</th>
                            <th>Restore Click ID</th>
                            <th>Email</th>
                            <th>Click Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($click_logs as $log): ?>
                            <tr>
                                <td><?php echo esc_html($log->user_id); ?></td>
                                <td><?php echo esc_html($log->click_id); ?></td>
                                <td>
                                    <?php
                                    // Fetch and display user email
                                    $user_info = get_userdata($log->user_id);
                                    echo esc_html($user_info ? $user_info->user_email : 'N/A');
                                    ?>
                                </td>
                                <td><?php echo esc_html($log->clicked_at); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php

}


function order_analytics_page() {
    global $wpdb;

        // Fetch the logs and counts from the database
        $email_logs = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "email_log");
        $total_emails_sent = count($email_logs);
        $unique_clicks_count = $wpdb->get_var("SELECT COUNT(DISTINCT click_id) FROM {$wpdb->prefix}cart_click_log");
        

        $restored_orders_count = 0;
        $other_orders_count = 0; // You will need to calculate this


        // Fetch all orders
        $orders = wc_get_orders(array(
            'limit' => -1, // Be cautious with this on large databases
        ));

        // Loop through the orders to count how many were restored from email
        foreach ($orders as $order) {
            $restored_from_email = get_post_meta($order->get_id(), 'restored_from_email', true);
            if ($restored_from_email) {
                $restored_orders_count++;
            } else {
                $other_orders_count++; // Assuming all other orders are not restored from email
            }
        }

        // Perform calculations for conversion and engagement rates
        $conversion_rate = ($unique_clicks_count > 0) ? ($restored_orders_count / $unique_clicks_count) * 100 : 0;
        $engagement_rate = ($total_emails_sent > 0) ? ($unique_clicks_count / $total_emails_sent) * 100 : 0;
    
        // Initialize arrays for line graph data
        $order_dates = [];
        $order_counts = [];

        // Fetch all orders
        $orders = wc_get_orders(array(
            'limit' => -1, // Adjust this based on your database size
        ));

        // Process orders to count per day
        foreach ($orders as $order) {
            $order_date = $order->get_date_created()->format('Y-m-d');
            if (!isset($order_dates[$order_date])) {
                $order_dates[$order_date] = 0;
            }
            $order_dates[$order_date]++;
        }

        // Prepare data for the line graph
        foreach ($order_dates as $date => $count) {
            array_push($order_counts, $count);
        }
        $line_graph_labels = json_encode(array_keys($order_dates));
        $line_graph_data = json_encode(array_values($order_counts));

        /* Print the labels and data for debugging
        echo '<pre>Labels: ';
        print_r($line_graph_labels);
        echo '</pre>';
        echo '<pre>Data: ';
        print_r($line_graph_data);
        echo '</pre>';*/

        // Enqueue Chart.js
        wp_enqueue_script('chartjs', 'https://cdn.jsdelivr.net/npm/chart.js');

        ?>

        <div class="wrap">

        <h1>Order Analytics</h1>

        <!-- Flex Container for Rate Boxes -->
        <div class="rates-container-flex" style=" gap:20px;">

            <!-- Container for Conversion Rate -->
            <div class="rate-container" style="flex: 1; background: linear-gradient(to right, #f8f9fa, #f8f9fa); color: black; margin-bottom: 20px; padding: 5px; border-radius: 10px; box-shadow: 0 6px 20px rgba(0,0,0,0.2); text-align: center;">
                <h2 style="font-size: 1.2em; margin-bottom: 0.5em;">Conversion Rate</h2>
                <p style="font-size: 1em; margin-bottom: 0.25em;"><strong>Rate:</strong> <?php echo number_format($conversion_rate, 2); ?>%</p>
                <p style="font-size: 0.8em; margin-bottom: 0.5em;">Conversion rate indicates the percentage of users who clicked the email link and completed an order.</p>
            </div>

            <!-- Container for Engagement Rate -->
            <div class="rate-container" style="flex: 1; background: linear-gradient(to right, #f8f9fa, #f8f9fa); color: black; padding: 5px; margin-bottom: 20px; border-radius: 10px; box-shadow: 0 6px 20px rgba(0,0,0,0.2); text-align: center;">
                <h2 style="font-size: 1.2em; margin-bottom: 0.5em;">Engagement Rate</h2>
                <p style="font-size: 1em;"><strong>Rate:</strong> <?php echo number_format($engagement_rate, 2); ?>%</p>
                <p style="font-size: 0.8em; margin-bottom: 0.5em;">Engagement rate represents the percentage of unique email clicks relative to the total emails sent.</p>
            </div>

        </div>

        <div class="analytics-container" style="display: flex; flex-wrap: wrap; justify-content: center; gap: 20px;">
            <div class="chart-container" style="flex: 0 0 auto; width: 25%; background: #f8f9fa; padding: 20px; border-radius: 10px; box-shadow: 0 4px 8px rgba(0,0,0,0.1);">
                <h2>Pie Chart for Order Sources</h2>
                <canvas id="pieChart" width="200" height="200"></canvas>
            </div>

            <div class="chart-container" style="flex: 0 0 auto; width:25%; background: #f8f9fa; padding: 20px; border-radius: 10px; box-shadow: 0 4px 8px rgba(0,0,0,0.1);">
                <h2>Line Graph for Orders Over Time</h2>
                <canvas id="lineChart" width="200" height="200"></canvas>
            </div>
        </div>


        </div>
        <script type="text/javascript">

            jQuery(document).ready(function($) {
                // Pie Chart for Order Sources
                var ctxPie = document.getElementById('pieChart').getContext('2d');
                new Chart(ctxPie, {
                    type: 'pie',
                    data: {
                        labels: ['Restored from Email', 'Other Orders'],
                        datasets: [{
                            data: [<?php echo $restored_orders_count; ?>, <?php echo $other_orders_count; ?>],
                            backgroundColor: ['#8745D1', '#CFA5FF'],
                        }]
                    }
                });

            });
        </script>


<script type="text/javascript">
    jQuery(document).ready(function($) {
        var ctxLine = document.getElementById('lineChart').getContext('2d');
        new Chart(ctxLine, {
            type: 'line',
            data: {
                labels: <?php echo $line_graph_labels; ?>,
                datasets: [{
                    label: 'Orders per Day',
                    data: <?php echo $line_graph_data; ?>,
                    backgroundColor: 'rgba(207, 165, 255, 0.3)',
                    borderColor: 'rgba(135, 69, 209, 1)',
                    borderWidth: 2,
                    fill: true, // Fill the area under the line
                    lineTension: 0.4 // Adjust for curvature (0 for straight lines, 0.4 for a gentle curve)
                }]
            },
            options: {
                scales: {
                    y: {
                        beginAtZero: true // Ensure the y-axis starts at 0
                    }
                },
                elements: {
                    point: {
                        radius: 4 // Adjust to make the data points more or less prominent
                    }
                }
            }
        });
    });
</script>

    </div>
    <?php
}


// Hook into the admin menu creation
add_action('admin_menu', 'abandoned_carts_plugin_menu');


// Initialize the abandoned carts plugin
add_action('init', 'abandoned_carts_plugin_init');
