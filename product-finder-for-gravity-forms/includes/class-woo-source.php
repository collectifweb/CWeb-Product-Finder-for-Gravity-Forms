<?php
/**
 * Source de produits basée sur WooCommerce.
 *
 * Les règles de scoring référencent les produits WooCommerce directement par
 * leur post ID. Cette source ne s'active que si WooCommerce est actif.
 */

if (!defined('ABSPATH')) {
    exit;
}

class GR_Woo_Source implements GR_Product_Source {

    public function get_all_products(): array {
        if (!function_exists('wc_get_products')) {
            return [];
        }

        $products = wc_get_products([
            'status' => 'publish',
            'limit'  => -1,
            'return' => 'objects',
        ]);

        return array_map([$this, 'normalize_product'], $products);
    }

    public function get_product(int $id): ?array {
        if (!function_exists('wc_get_product')) {
            return null;
        }

        $product = wc_get_product($id);
        if (!$product || $product->get_status() !== 'publish') {
            return null;
        }

        return $this->normalize_product($product);
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

    /**
     * @param WC_Product $product
     */
    private function normalize_product($product): array {
        $id = $product->get_id();

        $features_meta = (string) get_post_meta($id, '_gr_features', true);
        if ($features_meta === '') {
            // Fallback : attributs visibles WooCommerce
            $attributes = $product->get_attributes();
            $features = [];
            foreach ($attributes as $attr) {
                if (method_exists($attr, 'get_visible') && $attr->get_visible()) {
                    $features[] = $attr->get_name() . ': ' . implode(', ', $attr->get_options());
                }
            }
        } else {
            $features = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $features_meta))));
        }

        $payment_url = get_post_meta($id, '_gr_payment_url', true);
        if (!$payment_url) {
            $payment_url = $product->add_to_cart_url();
        }

        $cta_label = (string) get_post_meta($id, '_gr_cta_label', true);
        if ($cta_label === '') {
            $cta_label = $product->add_to_cart_text();
        }

        $image_id = $product->get_image_id();
        $image_url = $image_id ? wp_get_attachment_image_url($image_id, 'medium') : '';

        return [
            'id'          => $id,
            'name'        => $product->get_name(),
            'price_label' => wp_strip_all_tags($product->get_price_html()),
            'features'    => $features,
            'description' => $product->get_short_description() ?: $product->get_description(),
            'page_url'    => get_permalink($id) ?: '',
            'payment_url' => $payment_url,
            'cta_label'   => $cta_label,
            'image_url'   => $image_url ?: '',
        ];
    }
}
