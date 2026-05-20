<?php
/**
 * Moteur de recommandation par tags.
 *
 * Principe :
 *   - Chaque produit a une liste de tags libres ("ecommerce", "high-traffic", ...).
 *   - L'admin définit des règles : « si la réponse à un champ GF contient X,
 *     alors booster/exclure/requérir les produits taggés Y ».
 *   - Le moteur applique chaque règle aux réponses, additionne les scores,
 *     trie, et retourne les meilleurs.
 *
 * Format d'une règle :
 *   [
 *     'field_id'  => '20',           // ID du champ GF (string pour compat avec sous-champs)
 *     'match'     => '20000',        // sous-chaîne à matcher dans la réponse (case-insensitive)
 *     'tag'       => 'high-traffic', // tag produit ciblé
 *     'action'    => 'boost',        // boost | exclude | require
 *     'points'    => 20,             // utilisé seulement pour action=boost
 *   ]
 *
 * Stockées dans l'option `gr_scoring_rules` (array de règles).
 */

if (!defined('ABSPATH')) {
    exit;
}

class GR_Recommendation_Engine {

    private const REQUIRE_BONUS = 1000;
    private const EXCLUDE_PENALTY = 10000;

    private GR_Product_Source $source;
    private array $rules;

    /**
     * Point d'entrée statique.
     *
     * @param array $entry  Entrée Gravity Forms (clés = field IDs).
     * @return array        [recommended_product_id, alternative_products, explanation]
     */
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
            return [
                'recommended_product_id' => 0,
                'alternative_products'   => [],
                'explanation'            => __('No products are configured yet.', 'gravity-recommender'),
            ];
        }

        // Score de départ pour chaque produit.
        $scores = [];
        foreach ($products as $p) {
            $scores[$p['id']] = 0;
        }

        $required_tags_per_rule = [];
        $excluded_products      = [];
        $matched_tags           = [];

        foreach ($this->rules as $rule) {
            $rule = $this->normalize_rule($rule);
            if (!$rule) {
                continue;
            }

            $answer = $this->get_entry_value($entry, $rule['field_id']);
            if (!$this->answer_matches($answer, $rule['match'])) {
                continue;
            }

            $matched_tags[] = $rule['tag'];

            foreach ($products as $p) {
                $has_tag = in_array($rule['tag'], $p['tags'], true);

                if ($rule['action'] === 'boost' && $has_tag) {
                    $scores[$p['id']] += $rule['points'];
                } elseif ($rule['action'] === 'exclude' && $has_tag) {
                    $scores[$p['id']] -= self::EXCLUDE_PENALTY;
                    $excluded_products[$p['id']] = true;
                } elseif ($rule['action'] === 'require' && !$has_tag) {
                    $scores[$p['id']] -= self::REQUIRE_BONUS;
                } elseif ($rule['action'] === 'require' && $has_tag) {
                    $scores[$p['id']] += self::REQUIRE_BONUS;
                }
            }
        }

        // Tri par score décroissant.
        arsort($scores);

        $viable = [];
        foreach ($scores as $id => $score) {
            if (isset($excluded_products[$id])) {
                continue;
            }
            $viable[] = ['id' => $id, 'score' => $score];
        }

        if (empty($viable)) {
            return [
                'recommended_product_id' => 0,
                'alternative_products'   => [],
                'explanation'            => __('No product matched your answers. Please contact us for help.', 'gravity-recommender'),
            ];
        }

        $recommended = $viable[0]['id'];
        $alternatives = array_slice(array_column($viable, 'id'), 1, 2);

        return [
            'recommended_product_id' => $recommended,
            'alternative_products'   => $alternatives,
            'explanation'            => $this->build_explanation($recommended, $matched_tags),
        ];
    }

    /**
     * Normalise et valide une règle (retourne null si invalide).
     */
    private function normalize_rule(array $rule): ?array {
        $field_id = isset($rule['field_id']) ? (string) $rule['field_id'] : '';
        $match    = isset($rule['match']) ? (string) $rule['match'] : '';
        $tag      = isset($rule['tag']) ? strtolower(trim((string) $rule['tag'])) : '';
        $action   = isset($rule['action']) ? (string) $rule['action'] : 'boost';
        $points   = isset($rule['points']) ? (int) $rule['points'] : 10;

        if ($field_id === '' || $tag === '') {
            return null;
        }

        if (!in_array($action, ['boost', 'exclude', 'require'], true)) {
            $action = 'boost';
        }

        return compact('field_id', 'match', 'tag', 'action', 'points');
    }

    /**
     * Récupère la valeur d'un champ GF. Concatène les sous-champs (checkboxes) si besoin.
     */
    private function get_entry_value(array $entry, string $field_id): string {
        // Valeur directe.
        if (isset($entry[$field_id]) && $entry[$field_id] !== '') {
            return is_array($entry[$field_id]) ? implode(' ', $entry[$field_id]) : (string) $entry[$field_id];
        }

        // Sous-champs (checkboxes GF utilisent "fieldid.1", "fieldid.2", ...).
        $parts = [];
        foreach ($entry as $key => $value) {
            if (is_string($key) && str_starts_with($key, $field_id . '.') && $value !== '') {
                $parts[] = is_array($value) ? implode(' ', $value) : (string) $value;
            }
        }
        return implode(' ', $parts);
    }

    private function answer_matches(string $answer, string $needle): bool {
        if ($needle === '') {
            // Une règle sans needle matche dès qu'il y a une réponse non vide.
            return $answer !== '';
        }
        return stripos($answer, $needle) !== false;
    }

    private function build_explanation(int $product_id, array $matched_tags): string {
        $product = $this->source->get_product($product_id);
        if (!$product) {
            return '';
        }

        $name = $product['name'];

        // Permet à l'utilisateur de fournir une explication personnalisée via filtre.
        $custom = apply_filters('gr_recommendation_explanation', '', $product, array_unique($matched_tags));
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
