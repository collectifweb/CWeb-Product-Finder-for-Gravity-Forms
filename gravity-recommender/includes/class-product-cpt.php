<?php
/**
 * Custom Post Type `gr_product` — produits recommandables natifs au plugin.
 *
 * Les champs métier sont stockés en post-meta :
 *   - _gr_price_label  : libellé prix affiché (texte libre, ex. "$29 / month")
 *   - _gr_features     : liste de features, une par ligne
 *   - _gr_description  : description courte
 *   - _gr_page_url     : URL de la fiche produit
 *   - _gr_payment_url  : URL de paiement / panier
 *   - _gr_cta_label    : libellé du bouton (défaut "Add to cart")
 *
 * Les règles de scoring (voir page Scoring Rules) ciblent directement les
 * produits par leur ID — pas besoin de tagger les produits.
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
                    <input type="text" name="gr_price_label" id="gr_price_label" value="<?php echo esc_attr($values['price_label']); ?>" placeholder="$29 / month" />
                    <span class="gr-meta-hint"><?php esc_html_e('Free-form text shown on the product card. Example: "$29 / month" or "From $99".', 'gravity-recommender'); ?></span>
                </td>
            </tr>
            <tr>
                <th><label for="gr_description"><?php esc_html_e('Short description', 'gravity-recommender'); ?></label></th>
                <td>
                    <textarea name="gr_description" id="gr_description" rows="2" placeholder="<?php esc_attr_e('A one-line pitch shown below the features.', 'gravity-recommender'); ?>"><?php echo esc_textarea($values['description']); ?></textarea>
                </td>
            </tr>
            <tr>
                <th><label for="gr_features"><?php esc_html_e('Features (one per line)', 'gravity-recommender'); ?></label></th>
                <td>
                    <textarea name="gr_features" id="gr_features" rows="5" placeholder="Up to 3 users&#10;5 GB storage&#10;Priority email support"><?php echo esc_textarea($values['features']); ?></textarea>
                </td>
            </tr>
            <tr>
                <th><label for="gr_page_url"><?php esc_html_e('Product page URL', 'gravity-recommender'); ?></label></th>
                <td><input type="url" name="gr_page_url" id="gr_page_url" value="<?php echo esc_attr($values['page_url']); ?>" placeholder="https://example.com/plan/starter" /></td>
            </tr>
            <tr>
                <th><label for="gr_payment_url"><?php esc_html_e('Payment / cart URL', 'gravity-recommender'); ?></label></th>
                <td>
                    <input type="url" name="gr_payment_url" id="gr_payment_url" value="<?php echo esc_attr($values['payment_url']); ?>" placeholder="https://example.com/checkout?plan=starter" />
                    <span class="gr-meta-hint"><?php esc_html_e('Used by the CTA button. Falls back to the page URL if empty.', 'gravity-recommender'); ?></span>
                </td>
            </tr>
            <tr>
                <th><label for="gr_cta_label"><?php esc_html_e('CTA button label', 'gravity-recommender'); ?></label></th>
                <td><input type="text" name="gr_cta_label" id="gr_cta_label" value="<?php echo esc_attr($values['cta_label']); ?>" placeholder="<?php esc_attr_e('Add to cart', 'gravity-recommender'); ?>" /></td>
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

        // Sanitization is explicit per field so static analyzers (and reviewers)
        // can verify the chain wp_unslash → sanitize_*.
        if (isset($_POST['gr_price_label'])) {
            update_post_meta($post_id, '_gr_price_label', sanitize_text_field(wp_unslash($_POST['gr_price_label'])));
        }
        if (isset($_POST['gr_description'])) {
            update_post_meta($post_id, '_gr_description', sanitize_textarea_field(wp_unslash($_POST['gr_description'])));
        }
        if (isset($_POST['gr_features'])) {
            update_post_meta($post_id, '_gr_features', sanitize_textarea_field(wp_unslash($_POST['gr_features'])));
        }
        if (isset($_POST['gr_page_url'])) {
            update_post_meta($post_id, '_gr_page_url', esc_url_raw(wp_unslash($_POST['gr_page_url'])));
        }
        if (isset($_POST['gr_payment_url'])) {
            update_post_meta($post_id, '_gr_payment_url', esc_url_raw(wp_unslash($_POST['gr_payment_url'])));
        }
        if (isset($_POST['gr_cta_label'])) {
            update_post_meta($post_id, '_gr_cta_label', sanitize_text_field(wp_unslash($_POST['gr_cta_label'])));
        }
    }

    public static function get_meta_values(int $post_id): array {
        return [
            'price_label' => (string) get_post_meta($post_id, '_gr_price_label', true),
            'features'    => (string) get_post_meta($post_id, '_gr_features', true),
            'description' => (string) get_post_meta($post_id, '_gr_description', true),
            'page_url'    => (string) get_post_meta($post_id, '_gr_page_url', true),
            'payment_url' => (string) get_post_meta($post_id, '_gr_payment_url', true),
            'cta_label'   => (string) get_post_meta($post_id, '_gr_cta_label', true),
        ];
    }
}
