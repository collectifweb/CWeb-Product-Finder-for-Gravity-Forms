# Changelog

All notable changes to **Gravity Recommender** are documented here.
Format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [3.1.0-beta] — 2026-05-19

Second iteration based on real-world testing feedback. Scoring model rewritten from "tag-based" to **direct product targeting**, and the admin UI was reorganized into 3 distinct pages.

### Added
- **Scoring Rules** dedicated admin page (`Recommender → Scoring Rules`). No longer buried inside the setup wizard.
- **Help** admin page (`Recommender → Help`) with how-it-works, shortcode, filters reference, agency credits and contact info.
- **Conditions builder** with AND / OR logic — combine multiple form answers in a single rule.
- **Four effects per rule** : `Boost` (+N points), `Penalize` (−N points), `Exclude` (hard filter), `Require` (only required products are eligible).
- **Auto-detection of hidden fields** in the configured Gravity Form. One field → selected automatically. Multiple → user picks. Zero → instructions to add one.
- **Restricted-answer fields only** — rule builder ignores free-text fields, only exposes radio/dropdown/checkbox/multiselect with their actual choices.
- **Affiliate CTA when Gravity Forms is missing** — wizard halts gracefully with a `Get Gravity Forms` button (filter `gr_gravityforms_affiliate_url` for the URL).
- **AI fallback hook** (`gr_ai_fallback_recommendation`) — architecture-ready for plugging an AI service when no rule matches. No built-in implementation.

### Changed
- **Scoring engine rewritten** — rules now target products **by ID** directly instead of going through tags. More intuitive and easier to author.
- **Onboarding reordered** : Welcome → Source → **Products** → Form → Done. Products must exist before the user can wire form answers to them.
- **Setup wizard slimmed to 5 visible steps** (was 5 steps + buried rules step) — scoring rules have their own page now.
- **Example form replaced** — generic *SaaS Plan Picker* (3 questions, 3 plans) instead of the hosting-specific Collectif HUB form.
- **CPT product placeholders genericized** — no more hosting-themed defaults.

### Removed
- `_gr_tags` post meta — products are no longer tagged. Existing values are ignored by the engine.
- Tag-matching code path in the recommendation engine.

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
