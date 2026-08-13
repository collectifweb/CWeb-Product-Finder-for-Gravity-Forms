# Changelog

All notable changes to **CWeb Product Finder for Gravity Forms** are documented here.
Format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [3.1.4] — 2026-08-13

WordPress 7.1 compatibility pass, ahead of the August 19 release. Verified on a local WordPress 7.1-RC3 install (PHP 8.3, SQLite, Gravity Forms active) driven through a real browser: the five admin screens, the product meta box save round-trip, the example-form import, the scoring engine and the front-end shortcode all ran with `WP_DEBUG` on and produced zero PHP notices and zero JavaScript errors.

None of the seven changes announced in the 7.1 field guide touch this plugin: the `cwebpf_product` post type is declared `show_in_rest => false` without `editor` support, so it never opens the block editor; the plugin ships no jQuery dependency, no `@wordpress/components` code, no media hook and no toolbar item. Confirmed empirically — the 7.1 editor canvas iframe loads no plugin asset, and neither does the block editor page itself.

### Changed
- `Tested up to` raised from 6.9 to **7.1** in `readme.txt`.
- `import_example_form()` now reads the bundled JSON through `wp_json_file_decode()` instead of `file_get_contents()` + `json_decode()`. Clears the only warning left by the Plugin Check static sniffs, and drops four lines.

### Fixed
- **PHP warning "Undefined array key `condition_logic`"** in `CWebPF_Recommendation_Engine::normalize_rule()`. The guard read the key through `??` inside the `in_array()` test but then re-read it unguarded in the ternary's true branch, so a rule saved without the AND/OR setting emitted a warning and stored `null` instead of the intended `'all'` default.
- **Stale plugin name in the setup wizard.** Five strings in `class-admin-onboarding.php` (page title, admin notice, two descriptions, one file header) still read "Product Finder for Gravity Forms", the name used before the 3.1.3 rename. They now read "CWeb Product Finder for Gravity Forms", matching the plugin header, `readme.txt` and the WordPress.org listing.

## [3.1.3] — 2026-05-20

Second submission cycle to WordPress.org. The Plugin Review Team's automated pre-review flagged four blockers (generic name, short identifier prefix, inline `<style>`/`<script>` blocks, ownership signal). This release addresses all four.

### Changed
- **Plugin renamed** from "Product Finder for Gravity Forms" to **"CWeb Product Finder for Gravity Forms"**. The new name adds the agency's `CWeb` distinctive identifier at the start, per WP.org guideline: a plugin name must be distinctive, not just descriptive, even when using the `<feature> for <brand>` pattern.
- **Identifier prefix lengthened**: `GR_` / `gr_` / `.gr-` / `--gr-` (2 chars) renamed to `CWebPF_` / `CWEBPF_` / `cwebpf_` / `.cwebpf-` / `--cwebpf-` (6 chars), satisfying the WP.org rule that prefixes be at least 4 characters and distinct.
- **Shortcode renamed**: `[gravity_recommender]` → `[cwebpf_recommender]`.
- **Slug, folder, main PHP file and text domain** updated: `product-finder-for-gravity-forms` → `cweb-product-finder-for-gravity-forms`.
- **Plugin URI** now points to `github.com/collectifweb/CWeb-Product-Finder-for-Gravity-Forms`.
- **Contributors list** in readme.txt now includes both `collectifweb` and `alexandreminem` (the WordPress.org account that submits the plugin).

### Fixed
- **No more inline `<style>` or `<script>` blocks**. All six occurrences (`class-admin-help.php`, `class-admin-onboarding.php` ×2, `class-product-cpt.php`, `class-admin-rules.php` ×2) moved to separate asset files (`assets/css/admin.css`, `assets/js/admin-rules.js`, `assets/js/admin-onboarding.js`) registered via `wp_enqueue_style` / `wp_enqueue_script`. JS strings now pass through `wp_localize_script`. Each asset is conditionally enqueued only on the admin screen that needs it.

### Removed
- All `_gr_*` post meta keys (renamed to `_cwebpf_*`). Since the plugin was never approved on WP.org and only existed in beta, no migration path is provided. Fresh installs only.

## [3.1.2] — 2026-05-19

