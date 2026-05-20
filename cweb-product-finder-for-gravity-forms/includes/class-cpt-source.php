<?php
/**
 * Source de produits basée sur le Custom Post Type `cwebpf_product`.
 */

if (!defined('ABSPATH')) {
    exit;
}

class CWebPF_CPT_Source implements CWebPF_Product_Source {

    public function get_all_products(): array {
        $posts = get_posts([
            'post_type'      => CWebPF_Product_CPT::POST_TYPE,
            'post_status'    => 'publish',
            'numberposts'    => -1,
            'orderby'        => 'menu_order title',
            'order'          => 'ASC',
        ]);

        return array_map([$this, 'normalize_post'], $posts);
    }

    public function get_product(int $id): ?array {
        $post = get_post($id);
        if (!$post || $post->post_type !== CWebPF_Product_CPT::POST_TYPE || $post->post_status !== 'publish') {
            return null;
        }
        return $this->normalize_post($post);
    }

    public function get_products(array $ids): array {
        $result = [];
        foreach ($ids as $id) {
            $product = $this->get_product((int) $id);
            if ($product) {
                $result[] = $product;
            }
        }
        return $result;
    }

    private function normalize_post(WP_Post $post): array {
        $meta = CWebPF_Product_CPT::get_meta_values($post->ID);

        $features = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $meta['features']))));
        $payment_url = $meta['payment_url'] !== '' ? $meta['payment_url'] : $meta['page_url'];
        $thumbnail = get_the_post_thumbnail_url($post->ID, 'medium');

        return [
            'id'          => $post->ID,
            'name'        => $post->post_title,
            'price_label' => $meta['price_label'],
            'features'    => $features,
            'description' => $meta['description'],
            'page_url'    => $meta['page_url'],
            'payment_url' => $payment_url,
            'cta_label'   => $meta['cta_label'] !== '' ? $meta['cta_label'] : __('Add to cart', 'cweb-product-finder-for-gravity-forms'),
            'image_url'   => $thumbnail ?: '',
        ];
    }
}
