<?php
/**
 * Moteur de recommandation à effets directs sur produits.
 *
 * Une règle est un couple (conditions, effects) :
 *
 *   - Les **conditions** lisent les réponses du formulaire et décident si la
 *     règle s'applique. Combinées en AND (`condition_logic = 'all'`) ou
 *     en OR (`condition_logic = 'any'`).
 *
 *   - Les **effets** modifient le score d'un ou plusieurs produits :
 *       boost    → +N points
 *       penalty  → -N points
 *       exclude  → produit retiré des candidats
 *       require  → seuls les produits "require" sont éligibles
 *
 * Format stocké dans l'option `gr_scoring_rules` :
 *
 *   [
 *     [
 *       'id'              => 'rule_xxx',
 *       'name'            => 'Description (optionnel)',
 *       'condition_logic' => 'all' | 'any',
 *       'conditions'      => [
 *           ['field_id' => '12', 'operator' => 'equals', 'value' => 'Yes'],
 *           ...
 *       ],
 *       'effects' => [
 *           ['action' => 'boost',   'product_id' => 42, 'points' => 20],
 *           ['action' => 'penalty', 'product_id' => 17, 'points' => 10],
 *           ['action' => 'exclude', 'product_id' => 99, 'points' => 0],
 *           ['action' => 'require', 'product_id' => 42, 'points' => 0],
 *       ],
 *     ],
 *     ...
 *   ]
 *
 * Opérateurs supportés :
 *   - 'equals'      : réponse égale (case-insensitive)
 *   - 'not_equals'  : réponse différente
 *   - 'contains'    : sous-chaîne présente (case-insensitive)
 *   - 'not_empty'   : réponse non vide
 */

if (!defined('ABSPATH')) {
    exit;
}

class GR_Recommendation_Engine {

    private GR_Product_Source $source;
    private array $rules;

    public static function recommend(array $entry): array {
        $source = GR_Product_Source_Factory::make();
        $rules  = (array) get_option('gr_scoring_rules', []);
        return (new self($source, $rules))->calculate($entry);
    }

    public function __construct(GR_Product_Source $source, array $rules) {
        $this->source = $source;
        $this->rules  = $rules;
    }

    public function calculate(array $entry): array {
        $products = $this->source->get_all_products();

        if (empty($products)) {
            return $this->build_empty_result(__('No products are configured yet.', 'gravity-recommender'));
        }

        $scores = [];
        foreach ($products as $p) {
            $scores[$p['id']] = 0;
        }

        $excluded = [];
        $required = [];
        $applied_rules = [];

        foreach ($this->rules as $rule) {
            $rule = $this->normalize_rule($rule);
            if (!$rule) {
                continue;
            }
            if (!$this->rule_matches($rule, $entry)) {
                continue;
            }

            $applied_rules[] = $rule;

            foreach ($rule['effects'] as $effect) {
                if (!is_array($effect)) {
                    continue;
                }
                $pid = (int) ($effect['product_id'] ?? 0);
                if (!isset($scores[$pid])) {
                    continue;
                }
                $action = (string) ($effect['action'] ?? '');
                $points = (int) ($effect['points'] ?? 0);

                switch ($action) {
                    case 'boost':   $scores[$pid] += $points; break;
                    case 'penalty': $scores[$pid] -= $points; break;
                    case 'exclude': $excluded[$pid] = true;   break;
                    case 'require': $required[$pid] = true;   break;
                }
            }
        }

        $eligible_ids = array_diff(array_keys($scores), array_keys($excluded));

        if (!empty($required)) {
            $eligible_ids = array_intersect($eligible_ids, array_keys($required));
        }

        $eligible_ids = array_values($eligible_ids);

        if (empty($eligible_ids)) {
            $ai_result = apply_filters('gr_ai_fallback_recommendation', null, $entry, $products);
            if (is_array($ai_result) && isset($ai_result['recommended_product_id'])) {
                return $ai_result;
            }
            return $this->build_empty_result(__('No product matched your answers. Please contact us for help.', 'gravity-recommender'));
        }

        usort($eligible_ids, function ($a, $b) use ($scores) {
            if ($scores[$a] !== $scores[$b]) {
                return $scores[$b] - $scores[$a];
            }
            return $a - $b;
        });

        $recommended = $eligible_ids[0];
        $alternatives = array_slice($eligible_ids, 1, 2);

        return [
            'recommended_product_id' => $recommended,
            'alternative_products'   => $alternatives,
            'explanation'            => $this->build_explanation($recommended, $applied_rules),
        ];
    }

