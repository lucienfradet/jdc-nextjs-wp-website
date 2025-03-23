<?php
/**
 * Justify for Paragraph Block
 *
 * Adds justify alignment option to the Paragraph block in Gutenberg editor.
 *
 * @package JustifyForParagraphBlock
 * @since 1.0.0
 *
 * Plugin Name: Justify for Paragraph Block
 * Plugin URI: https://www.nick-digital-projects.com/Justify-for-Paragraph-Block
 * Description: Adds justify alignment option to the Paragraph block in Gutenberg editor.
 * Version: 1.0.0
 * Author: Nick Digital Projects
 * Author URI: http://www.nick-digital-projects.com
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: justify-for-paragraph-block
 */

// Prevent direct access to the plugin
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Enqueue scripts for the Gutenberg block editor
 * 
 * Loads the JavaScript file that adds justify alignment functionality
 * 
 * @since 1.0.0
 */
function jfpb_enqueue_editor_assets() {
    // Enqueue justify alignment script
    wp_enqueue_script(
        'jfpb-justify-alignment-script',  // Handle
        plugins_url('justify-for-paragraph-block.js', __FILE__),  // Script path
        array('wp-blocks', 'wp-dom-ready', 'wp-edit-post'),  // Dependencies
        filemtime(plugin_dir_path(__FILE__) . 'justify-for-paragraph-block.js'),  // Version
        true  // Load in footer
    );
}
add_action('enqueue_block_editor_assets', 'jfpb_enqueue_editor_assets');

/**
 * Enqueue block-specific styles for editor and frontend
 * 
 * Loads the CSS file for text justification
 * 
 * @since 1.0.0
 */
function jfpb_enqueue_block_assets() {
    // Enqueue justify alignment styles
    wp_enqueue_style(
        'jfpb-justify-editor-style',  // Handle
        plugins_url('editor-style.css', __FILE__),  // Style path
        array(),  // Dependencies
        filemtime(plugin_dir_path(__FILE__) . 'editor-style.css')
    );
}
add_action('enqueue_block_assets', 'jfpb_enqueue_block_assets');

/**
 * Add inline style for frontend text justification
 * 
 * Ensures justified text works on the frontend
 * 
 * @since 1.0.0
 */
function jfpb_add_frontend_style() {
    // Add justify text alignment CSS
    $style = '.has-text-align-justify { text-align: justify; }';
    wp_add_inline_style('wp-block-library', $style);
}
add_action('wp_enqueue_scripts', 'jfpb_add_frontend_style');