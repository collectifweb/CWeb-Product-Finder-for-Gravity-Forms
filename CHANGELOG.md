# Changelog

All notable changes to **Product Finder for Gravity Forms** are documented here.
Format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [3.1.2] — 2026-05-19

### Changed — WordPress.org submission preparation
- **Plugin renamed** from "Gravity Recommender" to **"Product Finder for Gravity Forms"** to comply with the WordPress.org Plugin Directory naming guidelines (third-party-trademark prefix not allowed; `<feature> for <brand>` is the supported pattern).
- Slug, folder, text domain, plugin URI and all human-readable mentions updated accordingly.
- Filter renamed: `gr_gravityforms_affiliate_url` → `gr_gravityforms_url`.

### Fixed
- Self-referencing CSS custom properties in `:root` — default colors/fonts now render even when the theme doesn't override them.
- Replaced `(int) wp_unslash(...)` with `absint(wp_unslash(...))` on `$_POST['gr_form_id']` and `$_GET['step']` — static analyzers don't accept type casts as sanitization.
- Uniformized license string as `GPLv2 or later` across plugin header, readme.txt and LICENSE.
- LICENSE body now explicitly states "or (at your option) any later version", matching the headers.

### Removed
- `-beta` suffix on the version number (W2 from the pre-submission audit).

## [3.1.1] — 2026-05-19

Hotfix release that addresses real-world testing feedback and the WordPress.org Plugin Checker.

### Fixed
- **Critical fatal on Setup step 3 ("Products")** — `GR_CPT_Source` was still calling `GR_Product_CPT::get_tags()`, which was removed in 3.1.0 along with the tag-based engine. Restored full compatibility.
- Removed leftover `tags` field from the product source interface, `GR_CPT_Source`, and `GR_Woo_Source`.

### Changed — WordPress.org compliance
- Dropped `load_plugin_textdomain()` call — discouraged since WP 4.6; wordpress.org auto-loads translations.
- Added `languages/` folder with a README so the `Domain Path` header points to an existing directory.
- `translators:` comments moved to sit immediately above the `__()` call (Help page Credits block).
- Switched `class-product-cpt.php` save logic from dynamic `call_user_func` sanitizer to per-field explicit `sanitize_text_field(wp_unslash(...))` chains so static analysis can verify them.
- `class-admin-rules.php` now deep-unslashes the submitted rules array with `map_deep + sanitize_text_field` before parsing.
- `class-admin-onboarding.php` unslashes `$_POST['gr_form_id']` and properly handles `$_GET` reads (wizard step + flash messages) with explicit `phpcs:ignore` comments on display-only paths after a `wp_safe_redirect`.

## [3.1.0] — 2026-05-19

Second iteration based on real-world testing feedback. Scoring model rewritten from "tag-based" to **direct product targeting**, and the admin UI was reorganized into 3 distinct pages.

### Added
- **Scoring Rules** dedicated admin page (`Recommender → Scoring Rules`). No longer buried inside the setup wizard.
- **Help** admin page (`Recommender → Help`) with how-it-works, shortcode, filters reference, agency credits and contact info.
- **Conditions builder** with AND / OR logic — combine multiple form answers in a single rule.
- **Four effects per rule** : `Boost` (+N points), `Penalize` (−N points), `Exclude` (hard filter), `Require` (only required products are eligible).
- **Auto-detection of hidden fields** in the configured Gravity Form. One field → selected automatically. Multiple → user picks. Zero → instructions to add one.
- **Restricted-answer fields only** — rule builder ignores free-text fields, only exposes radio/dropdown/checkbox/multiselect with their actual choices.
- **Affiliate CTA when Gravity Forms is missing** — wizard halts gracefully with a `Get Gravity Forms` button (filter `gr_gravityforms_url` for the URL).
- **AI fallback hook** (`gr_ai_fallback_recommendation`) — architecture-ready for plugging an AI service when no rule matches. No built-in implementation.

### Changed
- **Scoring engine rewritten** — rules now target products **by ID** directly instead of going through tags. More intuitive and easier to author.
- **Onboarding reordered** : Welcome → Source → **Products** → Form → Done. Products must exist before the user can wire form answers to them.
- **Setup wizard slimmed to 5 visible steps** — scoring rules have their own page now.
- **Example form replaced** — generic *SaaS Plan Picker* (3 questions, 3 plans).

### Removed
- `_gr_tags` post meta — products are no longer tagged. Existing values are ignored by the engine.
- Tag-matching code path in the recommendation engine.

## [3.0.0] — 2026-05-19

First general-purpose release.

### Added
- `gr_product` Custom Post Type for managing recommended products from the WP admin.
- WooCommerce product source (uses the native `product_tag` taxonomy for tagging).
- Source abstraction (`GR_Product_Source`) — implementations: `GR_CPT_Source`, `GR_Woo_Source`.
- Tag-based scoring engine — admin-defined rules.
- 5-step setup wizard under **Recommender → Setup**.
- Example Gravity Form importer in the wizard.
- CSS custom properties for theming (`--gr-primary`, `--gr-accent`, `--gr-font-heading`, etc.).
- Filters: `gr_form_id`, `gr_field_id`, `gr_recommendation_explanation`, `gr_card_disclaimer`, `gr_fallback_contact_url`.
- `readme.txt` in the format expected by wordpress.org.
- `LICENSE` (GPL v2 or later).
