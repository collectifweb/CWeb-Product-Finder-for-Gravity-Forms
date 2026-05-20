<?php
/**
 * Custom Post Type `gr_product` — produits recommandables natifs au plugin.
 *
 * Les champs métier sont stockés en post-meta :
 *   - _gr_price_label  : libellé prix affiché (texte libre, ex. "12$/mois")
 *   - _gr_features     : liste de features, une par ligne
 *   - _gr_description  : description courte
 *   - _gr_page_url     : URL de la fiche produit
 *   - _gr_payment_url  : URL de paiement / panier
 *   - _gr_cta_label    : libellé du bouton (défaut "Add to cart")
 *   - _gr_tags         : tags libres, séparés par virgules (ex. "ecommerce, high-traffic, cheap")
 *
 * Ces tags sont matchés contre les règles définies dans l'admin :
 * « si la réponse X au champ GF #N, alors booster (ou exclure) les produits taggés Y ».
 */

if (!defined('ABSPATH')) {
    exit;
}

class GR_Product_CPT {

    public const POST_TYPE = 'gr_product';

    public function __construct() {
        add_action('init', [$this, 'register_post_type']);
        add_action('add_meta_boxes', [$this, 'register_meta_boxes']);
        add_action('save_post_' . self::POST_TYPE, [$this, 'save_meta'], 10, 2);
    }

    public function register_post_type(): void {
        register_post_type(self::POST_TYPE, [
            'labels' => [
                'name'          => __('Recommended Products', 'gravity-recommender'),
                'singular_name' => __('Recommended Product', 'gravity-recommender'),
                'add_new'       => __('Add new', 'gravity-recommender'),
                'add_new_item'  => __('Add new product', 'gravity-recommender'),
                'edit_item'     => __('Edit product', 'gravity-recommender'),
                'new_item'      => __('New product', 'gravity-recommender'),
                'view_item'     => __('View product', 'gravity-recommender'),
                'search_items'  => __('Search products', 'gravity-recommender'),
                'menu_name'     => __('Recommender', 'gravity-recommender'),
            ],
            'public'          => false,
            'show_ui'         => true,
            'show_in_menu'    => true,
            'menu_icon'       => 'dashicons-awards',
            'menu_position'   => 30,
            'supports'        => ['title', 'thumbnail', 'page-attributes'],
            'capability_type' => 'post',
            'has_archive'     => false,
            'rewrite'         => false,
            'show_in_rest'    => false,
            'hierarchical'    => false,
        ]);
    }

    public function register_meta_boxes(): void {
        add_meta_box(
            'gr_product_attributes',
            __('Product attributes', 'gravity-recommender'),
            [$this, 'render_meta_box'],
            self::POST_TYPE,
            'normal',
            'high'
        );
    }

