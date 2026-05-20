<?php
/**
 * Onboarding admin pour Gravity Recommender.
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

class GR_Admin_Onboarding {

    private const PAGE_SLUG = 'gr-onboarding';
    private const OPTION_FORM_CONFIG = 'gr_form_config';
    private const OPTION_SOURCE      = 'gr_product_source';
    private const OPTION_STEP        = 'gr_onboarding_step';

    private const GF_DEFAULT_URL = 'https://www.gravityforms.com/';

    public function __construct() {
        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_init', [$this, 'handle_form_submissions']);
        add_action('admin_notices', [$this, 'maybe_show_setup_notice']);

        $main = GR_PLUGIN_DIR . 'gravity-recommender.php';
        add_filter('plugin_action_links_' . plugin_basename($main), [$this, 'add_settings_link']);
    }

    public function register_menu(): void {
        add_submenu_page(
            'edit.php?post_type=' . GR_Product_CPT::POST_TYPE,
            __('Recommender Setup', 'gravity-recommender'),
            __('Setup', 'gravity-recommender'),
            'manage_options',
            self::PAGE_SLUG,
            [$this, 'render_page']
        );
    }

    public function add_settings_link(array $links): array {
        $url = admin_url('edit.php?post_type=' . GR_Product_CPT::POST_TYPE . '&page=' . self::PAGE_SLUG);
        $links[] = '<a href="' . esc_url($url) . '">' . esc_html__('Setup', 'gravity-recommender') . '</a>';
        return $links;
    }

    public function maybe_show_setup_notice(): void {
        $config = (array) get_option(self::OPTION_FORM_CONFIG, []);
        if (!empty($config['form_id']) && !empty($config['field_id'])) {
            return;
        }
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if ($screen && $screen->id === GR_Product_CPT::POST_TYPE . '_page_' . self::PAGE_SLUG) {
            return;
        }

        $url = admin_url('edit.php?post_type=' . GR_Product_CPT::POST_TYPE . '&page=' . self::PAGE_SLUG);
        ?>
        <div class="notice notice-info">
            <p>
                <strong>Gravity Recommender</strong> —
                <?php esc_html_e('Finish the setup to start recommending products.', 'gravity-recommender'); ?>
                <a href="<?php echo esc_url($url); ?>" class="button button-primary" style="margin-left: 10px;">
                    <?php esc_html_e('Open setup wizard', 'gravity-recommender'); ?>
                </a>
            </p>
        </div>
        <?php
    }

    public function handle_form_submissions(): void {
        if (!isset($_POST['gr_action']) || !current_user_can('manage_options')) {
            return;
        }
        if (!isset($_POST['gr_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['gr_nonce'])), 'gr_onboarding')) {
            return;
        }

        $action = sanitize_text_field(wp_unslash($_POST['gr_action']));

        switch ($action) {
            case 'save_source':
                $source = sanitize_text_field(wp_unslash($_POST['gr_source'] ?? 'cpt'));
                if (!in_array($source, ['cpt', 'woocommerce'], true)) {
                    $source = 'cpt';
                }
                update_option(self::OPTION_SOURCE, $source);
                $this->redirect_to_step(3);
                break;

            case 'save_form_config':
                $form_id  = isset($_POST['gr_form_id']) ? absint(wp_unslash($_POST['gr_form_id'])) : 0;
                $field_id = isset($_POST['gr_field_id']) ? sanitize_text_field(wp_unslash($_POST['gr_field_id'])) : '';
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
        $url = admin_url('edit.php?post_type=' . GR_Product_CPT::POST_TYPE . '&page=' . self::PAGE_SLUG . '&step=' . $step);
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
            1 => __('Welcome', 'gravity-recommender'),
            2 => __('Source', 'gravity-recommender'),
            3 => __('Products', 'gravity-recommender'),
            4 => __('Form', 'gravity-recommender'),
            5 => __('Done', 'gravity-recommender'),
        ];
        ?>
        <div class="wrap gr-onboarding">
            <h1><?php esc_html_e('Gravity Recommender — Setup', 'gravity-recommender'); ?></h1>

            <ol class="gr-steps">
                <?php foreach ($steps as $n => $label): ?>
                    <li class="<?php echo $n === $current_step ? 'gr-step--current' : ($n < $current_step ? 'gr-step--done' : ''); ?>">
                        <span class="gr-step-num"><?php echo (int) $n; ?></span>
                        <span class="gr-step-label"><?php echo esc_html($label); ?></span>
                    </li>
                <?php endforeach; ?>
            </ol>

            <?php $this->render_flash_messages(); ?>

            <div class="gr-card">
        <?php
    }

    private function render_footer(): void {
        ?>
            </div>
        </div>
        <style>
            .gr-onboarding .gr-steps { display: flex; gap: 0; list-style: none; padding: 0; margin: 20px 0 30px; border-bottom: 1px solid #ddd; }
            .gr-onboarding .gr-steps li { flex: 1; padding: 12px 16px; opacity: 0.5; border-bottom: 3px solid transparent; }
            .gr-onboarding .gr-step--current { opacity: 1; border-bottom-color: #2271b1 !important; font-weight: 600; }
            .gr-onboarding .gr-step--done { opacity: 0.9; }
            .gr-onboarding .gr-step-num { display: inline-block; width: 24px; height: 24px; line-height: 24px; text-align: center; border-radius: 50%; background: #ddd; margin-right: 8px; }
            .gr-onboarding .gr-step--current .gr-step-num { background: #2271b1; color: #fff; }
            .gr-onboarding .gr-step--done .gr-step-num { background: #46b450; color: #fff; }
            .gr-onboarding .gr-card { background: #fff; padding: 30px; border: 1px solid #ccd0d4; max-width: 900px; }
            .gr-onboarding .gr-card h2 { margin-top: 0; }
            .gr-onboarding .gr-actions { margin-top: 24px; display: flex; gap: 12px; flex-wrap: wrap; }
            .gr-onboarding table.form-table th { padding-right: 24px; }
            .gr-onboarding .gr-callout { padding: 20px; background: #f0f6fc; border-left: 4px solid #2271b1; margin: 16px 0; }
            .gr-onboarding .gr-callout--warning { background: #fff8e5; border-left-color: #dba617; }
        </style>
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
            'form_imported'      => ['success', __('Example form imported. Select it below.', 'gravity-recommender')],
            'form_import_failed' => ['error',   __('Could not import the example form. Make sure Gravity Forms is active.', 'gravity-recommender')],
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
        $gf_url = apply_filters('gr_gravityforms_affiliate_url', self::GF_DEFAULT_URL);
        ?>
        <h2><?php esc_html_e('Welcome', 'gravity-recommender'); ?></h2>
        <p><?php esc_html_e('Gravity Recommender plugs into a Gravity Forms questionnaire and recommends products to the visitor based on their answers. The scoring engine runs locally — no external API.', 'gravity-recommender'); ?></p>

        <h3><?php esc_html_e('System check', 'gravity-recommender'); ?></h3>
        <ul style="line-height: 2;">
            <li>
                <?php if ($gf_active): ?>
                    ✅ <?php esc_html_e('Gravity Forms is active.', 'gravity-recommender'); ?>
                <?php else: ?>
                    ❌ <strong><?php esc_html_e('Gravity Forms is not installed.', 'gravity-recommender'); ?></strong>
                <?php endif; ?>
            </li>
            <li>
                <?php if ($woo_active): ?>
                    ✅ <?php esc_html_e('WooCommerce detected (optional). You can use Woo products as recommendations.', 'gravity-recommender'); ?>
                <?php else: ?>
                    ℹ️ <?php esc_html_e('WooCommerce is not installed (optional). The plugin ships with a built-in product type.', 'gravity-recommender'); ?>
                <?php endif; ?>
            </li>
        </ul>

        <?php if (!$gf_active): ?>
            <div class="gr-callout gr-callout--warning">
                <h3 style="margin-top:0;"><?php esc_html_e('You need Gravity Forms first', 'gravity-recommender'); ?></h3>
                <p><?php esc_html_e('Gravity Forms is a commercial plugin (the engine that runs your questionnaire). Gravity Recommender is the companion that handles the recommendation logic at the end of the form.', 'gravity-recommender'); ?></p>
                <p>
                    <a href="<?php echo esc_url($gf_url); ?>" class="button button-primary" target="_blank" rel="noopener">
                        <?php esc_html_e('Get Gravity Forms →', 'gravity-recommender'); ?>
                    </a>
                </p>
                <p style="margin-bottom: 0; font-size: 13px; color: #666;">
                    <?php esc_html_e('Already have it? Install and activate it, then refresh this page.', 'gravity-recommender'); ?>
                </p>
            </div>
        <?php else: ?>
            <div class="gr-actions">
                <a href="<?php echo esc_url(admin_url('edit.php?post_type=' . GR_Product_CPT::POST_TYPE . '&page=' . self::PAGE_SLUG . '&step=2')); ?>" class="button button-primary">
                    <?php esc_html_e('Get started →', 'gravity-recommender'); ?>
                </a>
            </div>
        <?php endif; ?>
        <?php
    }

    private function render_step_source(): void {
        $current = get_option(self::OPTION_SOURCE, 'cpt');
        $woo_active = class_exists('WooCommerce');
        ?>
        <h2><?php esc_html_e('Step 2 — Where will your products come from?', 'gravity-recommender'); ?></h2>
        <p><?php esc_html_e('Choose the catalog the recommender should pick from.', 'gravity-recommender'); ?></p>

        <form method="post" action="">
            <?php wp_nonce_field('gr_onboarding', 'gr_nonce'); ?>
            <input type="hidden" name="gr_action" value="save_source" />

            <label style="display: block; padding: 14px; border: 2px solid <?php echo $current === 'cpt' ? '#2271b1' : '#ddd'; ?>; margin-bottom: 10px; cursor: pointer;">
                <input type="radio" name="gr_source" value="cpt" <?php checked($current, 'cpt'); ?> />
                <strong><?php esc_html_e('Built-in Recommended Products (default)', 'gravity-recommender'); ?></strong>
                <p style="margin: 6px 0 0 24px; color: #666;"><?php esc_html_e('A dedicated WordPress menu for managing the products. Lightweight, no e-commerce setup required.', 'gravity-recommender'); ?></p>
            </label>

            <label style="display: block; padding: 14px; border: 2px solid <?php echo $current === 'woocommerce' ? '#2271b1' : '#ddd'; ?>; <?php echo !$woo_active ? 'opacity: 0.5;' : 'cursor: pointer;'; ?>">
                <input type="radio" name="gr_source" value="woocommerce" <?php checked($current, 'woocommerce'); ?> <?php disabled(!$woo_active); ?> />
                <strong><?php esc_html_e('WooCommerce products', 'gravity-recommender'); ?></strong>
                <?php if (!$woo_active): ?>
                    <em>— <?php esc_html_e('WooCommerce is not active.', 'gravity-recommender'); ?></em>
                <?php endif; ?>
                <p style="margin: 6px 0 0 24px; color: #666;"><?php esc_html_e('Use your existing Woo catalog. Products are referenced by their post ID in scoring rules.', 'gravity-recommender'); ?></p>
            </label>

            <div class="gr-actions">
                <button type="submit" class="button button-primary"><?php esc_html_e('Continue →', 'gravity-recommender'); ?></button>
            </div>
        </form>
        <?php
    }

    private function render_step_products(): void {
        $source = GR_Product_Source_Factory::make();
        $products = $source->get_all_products();
        $count = count($products);
        $is_cpt = get_option(self::OPTION_SOURCE, 'cpt') === 'cpt';
        ?>
        <h2><?php esc_html_e('Step 3 — Add a few products', 'gravity-recommender'); ?></h2>
        <p><?php esc_html_e('Create the products you want to recommend. You\'ll wire your form answers to them in the Scoring Rules page after setup.', 'gravity-recommender'); ?></p>

        <?php if ($count === 0): ?>
            <div class="gr-callout">
                <p><strong><?php esc_html_e('No products yet.', 'gravity-recommender'); ?></strong>
                   <?php esc_html_e('Add at least one before continuing.', 'gravity-recommender'); ?></p>
                <p>
                    <?php if ($is_cpt): ?>
                        <a href="<?php echo esc_url(admin_url('post-new.php?post_type=' . GR_Product_CPT::POST_TYPE)); ?>" class="button button-primary" target="_blank">
                            <?php esc_html_e('Add a product', 'gravity-recommender'); ?>
                        </a>
                    <?php else: ?>
                        <a href="<?php echo esc_url(admin_url('post-new.php?post_type=product')); ?>" class="button button-primary" target="_blank">
                            <?php esc_html_e('Add a WooCommerce product', 'gravity-recommender'); ?>
                        </a>
                    <?php endif; ?>
                    <a href="<?php echo esc_url(admin_url('edit.php?post_type=' . GR_Product_CPT::POST_TYPE . '&page=' . self::PAGE_SLUG . '&step=3')); ?>" class="button">
                        <?php esc_html_e('I added one, refresh', 'gravity-recommender'); ?>
                    </a>
                </p>
            </div>
        <?php else: ?>
            <div class="gr-callout">
                <p><strong><?php
                    printf(
                        /* translators: %d is the number of products */
                        esc_html(_n('%d product ready.', '%d products ready.', $count, 'gravity-recommender')),
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

        <div class="gr-actions">
            <?php if ($count > 0): ?>
                <a href="<?php echo esc_url(admin_url('edit.php?post_type=' . GR_Product_CPT::POST_TYPE . '&page=' . self::PAGE_SLUG . '&step=4')); ?>" class="button button-primary">
                    <?php esc_html_e('Continue →', 'gravity-recommender'); ?>
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
            echo '<h2>' . esc_html__('Step 4 — Pick your Gravity Form', 'gravity-recommender') . '</h2>';
            echo '<p>' . esc_html__('Gravity Forms is not active.', 'gravity-recommender') . '</p>';
            return;
        }

        $forms = GFAPI::get_forms();
        ?>
        <h2><?php esc_html_e('Step 4 — Pick your Gravity Form', 'gravity-recommender'); ?></h2>
        <p><?php esc_html_e('Choose the form the recommender will listen to. We\'ll auto-detect a hidden field where the JSON result is stored.', 'gravity-recommender'); ?></p>

        <?php if (empty($forms)): ?>
            <div class="gr-callout gr-callout--warning">
                <p><strong><?php esc_html_e('No Gravity Forms found.', 'gravity-recommender'); ?></strong>
                   <?php esc_html_e('Create your form first, or import our example one.', 'gravity-recommender'); ?></p>
                <form method="post" action="">
                    <?php wp_nonce_field('gr_onboarding', 'gr_nonce'); ?>
                    <input type="hidden" name="gr_action" value="import_example_form" />
                    <p><button type="submit" class="button button-primary"><?php esc_html_e('Import the example form', 'gravity-recommender'); ?></button></p>
                </form>
            </div>
        <?php else: ?>
            <p>
                <?php esc_html_e('Or, want to try with our example form?', 'gravity-recommender'); ?>
            </p>
            <form method="post" action="" style="margin-bottom: 16px;">
                <?php wp_nonce_field('gr_onboarding', 'gr_nonce'); ?>
                <input type="hidden" name="gr_action" value="import_example_form" />
                <button type="submit" class="button"><?php esc_html_e('Import the example form', 'gravity-recommender'); ?></button>
            </form>
        <?php endif; ?>

        <form method="post" action="" id="gr-form-config">
            <?php wp_nonce_field('gr_onboarding', 'gr_nonce'); ?>
            <input type="hidden" name="gr_action" value="save_form_config" />

            <table class="form-table">
                <tr>
                    <th><label for="gr_form_id"><?php esc_html_e('Gravity Form', 'gravity-recommender'); ?></label></th>
                    <td>
                        <select name="gr_form_id" id="gr_form_id" required>
                            <option value=""><?php esc_html_e('— Select a form —', 'gravity-recommender'); ?></option>
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
                    <th><label for="gr_field_id_select"><?php esc_html_e('Hidden field for the JSON', 'gravity-recommender'); ?></label></th>
                    <td>
                        <select name="gr_field_id" id="gr_field_id_select">
                            <option value=""><?php esc_html_e('— Auto-detected —', 'gravity-recommender'); ?></option>
                        </select>
                        <input type="text" id="gr_field_id_manual" value="" placeholder="<?php esc_attr_e('or type field ID manually', 'gravity-recommender'); ?>" style="display: none; margin-left: 8px;" />
                        <p class="description" id="gr_field_id_hint"><?php esc_html_e('Pick a form above and we\'ll detect its hidden field(s).', 'gravity-recommender'); ?></p>
                    </td>
                </tr>
            </table>

            <div class="gr-actions">
                <button type="submit" class="button button-primary"><?php esc_html_e('Save & continue →', 'gravity-recommender'); ?></button>
            </div>

            <script>
            (function () {
                const formSel = document.getElementById('gr_form_id');
                const fieldSel = document.getElementById('gr_field_id_select');
                const fieldManual = document.getElementById('gr_field_id_manual');
                const hint = document.getElementById('gr_field_id_hint');
                const currentFieldId = <?php echo wp_json_encode($field_id); ?>;

                function refreshFieldOptions() {
                    const opt = formSel.selectedOptions[0];
                    const hidden = opt && opt.dataset.hidden ? JSON.parse(opt.dataset.hidden) : [];
                    fieldSel.innerHTML = '';

                    if (!opt || !opt.value) {
                        fieldSel.style.display = '';
                        fieldManual.style.display = 'none';
                        fieldSel.name = 'gr_field_id';
                        fieldManual.name = '';
                        return;
                    }

                    if (hidden.length === 0) {
                        fieldSel.style.display = 'none';
                        fieldSel.name = '';
                        fieldManual.style.display = 'inline-block';
                        fieldManual.name = 'gr_field_id';
                        fieldManual.value = currentFieldId || '';
                        hint.textContent = <?php echo wp_json_encode(esc_html__('No hidden field found in this form. Add one in Gravity Forms (Hidden field), then type its ID here.', 'gravity-recommender')); ?>;
                        return;
                    }

                    fieldSel.style.display = '';
                    fieldSel.name = 'gr_field_id';
                    fieldManual.style.display = 'none';
                    fieldManual.name = '';

                    hidden.forEach(f => {
                        const o = document.createElement('option');
                        o.value = f.id;
                        o.textContent = '#' + f.id + ' — ' + (f.label || '(no label)');
                        if (String(f.id) === String(currentFieldId)) o.selected = true;
                        fieldSel.appendChild(o);
                    });

                    if (hidden.length === 1) {
                        fieldSel.value = hidden[0].id;
                        hint.textContent = <?php echo wp_json_encode(esc_html__('One hidden field found and selected automatically.', 'gravity-recommender')); ?>;
                    } else {
                        hint.textContent = <?php echo wp_json_encode(esc_html__('Multiple hidden fields detected. Pick the one that should store the recommendation result.', 'gravity-recommender')); ?>;
                    }
                }

                formSel.addEventListener('change', refreshFieldOptions);
                refreshFieldOptions();
            })();
            </script>
        </form>
        <?php
    }

    private function render_step_finish(): void {
        $config = (array) get_option(self::OPTION_FORM_CONFIG, []);
        $form_id = (int) ($config['form_id'] ?? 0);
        $field_id = (string) ($config['field_id'] ?? '');
        $shortcode = sprintf('[gravity_recommender form_id="%d" field_id="%s"]', $form_id, $field_id);
        ?>
        <h2>🎉 <?php esc_html_e('You\'re ready', 'gravity-recommender'); ?></h2>
        <p><?php esc_html_e('Paste this shortcode into your Gravity Form confirmation message (Form settings → Confirmations → Text):', 'gravity-recommender'); ?></p>

        <pre style="background: #f6f7f7; padding: 14px; border: 1px solid #ddd; font-size: 14px; user-select: all;"><?php echo esc_html($shortcode); ?></pre>

        <h3><?php esc_html_e('Next: define scoring rules', 'gravity-recommender'); ?></h3>
        <p><?php esc_html_e('Right now any product can be recommended (no rules defined). Head to Scoring Rules to express things like "if the visitor picks X, boost product A by 20 points and exclude product B".', 'gravity-recommender'); ?></p>

        <div class="gr-actions">
            <a href="<?php echo esc_url(admin_url('edit.php?post_type=' . GR_Product_CPT::POST_TYPE . '&page=gr-scoring-rules')); ?>" class="button button-primary">
                <?php esc_html_e('Go to Scoring Rules →', 'gravity-recommender'); ?>
            </a>
            <a href="<?php echo esc_url(admin_url('edit.php?post_type=' . GR_Product_CPT::POST_TYPE . '&page=gr-help')); ?>" class="button">
                <?php esc_html_e('Read the Help page', 'gravity-recommender'); ?>
            </a>
        </div>

        <form method="post" action="" style="margin-top: 30px;">
            <?php wp_nonce_field('gr_onboarding', 'gr_nonce'); ?>
            <input type="hidden" name="gr_action" value="reset" />
            <button type="submit" class="button-link" style="color: #666;"><?php esc_html_e('Restart the wizard', 'gravity-recommender'); ?></button>
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
                    'label' => (string) ($field['label'] ?? __('(no label)', 'gravity-recommender')),
                ];
            }
        }
        return $result;
    }

    private function import_example_form(): bool {
        if (!class_exists('GFAPI')) {
            return false;
        }
        $json_path = GR_PLUGIN_DIR . 'templates/example-form.json';
        if (!file_exists($json_path)) {
            return false;
        }
        $json = file_get_contents($json_path);
        if (!$json) {
            return false;
        }
        $data = json_decode($json, true);
        if (!is_array($data)) {
            return false;
        }
        $form_data = isset($data['0']) ? $data['0'] : $data;
        $result = GFAPI::add_form($form_data);
        return !is_wp_error($result);
    }
}
