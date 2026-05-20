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
            __('Help & About', 'gravity-recommender'),
            __('Help', 'gravity-recommender'),
            'manage_options',
            self::PAGE_SLUG,
            [$this, 'render_page']
        );
    }

    public function render_page(): void {
        ?>
        <div class="wrap gr-help">
            <h1><?php esc_html_e('Help & About', 'gravity-recommender'); ?></h1>

            <div class="gr-help-grid">

                <section class="gr-help-card">
                    <h2><?php esc_html_e('How it works', 'gravity-recommender'); ?></h2>
                    <ol>
                        <li><?php esc_html_e('A visitor fills your Gravity Form.', 'gravity-recommender'); ?></li>
                        <li><?php esc_html_e('On submission, the recommender scores each of your products against the visitor\'s answers using the rules you defined.', 'gravity-recommender'); ?></li>
                        <li><?php esc_html_e('The form confirmation displays the top product (plus alternatives) as styled cards.', 'gravity-recommender'); ?></li>
                    </ol>
                    <p><?php esc_html_e('Everything happens locally in PHP — no external API.', 'gravity-recommender'); ?></p>
                </section>

                <section class="gr-help-card">
                    <h2><?php esc_html_e('Quick links', 'gravity-recommender'); ?></h2>
                    <ul>
                        <li><a href="<?php echo esc_url(admin_url('edit.php?post_type=' . GR_Product_CPT::POST_TYPE . '&page=gr-onboarding')); ?>"><?php esc_html_e('Setup wizard', 'gravity-recommender'); ?></a></li>
                        <li><a href="<?php echo esc_url(admin_url('edit.php?post_type=' . GR_Product_CPT::POST_TYPE)); ?>"><?php esc_html_e('Manage products', 'gravity-recommender'); ?></a></li>
                        <li><a href="<?php echo esc_url(admin_url('edit.php?post_type=' . GR_Product_CPT::POST_TYPE . '&page=gr-scoring-rules')); ?>"><?php esc_html_e('Scoring rules', 'gravity-recommender'); ?></a></li>
                        <li><a href="<?php echo esc_url(admin_url('admin.php?page=gf_edit_forms')); ?>"><?php esc_html_e('Gravity Forms', 'gravity-recommender'); ?></a></li>
                    </ul>
                </section>

                <section class="gr-help-card">
                    <h2><?php esc_html_e('The scoring model', 'gravity-recommender'); ?></h2>
                    <p><?php esc_html_e('Each rule is a "When → Then":', 'gravity-recommender'); ?></p>
                    <ul>
                        <li><strong><?php esc_html_e('When', 'gravity-recommender'); ?></strong> — <?php esc_html_e('one or more conditions on form answers, combined with AND or OR.', 'gravity-recommender'); ?></li>
                        <li><strong><?php esc_html_e('Then', 'gravity-recommender'); ?></strong> — <?php esc_html_e('one or more effects on products:', 'gravity-recommender'); ?>
                            <ul>
                                <li><strong>Boost</strong> — <?php esc_html_e('add N points to the product score', 'gravity-recommender'); ?></li>
                                <li><strong>Penalize</strong> — <?php esc_html_e('subtract N points from the product score', 'gravity-recommender'); ?></li>
                                <li><strong>Exclude</strong> — <?php esc_html_e('remove this product from candidates (hard filter)', 'gravity-recommender'); ?></li>
                                <li><strong>Require</strong> — <?php esc_html_e('force-include this product; any product not required by any matched rule is filtered out', 'gravity-recommender'); ?></li>
                            </ul>
                        </li>
                    </ul>
                    <p><?php esc_html_e('Conditions only see fields with restricted answers — radio, dropdown, checkbox. Free-text inputs are ignored on purpose.', 'gravity-recommender'); ?></p>
                </section>

                <section class="gr-help-card">
                    <h2><?php esc_html_e('Shortcode', 'gravity-recommender'); ?></h2>
                    <p><?php esc_html_e('Paste this in the Gravity Form\'s confirmation message (Form settings → Confirmations → Text):', 'gravity-recommender'); ?></p>
                    <pre><?php
                        $config = (array) get_option('gr_form_config', []);
                        $form_id = (int) ($config['form_id'] ?? 0);
                        $field_id = (string) ($config['field_id'] ?? '');
                        $sample = sprintf('[gravity_recommender form_id="%s" field_id="%s"]', $form_id ?: 'X', $field_id ?: 'Y');
                        echo esc_html($sample);
                    ?></pre>
                </section>

                <section class="gr-help-card">
                    <h2><?php esc_html_e('Filters (for developers)', 'gravity-recommender'); ?></h2>
                    <table class="widefat striped">
                        <tbody>
                            <tr><th><code>gr_form_id</code></th><td><?php esc_html_e('Override the listened form ID', 'gravity-recommender'); ?></td></tr>
                            <tr><th><code>gr_field_id</code></th><td><?php esc_html_e('Override the hidden field that stores the JSON', 'gravity-recommender'); ?></td></tr>
                            <tr><th><code>gr_recommendation_explanation</code></th><td><?php esc_html_e('Replace the default "Based on your answers..." copy', 'gravity-recommender'); ?></td></tr>
                            <tr><th><code>gr_ai_fallback_recommendation</code></th><td><?php esc_html_e('Plug an AI service to pick a product when no rule matches (architecture-ready, no built-in impl)', 'gravity-recommender'); ?></td></tr>
                            <tr><th><code>gr_card_disclaimer</code></th><td><?php esc_html_e('Inject a disclaimer line under the cards', 'gravity-recommender'); ?></td></tr>
                            <tr><th><code>gr_fallback_contact_url</code></th><td><?php esc_html_e('URL used on the error fallback "Contact us" button', 'gravity-recommender'); ?></td></tr>
                        </tbody>
                    </table>
                </section>

                <section class="gr-help-card gr-help-credits">
                    <h2><?php esc_html_e('Credits & contact', 'gravity-recommender'); ?></h2>
                    <p>
                        <?php
                        printf(
                            /* translators: %1$s is the agency link, %2$s is the contact email link */
                            wp_kses(
                                __('Built by %1$s. Contact: %2$s.', 'gravity-recommender'),
                                ['a' => ['href' => [], 'target' => [], 'rel' => []]]
                            ),
                            '<a href="https://collectif-web.ca" target="_blank" rel="noopener"><strong>Collectif WEB</strong></a>',
                            '<a href="mailto:alexandre@collectifweb.ca">alexandre@collectifweb.ca</a>'
                        );
                        ?>
                    </p>
                    <p>
                        <?php
                        printf(
                            /* translators: %s is the GitHub repo link */
                            wp_kses(
                                __('Want to contribute or report a bug? Head over to the %s.', 'gravity-recommender'),
                                ['a' => ['href' => [], 'target' => [], 'rel' => []]]
                            ),
                            '<a href="https://github.com/collectifweb/Gravity-Recommender_wp-plugin" target="_blank" rel="noopener">GitHub repo</a>'
                        );
                        ?>
                    </p>
                    <p><em><?php
                        printf(
                            /* translators: %s is the plugin version */
                            esc_html__('Plugin version: %s', 'gravity-recommender'),
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
