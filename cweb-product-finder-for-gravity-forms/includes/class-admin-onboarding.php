<?php
/**
 * Onboarding admin pour Product Finder for Gravity Forms.
 *
 * 4 étapes effectives + un récap (les règles de scoring ont leur propre page) :
 *
 *   1. Welcome / dependency check (with affiliate CTA if GF missing)
 *   2. Product source (CPT or WooCommerce)
 *   3. Add at least one product
 *   4. Pick Gravity Form + auto-detect hidden field
 *   5. Done — shortcode + pointer to Scoring Rules
 */

if (!defined('ABSPATH')) {
    exit;
}

class CWebPF_Admin_Onboarding {

    private const PAGE_SLUG = 'cwebpf-onboarding';
    private const OPTION_FORM_CONFIG = 'cwebpf_form_config';
    private const OPTION_SOURCE      = 'cwebpf_product_source';
    private const OPTION_STEP        = 'cwebpf_onboarding_step';

    private const GF_DEFAULT_URL = 'https://www.gravityforms.com/';

    public function __construct() {
        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_init', [$this, 'handle_form_submissions']);
        add_action('admin_notices', [$this, 'maybe_show_setup_notice']);

        add_filter('plugin_action_links_' . plugin_basename(CWEBPF_PLUGIN_FILE), [$this, 'add_settings_link']);
    }

    public function register_menu(): void {
        add_submenu_page(
            'edit.php?post_type=' . CWebPF_Product_CPT::POST_TYPE,
            __('Recommender Setup', 'cweb-product-finder-for-gravity-forms'),
            __('Setup', 'cweb-product-finder-for-gravity-forms'),
            'manage_options',
            self::PAGE_SLUG,
            [$this, 'render_page']
        );
    }

    public function add_settings_link(array $links): array {
        $url = admin_url('edit.php?post_type=' . CWebPF_Product_CPT::POST_TYPE . '&page=' . self::PAGE_SLUG);
        $links[] = '<a href="' . esc_url($url) . '">' . esc_html__('Setup', 'cweb-product-finder-for-gravity-forms') . '</a>';
        return $links;
    }

    public function maybe_show_setup_notice(): void {
        $config = (array) get_option(self::OPTION_FORM_CONFIG, []);
        if (!empty($config['form_id']) && !empty($config['field_id'])) {
            return;
        }
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if ($screen && $screen->id === CWebPF_Product_CPT::POST_TYPE . '_page_' . self::PAGE_SLUG) {
            return;
        }

        $url = admin_url('edit.php?post_type=' . CWebPF_Product_CPT::POST_TYPE . '&page=' . self::PAGE_SLUG);
        ?>
        <div class="notice notice-info">
            <p>
                <strong>Product Finder for Gravity Forms</strong> —
                <?php esc_html_e('Finish the setup to start recommending products.', 'cweb-product-finder-for-gravity-forms'); ?>
                <a href="<?php echo esc_url($url); ?>" class="button button-primary" style="margin-left: 10px;">
                    <?php esc_html_e('Open setup wizard', 'cweb-product-finder-for-gravity-forms'); ?>
                </a>
            </p>
        </div>
        <?php
    }

