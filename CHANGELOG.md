# Changelog

All notable changes to **Gravity Recommender** are documented here.
Format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [3.0.0-beta] — 2026-05-19

Major rewrite: the plugin is now a **general-purpose** Gravity Forms product recommender. The previous 2.x line was hardcoded for a specific hosting catalog (Collectif HUB) and is no longer maintained on `main`.

### Added
- `gr_product` Custom Post Type for managing recommended products from the WP admin.
- WooCommerce product source (uses the native `product_tag` taxonomy for tagging).
- Source abstraction (`GR_Product_Source`) — implementations: `GR_CPT_Source`, `GR_Woo_Source`.
- Tag-based scoring engine — admin-defined rules instead of hardcoded logic.
- 5-step setup wizard under **Recommender → Setup**.
- Example Gravity Form importer in the wizard.
- CSS custom properties for theming (`--gr-primary`, `--gr-accent`, `--gr-font-heading`, etc.).
- Filters: `gr_form_id`, `gr_field_id`, `gr_recommendation_explanation`, `gr_card_disclaimer`, `gr_fallback_contact_url`.
- `readme.txt` in the format expected by wordpress.org.
- `LICENSE` (GPL v2 or later).

### Changed
- Shortcode renamed: `[hub_recommended_products]` → `[gravity_recommender]`.
- All class / constant / function / CSS prefixes: `CHR_` / `chr_` / `.chr-` → `GR_` / `gr_` / `.gr-`.
- Plugin folder renamed: `plugin/` → `gravity-recommender/`.
- Plugin author updated to **Collectif WEB** (Alexandre Alves).

### Removed
- Static PHP product registry (was hardcoded with the Collectif HUB catalog).
- Hosting-specific scoring criteria (num_sites, traffic, budget, etc.) — replaced by user-defined tag rules.
- `docs/SITE_HEALTH.md` (internal site health dump — not meant for OSS).
- Server / SSH details from `docs/ARCHITECTURE.md`.

## [2.1.3] — 2026-03-08
- Force 3-column product grid on the front-end.

## [2.1.2]
- Load CSS on all front-end pages (Elementor widgets don't trigger `has_shortcode()`).

## [2.1.1]
- CSS rewrite for Elementor compatibility.

## [2.1.0]
- Built-in PHP scoring engine replaces the GF OpenAI feed.

## [2.0.x and earlier]
- Initial Collectif HUB-specific implementation. Hardcoded product catalog, OpenAI integration via Gravity Forms OpenAI add-on.
