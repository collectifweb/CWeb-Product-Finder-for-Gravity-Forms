<?php
/**
 * Admin page : Scoring Rules.
 *
 * Page dédiée pour gérer les règles de scoring, accessible depuis le menu
 * Recommender → Scoring Rules. Auto-détecte les champs du formulaire GF
 * configuré et les choix disponibles. Limite les champs sélectionnables
 * à ceux à réponse restreinte (radio, dropdown, checkbox, multiselect).
 *
 * Chaque règle se construit en 2 blocs :
 *   1. Conditions (1+) avec AND/OR
 *   2. Effets (1+) — boost / penalty / exclude / require sur un produit donné
 *
 * Stockage dans l'option `cwebpf_scoring_rules`.
 */

if (!defined('ABSPATH')) {
    exit;
}

class CWebPF_Admin_Rules {

    private const PAGE_SLUG = 'cwebpf-scoring-rules';
    private const OPTION    = 'cwebpf_scoring_rules';

    private const SUPPORTED_FIELD_TYPES = ['radio', 'select', 'checkbox', 'multiselect'];

    public function __construct() {
        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_post_cwebpf_save_rules', [$this, 'handle_save']);
    }

    public function register_menu(): void {
        add_submenu_page(
            'edit.php?post_type=' . CWebPF_Product_CPT::POST_TYPE,
            __('Scoring Rules', 'cweb-product-finder-for-gravity-forms'),
            __('Scoring Rules', 'cweb-product-finder-for-gravity-forms'),
            'manage_options',
            self::PAGE_SLUG,
            [$this, 'render_page']
        );
    }

    public function handle_save(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Permission denied.', 'cweb-product-finder-for-gravity-forms'));
        }
        check_admin_referer('cwebpf_save_rules');

        // Deep-unslash the whole rules array; per-field sanitization happens in parse_submitted_rules().
        $raw_rules = isset($_POST['cwebpf_rules']) && is_array($_POST['cwebpf_rules'])
            ? map_deep(wp_unslash($_POST['cwebpf_rules']), 'sanitize_text_field')
            : [];

        $rules = $this->parse_submitted_rules($raw_rules);
        update_option(self::OPTION, $rules);

