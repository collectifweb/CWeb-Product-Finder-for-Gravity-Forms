<?php
/**
 * Custom Post Type `cwebpf_product` — produits recommandables natifs au plugin.
 *
 * Les champs métier sont stockés en post-meta :
 *   - _cwebpf_price_label  : libellé prix affiché (texte libre, ex. "$29 / month")
 *   - _cwebpf_features     : liste de features, une par ligne
 *   - _cwebpf_description  : description courte
 *   - _cwebpf_page_url     : URL de la fiche produit
 *   - _cwebpf_payment_url  : URL de paiement / panier
 *   - _cwebpf_cta_label    : libellé du bouton (défaut "Add to cart")
 *
 * Les règles de scoring (voir page Scoring Rules) ciblent directement les
 * produits par leur ID — pas besoin de tagger les produits.
 */

if (!defined('ABSPATH')) {
    exit;
}

class CWebPF_Product_CPT {

    public const POST_TYPE = 'cwebpf_product';

    public function __construct() {
        add_action('init', [$this, 'register_post_type']);
        add_action('add_meta_boxes', [$this, 'register_meta_boxes']);
        add_action('save_post_' . self::POST_TYPE, [$this, 'save_meta'], 10, 2);
    }

    public function register_post_type(): void {
        register_post_type(self::POST_TYPE, [
            'labels' => [
                'name'          => __('Recommended Products', 'cweb-product-finder-for-gravity-forms'),
                'singular_name' => __('Recommended Product', 'cweb-product-finder-for-gravity-forms'),
                'add_new'       => __('Add new', 'cweb-product-finder-for-gravity-forms'),
                'add_new_item'  => __('Add new product', 'cweb-product-finder-for-gravity-forms'),
                'edit_item'     => __('Edit product', 'cweb-product-finder-for-gravity-forms'),
                'new_item'      => __('New product', 'cweb-product-finder-for-gravity-forms'),
                'view_item'     => __('View product', 'cweb-product-finder-for-gravity-forms'),
                'search_items'  => __('Search products', 'cweb-product-finder-for-gravity-forms'),
                'menu_name'     => __('Recommender', 'cweb-product-finder-for-gravity-forms'),
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
            'cwebpf_product_attributes',
            __('Product attributes', 'cweb-product-finder-for-gravity-forms'),
            [$this, 'render_meta_box'],
            self::POST_TYPE,
            'normal',
            'high'
        );
    }

    public function render_meta_box(WP_Post $post): void {
        wp_nonce_field('cwebpf_save_product_meta', 'cwebpf_product_nonce');
        $values = self::get_meta_values($post->ID);
        ?>
        <table class="cwebpf-meta-table">
            <tr>
                <th><label for="cwebpf_price_label"><?php esc_html_e('Price label', 'cweb-product-finder-for-gravity-forms'); ?></label></th>
                <td>
                    <input type="text" name="cwebpf_price_label" id="cwebpf_price_label" value="<?php echo esc_attr($values['price_label']); ?>" placeholder="$29 / month" />
                    <span class="cwebpf-meta-hint"><?php esc_html_e('Free-form text shown on the product card. Example: "$29 / month" or "From $99".', 'cweb-product-finder-for-gravity-forms'); ?></span>
                </td>
            </tr>
            <tr>
                <th><label for="cwebpf_description"><?php esc_html_e('Short description', 'cweb-product-finder-for-gravity-forms'); ?></label></th>
                <td>
                    <textarea name="cwebpf_description" id="cwebpf_description" rows="2" placeholder="<?php esc_attr_e('A one-line pitch shown below the features.', 'cweb-product-finder-for-gravity-forms'); ?>"><?php echo esc_textarea($values['description']); ?></textarea>
                </td>
            </tr>
            <tr>
                <th><label for="cwebpf_features"><?php esc_html_e('Features (one per line)', 'cweb-product-finder-for-gravity-forms'); ?></label></th>
                <td>
                    <textarea name="cwebpf_features" id="cwebpf_features" rows="5" placeholder="Up to 3 users&#10;5 GB storage&#10;Priority email support"><?php echo esc_textarea($values['features']); ?></textarea>
                </td>
            </tr>
            <tr>
                <th><label for="cwebpf_page_url"><?php esc_html_e('Product page URL', 'cweb-product-finder-for-gravity-forms'); ?></label></th>
                <td><input type="url" name="cwebpf_page_url" id="cwebpf_page_url" value="<?php echo esc_attr($values['page_url']); ?>" placeholder="https://example.com/plan/starter" /></td>
            </tr>
            <tr>
                <th><label for="cwebpf_payment_url"><?php esc_html_e('Payment / cart URL', 'cweb-product-finder-for-gravity-forms'); ?></label></th>
                <td>
                    <input type="url" name="cwebpf_payment_url" id="cwebpf_payment_url" value="<?php echo esc_attr($values['payment_url']); ?>" placeholder="https://example.com/checkout?plan=starter" />
                    <span class="cwebpf-meta-hint"><?php esc_html_e('Used by the CTA button. Falls back to the page URL if empty.', 'cweb-product-finder-for-gravity-forms'); ?></span>
                </td>
            </tr>
            <tr>
                <th><label for="cwebpf_cta_label"><?php esc_html_e('CTA button label', 'cweb-product-finder-for-gravity-forms'); ?></label></th>
                <td><input type="text" name="cwebpf_cta_label" id="cwebpf_cta_label" value="<?php echo esc_attr($values['cta_label']); ?>" placeholder="<?php esc_attr_e('Add to cart', 'cweb-product-finder-for-gravity-forms'); ?>" /></td>
            </tr>
        </table>
        <?php
    }

    public function save_meta(int $post_id, WP_Post $post): void {
        if (!isset($_POST['cwebpf_product_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['cwebpf_product_nonce'])), 'cwebpf_save_product_meta')) {
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
        if (isset($_POST['cwebpf_price_label'])) {
            update_post_meta($post_id, '_cwebpf_price_label', sanitize_text_field(wp_unslash($_POST['cwebpf_price_label'])));
        }
        if (isset($_POST['cwebpf_description'])) {
            update_post_meta($post_id, '_cwebpf_description', sanitize_textarea_field(wp_unslash($_POST['cwebpf_description'])));
        }
        if (isset($_POST['cwebpf_features'])) {
            update_post_meta($post_id, '_cwebpf_features', sanitize_textarea_field(wp_unslash($_POST['cwebpf_features'])));
        }
        if (isset($_POST['cwebpf_page_url'])) {
            update_post_meta($post_id, '_cwebpf_page_url', esc_url_raw(wp_unslash($_POST['cwebpf_page_url'])));
        }
        if (isset($_POST['cwebpf_payment_url'])) {
            update_post_meta($post_id, '_cwebpf_payment_url', esc_url_raw(wp_unslash($_POST['cwebpf_payment_url'])));
        }
        if (isset($_POST['cwebpf_cta_label'])) {
            update_post_meta($post_id, '_cwebpf_cta_label', sanitize_text_field(wp_unslash($_POST['cwebpf_cta_label'])));
        }
    }

    public static function get_meta_values(int $post_id): array {
        return [
            'price_label' => (string) get_post_meta($post_id, '_cwebpf_price_label', true),
            'features'    => (string) get_post_meta($post_id, '_cwebpf_features', true),
            'description' => (string) get_post_meta($post_id, '_cwebpf_description', true),
            'page_url'    => (string) get_post_meta($post_id, '_cwebpf_page_url', true),
            'payment_url' => (string) get_post_meta($post_id, '_cwebpf_payment_url', true),
            'cta_label'   => (string) get_post_meta($post_id, '_cwebpf_cta_label', true),
        ];
    }
}
