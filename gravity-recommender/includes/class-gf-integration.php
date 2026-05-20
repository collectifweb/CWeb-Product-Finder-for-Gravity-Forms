<?php
/**
 * Intégration avec Gravity Forms.
 *
 * Hooks :
 *   1. gform_entry_post_save  → calcule la recommandation et écrit le JSON
 *                                dans le champ caché configuré.
 *   2. gform_confirmation     → injecte entry_id dans le shortcode et remplace
 *                                un éventuel placeholder field_id="XX".
 *
 * Le form_id et le field_id sont configurés via l'option `gr_form_config`
 * (gérée par l'onboarding) et restent surchargeables via filtre.
 */

if (!defined('ABSPATH')) {
    exit;
}

class GR_GF_Integration {

    public function __construct() {
        add_filter('gform_entry_post_save', [$this, 'process_entry'], 10, 2);
        add_filter('gform_confirmation', [$this, 'customize_confirmation'], 10, 4);
    }

    private function form_id(): int {
        $config = (array) get_option('gr_form_config', []);
        $form_id = (int) ($config['form_id'] ?? 0);
        return (int) apply_filters('gr_form_id', $form_id);
    }

    private function field_id(): string {
        $config = (array) get_option('gr_form_config', []);
        $field_id = (string) ($config['field_id'] ?? '');
        return (string) apply_filters('gr_field_id', $field_id);
    }

    public function process_entry(array $entry, array $form): array {
        $form_id = $this->form_id();
        $field_id = $this->field_id();

        if ($form_id === 0 || $field_id === '' || (int) $form['id'] !== $form_id) {
            return $entry;
        }

        $result = GR_Recommendation_Engine::recommend($entry);
        $json = wp_json_encode($result, JSON_UNESCAPED_UNICODE);

        if (class_exists('GFAPI')) {
            GFAPI::update_entry_field($entry['id'], $field_id, $json);
        }
        $entry[$field_id] = $json;

        return $entry;
    }

    public function customize_confirmation($confirmation, $form, $entry, $ajax) {
        $form_id = $this->form_id();
        if ($form_id === 0 || (int) $form['id'] !== $form_id || !is_string($confirmation)) {
            return $confirmation;
        }

        $field_id = $this->field_id();
        if ($field_id !== '') {
            $confirmation = str_replace('field_id="XX"', 'field_id="' . $field_id . '"', $confirmation);
        }

        if (str_contains($confirmation, 'gravity_recommender')) {
            $entry_id = (int) $entry['id'];
            $confirmation = str_replace(
                '[gravity_recommender',
                '[gravity_recommender entry_id="' . $entry_id . '"',
                $confirmation
            );
        }

        return $confirmation;
    }
}
