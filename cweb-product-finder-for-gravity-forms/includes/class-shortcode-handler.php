<?php
/**
 * Shortcode [cwebpf_recommender]
 *
 * Lit le JSON sauvé dans le champ caché Gravity Forms et affiche
 * les cartes produits via la source de produits configurée.
 *
 * Usage dans la confirmation GF :
 *   [cwebpf_recommender form_id="5" field_id="44"]
 *   [cwebpf_recommender entry_id="123" form_id="5" field_id="44"]
 */

if (!defined('ABSPATH')) {
    exit;
}

class CWebPF_Shortcode_Handler {

    public function __construct() {
        add_shortcode('cwebpf_recommender', [$this, 'render_shortcode']);
    }

    public function render_shortcode($atts): string {
        $config = (array) get_option('cwebpf_form_config', []);

        $atts = shortcode_atts([
            'form_id'  => isset($config['form_id']) ? (string) $config['form_id'] : '',
            'field_id' => isset($config['field_id']) ? (string) $config['field_id'] : '',
            'entry_id' => '',
        ], $atts);

        $json = $this->get_stored_result($atts);
        if (!$json) {
            return $this->render_error_message();
        }

        $data = $this->parse_json($json);
        if (!$data) {
            return $this->render_error_message();
        }

        return $this->render_product_cards($data);
    }

    private function get_stored_result(array $atts): ?string {
        if (!class_exists('GFAPI')) {
            return null;
        }

        $field_id = (string) $atts['field_id'];
        if ($field_id === '') {
            return null;
        }

        if (!empty($atts['entry_id'])) {
            $entry = GFAPI::get_entry((int) $atts['entry_id']);
            if (is_wp_error($entry)) {
                return null;
            }
            return !empty($entry[$field_id]) ? (string) $entry[$field_id] : null;
        }

        if (empty($atts['form_id'])) {
            return null;
        }

        $entries = GFAPI::get_entries(
            (int) $atts['form_id'],
            ['status' => 'active'],
            ['key' => 'date_created', 'direction' => 'DESC'],
            ['offset' => 0, 'page_size' => 1]
        );

        if (empty($entries)) {
            return null;
        }

        return !empty($entries[0][$field_id]) ? (string) $entries[0][$field_id] : null;
    }

    private function parse_json(string $json_string): ?array {
        $clean = trim($json_string);

        if (preg_match('/```(?:json)?\s*(\{.*?\})\s*```/s', $clean, $matches)) {
            $clean = $matches[1];
        }

        $data = json_decode($clean, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        if (!isset($data['recommended_product_id'])) {
            return null;
        }

        if (!isset($data['alternative_products']) || !is_array($data['alternative_products'])) {
            $data['alternative_products'] = [];
        }

        return $data;
    }

    private function render_product_cards(array $data): string {
        $recommended_id = (int) $data['recommended_product_id'];
        $alternative_ids = array_map('intval', $data['alternative_products']);
        $explanation = (string) ($data['explanation'] ?? '');

        $all_ids = array_unique(array_merge([$recommended_id], $alternative_ids));
        $all_ids = array_filter(array_slice($all_ids, 0, 3));

        $source = CWebPF_Product_Source_Factory::make();
        $products = $source->get_products($all_ids);

        if (empty($products)) {
            return $this->render_error_message();
        }

        ob_start();
        ?>
        <div class="cwebpf-recommendations-wrapper">
            <?php if ($explanation): ?>
                <div class="cwebpf-explanation">
                    <p><?php echo esc_html($explanation); ?></p>
                </div>
            <?php endif; ?>

            <div class="cwebpf-products-grid">
                <?php foreach ($products as $index => $product): ?>
                    <?php $this->render_single_card($product, $index === 0); ?>
                <?php endforeach; ?>
            </div>

            <?php
            $disclaimer = (string) apply_filters('cwebpf_card_disclaimer', '');
            if ($disclaimer !== ''):
            ?>
                <div class="cwebpf-disclaimer">
                    <p><?php echo esc_html($disclaimer); ?></p>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    private function render_single_card(array $product, bool $is_featured): void {
        $card_class = $is_featured
            ? 'cwebpf-product-card cwebpf-product-card--featured'
            : 'cwebpf-product-card';

        $cta_label = $product['cta_label'] !== '' ? $product['cta_label'] : __('Add to cart', 'cweb-product-finder-for-gravity-forms');
        $cta_url = $product['payment_url'] !== '' ? $product['payment_url'] : ($product['page_url'] !== '' ? $product['page_url'] : '#');
        ?>
        <div class="<?php echo esc_attr($card_class); ?>">
            <?php if ($is_featured): ?>
                <div class="cwebpf-badge"><?php esc_html_e('Our recommendation', 'cweb-product-finder-for-gravity-forms'); ?></div>
            <?php endif; ?>

            <div class="cwebpf-product-content">
                <h3 class="cwebpf-product-title"><?php echo esc_html($product['name']); ?></h3>

                <?php if ($product['price_label'] !== ''): ?>
                    <div class="cwebpf-product-price">
                        <span class="cwebpf-price-amount"><?php echo esc_html($product['price_label']); ?></span>
                    </div>
                <?php endif; ?>

                <?php if (!empty($product['features'])): ?>
                    <ul class="cwebpf-product-features">
                        <?php foreach ($product['features'] as $feature): ?>
                            <li><?php echo esc_html($feature); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <?php if ($product['description'] !== ''): ?>
                    <div class="cwebpf-product-description">
                        <p><?php echo esc_html($product['description']); ?></p>
                    </div>
                <?php endif; ?>

                <a href="<?php echo esc_url($cta_url); ?>" class="cwebpf-product-button" target="_blank" rel="noopener">
                    <?php echo esc_html($cta_label); ?>
                </a>

                <?php if ($product['page_url'] !== '' && $product['page_url'] !== $cta_url): ?>
                    <a href="<?php echo esc_url($product['page_url']); ?>" class="cwebpf-product-details-link" target="_blank" rel="noopener">
                        <?php esc_html_e('See details', 'cweb-product-finder-for-gravity-forms'); ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    private function render_error_message(): string {
        $fallback_url = (string) apply_filters('cwebpf_fallback_contact_url', '');

        ob_start();
        ?>
        <div class="cwebpf-error-message">
            <h3><?php esc_html_e('We could not generate a recommendation', 'cweb-product-finder-for-gravity-forms'); ?></h3>
            <p><?php esc_html_e('Our team will gladly help you choose the right product.', 'cweb-product-finder-for-gravity-forms'); ?></p>
            <?php if ($fallback_url !== ''): ?>
                <a href="<?php echo esc_url($fallback_url); ?>" class="cwebpf-contact-button">
                    <?php esc_html_e('Contact us', 'cweb-product-finder-for-gravity-forms'); ?>
                </a>
            <?php endif; ?>
        </div>
        <?php
        return (string) ob_get_clean();
    }
}