        wp_safe_redirect(add_query_arg('saved', '1', admin_url('edit.php?post_type=' . CWebPF_Product_CPT::POST_TYPE . '&page=' . self::PAGE_SLUG)));
        exit;
    }

    public function render_page(): void {
        $form_id = (int) (get_option('cwebpf_form_config', [])['form_id'] ?? 0);
        $form    = $this->get_form($form_id);
        $fields  = $form ? $this->extract_restricted_fields($form) : [];
        $products = $this->get_products_for_select();
        $rules   = (array) get_option(self::OPTION, []);

        ?>
        <div class="wrap cwebpf-rules">
            <h1 class="wp-heading-inline"><?php esc_html_e('Scoring Rules', 'cweb-product-finder-for-gravity-forms'); ?></h1>

            <?php
            // Display-only success message after wp_safe_redirect from handle_save(). No state change → no nonce needed.
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            if (!empty($_GET['saved'])):
            ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Rules saved.', 'cweb-product-finder-for-gravity-forms'); ?></p></div>
            <?php endif; ?>

            <p class="description" style="max-width: 800px;">
                <?php esc_html_e('A rule says: when your visitor answers a form question in a specific way, then adjust the score of one or more products. Combine rules to express any selection logic — no code needed.', 'cweb-product-finder-for-gravity-forms'); ?>
            </p>

            <?php if (!$form_id || !$form): ?>
                <div class="notice notice-warning inline">
                    <p><?php
                        printf(
                            /* translators: %s is a link to the Setup page */
                            esc_html__('No Gravity Form is configured yet. %s first.', 'cweb-product-finder-for-gravity-forms'),
                            '<a href="' . esc_url(admin_url('edit.php?post_type=' . CWebPF_Product_CPT::POST_TYPE . '&page=cwebpf-onboarding')) . '">' . esc_html__('Complete the Setup wizard', 'cweb-product-finder-for-gravity-forms') . '</a>'
                        );
                    ?></p>
                </div>
                <?php return; ?>
            <?php endif; ?>

            <?php if (empty($products)): ?>
                <div class="notice notice-warning inline">
                    <p><?php
                        printf(
                            /* translators: %s is a link to "add product" */
                            esc_html__('You need at least one product before creating rules. %s.', 'cweb-product-finder-for-gravity-forms'),
                            '<a href="' . esc_url(admin_url('post-new.php?post_type=' . CWebPF_Product_CPT::POST_TYPE)) . '">' . esc_html__('Add a product', 'cweb-product-finder-for-gravity-forms') . '</a>'
                        );
                    ?></p>
                </div>
            <?php endif; ?>

            <?php if (empty($fields)): ?>
                <div class="notice notice-info inline">
                    <p><?php esc_html_e('No restricted-answer field found in the configured form. Scoring rules only work with radio, dropdown, or checkbox fields — not free-text inputs.', 'cweb-product-finder-for-gravity-forms'); ?></p>
                </div>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="cwebpf-rules-form">
                <input type="hidden" name="action" value="cwebpf_save_rules" />
                <?php wp_nonce_field('cwebpf_save_rules'); ?>

                <div id="cwebpf-rules-list" data-initial-count="<?php echo (int) count($rules); ?>">
                    <?php foreach ($rules as $i => $rule): ?>
                        <?php $this->render_rule_card($i, $rule, $fields, $products); ?>
                    <?php endforeach; ?>
                </div>

                <p>
                    <button type="button" class="button button-secondary" id="cwebpf-add-rule" <?php disabled(empty($fields) || empty($products)); ?>>
                        + <?php esc_html_e('Add a rule', 'cweb-product-finder-for-gravity-forms'); ?>
                    </button>
                </p>

                <p>
                    <button type="submit" class="button button-primary"><?php esc_html_e('Save all rules', 'cweb-product-finder-for-gravity-forms'); ?></button>
                </p>
            </form>

            <template id="cwebpf-rule-template"><?php $this->render_rule_card('__RIDX__', null, $fields, $products); ?></template>
            <template id="cwebpf-condition-template"><?php $this->render_condition_row('__RIDX__', '__CIDX__', null, $fields); ?></template>
            <template id="cwebpf-effect-template"><?php $this->render_effect_row('__RIDX__', '__EIDX__', null, $products); ?></template>
        </div>
        <?php
    }

    // ----------------------------------------------------------------
    // Rendering blocks
    // ----------------------------------------------------------------

    private function render_rule_card($ridx, ?array $rule, array $fields, array $products): void {
        $rule = is_array($rule) ? $rule : ['name' => '', 'condition_logic' => 'all', 'conditions' => [['field_id' => '', 'operator' => 'equals', 'value' => '']], 'effects' => [['action' => 'boost', 'product_id' => 0, 'points' => 10]]];
        ?>
        <div class="cwebpf-rule-card" data-ridx="<?php echo esc_attr((string) $ridx); ?>">
            <div class="cwebpf-rule-header">
                <input type="text"
                       name="cwebpf_rules[<?php echo esc_attr((string) $ridx); ?>][name]"
                       value="<?php echo esc_attr($rule['name'] ?? ''); ?>"
                       placeholder="<?php esc_attr_e('Rule label (for your own reference)', 'cweb-product-finder-for-gravity-forms'); ?>"
                       class="cwebpf-rule-name" />
                <button type="button" class="button-link cwebpf-remove-rule" title="<?php esc_attr_e('Remove rule', 'cweb-product-finder-for-gravity-forms'); ?>">✕</button>
            </div>

            <div class="cwebpf-rule-section">
                <div class="cwebpf-rule-section-title">
                    <strong><?php esc_html_e('When', 'cweb-product-finder-for-gravity-forms'); ?></strong>
                    <select name="cwebpf_rules[<?php echo esc_attr((string) $ridx); ?>][condition_logic]">
                        <option value="all" <?php selected($rule['condition_logic'] ?? 'all', 'all'); ?>><?php esc_html_e('all of these are true (AND)', 'cweb-product-finder-for-gravity-forms'); ?></option>
                        <option value="any" <?php selected($rule['condition_logic'] ?? '', 'any'); ?>><?php esc_html_e('any of these is true (OR)', 'cweb-product-finder-for-gravity-forms'); ?></option>
                    </select>
                </div>
                <div class="cwebpf-conditions">
                    <?php
                    $conditions = !empty($rule['conditions']) && is_array($rule['conditions']) ? $rule['conditions'] : [['field_id' => '', 'operator' => 'equals', 'value' => '']];
                    foreach ($conditions as $cidx => $cond) {
                        $this->render_condition_row($ridx, $cidx, $cond, $fields);
                    }
                    ?>
                </div>
                <p><button type="button" class="button-link cwebpf-add-condition">+ <?php esc_html_e('Add condition', 'cweb-product-finder-for-gravity-forms'); ?></button></p>
            </div>

            <div class="cwebpf-rule-section">
                <div class="cwebpf-rule-section-title"><strong><?php esc_html_e('Then', 'cweb-product-finder-for-gravity-forms'); ?></strong></div>
                <div class="cwebpf-effects">
                    <?php
                    $effects = !empty($rule['effects']) && is_array($rule['effects']) ? $rule['effects'] : [['action' => 'boost', 'product_id' => 0, 'points' => 10]];
                    foreach ($effects as $eidx => $effect) {
                        $this->render_effect_row($ridx, $eidx, $effect, $products);
                    }
                    ?>
                </div>
                <p><button type="button" class="button-link cwebpf-add-effect">+ <?php esc_html_e('Add another effect', 'cweb-product-finder-for-gravity-forms'); ?></button></p>
            </div>
        </div>
        <?php
    }

    private function render_condition_row($ridx, $cidx, ?array $cond, array $fields): void {
        $cond = is_array($cond) ? $cond : ['field_id' => '', 'operator' => 'equals', 'value' => ''];
        $name = "cwebpf_rules[" . $ridx . "][conditions][" . $cidx . "]";
        $current_field_id = (string) ($cond['field_id'] ?? '');
        $current_value    = (string) ($cond['value'] ?? '');
        ?>
        <div class="cwebpf-condition-row" data-cidx="<?php echo esc_attr((string) $cidx); ?>">
            <select name="<?php echo esc_attr($name . '[field_id]'); ?>" class="cwebpf-cond-field">
                <option value=""><?php esc_html_e('— Select field —', 'cweb-product-finder-for-gravity-forms'); ?></option>
                <?php foreach ($fields as $field): ?>
                    <option
                        value="<?php echo esc_attr($field['id']); ?>"
                        data-choices='<?php echo esc_attr(wp_json_encode($field['choices'])); ?>'
                        <?php selected($current_field_id, $field['id']); ?>>
                        #<?php echo esc_html($field['id']); ?> — <?php echo esc_html($field['label']); ?> (<?php echo esc_html($field['type']); ?>)
                    </option>
                <?php endforeach; ?>
            </select>

            <select name="<?php echo esc_attr($name . '[operator]'); ?>" class="cwebpf-cond-operator">
                <option value="equals"     <?php selected($cond['operator'] ?? 'equals', 'equals'); ?>><?php esc_html_e('is', 'cweb-product-finder-for-gravity-forms'); ?></option>
                <option value="not_equals" <?php selected($cond['operator'] ?? '', 'not_equals'); ?>><?php esc_html_e('is not', 'cweb-product-finder-for-gravity-forms'); ?></option>
                <option value="contains"   <?php selected($cond['operator'] ?? '', 'contains'); ?>><?php esc_html_e('contains', 'cweb-product-finder-for-gravity-forms'); ?></option>
                <option value="not_empty"  <?php selected($cond['operator'] ?? '', 'not_empty'); ?>><?php esc_html_e('is filled', 'cweb-product-finder-for-gravity-forms'); ?></option>
            </select>

            <select name="<?php echo esc_attr($name . '[value]'); ?>" class="cwebpf-cond-value">
                <option value=""><?php esc_html_e('— pick a choice —', 'cweb-product-finder-for-gravity-forms'); ?></option>
                <?php
                // Pré-remplit avec les choix du field sélectionné (si connu).
                $choices = [];
                foreach ($fields as $field) {
                    if ((string) $field['id'] === $current_field_id) {
                        $choices = $field['choices'];
                        break;
                    }
                }
                foreach ($choices as $choice):
                ?>
                    <option value="<?php echo esc_attr($choice); ?>" <?php selected($current_value, $choice); ?>><?php echo esc_html($choice); ?></option>
                <?php endforeach; ?>
                <?php if ($current_value !== '' && !in_array($current_value, $choices, true)): ?>
                    <option value="<?php echo esc_attr($current_value); ?>" selected><?php echo esc_html($current_value); ?> <?php esc_html_e('(custom)', 'cweb-product-finder-for-gravity-forms'); ?></option>
                <?php endif; ?>
            </select>

            <button type="button" class="button-link cwebpf-remove-condition" title="<?php esc_attr_e('Remove condition', 'cweb-product-finder-for-gravity-forms'); ?>">✕</button>
        </div>
        <?php
    }

    private function render_effect_row($ridx, $eidx, ?array $effect, array $products): void {
        $effect = is_array($effect) ? $effect : ['action' => 'boost', 'product_id' => 0, 'points' => 10];
        $name = "cwebpf_rules[" . $ridx . "][effects][" . $eidx . "]";
        $action = (string) ($effect['action'] ?? 'boost');
        ?>
        <div class="cwebpf-effect-row" data-eidx="<?php echo esc_attr((string) $eidx); ?>">
            <select name="<?php echo esc_attr($name . '[action]'); ?>" class="cwebpf-effect-action">
                <option value="boost"   <?php selected($action, 'boost'); ?>><?php esc_html_e('+ Boost', 'cweb-product-finder-for-gravity-forms'); ?></option>
                <option value="penalty" <?php selected($action, 'penalty'); ?>><?php esc_html_e('− Penalize', 'cweb-product-finder-for-gravity-forms'); ?></option>
                <option value="exclude" <?php selected($action, 'exclude'); ?>><?php esc_html_e('🚫 Exclude', 'cweb-product-finder-for-gravity-forms'); ?></option>
                <option value="require" <?php selected($action, 'require'); ?>><?php esc_html_e('✅ Require', 'cweb-product-finder-for-gravity-forms'); ?></option>
            </select>

            <select name="<?php echo esc_attr($name . '[product_id]'); ?>" class="cwebpf-effect-product">
                <option value="0"><?php esc_html_e('— Select product —', 'cweb-product-finder-for-gravity-forms'); ?></option>
                <?php foreach ($products as $p): ?>
                    <option value="<?php echo (int) $p['id']; ?>" <?php selected((int) ($effect['product_id'] ?? 0), (int) $p['id']); ?>>
                        <?php echo esc_html($p['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <span class="cwebpf-effect-points-wrap" <?php echo in_array($action, ['exclude', 'require'], true) ? 'style="display:none;"' : ''; ?>>
                <?php esc_html_e('by', 'cweb-product-finder-for-gravity-forms'); ?>
                <input type="number" name="<?php echo esc_attr($name . '[points]'); ?>" value="<?php echo esc_attr((string) ($effect['points'] ?? 10)); ?>" min="0" step="1" style="width: 70px;" />
                <?php esc_html_e('points', 'cweb-product-finder-for-gravity-forms'); ?>
            </span>

            <button type="button" class="button-link cwebpf-remove-effect" title="<?php esc_attr_e('Remove effect', 'cweb-product-finder-for-gravity-forms'); ?>">✕</button>
        </div>
        <?php
    }

    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------

    private function get_form(int $form_id): ?array {
        if (!$form_id || !class_exists('GFAPI')) {
            return null;
        }
        $form = GFAPI::get_form($form_id);
        return $form ?: null;
    }

    /**
     * Extrait les champs à réponse restreinte (radio/select/checkbox/multiselect)
     * avec leurs choix possibles.
     */
    private function extract_restricted_fields(array $form): array {
        $result = [];
        foreach ($form['fields'] ?? [] as $field) {
            if (!is_array($field)) {
                if (is_object($field) && method_exists($field, 'toArray')) {
                    $field = $field->toArray();
                } elseif (is_object($field)) {
                    $field = (array) $field;
                } else {
                    continue;
                }
            }
            $type = (string) ($field['type'] ?? '');
            if (!in_array($type, self::SUPPORTED_FIELD_TYPES, true)) {
                continue;
            }
            $choices = [];
            foreach (($field['choices'] ?? []) as $choice) {
                if (is_array($choice) && isset($choice['value'])) {
                    $choices[] = (string) $choice['value'];
                } elseif (is_array($choice) && isset($choice['text'])) {
                    $choices[] = (string) $choice['text'];
                }
            }
            $result[] = [
                'id'      => (string) $field['id'],
                'label'   => (string) ($field['label'] ?? ''),
                'type'    => $type,
                'choices' => $choices,
            ];
        }
        return $result;
    }

    private function get_products_for_select(): array {
        $source = CWebPF_Product_Source_Factory::make();
        $products = $source->get_all_products();
        $rows = [];
        foreach ($products as $p) {
            $rows[] = ['id' => (int) $p['id'], 'name' => (string) $p['name']];
        }
        return $rows;
    }

    private function parse_submitted_rules($input): array {
        if (!is_array($input)) {
            return [];
        }
        $rules = [];
        foreach ($input as $rule_in) {
            if (!is_array($rule_in)) {
                continue;
            }

            $conditions = [];
            foreach ((array) ($rule_in['conditions'] ?? []) as $c) {
                if (!is_array($c)) {
                    continue;
                }
                $field_id = sanitize_text_field(wp_unslash((string) ($c['field_id'] ?? '')));
                $operator = (string) ($c['operator'] ?? 'equals');
                if (!in_array($operator, ['equals', 'not_equals', 'contains', 'not_empty'], true)) {
                    $operator = 'equals';
                }
                $value = sanitize_text_field(wp_unslash((string) ($c['value'] ?? '')));
                if ($field_id === '') {
                    continue;
                }
                $conditions[] = ['field_id' => $field_id, 'operator' => $operator, 'value' => $value];
            }

            $effects = [];
            foreach ((array) ($rule_in['effects'] ?? []) as $e) {
                if (!is_array($e)) {
                    continue;
                }
                $action = (string) ($e['action'] ?? 'boost');
                if (!in_array($action, ['boost', 'penalty', 'exclude', 'require'], true)) {
                    $action = 'boost';
                }
                $product_id = (int) ($e['product_id'] ?? 0);
                if ($product_id <= 0) {
                    continue;
                }
                $effects[] = [
                    'action'     => $action,
                    'product_id' => $product_id,
                    'points'     => max(0, (int) ($e['points'] ?? 0)),
                ];
            }

            if (empty($conditions) || empty($effects)) {
                continue;
            }

            $rules[] = [
                'name'            => sanitize_text_field(wp_unslash((string) ($rule_in['name'] ?? ''))),
                'condition_logic' => in_array($rule_in['condition_logic'] ?? 'all', ['all', 'any'], true) ? $rule_in['condition_logic'] : 'all',
                'conditions'      => $conditions,
                'effects'         => $effects,
            ];
        }
        return $rules;
    }

}
