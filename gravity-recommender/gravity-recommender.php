<?php
/**
 * Plugin Name: Gravity Recommender
 * Plugin URI: https://github.com/collectifweb/Gravity-Recommender_wp-plugin
 * Description: Recommend products at the end of a Gravity Forms questionnaire. Rules-based scoring engine targeting products directly — no external API. Works with the built-in product CPT or WooCommerce.
 * Version: 3.1.1-beta
 * Author: Collectif WEB
 * Author URI: https://collectif-web.ca
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: gravity-recommender
 * Domain Path: /languages
 * Requires PHP: 8.0
 * Requires at least: 6.0
 */

if (!defined('ABSPATH')) {
    exit;
}

define('GR_VERSION', '3.1.1-beta');
define('GR_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('GR_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once GR_PLUGIN_DIR . 'includes/class-product-source.php';
require_once GR_PLUGIN_DIR . 'includes/class-cpt-source.php';
require_once GR_PLUGIN_DIR . 'includes/class-woo-source.php';
require_once GR_PLUGIN_DIR . 'includes/class-product-cpt.php';
require_once GR_PLUGIN_DIR . 'includes/class-recommendation-engine.php';
require_once GR_PLUGIN_DIR . 'includes/class-shortcode-handler.php';
require_once GR_PLUGIN_DIR . 'includes/class-gf-integration.php';
require_once GR_PLUGIN_DIR . 'includes/class-admin-onboarding.php';
require_once GR_PLUGIN_DIR . 'includes/class-admin-rules.php';
require_once GR_PLUGIN_DIR . 'includes/class-admin-help.php';

function gr_init(): void {
    // Translations are auto-loaded by WordPress 4.6+ for plugins hosted on wordpress.org.
    new GR_Product_CPT();
    new GR_Shortcode_Handler();

    if (is_admin()) {
        new GR_Admin_Onboarding();
        new GR_Admin_Rules();
        new GR_Admin_Help();
    }

    if (class_exists('GFForms')) {
        new GR_GF_Integration();
    }
}
add_action('plugins_loaded', 'gr_init');

/**
 * CSS chargé sur le front-end. Le fichier est scopé aux classes `.gr-*`
 * pour éviter les conflits, et chargé partout car certains builders
 * (Elementor, etc.) insèrent le formulaire GF via widget — `has_shortcode()`
 * sur `post_content` ne les détecte pas.
 */
function gr_enqueue_styles(): void {
    if (!is_admin()) {
        wp_enqueue_style(
            'gr-product-cards',
            GR_PLUGIN_URL . 'assets/css/product-cards.css',
            [],
            GR_VERSION
        );
    }
}
add_action('wp_enqueue_scripts', 'gr_enqueue_styles');

register_activation_hook(__FILE__, function (): void {
    // Force la registration du CPT pour que les rewrites soient flush proprement.
    (new GR_Product_CPT())->register_post_type();
    flush_rewrite_rules();
});

register_deactivation_hook(__FILE__, function (): void {
    flush_rewrite_rules();
});
