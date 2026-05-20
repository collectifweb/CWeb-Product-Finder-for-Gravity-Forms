<?php
/**
 * Onboarding admin pour Gravity Recommender.
 *
 * Page accessible via : Outils → Recommender Setup.
 *
 * Wizard 5 étapes :
 *   1. Bienvenue / vérification dépendances
 *   2. Source de produits (CPT ou WooCommerce)
 *   3. Formulaire Gravity Forms (sélection + champ caché + import du modèle)
 *   4. Règles de scoring (mapping field → tag)
 *   5. Récap + shortcode à coller
 */

if (!defined('ABSPATH')) {
    exit;
}

class GR_Admin_Onboarding {

    private const PAGE_SLUG = 'gr-onboarding';
    private const OPTION_FORM_CONFIG = 'gr_form_config';
    private const OPTION_RULES       = 'gr_scoring_rules';
    private const OPTION_SOURCE      = 'gr_product_source';
    private const OPTION_STEP        = 'gr_onboarding_step';

    public function __construct() {
        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_init', [$this, 'handle_form_submissions']);
        add_action('admin_notices', [$this, 'maybe_show_setup_notice']);
        add_filter('plugin_action_links_' . plugin_basename(GR_PLUGIN_DIR . 'gravity-recommender.php'), [$this, 'add_settings_link']);
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
                <strong><?php esc_html_e('Gravity Recommender', 'gravity-recommender'); ?></strong> —
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
                update_option(self::OPTION_STEP, 3);
                $this->redirect_to_step(3);
                break;

            case 'save_form_config':
                $form_id  = (int) ($_POST['gr_form_id'] ?? 0);
                $field_id = sanitize_text_field(wp_unslash($_POST['gr_field_id'] ?? ''));
                update_option(self::OPTION_FORM_CONFIG, [
                    'form_id'  => $form_id,
                    'field_id' => $field_id,
                ]);
                update_option(self::OPTION_STEP, 4);
                $this->redirect_to_step(4);
                break;

            case 'import_example_form':
                $imported = $this->import_example_form();
                $redirect_step = $imported ? 3 : 3;
                $this->redirect_to_step($redirect_step, $imported ? 'form_imported' : 'form_import_failed');
                break;

            case 'save_rules':
                $rules = $this->parse_submitted_rules($_POST['gr_rules'] ?? []);
                update_option(self::OPTION_RULES, $rules);
                update_option(self::OPTION_STEP, 5);
                $this->redirect_to_step(5);
                break;

            case 'reset':
                delete_option(self::OPTION_STEP);
                $this->redirect_to_step(1);
                break;
        }
    }

    private function redirect_to_step(int $step, ?string $message = null): void {
        $url = admin_url('edit.php?post_type=' . GR_Product_CPT::POST_TYPE . '&page=' . self::PAGE_SLUG . '&step=' . $step);
        if ($message) {
            $url = add_query_arg('msg', $message, $url);
        }
        wp_safe_redirect($url);
        exit;
    }

    public function render_page(): void {
        $step = isset($_GET['step']) ? max(1, min(5, (int) $_GET['step'])) : (int) get_option(self::OPTION_STEP, 1);
        $this->render_header($step);

        switch ($step) {
            case 1: $this->render_step_welcome(); break;
            case 2: $this->render_step_source(); break;
            case 3: $this->render_step_form(); break;
            case 4: $this->render_step_rules(); break;
            case 5: $this->render_step_finish(); break;
        }

        $this->render_footer();
    }

    private function render_header(int $current_step): void {
        $steps = [
            1 => __('Welcome', 'gravity-recommender'),
            2 => __('Product source', 'gravity-recommender'),
            3 => __('Gravity Form', 'gravity-recommender'),
            4 => __('Scoring rules', 'gravity-recommender'),
            5 => __('Finish', 'gravity-recommender'),
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
            .gr-onboarding .gr-actions { margin-top: 24px; display: flex; gap: 12px; }
            .gr-onboarding table.form-table th { padding-right: 24px; }
            .gr-rules-table { width: 100%; border-collapse: collapse; margin-top: 12px; }
            .gr-rules-table th, .gr-rules-table td { padding: 8px; border-bottom: 1px solid #eee; text-align: left; vertical-align: top; }
            .gr-rules-table input, .gr-rules-table select { width: 100%; }
            .gr-rules-table .gr-col-remove { width: 60px; text-align: center; }
            .gr-tag-summary code { background: #f0f0f1; padding: 2px 6px; border-radius: 3px; }
        </style>
        <?php
    }

    private function render_flash_messages(): void {
        if (empty($_GET['msg'])) {
            return;
        }
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

    // ----------------------------------------------------------------
    // Steps
    // ----------------------------------------------------------------

    private function render_step_welcome(): void {
        $gf_active = class_exists('GFForms');
        ?>
        <h2><?php esc_html_e('Welcome', 'gravity-recommender'); ?></h2>
        <p><?php esc_html_e('Gravity Recommender plugs into a Gravity Forms questionnaire and recommends one or more products to the visitor based on their answers. The scoring engine runs locally in PHP — no external API needed.', 'gravity-recommender'); ?></p>

        <h3><?php esc_html_e('System check', 'gravity-recommender'); ?></h3>
        <ul style="line-height: 2;">
            <li>
                <?php if ($gf_active): ?>
                    ✅ <?php esc_html_e('Gravity Forms is active.', 'gravity-recommender'); ?>
                <?php else: ?>
                    ❌ <strong><?php esc_html_e('Gravity Forms is not active.', 'gravity-recommender'); ?></strong>
                    <?php esc_html_e('This plugin requires Gravity Forms.', 'gravity-recommender'); ?>
                <?php endif; ?>
            </li>
            <li>
                <?php if (class_exists('WooCommerce')): ?>
                    ✅ <?php esc_html_e('WooCommerce detected (optional). You can use Woo products as recommendations.', 'gravity-recommender'); ?>
                <?php else: ?>
                    ℹ️ <?php esc_html_e('WooCommerce is not installed (optional). The plugin will use its built-in product type.', 'gravity-recommender'); ?>
                <?php endif; ?>
            </li>
        </ul>

        <div class="gr-actions">
            <a href="<?php echo esc_url(admin_url('edit.php?post_type=' . GR_Product_CPT::POST_TYPE . '&page=' . self::PAGE_SLUG . '&step=2')); ?>" class="button button-primary"><?php esc_html_e('Get started →', 'gravity-recommender'); ?></a>
        </div>
        <?php
    }

    private function render_step_source(): void {
        $current = get_option(self::OPTION_SOURCE, 'cpt');
        $woo_active = class_exists('WooCommerce');
        ?>
        <h2><?php esc_html_e('Step 2 — Choose the product source', 'gravity-recommender'); ?></h2>
        <p><?php esc_html_e('Which products should the recommender pick from?', 'gravity-recommender'); ?></p>

        <form method="post" action="">
            <?php wp_nonce_field('gr_onboarding', 'gr_nonce'); ?>
            <input type="hidden" name="gr_action" value="save_source" />

            <label style="display: block; padding: 14px; border: 2px solid <?php echo $current === 'cpt' ? '#2271b1' : '#ddd'; ?>; margin-bottom: 10px; cursor: pointer;">
                <input type="radio" name="gr_source" value="cpt" <?php checked($current, 'cpt'); ?> />
                <strong><?php esc_html_e('Built-in Recommended Products', 'gravity-recommender'); ?></strong>
                <p style="margin: 6px 0 0 24px; color: #666;"><?php esc_html_e('Manage products from a dedicated WordPress menu. Use this if you want a lightweight catalog separate from your store.', 'gravity-recommender'); ?></p>
            </label>

            <label style="display: block; padding: 14px; border: 2px solid <?php echo $current === 'woocommerce' ? '#2271b1' : '#ddd'; ?>; <?php echo !$woo_active ? 'opacity: 0.5;' : 'cursor: pointer;'; ?>">
                <input type="radio" name="gr_source" value="woocommerce" <?php checked($current, 'woocommerce'); ?> <?php disabled(!$woo_active); ?> />
                <strong><?php esc_html_e('WooCommerce products', 'gravity-recommender'); ?></strong>
                <?php if (!$woo_active): ?>
                    <em>— <?php esc_html_e('WooCommerce is not active.', 'gravity-recommender'); ?></em>
                <?php endif; ?>
                <p style="margin: 6px 0 0 24px; color: #666;"><?php esc_html_e('Use your existing WooCommerce catalog. Tag products with the WooCommerce product tags taxonomy — those tags will be matched against your scoring rules.', 'gravity-recommender'); ?></p>
            </label>

            <div class="gr-actions">
                <button type="submit" class="button button-primary"><?php esc_html_e('Continue →', 'gravity-recommender'); ?></button>
            </div>
        </form>
        <?php
    }

    private function render_step_form(): void {
        $config = (array) get_option(self::OPTION_FORM_CONFIG, []);
        $form_id = (int) ($config['form_id'] ?? 0);
        $field_id = (string) ($config['field_id'] ?? '');

        if (!class_exists('GFAPI')) {
            ?>
            <h2><?php esc_html_e('Step 3 — Pick your Gravity Form', 'gravity-recommender'); ?></h2>
            <p><?php esc_html_e('Gravity Forms is not active.', 'gravity-recommender'); ?></p>
            <?php
            return;
        }

        $forms = GFAPI::get_forms();
        ?>
        <h2><?php esc_html_e('Step 3 — Pick your Gravity Form', 'gravity-recommender'); ?></h2>
        <p><?php esc_html_e('Choose the form that asks your visitors questions, and the hidden field where the recommendation JSON will be stored.', 'gravity-recommender'); ?></p>

        <?php if (empty($forms)): ?>
            <div class="notice notice-warning inline" style="margin: 14px 0;">
                <p><?php esc_html_e('No Gravity Forms found. You can either create one yourself or import our example form below.', 'gravity-recommender'); ?></p>
            </div>
        <?php endif; ?>

        <p>
            <?php esc_html_e('No form yet?', 'gravity-recommender'); ?>
            <form method="post" action="" style="display: inline;">
                <?php wp_nonce_field('gr_onboarding', 'gr_nonce'); ?>
                <input type="hidden" name="gr_action" value="import_example_form" />
                <button type="submit" class="button"><?php esc_html_e('Import the example form', 'gravity-recommender'); ?></button>
            </form>
        </p>

        <form method="post" action="">
            <?php wp_nonce_field('gr_onboarding', 'gr_nonce'); ?>
            <input type="hidden" name="gr_action" value="save_form_config" />

            <table class="form-table">
                <tr>
                    <th><label for="gr_form_id"><?php esc_html_e('Gravity Form', 'gravity-recommender'); ?></label></th>
                    <td>
                        <select name="gr_form_id" id="gr_form_id" required>
                            <option value=""><?php esc_html_e('— Select a form —', 'gravity-recommender'); ?></option>
                            <?php foreach ($forms as $form): ?>
                                <option value="<?php echo (int) $form['id']; ?>" <?php selected($form_id, (int) $form['id']); ?>>
                                    #<?php echo (int) $form['id']; ?> — <?php echo esc_html($form['title']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="gr_field_id"><?php esc_html_e('Hidden field ID for the JSON', 'gravity-recommender'); ?></label></th>
                    <td>
                        <input type="text" name="gr_field_id" id="gr_field_id" value="<?php echo esc_attr($field_id); ?>" placeholder="e.g. 44" required />
                        <p class="description"><?php esc_html_e('Add a hidden field to your form, note its ID, and enter it here. The recommendation JSON will be written there on submission.', 'gravity-recommender'); ?></p>
                    </td>
                </tr>
            </table>

            <div class="gr-actions">
                <button type="submit" class="button button-primary"><?php esc_html_e('Continue →', 'gravity-recommender'); ?></button>
            </div>
        </form>
        <?php
    }

    private function render_step_rules(): void {
        $rules = (array) get_option(self::OPTION_RULES, []);
        $config = (array) get_option(self::OPTION_FORM_CONFIG, []);
        $form_id = (int) ($config['form_id'] ?? 0);

        $field_choices = $this->get_form_field_choices($form_id);
        $available_tags = $this->get_available_tags();
        ?>
        <h2><?php esc_html_e('Step 4 — Scoring rules', 'gravity-recommender'); ?></h2>
        <p><?php esc_html_e('Each rule says: "if a form answer matches this text, then BOOST / EXCLUDE / REQUIRE products with this tag". Add as many rules as you need.', 'gravity-recommender'); ?></p>

        <?php if (!empty($available_tags)): ?>
            <p class="gr-tag-summary">
                <strong><?php esc_html_e('Tags found on your products:', 'gravity-recommender'); ?></strong>
                <?php foreach ($available_tags as $t): ?><code><?php echo esc_html($t); ?></code> <?php endforeach; ?>
            </p>
        <?php else: ?>
            <div class="notice notice-warning inline" style="margin: 14px 0;">
                <p><?php
                    printf(
                        /* translators: %s is a link to the Recommended Products page */
                        esc_html__('No products with tags found yet. %s before defining rules.', 'gravity-recommender'),
                        '<a href="' . esc_url(admin_url('edit.php?post_type=' . GR_Product_CPT::POST_TYPE)) . '">' . esc_html__('Create some products and tag them', 'gravity-recommender') . '</a>'
                    );
                ?></p>
            </div>
        <?php endif; ?>

        <form method="post" action="">
            <?php wp_nonce_field('gr_onboarding', 'gr_nonce'); ?>
            <input type="hidden" name="gr_action" value="save_rules" />

            <table class="gr-rules-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Field', 'gravity-recommender'); ?></th>
                        <th><?php esc_html_e('Answer contains', 'gravity-recommender'); ?></th>
                        <th><?php esc_html_e('Action', 'gravity-recommender'); ?></th>
                        <th><?php esc_html_e('Tag', 'gravity-recommender'); ?></th>
                        <th><?php esc_html_e('Points', 'gravity-recommender'); ?></th>
                        <th class="gr-col-remove"></th>
                    </tr>
                </thead>
                <tbody id="gr-rules-tbody">
                    <?php
                    $rows = !empty($rules) ? $rules : [['field_id' => '', 'match' => '', 'action' => 'boost', 'tag' => '', 'points' => 10]];
                    foreach ($rows as $i => $rule):
                        $this->render_rule_row($i, $rule, $field_choices, $available_tags);
                    endforeach;
                    ?>
                </tbody>
            </table>

            <p style="margin-top: 12px;">
                <button type="button" class="button" id="gr-add-rule">+ <?php esc_html_e('Add a rule', 'gravity-recommender'); ?></button>
            </p>

            <div class="gr-actions">
                <button type="submit" class="button button-primary"><?php esc_html_e('Save rules →', 'gravity-recommender'); ?></button>
            </div>
        </form>

        <template id="gr-rule-template">
            <?php $this->render_rule_row('__INDEX__', ['field_id' => '', 'match' => '', 'action' => 'boost', 'tag' => '', 'points' => 10], $field_choices, $available_tags); ?>
        </template>

        <script>
        (function () {
            let counter = <?php echo (int) count($rows); ?>;
            const tbody = document.getElementById('gr-rules-tbody');
            const template = document.getElementById('gr-rule-template');

            document.getElementById('gr-add-rule').addEventListener('click', function () {
                const html = template.innerHTML.replaceAll('__INDEX__', counter++);
                tbody.insertAdjacentHTML('beforeend', html);
            });

            tbody.addEventListener('click', function (e) {
                if (e.target.classList.contains('gr-remove-rule')) {
                    e.target.closest('tr').remove();
                }
            });
        })();
        </script>
        <?php
    }

    private function render_rule_row($index, array $rule, array $field_choices, array $available_tags): void {
        $name = 'gr_rules[' . esc_attr($index) . ']';
        ?>
        <tr>
            <td>
                <input type="text" name="<?php echo esc_attr($name); ?>[field_id]" value="<?php echo esc_attr($rule['field_id'] ?? ''); ?>" placeholder="e.g. 20" style="width: 80px;" />
            </td>
            <td>
                <input type="text" name="<?php echo esc_attr($name); ?>[match]" value="<?php echo esc_attr($rule['match'] ?? ''); ?>" placeholder="text or value to match" />
            </td>
            <td>
                <select name="<?php echo esc_attr($name); ?>[action]">
                    <option value="boost" <?php selected($rule['action'] ?? 'boost', 'boost'); ?>><?php esc_html_e('Boost', 'gravity-recommender'); ?></option>
                    <option value="exclude" <?php selected($rule['action'] ?? '', 'exclude'); ?>><?php esc_html_e('Exclude', 'gravity-recommender'); ?></option>
                    <option value="require" <?php selected($rule['action'] ?? '', 'require'); ?>><?php esc_html_e('Require', 'gravity-recommender'); ?></option>
                </select>
            </td>
            <td>
                <input type="text" name="<?php echo esc_attr($name); ?>[tag]" value="<?php echo esc_attr($rule['tag'] ?? ''); ?>" placeholder="tag name" list="gr-tag-list" />
                <datalist id="gr-tag-list">
                    <?php foreach ($available_tags as $t): ?>
                        <option value="<?php echo esc_attr($t); ?>"></option>
                    <?php endforeach; ?>
                </datalist>
            </td>
            <td>
                <input type="number" name="<?php echo esc_attr($name); ?>[points]" value="<?php echo esc_attr($rule['points'] ?? 10); ?>" min="0" step="1" style="width: 70px;" />
            </td>
            <td class="gr-col-remove">
                <button type="button" class="button-link gr-remove-rule" title="<?php esc_attr_e('Remove rule', 'gravity-recommender'); ?>">✕</button>
            </td>
        </tr>
        <?php
    }

    private function render_step_finish(): void {
        $config = (array) get_option(self::OPTION_FORM_CONFIG, []);
        $form_id = (int) ($config['form_id'] ?? 0);
        $field_id = (string) ($config['field_id'] ?? '');
        $shortcode = sprintf('[gravity_recommender form_id="%d" field_id="%s"]', $form_id, $field_id);
        ?>
        <h2><?php esc_html_e('🎉 You\'re ready', 'gravity-recommender'); ?></h2>
        <p><?php esc_html_e('Paste this shortcode into your Gravity Form confirmation message (Form settings → Confirmations → Text):', 'gravity-recommender'); ?></p>

        <pre style="background: #f6f7f7; padding: 14px; border: 1px solid #ddd; font-size: 14px;"><?php echo esc_html($shortcode); ?></pre>

        <h3><?php esc_html_e('Next steps', 'gravity-recommender'); ?></h3>
        <ul style="line-height: 1.8;">
            <li>📦 <a href="<?php echo esc_url(admin_url('edit.php?post_type=' . GR_Product_CPT::POST_TYPE)); ?>"><?php esc_html_e('Manage your products', 'gravity-recommender'); ?></a></li>
            <li>⚙️ <a href="<?php echo esc_url(admin_url('edit.php?post_type=' . GR_Product_CPT::POST_TYPE . '&page=' . self::PAGE_SLUG . '&step=4')); ?>"><?php esc_html_e('Adjust scoring rules', 'gravity-recommender'); ?></a></li>
            <li>🧪 <?php esc_html_e('Submit your form once and check the result page. Tweak rules if needed.', 'gravity-recommender'); ?></li>
        </ul>

        <form method="post" action="" style="margin-top: 24px;">
            <?php wp_nonce_field('gr_onboarding', 'gr_nonce'); ?>
            <input type="hidden" name="gr_action" value="reset" />
            <button type="submit" class="button"><?php esc_html_e('Restart setup', 'gravity-recommender'); ?></button>
        </form>
        <?php
    }

    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------

    private function get_form_field_choices(int $form_id): array {
        if (!$form_id || !class_exists('GFAPI')) {
            return [];
        }
        $form = GFAPI::get_form($form_id);
        if (!$form || empty($form['fields'])) {
            return [];
        }
        $choices = [];
        foreach ($form['fields'] as $field) {
            $choices[$field['id']] = $field['label'];
        }
        return $choices;
    }

    private function get_available_tags(): array {
        $source = GR_Product_Source_Factory::make();
        $products = $source->get_all_products();
        $tags = [];
        foreach ($products as $p) {
            foreach ($p['tags'] as $tag) {
                $tags[$tag] = true;
            }
        }
        $tags = array_keys($tags);
        sort($tags);
        return $tags;
    }

    private function parse_submitted_rules($input): array {
        if (!is_array($input)) {
            return [];
        }
        $rules = [];
        foreach ($input as $row) {
            if (!is_array($row)) {
                continue;
            }
            $field_id = isset($row['field_id']) ? sanitize_text_field(wp_unslash($row['field_id'])) : '';
            $tag      = isset($row['tag']) ? sanitize_text_field(wp_unslash($row['tag'])) : '';
            if ($field_id === '' || $tag === '') {
                continue;
            }
            $rules[] = [
                'field_id' => $field_id,
                'match'    => isset($row['match']) ? sanitize_text_field(wp_unslash($row['match'])) : '',
                'action'   => isset($row['action']) && in_array($row['action'], ['boost', 'exclude', 'require'], true)
                    ? $row['action']
                    : 'boost',
                'tag'      => strtolower(trim($tag)),
                'points'   => isset($row['points']) ? max(0, (int) $row['points']) : 10,
            ];
        }
        return $rules;
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
        // GF exports peuvent être au format "0" => form_data ou directement form_data
        $form_data = isset($data['0']) ? $data['0'] : $data;
        $result = GFAPI::add_form($form_data);
        return !is_wp_error($result);
    }
}