    public function render_meta_box(WP_Post $post): void {
        wp_nonce_field('gr_save_product_meta', 'gr_product_nonce');
        $values = self::get_meta_values($post->ID);
        ?>
        <style>
            .gr-meta-table { width: 100%; border-collapse: collapse; }
            .gr-meta-table th { width: 200px; text-align: left; padding: 12px 8px; vertical-align: top; font-weight: 600; }
            .gr-meta-table td { padding: 12px 8px; }
            .gr-meta-table input[type=text],
            .gr-meta-table input[type=url],
            .gr-meta-table textarea { width: 100%; max-width: 640px; }
            .gr-meta-hint { color: #666; font-size: 12px; display: block; margin-top: 4px; }
        </style>
        <table class="gr-meta-table">
            <tr>
                <th><label for="gr_price_label"><?php esc_html_e('Price label', 'gravity-recommender'); ?></label></th>
                <td>
                    <input type="text" name="gr_price_label" id="gr_price_label" value="<?php echo esc_attr($values['price_label']); ?>" placeholder="$12 / month" />
                    <span class="gr-meta-hint"><?php esc_html_e('Free-form. Displayed on the product card.', 'gravity-recommender'); ?></span>
                </td>
            </tr>
            <tr>
                <th><label for="gr_description"><?php esc_html_e('Short description', 'gravity-recommender'); ?></label></th>
                <td><textarea name="gr_description" id="gr_description" rows="2"><?php echo esc_textarea($values['description']); ?></textarea></td>
            </tr>
            <tr>
                <th><label for="gr_features"><?php esc_html_e('Features (one per line)', 'gravity-recommender'); ?></label></th>
                <td><textarea name="gr_features" id="gr_features" rows="5" placeholder="2 CPU&#10;4 GB RAM&#10;Unlimited bandwidth"><?php echo esc_textarea($values['features']); ?></textarea></td>
            </tr>
            <tr>
                <th><label for="gr_page_url"><?php esc_html_e('Product page URL', 'gravity-recommender'); ?></label></th>
                <td><input type="url" name="gr_page_url" id="gr_page_url" value="<?php echo esc_attr($values['page_url']); ?>" /></td>
            </tr>
            <tr>
                <th><label for="gr_payment_url"><?php esc_html_e('Payment / cart URL', 'gravity-recommender'); ?></label></th>
                <td>
                    <input type="url" name="gr_payment_url" id="gr_payment_url" value="<?php echo esc_attr($values['payment_url']); ?>" />
                    <span class="gr-meta-hint"><?php esc_html_e('Used by the CTA button. Falls back to the page URL if empty.', 'gravity-recommender'); ?></span>
                </td>
            </tr>
            <tr>
                <th><label for="gr_cta_label"><?php esc_html_e('CTA button label', 'gravity-recommender'); ?></label></th>
                <td><input type="text" name="gr_cta_label" id="gr_cta_label" value="<?php echo esc_attr($values['cta_label']); ?>" placeholder="<?php esc_attr_e('Add to cart', 'gravity-recommender'); ?>" /></td>
            </tr>
            <tr>
                <th><label for="gr_tags"><?php esc_html_e('Tags (comma-separated)', 'gravity-recommender'); ?></label></th>
                <td>
                    <input type="text" name="gr_tags" id="gr_tags" value="<?php echo esc_attr($values['tags']); ?>" placeholder="ecommerce, high-traffic, budget" />
                    <span class="gr-meta-hint"><?php esc_html_e('Used by the scoring engine to match form answers. Define your own tag vocabulary in the admin.', 'gravity-recommender'); ?></span>
                </td>
            </tr>
        </table>
        <?php
    }

    public function save_meta(int $post_id, WP_Post $post): void {
        if (!isset($_POST['gr_product_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['gr_product_nonce'])), 'gr_save_product_meta')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $fields = [
            'gr_price_label' => ['key' => '_gr_price_label', 'sanitizer' => 'sanitize_text_field'],
            'gr_description' => ['key' => '_gr_description', 'sanitizer' => 'sanitize_textarea_field'],
            'gr_features'    => ['key' => '_gr_features',    'sanitizer' => 'sanitize_textarea_field'],
            'gr_page_url'    => ['key' => '_gr_page_url',    'sanitizer' => 'esc_url_raw'],
            'gr_payment_url' => ['key' => '_gr_payment_url', 'sanitizer' => 'esc_url_raw'],
            'gr_cta_label'   => ['key' => '_gr_cta_label',   'sanitizer' => 'sanitize_text_field'],
            'gr_tags'        => ['key' => '_gr_tags',        'sanitizer' => 'sanitize_text_field'],
        ];

        foreach ($fields as $form_key => $config) {
            if (!isset($_POST[$form_key])) {
                continue;
            }
            $raw = wp_unslash($_POST[$form_key]);
            update_post_meta($post_id, $config['key'], call_user_func($config['sanitizer'], $raw));
        }
    }

    /**
     * Retourne tous les meta d'un produit (avec defaults).
     */
    public static function get_meta_values(int $post_id): array {
        return [
            'price_label' => (string) get_post_meta($post_id, '_gr_price_label', true),
            'features'    => (string) get_post_meta($post_id, '_gr_features', true),
            'description' => (string) get_post_meta($post_id, '_gr_description', true),
            'page_url'    => (string) get_post_meta($post_id, '_gr_page_url', true),
            'payment_url' => (string) get_post_meta($post_id, '_gr_payment_url', true),
            'cta_label'   => (string) get_post_meta($post_id, '_gr_cta_label', true),
            'tags'        => (string) get_post_meta($post_id, '_gr_tags', true),
        ];
    }

    /**
     * Retourne les tags d'un produit sous forme d'array normalisé (lowercase, trimmed).
     */
    public static function get_tags(int $post_id): array {
        $raw = (string) get_post_meta($post_id, '_gr_tags', true);
        if ($raw === '') {
            return [];
        }
        $tags = array_map('trim', explode(',', strtolower($raw)));
        return array_values(array_filter($tags));
    }
}