    private function build_empty_result(string $explanation): array {
        return [
            'recommended_product_id' => 0,
            'alternative_products'   => [],
            'explanation'            => $explanation,
        ];
    }

    private function normalize_rule($rule): ?array {
        if (!is_array($rule)) {
            return null;
        }
        $rule['condition_logic'] = in_array($rule['condition_logic'] ?? 'all', ['all', 'any'], true)
            ? $rule['condition_logic']
            : 'all';
        $rule['conditions'] = is_array($rule['conditions'] ?? null) ? $rule['conditions'] : [];
        $rule['effects']    = is_array($rule['effects'] ?? null)    ? $rule['effects']    : [];

        if (empty($rule['conditions']) || empty($rule['effects'])) {
            return null;
        }
        return $rule;
    }

    private function rule_matches(array $rule, array $entry): bool {
        $results = [];
        foreach ($rule['conditions'] as $condition) {
            $results[] = $this->condition_matches($condition, $entry);
        }
        return $rule['condition_logic'] === 'any'
            ? in_array(true, $results, true)
            : !in_array(false, $results, true);
    }

    private function condition_matches($condition, array $entry): bool {
        if (!is_array($condition)) {
            return false;
        }
        $field_id = (string) ($condition['field_id'] ?? '');
        $operator = (string) ($condition['operator'] ?? 'equals');
        $value    = (string) ($condition['value'] ?? '');

        if ($field_id === '') {
            return false;
        }

        $answer = $this->get_entry_value($entry, $field_id);

        switch ($operator) {
            case 'equals':
                return strcasecmp($answer, $value) === 0
                    || $this->any_checkbox_value_equals($entry, $field_id, $value);
            case 'not_equals':
                return strcasecmp($answer, $value) !== 0
                    && !$this->any_checkbox_value_equals($entry, $field_id, $value);
            case 'contains':
                if ($value === '') {
                    return $answer !== '';
                }
                return stripos($answer, $value) !== false;
            case 'not_empty':
                return $answer !== '';
            default:
                return false;
        }
    }

    private function get_entry_value(array $entry, string $field_id): string {
        if (isset($entry[$field_id]) && $entry[$field_id] !== '') {
            return is_array($entry[$field_id]) ? implode(' ', $entry[$field_id]) : (string) $entry[$field_id];
        }
        $parts = [];
        foreach ($entry as $key => $val) {
            if (is_string($key) && str_starts_with($key, $field_id . '.') && $val !== '') {
                $parts[] = is_array($val) ? implode(' ', $val) : (string) $val;
            }
        }
        return implode(' ', $parts);
    }

    private function any_checkbox_value_equals(array $entry, string $field_id, string $value): bool {
        foreach ($entry as $key => $val) {
            if (is_string($key) && str_starts_with($key, $field_id . '.') && is_string($val)) {
                if (strcasecmp($val, $value) === 0) {
                    return true;
                }
            }
        }
        return false;
    }

    private function build_explanation(int $product_id, array $applied_rules): string {
        $product = $this->source->get_product($product_id);
        $name = $product['name'] ?? '';

        $custom = apply_filters('gr_recommendation_explanation', '', $product, $applied_rules);
        if (is_string($custom) && $custom !== '') {
            return $custom;
        }

        return sprintf(
            /* translators: %s is a product name */
            __('Based on your answers, we recommend the %s.', 'gravity-recommender'),
            $name
        );
    }
}