    public function handle_form_submissions(): void {
        if (!isset($_POST['cwebpf_action']) || !current_user_can('manage_options')) {
            return;
        }
        if (!isset($_POST['cwebpf_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['cwebpf_nonce'])), 'cwebpf_onboarding')) {
            return;
        }

        $action = sanitize_text_field(wp_unslash($_POST['cwebpf_action']));

        switch ($action) {
            case 'save_source':
                $source = sanitize_text_field(wp_unslash($_POST['cwebpf_source'] ?? 'cpt'));
                if (!in_array($source, ['cpt', 'woocommerce'], true)) {
                    $source = 'cpt';
                }
                update_option(self::OPTION_SOURCE, $source);
                $this->redirect_to_step(3);
                break;

            case 'save_form_config':
                $form_id  = isset($_POST['cwebpf_form_id']) ? absint(wp_unslash($_POST['cwebpf_form_id'])) : 0;
                $field_id = isset($_POST['cwebpf_field_id']) ? sanitize_text_field(wp_unslash($_POST['cwebpf_field_id'])) : '';
                update_option(self::OPTION_FORM_CONFIG, ['form_id' => $form_id, 'field_id' => $field_id]);
                $this->redirect_to_step(5);
                break;

            case 'import_example_form':
                $imported = $this->import_example_form();
                $this->redirect_to_step(4, $imported ? 'form_imported' : 'form_import_failed');
                break;

            case 'reset':
                delete_option(self::OPTION_STEP);
                $this->redirect_to_step(1);
                break;
        }
    }

    private function redirect_to_step(int $step, ?string $message = null): void {
        update_option(self::OPTION_STEP, $step);
        $url = admin_url('edit.php?post_type=' . CWebPF_Product_CPT::POST_TYPE . '&page=' . self::PAGE_SLUG . '&step=' . $step);
        if ($message) {
            $url = add_query_arg('msg', $message, $url);
        }
        wp_safe_redirect($url);
        exit;
    }

    public function render_page(): void {
        // The wizard step selector is purely cosmetic navigation and clamped to 1-5.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $step_raw = isset($_GET['step']) ? absint(wp_unslash($_GET['step'])) : (int) get_option(self::OPTION_STEP, 1);
        $step = max(1, min(5, $step_raw));
        $this->render_header($step);

        switch ($step) {
            case 1: $this->render_step_welcome(); break;
            case 2: $this->render_step_source(); break;
            case 3: $this->render_step_products(); break;
            case 4: $this->render_step_form(); break;
            case 5: $this->render_step_finish(); break;
        }

        $this->render_footer();
    }

    private function render_header(int $current_step): void {
        $steps = [
            1 => __('Welcome', 'cweb-product-finder-for-gravity-forms'),
            2 => __('Source', 'cweb-product-finder-for-gravity-forms'),
            3 => __('Products', 'cweb-product-finder-for-gravity-forms'),
            4 => __('Form', 'cweb-product-finder-for-gravity-forms'),
            5 => __('Done', 'cweb-product-finder-for-gravity-forms'),
        ];
        ?>
        <div class="wrap cwebpf-onboarding">
            <h1><?php esc_html_e('Product Finder for Gravity Forms — Setup', 'cweb-product-finder-for-gravity-forms'); ?></h1>

            <ol class="cwebpf-steps">
                <?php foreach ($steps as $n => $label): ?>
                    <li class="<?php echo $n === $current_step ? 'cwebpf-step--current' : ($n < $current_step ? 'cwebpf-step--done' : ''); ?>">
                        <span class="cwebpf-step-num"><?php echo (int) $n; ?></span>
                        <span class="cwebpf-step-label"><?php echo esc_html($label); ?></span>
                    </li>
                <?php endforeach; ?>
            </ol>

            <?php $this->render_flash_messages(); ?>

            <div class="cwebpf-card">
        <?php
    }

    private function render_footer(): void {
        ?>
            </div>
        </div>
        <?php
    }

    private function render_flash_messages(): void {
        // Read-only flash key set by our own wp_safe_redirect — display-only, no state change.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (empty($_GET['msg'])) {
            return;
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $msg = sanitize_text_field(wp_unslash($_GET['msg']));
        $messages = [
            'form_imported'      => ['success', __('Example form imported. Select it below.', 'cweb-product-finder-for-gravity-forms')],
            'form_import_failed' => ['error',   __('Could not import the example form. Make sure Gravity Forms is active.', 'cweb-product-finder-for-gravity-forms')],
        ];
        if (!isset($messages[$msg])) {
            return;
        }
        [$type, $text] = $messages[$msg];
        printf('<div class="notice notice-%s"><p>%s</p></div>', esc_attr($type), esc_html($text));
    }

    private function render_step_welcome(): void {
        $gf_active = class_exists('GFForms');
        $woo_active = class_exists('WooCommerce');
        $gf_url = apply_filters('cwebpf_gravityforms_url', self::GF_DEFAULT_URL);
        ?>
        <h2><?php esc_html_e('Welcome', 'cweb-product-finder-for-gravity-forms'); ?></h2>
        <p><?php esc_html_e('Product Finder for Gravity Forms plugs into a Gravity Forms questionnaire and recommends products to the visitor based on their answers. The scoring engine runs locally — no external API.', 'cweb-product-finder-for-gravity-forms'); ?></p>

        <h3><?php esc_html_e('System check', 'cweb-product-finder-for-gravity-forms'); ?></h3>
        <ul style="line-height: 2;">
            <li>
                <?php if ($gf_active): ?>
                    ✅ <?php esc_html_e('Gravity Forms is active.', 'cweb-product-finder-for-gravity-forms'); ?>
                <?php else: ?>
                    ❌ <strong><?php esc_html_e('Gravity Forms is not installed.', 'cweb-product-finder-for-gravity-forms'); ?></strong>
                <?php endif; ?>
            </li>
            <li>
                <?php if ($woo_active): ?>
                    ✅ <?php esc_html_e('WooCommerce detected (optional). You can use Woo products as recommendations.', 'cweb-product-finder-for-gravity-forms'); ?>
                <?php else: ?>
                    ℹ️ <?php esc_html_e('WooCommerce is not installed (optional). The plugin ships with a built-in product type.', 'cweb-product-finder-for-gravity-forms'); ?>
                <?php endif; ?>
            </li>
        </ul>

        <?php if (!$gf_active): ?>
            <div class="cwebpf-callout cwebpf-callout--warning">
                <h3 style="margin-top:0;"><?php esc_html_e('You need Gravity Forms first', 'cweb-product-finder-for-gravity-forms'); ?></h3>
                <p><?php esc_html_e('Gravity Forms is a commercial plugin (the engine that runs your questionnaire). Product Finder for Gravity Forms is the companion that handles the recommendation logic at the end of the form.', 'cweb-product-finder-for-gravity-forms'); ?></p>
                <p>
                    <a href="<?php echo esc_url($gf_url); ?>" class="button button-primary" target="_blank" rel="noopener">
                        <?php esc_html_e('Get Gravity Forms →', 'cweb-product-finder-for-gravity-forms'); ?>
                    </a>
                </p>
                <p style="margin-bottom: 0; font-size: 13px; color: #666;">
                    <?php esc_html_e('Already have it? Install and activate it, then refresh this page.', 'cweb-product-finder-for-gravity-forms'); ?>
                </p>
            </div>
        <?php else: ?>
            <div class="cwebpf-actions">
                <a href="<?php echo esc_url(admin_url('edit.php?post_type=' . CWebPF_Product_CPT::POST_TYPE . '&page=' . self::PAGE_SLUG . '&step=2')); ?>" class="button button-primary">
                    <?php esc_html_e('Get started →', 'cweb-product-finder-for-gravity-forms'); ?>
                </a>
            </div>
        <?php endif; ?>
        <?php
    }

    private function render_step_source(): void {
        $current = get_option(self::OPTION_SOURCE, 'cpt');
        $woo_active = class_exists('WooCommerce');
        ?>
        <h2><?php esc_html_e('Step 2 — Where will your products come from?', 'cweb-product-finder-for-gravity-forms'); ?></h2>
        <p><?php esc_html_e('Choose the catalog the recommender should pick from.', 'cweb-product-finder-for-gravity-forms'); ?></p>

        <form method="post" action="">
            <?php wp_nonce_field('cwebpf_onboarding', 'cwebpf_nonce'); ?>
            <input type="hidden" name="cwebpf_action" value="save_source" />

            <label style="display: block; padding: 14px; border: 2px solid <?php echo $current === 'cpt' ? '#2271b1' : '#ddd'; ?>; margin-bottom: 10px; cursor: pointer;">
                <input type="radio" name="cwebpf_source" value="cpt" <?php checked($current, 'cpt'); ?> />
                <strong><?php esc_html_e('Built-in Recommended Products (default)', 'cweb-product-finder-for-gravity-forms'); ?></strong>
                <p style="margin: 6px 0 0 24px; color: #666;"><?php esc_html_e('A dedicated WordPress menu for managing the products. Lightweight, no e-commerce setup required.', 'cweb-product-finder-for-gravity-forms'); ?></p>
            </label>

            <label style="display: block; padding: 14px; border: 2px solid <?php echo $current === 'woocommerce' ? '#2271b1' : '#ddd'; ?>; <?php echo !$woo_active ? 'opacity: 0.5;' : 'cursor: pointer;'; ?>">
                <input type="radio" name="cwebpf_source" value="woocommerce" <?php checked($current, 'woocommerce'); ?> <?php disabled(!$woo_active); ?> />
                <strong><?php esc_html_e('WooCommerce products', 'cweb-product-finder-for-gravity-forms'); ?></strong>
                <?php if (!$woo_active): ?>
                    <em>— <?php esc_html_e('WooCommerce is not active.', 'cweb-product-finder-for-gravity-forms'); ?></em>
                <?php endif; ?>
                <p style="margin: 6px 0 0 24px; color: #666;"><?php esc_html_e('Use your existing Woo catalog. Products are referenced by their post ID in scoring rules.', 'cweb-product-finder-for-gravity-forms'); ?></p>
            </label>

            <div class="cwebpf-actions">
                <button type="submit" class="button button-primary"><?php esc_html_e('Continue →', 'cweb-product-finder-for-gravity-forms'); ?></button>
            </div>
        </form>
        <?php
    }

    private function render_step_products(): void {
        $source = CWebPF_Product_Source_Factory::make();
        $products = $source->get_all_products();
        $count = count($products);
        $is_cpt = get_option(self::OPTION_SOURCE, 'cpt') === 'cpt';
        ?>
        <h2><?php esc_html_e('Step 3 — Add a few products', 'cweb-product-finder-for-gravity-forms'); ?></h2>
        <p><?php esc_html_e('Create the products you want to recommend. You\'ll wire your form answers to them in the Scoring Rules page after setup.', 'cweb-product-finder-for-gravity-forms'); ?></p>

        <?php if ($count === 0): ?>
            <div class="cwebpf-callout">
                <p><strong><?php esc_html_e('No products yet.', 'cweb-product-finder-for-gravity-forms'); ?></strong>
                   <?php esc_html_e('Add at least one before continuing.', 'cweb-product-finder-for-gravity-forms'); ?></p>
                <p>
                    <?php if ($is_cpt): ?>
                        <a href="<?php echo esc_url(admin_url('post-new.php?post_type=' . CWebPF_Product_CPT::POST_TYPE)); ?>" class="button button-primary" target="_blank">
                            <?php esc_html_e('Add a product', 'cweb-product-finder-for-gravity-forms'); ?>
                        </a>
                    <?php else: ?>
                        <a href="<?php echo esc_url(admin_url('post-new.php?post_type=product')); ?>" class="button button-primary" target="_blank">
                            <?php esc_html_e('Add a WooCommerce product', 'cweb-product-finder-for-gravity-forms'); ?>
                        </a>
                    <?php endif; ?>
                    <a href="<?php echo esc_url(admin_url('edit.php?post_type=' . CWebPF_Product_CPT::POST_TYPE . '&page=' . self::PAGE_SLUG . '&step=3')); ?>" class="button">
                        <?php esc_html_e('I added one, refresh', 'cweb-product-finder-for-gravity-forms'); ?>
                    </a>
                </p>
            </div>
        <?php else: ?>
            <div class="cwebpf-callout">
                <p><strong><?php
                    printf(
                        /* translators: %d is the number of products */
                        esc_html(_n('%d product ready.', '%d products ready.', $count, 'cweb-product-finder-for-gravity-forms')),
                        (int) $count
                    );
                ?></strong></p>
                <ul style="margin-left: 20px;">
                    <?php foreach (array_slice($products, 0, 5) as $p): ?>
                        <li><?php echo esc_html($p['name']); ?></li>
                    <?php endforeach; ?>
                    <?php if ($count > 5): ?>
                        <li>…</li>
                    <?php endif; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="cwebpf-actions">
            <?php if ($count > 0): ?>
                <a href="<?php echo esc_url(admin_url('edit.php?post_type=' . CWebPF_Product_CPT::POST_TYPE . '&page=' . self::PAGE_SLUG . '&step=4')); ?>" class="button button-primary">
                    <?php esc_html_e('Continue →', 'cweb-product-finder-for-gravity-forms'); ?>
                </a>
            <?php endif; ?>
        </div>
        <?php
    }

    private function render_step_form(): void {
        $config = (array) get_option(self::OPTION_FORM_CONFIG, []);
        $form_id = (int) ($config['form_id'] ?? 0);
        $field_id = (string) ($config['field_id'] ?? '');

        if (!class_exists('GFAPI')) {
            echo '<h2>' . esc_html__('Step 4 — Pick your Gravity Form', 'cweb-product-finder-for-gravity-forms') . '</h2>';
            echo '<p>' . esc_html__('Gravity Forms is not active.', 'cweb-product-finder-for-gravity-forms') . '</p>';
            return;
        }

        $forms = GFAPI::get_forms();
        ?>
        <h2><?php esc_html_e('Step 4 — Pick your Gravity Form', 'cweb-product-finder-for-gravity-forms'); ?></h2>
        <p><?php esc_html_e('Choose the form the recommender will listen to. We\'ll auto-detect a hidden field where the JSON result is stored.', 'cweb-product-finder-for-gravity-forms'); ?></p>

        <?php if (empty($forms)): ?>
            <div class="cwebpf-callout cwebpf-callout--warning">
                <p><strong><?php esc_html_e('No Gravity Forms found.', 'cweb-product-finder-for-gravity-forms'); ?></strong>
                   <?php esc_html_e('Create your form first, or import our example one.', 'cweb-product-finder-for-gravity-forms'); ?></p>
                <form method="post" action="">
                    <?php wp_nonce_field('cwebpf_onboarding', 'cwebpf_nonce'); ?>
                    <input type="hidden" name="cwebpf_action" value="import_example_form" />
                    <p><button type="submit" class="button button-primary"><?php esc_html_e('Import the example form', 'cweb-product-finder-for-gravity-forms'); ?></button></p>
                </form>
            </div>
        <?php else: ?>
            <p>
                <?php esc_html_e('Or, want to try with our example form?', 'cweb-product-finder-for-gravity-forms'); ?>
            </p>
            <form method="post" action="" style="margin-bottom: 16px;">
                <?php wp_nonce_field('cwebpf_onboarding', 'cwebpf_nonce'); ?>
                <input type="hidden" name="cwebpf_action" value="import_example_form" />
                <button type="submit" class="button"><?php esc_html_e('Import the example form', 'cweb-product-finder-for-gravity-forms'); ?></button>
            </form>
        <?php endif; ?>

        <form method="post" action="" id="cwebpf-form-config">
            <?php wp_nonce_field('cwebpf_onboarding', 'cwebpf_nonce'); ?>
            <input type="hidden" name="cwebpf_action" value="save_form_config" />

            <table class="form-table">
                <tr>
                    <th><label for="cwebpf_form_id"><?php esc_html_e('Gravity Form', 'cweb-product-finder-for-gravity-forms'); ?></label></th>
                    <td>
                        <select name="cwebpf_form_id" id="cwebpf_form_id" data-current-field-id="<?php echo esc_attr((string) $field_id); ?>" required>
                            <option value=""><?php esc_html_e('— Select a form —', 'cweb-product-finder-for-gravity-forms'); ?></option>
                            <?php foreach ($forms as $form):
                                $hidden_fields = $this->extract_hidden_fields($form);
                                ?>
                                <option value="<?php echo (int) $form['id']; ?>"
                                        data-hidden='<?php echo esc_attr(wp_json_encode($hidden_fields)); ?>'
                                        <?php selected($form_id, (int) $form['id']); ?>>
                                    #<?php echo (int) $form['id']; ?> — <?php echo esc_html($form['title']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="cwebpf_field_id_select"><?php esc_html_e('Hidden field for the JSON', 'cweb-product-finder-for-gravity-forms'); ?></label></th>
                    <td>
                        <select name="cwebpf_field_id" id="cwebpf_field_id_select">
                            <option value=""><?php esc_html_e('— Auto-detected —', 'cweb-product-finder-for-gravity-forms'); ?></option>
                        </select>
                        <input type="text" id="cwebpf_field_id_manual" value="" placeholder="<?php esc_attr_e('or type field ID manually', 'cweb-product-finder-for-gravity-forms'); ?>" style="display: none; margin-left: 8px;" />
                        <p class="description" id="cwebpf_field_id_hint"><?php esc_html_e('Pick a form above and we\'ll detect its hidden field(s).', 'cweb-product-finder-for-gravity-forms'); ?></p>
                    </td>
                </tr>
            </table>

            <div class="cwebpf-actions">
                <button type="submit" class="button button-primary"><?php esc_html_e('Save & continue →', 'cweb-product-finder-for-gravity-forms'); ?></button>
            </div>

        </form>
        <?php
    }

    private function render_step_finish(): void {
        $config = (array) get_option(self::OPTION_FORM_CONFIG, []);
        $form_id = (int) ($config['form_id'] ?? 0);
        $field_id = (string) ($config['field_id'] ?? '');
        $shortcode = sprintf('[cwebpf_recommender form_id="%d" field_id="%s"]', $form_id, $field_id);
        ?>
        <h2>🎉 <?php esc_html_e('You\'re ready', 'cweb-product-finder-for-gravity-forms'); ?></h2>
        <p><?php esc_html_e('Paste this shortcode into your Gravity Form confirmation message (Form settings → Confirmations → Text):', 'cweb-product-finder-for-gravity-forms'); ?></p>

        <pre style="background: #f6f7f7; padding: 14px; border: 1px solid #ddd; font-size: 14px; user-select: all;"><?php echo esc_html($shortcode); ?></pre>

        <h3><?php esc_html_e('Next: define scoring rules', 'cweb-product-finder-for-gravity-forms'); ?></h3>
        <p><?php esc_html_e('Right now any product can be recommended (no rules defined). Head to Scoring Rules to express things like "if the visitor picks X, boost product A by 20 points and exclude product B".', 'cweb-product-finder-for-gravity-forms'); ?></p>

        <div class="cwebpf-actions">
            <a href="<?php echo esc_url(admin_url('edit.php?post_type=' . CWebPF_Product_CPT::POST_TYPE . '&page=cwebpf-scoring-rules')); ?>" class="button button-primary">
                <?php esc_html_e('Go to Scoring Rules →', 'cweb-product-finder-for-gravity-forms'); ?>
            </a>
            <a href="<?php echo esc_url(admin_url('edit.php?post_type=' . CWebPF_Product_CPT::POST_TYPE . '&page=cwebpf-help')); ?>" class="button">
                <?php esc_html_e('Read the Help page', 'cweb-product-finder-for-gravity-forms'); ?>
            </a>
        </div>

        <form method="post" action="" style="margin-top: 30px;">
            <?php wp_nonce_field('cwebpf_onboarding', 'cwebpf_nonce'); ?>
            <input type="hidden" name="cwebpf_action" value="reset" />
            <button type="submit" class="button-link" style="color: #666;"><?php esc_html_e('Restart the wizard', 'cweb-product-finder-for-gravity-forms'); ?></button>
        </form>
        <?php
    }

    private function extract_hidden_fields(array $form): array {
        $result = [];
        foreach ($form['fields'] ?? [] as $field) {
            if (is_object($field)) {
                $field = (array) $field;
            }
            if (!is_array($field)) {
                continue;
            }
            if (($field['type'] ?? '') === 'hidden') {
                $result[] = [
                    'id'    => (string) $field['id'],
                    'label' => (string) ($field['label'] ?? __('(no label)', 'cweb-product-finder-for-gravity-forms')),
                ];
            }
        }
        return $result;
    }

    private function import_example_form(): bool {
        if (!class_exists('GFAPI')) {
            return false;
        }
        $json_path = CWEBPF_PLUGIN_DIR . 'templates/example-form.json';
        if (!file_exists($json_path)) {
            return false;
        }
        $data = wp_json_file_decode($json_path, ['associative' => true]);
        if (!is_array($data)) {
            return false;
        }
        $form_data = isset($data['0']) ? $data['0'] : $data;
        $result = GFAPI::add_form($form_data);
        return !is_wp_error($result);
    }
}
