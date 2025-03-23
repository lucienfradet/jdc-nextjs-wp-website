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
    
    // Block frontend access with message if not an allowed path
    if (!$is_allowed) {
        wp_die('This site is headless. Use the API to fetch content.');
    }
}

// Add the access control function to the template_redirect hook
add_action('template_redirect', 'headless_access_control');
