<?php
/**
 * Admin page : Help & About.
 *
 * Documentation rapide, crédits, contact, lien repo. Accessible depuis
 * Recommender → Help.
 */

if (!defined('ABSPATH')) {
    exit;
}

class GR_Admin_Help {

    private const PAGE_SLUG = 'gr-help';

    public function __construct() {
        add_action('admin_menu', [$this, 'register_menu']);
    }

    public function register_menu(): void {
        add_submenu_page(
            'edit.php?post_type=' . GR_Product_CPT::POST_TYPE,
            __('Help & About', 'product-finder-for-gravity-forms'),
            __('Help', 'product-finder-for-gravity-forms'),
            'manage_options',
            self::PAGE_SLUG,
            [$this, 'render_page']
        );
    }

    public function render_page(): void {
        ?>
        <div class="wrap gr-help">
            <h1><?php esc_html_e('Help & About', 'product-finder-for-gravity-forms'); ?></h1>

            <div class="gr-help-grid">

                <section class="gr-help-card">
                    <h2><?php esc_html_e('How it works', 'product-finder-for-gravity-forms'); ?></h2>
                    <ol>
                        <li><?php esc_html_e('A visitor fills your Gravity Form.', 'product-finder-for-gravity-forms'); ?></li>
                        <li><?php esc_html_e('On submission, the recommender scores each of your products against the visitor\'s answers using the rules you defined.', 'product-finder-for-gravity-forms'); ?></li>
                        <li><?php esc_html_e('The form confirmation displays the top product (plus alternatives) as styled cards.', 'product-finder-for-gravity-forms'); ?></li>
                    </ol>
                    <p><?php esc_html_e('Everything happens locally in PHP — no external API.', 'product-finder-for-gravity-forms'); ?></p>
                </section>

                <section class="gr-help-card">
                    <h2><?php esc_html_e('Quick links', 'product-finder-for-gravity-forms'); ?></h2>
                    <ul>
                        <li><a href="<?php echo esc_url(admin_url('edit.php?post_type=' . GR_Product_CPT::POST_TYPE . '&page=gr-onboarding')); ?>"><?php esc_html_e('Setup wizard', 'product-finder-for-gravity-forms'); ?></a></li>
                        <li><a href="<?php echo esc_url(admin_url('edit.php?post_type=' . GR_Product_CPT::POST_TYPE)); ?>"><?php esc_html_e('Manage products', 'product-finder-for-gravity-forms'); ?></a></li>
                        <li><a href="<?php echo esc_url(admin_url('edit.php?post_type=' . GR_Product_CPT::POST_TYPE . '&page=gr-scoring-rules')); ?>"><?php esc_html_e('Scoring rules', 'product-finder-for-gravity-forms'); ?></a></li>
                        <li><a href="<?php echo esc_url(admin_url('admin.php?page=gf_edit_forms')); ?>"><?php esc_html_e('Gravity Forms', 'product-finder-for-gravity-forms'); ?></a></li>
                    </ul>
                </section>

                <section class="gr-help-card">
                    <h2><?php esc_html_e('The scoring model', 'product-finder-for-gravity-forms'); ?></h2>
                    <p><?php esc_html_e('Each rule is a "When → Then":', 'product-finder-for-gravity-forms'); ?></p>
                    <ul>
                        <li><strong><?php esc_html_e('When', 'product-finder-for-gravity-forms'); ?></strong> — <?php esc_html_e('one or more conditions on form answers, combined with AND or OR.', 'product-finder-for-gravity-forms'); ?></li>
                        <li><strong><?php esc_html_e('Then', 'product-finder-for-gravity-forms'); ?></strong> — <?php esc_html_e('one or more effects on products:', 'product-finder-for-gravity-forms'); ?>
                            <ul>
                                <li><strong>Boost</strong> — <?php esc_html_e('add N points to the product score', 'product-finder-for-gravity-forms'); ?></li>
                                <li><strong>Penalize</strong> — <?php esc_html_e('subtract N points from the product score', 'product-finder-for-gravity-forms'); ?></li>
                                <li><strong>Exclude</strong> — <?php esc_html_e('remove this product from candidates (hard filter)', 'product-finder-for-gravity-forms'); ?></li>
                                <li><strong>Require</strong> — <?php esc_html_e('force-include this product; any product not required by any matched rule is filtered out', 'product-finder-for-gravity-forms'); ?></li>
                            </ul>
                        </li>
                    </ul>
                    <p><?php esc_html_e('Conditions only see fields with restricted answers — radio, dropdown, checkbox. Free-text inputs are ignored on purpose.', 'product-finder-for-gravity-forms'); ?></p>
                </section>

                <section class="gr-help-card">
                    <h2><?php esc_html_e('Shortcode', 'product-finder-for-gravity-forms'); ?></h2>
                    <p><?php esc_html_e('Paste this in the Gravity Form\'s confirmation message (Form settings → Confirmations → Text):', 'product-finder-for-gravity-forms'); ?></p>
                    <pre><?php
                        $config = (array) get_option('gr_form_config', []);
                        $form_id = (int) ($config['form_id'] ?? 0);
                        $field_id = (string) ($config['field_id'] ?? '');
                        $sample = sprintf('[gravity_recommender form_id="%s" field_id="%s"]', $form_id ?: 'X', $field_id ?: 'Y');
                        echo esc_html($sample);
                    ?></pre>
                </section>

                <section class="gr-help-card">
                    <h2><?php esc_html_e('Filters (for developers)', 'product-finder-for-gravity-forms'); ?></h2>
                    <table class="widefat striped">
                        <tbody>
                            <tr><th><code>gr_form_id</code></th><td><?php esc_html_e('Override the listened form ID', 'product-finder-for-gravity-forms'); ?></td></tr>
                            <tr><th><code>gr_field_id</code></th><td><?php esc_html_e('Override the hidden field that stores the JSON', 'product-finder-for-gravity-forms'); ?></td></tr>
                            <tr><th><code>gr_recommendation_explanation</code></th><td><?php esc_html_e('Replace the default "Based on your answers..." copy', 'product-finder-for-gravity-forms'); ?></td></tr>
                            <tr><th><code>gr_ai_fallback_recommendation</code></th><td><?php esc_html_e('Plug an AI service to pick a product when no rule matches (architecture-ready, no built-in impl)', 'product-finder-for-gravity-forms'); ?></td></tr>
                            <tr><th><code>gr_card_disclaimer</code></th><td><?php esc_html_e('Inject a disclaimer line under the cards', 'product-finder-for-gravity-forms'); ?></td></tr>
                            <tr><th><code>gr_fallback_contact_url</code></th><td><?php esc_html_e('URL used on the error fallback "Contact us" button', 'product-finder-for-gravity-forms'); ?></td></tr>
                        </tbody>
                    </table>
                </section>

                <section class="gr-help-card gr-help-credits">
                    <h2><?php esc_html_e('Credits & contact', 'product-finder-for-gravity-forms'); ?></h2>
                    <p>
                        <?php
                        /* translators: %1$s is the agency link, %2$s is the contact email link */
                        $credits_template = __('Built by %1$s. Contact: %2$s.', 'product-finder-for-gravity-forms');
                        $allowed_html = ['a' => ['href' => [], 'target' => [], 'rel' => []], 'strong' => []];
                        printf(
                            wp_kses($credits_template, $allowed_html),
                            '<a href="https://collectif-web.ca" target="_blank" rel="noopener"><strong>Collectif WEB</strong></a>',
                            '<a href="mailto:alexandre@collectifweb.ca">alexandre@collectifweb.ca</a>'
                        );
                        ?>
                    </p>
                    <p>
                        <?php
                        /* translators: %s is the GitHub repo link */
                        $github_template = __('Want to contribute or report a bug? Head over to the %s.', 'product-finder-for-gravity-forms');
                        printf(
                            wp_kses($github_template, $allowed_html),
                            '<a href="https://github.com/collectifweb/Product-Finder-for-Gravity-Forms" target="_blank" rel="noopener">GitHub repo</a>'
                        );
                        ?>
                    </p>
                    <p><em><?php
                        printf(
                            /* translators: %s is the plugin version */
                            esc_html__('Plugin version: %s', 'product-finder-for-gravity-forms'),
                            esc_html(GR_VERSION)
                        );
                    ?></em></p>
                </section>

            </div>
        </div>

        <style>
            .gr-help-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(380px, 1fr)); gap: 20px; margin-top: 20px; }
            .gr-help-card { background: #fff; border: 1px solid #ccd0d4; padding: 20px 24px; }
            .gr-help-card h2 { margin-top: 0; font-size: 16px; }
            .gr-help-card pre { background: #f6f7f7; padding: 10px; border: 1px solid #eee; font-size: 13px; overflow-x: auto; }
            .gr-help-card ul { padding-left: 20px; }
            .gr-help-card ul ul { margin-top: 6px; }
            .gr-help-card table { margin-top: 10px; }
            .gr-help-card table th { width: 220px; text-align: left; }
            .gr-help-card table code { background: #f0f0f1; padding: 2px 6px; border-radius: 3px; }
            .gr-help-credits { grid-column: 1 / -1; background: #f6fcff; }
        </style>
        <?php
    }
}
