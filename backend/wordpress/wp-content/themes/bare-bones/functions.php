<?php
// Disable theme features
add_filter('show_admin_bar', '__return_false');
add_filter('template_include', function() {
    return __DIR__ . '/index.php';
});

// Enqueue a blank stylesheet
add_action('wp_enqueue_scripts', function() {
    wp_enqueue_style('bare-bones-style', false);
});

// Headless Access Control Function
function headless_access_control() {
    // Critical paths to allow
    $allowed_paths = [
        'wp-cron.php',
        'wp-json',
        'wp-admin',
        'wp-login.php',
        'xmlrpc.php' // Some services need this
    ];
    
    // Check if current path contains any allowed paths
    $current_path = $_SERVER['REQUEST_URI'];
    $is_allowed = false;
    
    foreach ($allowed_paths as $path) {
        if (strpos($current_path, $path) !== false) {
            $is_allowed = true;
            break;
        }
    }

    // More permissive check for any MailPoet related parameters
    if (isset($_GET['mailpoet_page']) ||
        array_key_exists('mailpoet_router', $_GET) ||
        (isset($_GET['endpoint']) && $_GET['endpoint'] == 'subscription')) {
        $is_allowed = true;
    }
    
    // Block frontend access with custom page if not an allowed path
    if (!$is_allowed) {
        // Set a flag to indicate this is direct access (no success banner)
        $_GET['direct_access'] = true;
        
        // Display custom headless page
        display_headless_page();
        exit;
    }
}

// Function to display the headless page without success banner
function display_headless_page() {
    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Jardin des Chefs - Site Headless</title>
        <style>
            :root {
                --green: #24311B;
                --green-transparent: rgba(36, 49, 27, 0.85);
                --red: #A22D22;
                --beige: #F5F0E1;
                --beige-transparent: rgba(245,240,225, 0.75);
                --orange: #D17829;
                --pink: #C17C74;
                --blue: #153C66;
            }

            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }

            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
                line-height: 1.6;
                color: #333;
                background: #f5f5f5;
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .container {
                max-width: 600px;
                background: var(--beige);
                border-radius: 15px;
                box-shadow: 0 20px 40px rgba(0,0,0,0.1);
                padding: 3rem;
                text-align: center;
                position: relative;
                overflow: hidden;
            }

            .container::before {
                content: '';
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                height: 5px;
                background: var(--green);
            }

            .logo-container {
                margin-bottom: 2rem;
            }

            .logo-img {
                max-width: 200px;
                height: auto;
            }

            .headless-info {
                background: rgba(255, 255, 255, 0.7);
                padding: 1.5rem;
                border-radius: 10px;
                margin-bottom: 2rem;
                border-left: 4px solid var(--green);
            }

            .headless-info p {
                color: #666;
                font-size: 1.1rem;
            }

            .main-button {
                display: inline-block;
                background: var(--green);
                color: white;
                padding: 1rem 2rem;
                text-decoration: none;
                border-radius: 50px;
                font-size: 1.1rem;
                font-weight: 600;
                transition: all 0.3s ease;
                box-shadow: 0 5px 15px var(--green-transparent);
                margin-top: 1rem;
            }

            .main-button:hover {
                transform: translateY(-2px);
                box-shadow: 0 8px 25px var(--green-transparent);
                background: var(--green-transparent);
            }

            .admin-link {
                padding-top: 10px;
                color: var(--green);
            }

            @media (max-width: 768px) {
                .container {
                    margin: 1rem;
                    padding: 2rem;
                }
                
                .logo {
                    font-size: 2rem;
                }
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="logo-container">
                <img src="https://jardindeschefs.ca/images/jdc_logo.png" alt="Jardin des Chefs" class="logo-img">
            </div>

            <div class="headless-info">
                <p>Ce site WordPress fonctionne en mode headless et sert d'API pour d'autres applications.</p>
            </div>

            <a href="https://jardindeschefs.ca" class="main-button">
                Visiter le Site Principal
            </a>

            <a href="https://wordpress.jardindeschefs.ca/wp-admin/" class="admin-link">
                Accéder à la page d'administrateur
            </a>
        </div>
    </body>
    </html>
    <?php
}

// Add the access control function to the template_redirect hook
add_action('template_redirect', 'headless_access_control');

/**
 * Add Max Bookings Per Time Slot functionality to booking products
 */

// Add a custom product tab for booking products
add_filter('woocommerce_product_data_tabs', 'add_max_booking_per_slot_product_tab', 99, 1);
function add_max_booking_per_slot_product_tab($tabs) {
    // Only add the tab for booking product type
    $tabs['max_booking_per_slot'] = array(
        'label'    => __('Max Booking Per Slot', 'woocommerce'),
        'target'   => 'max_booking_per_slot_product_data',
        'class'    => array('show_if_booking'),
        'priority' => 21
    );
    return $tabs;
}

// Add the content to the custom product tab
add_action('woocommerce_product_data_panels', 'add_max_booking_per_slot_product_tab_content');
function add_max_booking_per_slot_product_tab_content() {
    ?>
    <div id="max_booking_per_slot_product_data" class="panel woocommerce_options_panel">
        <div class="options_group">
            <?php
            // Add a number field for max bookings per slot
            woocommerce_wp_text_input(array(
                'id'          => '_max_booking_per_slot',
                'label'       => __('Maximum Bookings Per Slot', 'woocommerce'),
                'description' => __('Set the maximum number of bookings allowed per time slot.', 'woocommerce'),
                'desc_tip'    => true,
                'type'        => 'number',
                'custom_attributes' => array(
                    'step' => '1',
                    'min'  => '1'
                )
            ));
            ?>
        </div>
    </div>
    <?php
}

// Save the custom field data
add_action('woocommerce_process_product_meta', 'save_max_booking_per_slot_field');
function save_max_booking_per_slot_field($post_id) {
    $max_booking_per_slot = isset($_POST['_max_booking_per_slot']) ? sanitize_text_field($_POST['_max_booking_per_slot']) : '';
    update_post_meta($post_id, '_max_booking_per_slot', $max_booking_per_slot);
}

// Register the field with the REST API
add_action('rest_api_init', 'register_max_booking_per_slot_rest_field');
function register_max_booking_per_slot_rest_field() {
    register_rest_field(
        'product',
        'max_booking_per_slot',
        array(
            'get_callback'    => 'get_max_booking_per_slot_callback',
            'update_callback' => 'update_max_booking_per_slot_callback',
            'schema'          => array(
                'description' => __('Maximum bookings per time slot.', 'woocommerce'),
                'type'        => 'integer',
                'context'     => array('view', 'edit')
            )
        )
    );
}

// Callback to get the field value
function get_max_booking_per_slot_callback($post, $attr, $request, $object_type) {
    return (int) get_post_meta($post['id'], '_max_booking_per_slot', true);
}

// Callback to update the field value
function update_max_booking_per_slot_callback($value, $post, $attr) {
    return update_post_meta($post->ID, '_max_booking_per_slot', (int) $value);
}
