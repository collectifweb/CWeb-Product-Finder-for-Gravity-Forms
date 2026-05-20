<?php
/**
 * Interface des sources de produits.
 *
 * Une source fournit au moteur la liste des produits avec leurs attributs.
 * Implémentations livrées : CPT natif (`GR_CPT_Source`) et WooCommerce (`GR_Woo_Source`).
 *
 * Structure d'un produit normalisé :
 *   [
 *     'id'          => int,
 *     'name'        => string,
 *     'price_label' => string,   // libellé prix affiché
 *     'features'    => string[], // liste de features
 *     'description' => string,
 *     'page_url'    => string,
 *     'payment_url' => string,
 *     'cta_label'   => string,
 *     'tags'        => string[], // tags normalisés (lowercase, trimmed)
 *     'image_url'   => string,
 *   ]
 */

if (!defined('ABSPATH')) {
    exit;
}

interface GR_Product_Source {

    /** Retourne tous les produits disponibles. */
    public function get_all_products(): array;

    /** Retourne un produit par son ID. */
    public function get_product(int $id): ?array;

    /** Retourne plusieurs produits par leurs IDs, dans l'ordre fourni. */
    public function get_products(array $ids): array;
}

/**
 * Factory : retourne la source configurée dans les options du plugin.
 */
class GR_Product_Source_Factory {

    public static function make(): GR_Product_Source {
        $configured = get_option('gr_product_source', 'cpt');

        if ($configured === 'woocommerce' && class_exists('WooCommerce')) {
            return new GR_Woo_Source();
        }

        return new GR_CPT_Source();
    }
}
