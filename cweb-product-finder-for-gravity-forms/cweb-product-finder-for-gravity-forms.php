<?php
/**
 * Plugin Name: CWeb Product Finder for Gravity Forms
 * Plugin URI: https://github.com/collectifweb/CWeb-Product-Finder-for-Gravity-Forms
 * Description: Recommend products at the end of a Gravity Forms questionnaire. Rules-based scoring engine targeting products directly. No external API. Works with the built-in product CPT or WooCommerce.
 * Version: 3.1.4
 * Author: Collectif WEB
 * Author URI: https://collectif-web.ca
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: cweb-product-finder-for-gravity-forms
 * Domain Path: /languages
 * Requires PHP: 8.0
 * Requires at least: 6.0
 */

if (!defined('ABSPATH')) {
    exit;
}

define('CWEBPF_VERSION', '3.1.4');
define('CWEBPF_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CWEBPF_PLUGIN_URL', plugin_dir_url(__FILE__));
define('CWEBPF_PLUGIN_FILE', __FILE__);

require_once CWEBPF_PLUGIN_DIR . 'includes/class-product-source.php';
require_once CWEBPF_PLUGIN_DIR . 'includes/class-cpt-source.php';
require_once CWEBPF_PLUGIN_DIR . 'includes/class-woo-source.php';
require_once CWEBPF_PLUGIN_DIR . 'includes/class-product-cpt.php';
require_once CWEBPF_PLUGIN_DIR . 'includes/class-recommendation-engine.php';
require_once CWEBPF_PLUGIN_DIR . 'includes/class-shortcode-handler.php';
require_once CWEBPF_PLUGIN_DIR . 'includes/class-gf-integration.php';
require_once CWEBPF_PLUGIN_DIR . 'includes/class-admin-onboarding.php';
require_once CWEBPF_PLUGIN_DIR . 'includes/class-admin-rules.php';
require_once CWEBPF_PLUGIN_DIR . 'includes/class-admin-help.php';

function cwebpf_init(): void {
    new CWebPF_Product_CPT();
    new CWebPF_Shortcode_Handler();

    if (is_admin()) {
        new CWebPF_Admin_Onboarding();
        new CWebPF_Admin_Rules();
        new CWebPF_Admin_Help();
    }

    if (class_exists('GFForms')) {
        new CWebPF_GF_Integration();
    }
}
add_action('plugins_loaded', 'cwebpf_init');

/**
 * Front-end CSS. Loaded on all front-end pages because page builders like
 * Elementor insert GF forms via widgets, which has_shortcode() on post_content
 * doesn't detect.
 */
function cwebpf_enqueue_front_styles(): void {
    if (!is_admin()) {
        wp_enqueue_style(
            'cwebpf-product-cards',
            CWEBPF_PLUGIN_URL . 'assets/css/product-cards.css',
            [],
            CWEBPF_VERSION
        );
    }
}
add_action('wp_enqueue_scripts', 'cwebpf_enqueue_front_styles');

/**
 * Admin assets. Each admin screen of this plugin loads only what it needs.
 */
function cwebpf_enqueue_admin_assets(string $hook_suffix): void {
    $post_type = CWebPF_Product_CPT::POST_TYPE;
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    $screen_id = $screen ? $screen->id : '';

    $is_product_edit = ($screen_id === $post_type || $screen_id === 'edit-' . $post_type);
    $is_onboarding = ($screen_id === $post_type . '_page_cwebpf-onboarding');
    $is_rules = ($screen_id === $post_type . '_page_cwebpf-scoring-rules');
    $is_help = ($screen_id === $post_type . '_page_cwebpf-help');

    if (!$is_product_edit && !$is_onboarding && !$is_rules && !$is_help) {
        return;
    }

    wp_enqueue_style(
        'cwebpf-admin',
        CWEBPF_PLUGIN_URL . 'assets/css/admin.css',
        [],
        CWEBPF_VERSION
    );

    if ($is_rules) {
        wp_enqueue_script(
            'cwebpf-admin-rules',
            CWEBPF_PLUGIN_URL . 'assets/js/admin-rules.js',
            [],
            CWEBPF_VERSION,
            true
        );
        wp_localize_script('cwebpf-admin-rules', 'cwebpfRulesL10n', [
            'pickAChoice' => __('— pick a choice —', 'cweb-product-finder-for-gravity-forms'),
        ]);
    }

    if ($is_onboarding) {
        wp_enqueue_script(
            'cwebpf-admin-onboarding',
            CWEBPF_PLUGIN_URL . 'assets/js/admin-onboarding.js',
            [],
            CWEBPF_VERSION,
            true
        );
        wp_localize_script('cwebpf-admin-onboarding', 'cwebpfOnboardingL10n', [
            'noHiddenField' => esc_html__('No hidden field found in this form. Add one in Gravity Forms (Hidden field), then type its ID here.', 'cweb-product-finder-for-gravity-forms'),
            'oneHiddenField' => esc_html__('One hidden field found and selected automatically.', 'cweb-product-finder-for-gravity-forms'),
            'multipleHiddenFields' => esc_html__('Multiple hidden fields detected. Pick the one that should store the recommendation result.', 'cweb-product-finder-for-gravity-forms'),
        ]);
    }
}
add_action('admin_enqueue_scripts', 'cwebpf_enqueue_admin_assets');

register_activation_hook(__FILE__, function (): void {
    (new CWebPF_Product_CPT())->register_post_type();
    flush_rewrite_rules();
});

register_deactivation_hook(__FILE__, function (): void {
    flush_rewrite_rules();
});
