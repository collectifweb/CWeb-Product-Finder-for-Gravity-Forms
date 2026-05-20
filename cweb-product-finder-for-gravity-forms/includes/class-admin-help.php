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

class CWebPF_Admin_Help {

    private const PAGE_SLUG = 'cwebpf-help';

    public function __construct() {
        add_action('admin_menu', [$this, 'register_menu']);
    }

    public function register_menu(): void {
        add_submenu_page(
            'edit.php?post_type=' . CWebPF_Product_CPT::POST_TYPE,
            __('Help & About', 'cweb-product-finder-for-gravity-forms'),
            __('Help', 'cweb-product-finder-for-gravity-forms'),
            'manage_options',
            self::PAGE_SLUG,
            [$this, 'render_page']
        );
    }

    public function render_page(): void {
        ?>
        <div class="wrap cwebpf-help">
            <h1><?php esc_html_e('Help & About', 'cweb-product-finder-for-gravity-forms'); ?></h1>

            <div class="cwebpf-help-grid">

                <section class="cwebpf-help-card">
                    <h2><?php esc_html_e('How it works', 'cweb-product-finder-for-gravity-forms'); ?></h2>
                    <ol>
                        <li><?php esc_html_e('A visitor fills your Gravity Form.', 'cweb-product-finder-for-gravity-forms'); ?></li>
                        <li><?php esc_html_e('On submission, the recommender scores each of your products against the visitor\'s answers using the rules you defined.', 'cweb-product-finder-for-gravity-forms'); ?></li>
                        <li><?php esc_html_e('The form confirmation displays the top product (plus alternatives) as styled cards.', 'cweb-product-finder-for-gravity-forms'); ?></li>
                    </ol>
                    <p><?php esc_html_e('Everything happens locally in PHP — no external API.', 'cweb-product-finder-for-gravity-forms'); ?></p>
                </section>

                <section class="cwebpf-help-card">
                    <h2><?php esc_html_e('Quick links', 'cweb-product-finder-for-gravity-forms'); ?></h2>
                    <ul>
                        <li><a href="<?php echo esc_url(admin_url('edit.php?post_type=' . CWebPF_Product_CPT::POST_TYPE . '&page=cwebpf-onboarding')); ?>"><?php esc_html_e('Setup wizard', 'cweb-product-finder-for-gravity-forms'); ?></a></li>
                        <li><a href="<?php echo esc_url(admin_url('edit.php?post_type=' . CWebPF_Product_CPT::POST_TYPE)); ?>"><?php esc_html_e('Manage products', 'cweb-product-finder-for-gravity-forms'); ?></a></li>
                        <li><a href="<?php echo esc_url(admin_url('edit.php?post_type=' . CWebPF_Product_CPT::POST_TYPE . '&page=cwebpf-scoring-rules')); ?>"><?php esc_html_e('Scoring rules', 'cweb-product-finder-for-gravity-forms'); ?></a></li>
                        <li><a href="<?php echo esc_url(admin_url('admin.php?page=gf_edit_forms')); ?>"><?php esc_html_e('Gravity Forms', 'cweb-product-finder-for-gravity-forms'); ?></a></li>
                    </ul>
                </section>

                <section class="cwebpf-help-card">
                    <h2><?php esc_html_e('The scoring model', 'cweb-product-finder-for-gravity-forms'); ?></h2>
                    <p><?php esc_html_e('Each rule is a "When → Then":', 'cweb-product-finder-for-gravity-forms'); ?></p>
                    <ul>
                        <li><strong><?php esc_html_e('When', 'cweb-product-finder-for-gravity-forms'); ?></strong> — <?php esc_html_e('one or more conditions on form answers, combined with AND or OR.', 'cweb-product-finder-for-gravity-forms'); ?></li>
                        <li><strong><?php esc_html_e('Then', 'cweb-product-finder-for-gravity-forms'); ?></strong> — <?php esc_html_e('one or more effects on products:', 'cweb-product-finder-for-gravity-forms'); ?>
                            <ul>
                                <li><strong>Boost</strong> — <?php esc_html_e('add N points to the product score', 'cweb-product-finder-for-gravity-forms'); ?></li>
                                <li><strong>Penalize</strong> — <?php esc_html_e('subtract N points from the product score', 'cweb-product-finder-for-gravity-forms'); ?></li>
                                <li><strong>Exclude</strong> — <?php esc_html_e('remove this product from candidates (hard filter)', 'cweb-product-finder-for-gravity-forms'); ?></li>
                                <li><strong>Require</strong> — <?php esc_html_e('force-include this product; any product not required by any matched rule is filtered out', 'cweb-product-finder-for-gravity-forms'); ?></li>
                            </ul>
                        </li>
                    </ul>
                    <p><?php esc_html_e('Conditions only see fields with restricted answers — radio, dropdown, checkbox. Free-text inputs are ignored on purpose.', 'cweb-product-finder-for-gravity-forms'); ?></p>
                </section>

                <section class="cwebpf-help-card">
                    <h2><?php esc_html_e('Shortcode', 'cweb-product-finder-for-gravity-forms'); ?></h2>
                    <p><?php esc_html_e('Paste this in the Gravity Form\'s confirmation message (Form settings → Confirmations → Text):', 'cweb-product-finder-for-gravity-forms'); ?></p>
                    <pre><?php
                        $config = (array) get_option('cwebpf_form_config', []);
                        $form_id = (int) ($config['form_id'] ?? 0);
                        $field_id = (string) ($config['field_id'] ?? '');
                        $sample = sprintf('[cwebpf_recommender form_id="%s" field_id="%s"]', $form_id ?: 'X', $field_id ?: 'Y');
                        echo esc_html($sample);
                    ?></pre>
                </section>

                <section class="cwebpf-help-card">
                    <h2><?php esc_html_e('Filters (for developers)', 'cweb-product-finder-for-gravity-forms'); ?></h2>
                    <table class="widefat striped">
                        <tbody>
                            <tr><th><code>cwebpf_form_id</code></th><td><?php esc_html_e('Override the listened form ID', 'cweb-product-finder-for-gravity-forms'); ?></td></tr>
                            <tr><th><code>cwebpf_field_id</code></th><td><?php esc_html_e('Override the hidden field that stores the JSON', 'cweb-product-finder-for-gravity-forms'); ?></td></tr>
                            <tr><th><code>cwebpf_recommendation_explanation</code></th><td><?php esc_html_e('Replace the default "Based on your answers..." copy', 'cweb-product-finder-for-gravity-forms'); ?></td></tr>
                            <tr><th><code>cwebpf_ai_fallback_recommendation</code></th><td><?php esc_html_e('Plug an AI service to pick a product when no rule matches (architecture-ready, no built-in impl)', 'cweb-product-finder-for-gravity-forms'); ?></td></tr>
                            <tr><th><code>cwebpf_card_disclaimer</code></th><td><?php esc_html_e('Inject a disclaimer line under the cards', 'cweb-product-finder-for-gravity-forms'); ?></td></tr>
                            <tr><th><code>cwebpf_fallback_contact_url</code></th><td><?php esc_html_e('URL used on the error fallback "Contact us" button', 'cweb-product-finder-for-gravity-forms'); ?></td></tr>
                        </tbody>
                    </table>
                </section>

                <section class="cwebpf-help-card cwebpf-help-credits">
                    <h2><?php esc_html_e('Credits & contact', 'cweb-product-finder-for-gravity-forms'); ?></h2>
                    <p>
                        <?php
                        /* translators: %1$s is the agency link, %2$s is the contact email link */
                        $credits_template = __('Built by %1$s. Contact: %2$s.', 'cweb-product-finder-for-gravity-forms');
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
                        $github_template = __('Want to contribute or report a bug? Head over to the %s.', 'cweb-product-finder-for-gravity-forms');
                        printf(
                            wp_kses($github_template, $allowed_html),
                            '<a href="https://github.com/collectifweb/CWeb-Product-Finder-for-Gravity-Forms" target="_blank" rel="noopener">GitHub repo</a>'
                        );
                        ?>
                    </p>
                    <p><em><?php
                        printf(
                            /* translators: %s is the plugin version */
                            esc_html__('Plugin version: %s', 'cweb-product-finder-for-gravity-forms'),
                            esc_html(CWEBPF_VERSION)
                        );
                    ?></em></p>
                </section>

            </div>
        </div>

        <?php
    }
}