### Changed — WordPress.org submission preparation
- **Plugin renamed** from "Gravity Recommender" to **"CWeb Product Finder for Gravity Forms"** to comply with the WordPress.org Plugin Directory naming guidelines (third-party-trademark prefix not allowed; `<feature> for <brand>` is the supported pattern).
- Slug, folder, text domain, plugin URI and all human-readable mentions updated accordingly.
- Filter renamed: `cwebpf_gravityforms_affiliate_url` → `cwebpf_gravityforms_url`.

### Fixed
- Self-referencing CSS custom properties in `:root` — default colors/fonts now render even when the theme doesn't override them.
- Replaced `(int) wp_unslash(...)` with `absint(wp_unslash(...))` on `$_POST['cwebpf_form_id']` and `$_GET['step']` — static analyzers don't accept type casts as sanitization.
- Uniformized license string as `GPLv2 or later` across plugin header, readme.txt and LICENSE.
- LICENSE body now explicitly states "or (at your option) any later version", matching the headers.

### Removed
- `-beta` suffix on the version number (W2 from the pre-submission audit).

## [3.1.1] — 2026-05-19

Hotfix release that addresses real-world testing feedback and the WordPress.org Plugin Checker.

### Fixed
- **Critical fatal on Setup step 3 ("Products")** — `CWebPF_CPT_Source` was still calling `CWebPF_Product_CPT::get_tags()`, which was removed in 3.1.0 along with the tag-based engine. Restored full compatibility.
- Removed leftover `tags` field from the product source interface, `CWebPF_CPT_Source`, and `CWebPF_Woo_Source`.

### Changed — WordPress.org compliance
- Dropped `load_plugin_textdomain()` call — discouraged since WP 4.6; wordpress.org auto-loads translations.
- Added `languages/` folder with a README so the `Domain Path` header points to an existing directory.
- `translators:` comments moved to sit immediately above the `__()` call (Help page Credits block).
- Switched `class-product-cpt.php` save logic from dynamic `call_user_func` sanitizer to per-field explicit `sanitize_text_field(wp_unslash(...))` chains so static analysis can verify them.
- `class-admin-rules.php` now deep-unslashes the submitted rules array with `map_deep + sanitize_text_field` before parsing.
- `class-admin-onboarding.php` unslashes `$_POST['cwebpf_form_id']` and properly handles `$_GET` reads (wizard step + flash messages) with explicit `phpcs:ignore` comments on display-only paths after a `wp_safe_redirect`.

## [3.1.0] — 2026-05-19

Second iteration based on real-world testing feedback. Scoring model rewritten from "tag-based" to **direct product targeting**, and the admin UI was reorganized into 3 distinct pages.

### Added
- **Scoring Rules** dedicated admin page (`Recommender → Scoring Rules`). No longer buried inside the setup wizard.
- **Help** admin page (`Recommender → Help`) with how-it-works, shortcode, filters reference, agency credits and contact info.
- **Conditions builder** with AND / OR logic — combine multiple form answers in a single rule.
- **Four effects per rule** : `Boost` (+N points), `Penalize` (−N points), `Exclude` (hard filter), `Require` (only required products are eligible).
- **Auto-detection of hidden fields** in the configured Gravity Form. One field → selected automatically. Multiple → user picks. Zero → instructions to add one.
- **Restricted-answer fields only** — rule builder ignores free-text fields, only exposes radio/dropdown/checkbox/multiselect with their actual choices.
- **Affiliate CTA when Gravity Forms is missing** — wizard halts gracefully with a `Get Gravity Forms` button (filter `cwebpf_gravityforms_url` for the URL).
- **AI fallback hook** (`cwebpf_ai_fallback_recommendation`) — architecture-ready for plugging an AI service when no rule matches. No built-in implementation.

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
- `cwebpf_product` Custom Post Type for managing recommended products from the WP admin.
- WooCommerce product source (uses the native `product_tag` taxonomy for tagging).
- Source abstraction (`CWebPF_Product_Source`) — implementations: `CWebPF_CPT_Source`, `CWebPF_Woo_Source`.
- Tag-based scoring engine — admin-defined rules.
- 5-step setup wizard under **Recommender → Setup**.
- Example Gravity Form importer in the wizard.
- CSS custom properties for theming (`--cwebpf-primary`, `--cwebpf-accent`, `--cwebpf-font-heading`, etc.).
- Filters: `cwebpf_form_id`, `cwebpf_field_id`, `cwebpf_recommendation_explanation`, `cwebpf_card_disclaimer`, `cwebpf_fallback_contact_url`.
- `readme.txt` in the format expected by wordpress.org.
- `LICENSE` (GPL v2 or later).
