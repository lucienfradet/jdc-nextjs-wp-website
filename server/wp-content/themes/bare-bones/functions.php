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
