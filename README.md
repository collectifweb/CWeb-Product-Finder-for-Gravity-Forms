# Gravity Recommender

> A tag-based product recommender that plugs into the end of any Gravity Forms questionnaire. No external API — all scoring happens locally in PHP.

[![License: GPL v2+](https://img.shields.io/badge/License-GPLv2-blue.svg)](LICENSE)
![PHP >= 8.0](https://img.shields.io/badge/PHP-%3E%3D%208.0-777BB4)
![WordPress >= 6.0](https://img.shields.io/badge/WordPress-%3E%3D%206.0-21759B)

Gravity Recommender takes a visitor's answers from a Gravity Forms form, scores each of your products against those answers using a tag-based rules system you configure in WordPress admin, and renders the best match (plus a couple of alternatives) as styled cards on the form confirmation page.

It works with two product sources: a lightweight built-in Custom Post Type, or your existing WooCommerce products.

## Why?

Most "recommendation" plugins on WordPress call out to OpenAI / Claude / a remote API. That brings real costs (per-recommendation billing), latency, vendor lock-in, and sends visitor data offsite.

But most product-recommendation logic is genuinely simple — "if the visitor says X, prefer products that do Y" — and that translates beautifully to a small set of tag-based rules. This plugin codifies that:

- ⚡ **Instant** — no network round-trip
- 💸 **Free** — zero cost per recommendation
- 🔒 **Private** — visitor answers never leave your server
- 🎯 **Deterministic** — same input, same output. Testable.
- 🛠 **No-code config** — define rules from a WP admin UI, not a code editor

## How it works

```
Visitor submits a Gravity Form
    │
    ▼
gform_entry_post_save  →  Tag-based scoring engine
    │                     (sums boosts/exclusions per product)
    ▼
JSON result saved in a hidden GF field
    │
    ▼
gform_confirmation  →  Shortcode receives entry_id
    │
    ▼
[gravity_recommender] →  Renders product cards
```

See [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) for the full design.

## Installation

1. Drop the [`gravity-recommender/`](gravity-recommender/) folder into `wp-content/plugins/`.
2. Activate **Gravity Recommender** in WordPress.
3. Go to **Recommender → Setup** and follow the 5-step wizard:
   1. Welcome / dependency check
   2. Pick the product source (CPT or WooCommerce)
   3. Select your Gravity Form (or import the example one shipped with the plugin)
   4. Define scoring rules (field answers → product tags)
   5. Grab the shortcode and paste it into the form confirmation

## The scoring model

Every product carries a list of **tags** (free-form, e.g. `ecommerce`, `high-traffic`, `cheap`, `pro-managed`). Every **rule** says:

> If the answer to field `#N` contains `X`, then **BOOST** / **EXCLUDE** / **REQUIRE** products tagged `Y` (by `P` points, if boosting).

That's it. The engine sums boosts across all matching rules, applies exclusions and requirements as hard filters, sorts, and returns the top product plus two alternatives.

## Configuration

### Form & field IDs

The setup wizard saves these in `wp_options`. To override them programmatically (e.g. across environments), use filters:

```php
add_filter('gr_form_id',  fn() => 7);
add_filter('gr_field_id', fn() => '50');
```

### Custom explanation

Replace the default "Based on your answers, we recommend X" with your own copy:

```php
add_filter('gr_recommendation_explanation', function ($default, $product, $matched_tags) {
    if (in_array('ecommerce', $matched_tags, true)) {
        return 'Because you mentioned e-commerce, we suggest ' . $product['name'] . '.';
    }
    return $default;
}, 10, 3);
```

### Theming

CSS classes are prefixed `.gr-*`. Colors and fonts are CSS custom properties — override them in your theme:

```css
:root {
    --gr-primary:        #1f2937;
    --gr-secondary:      #3b82f6;
    --gr-accent:         #f59e0b;
    --gr-font-heading:   'Inter', sans-serif;
    --gr-font-body:      'Inter', sans-serif;
}
```

## Requirements

- WordPress 6.0+
- PHP 8.0+
- [Gravity Forms](https://www.gravityforms.com/) 2.9+

Optional: WooCommerce (for the WooCommerce product source).

## Credits

Built by [**Alexandre Alves**](mailto:alexandre@collectifweb.ca) at [**Collectif WEB**](https://collectif-web.ca). Originally created for [Collectif HUB](https://collectif-hub.ca); rewritten for general use in v3.0.

If this plugin saves you a recurring API bill, a GitHub star is appreciated ⭐

## License

[GPL v2 or later](LICENSE) — same as WordPress itself.

## Contributing

Issues and pull requests welcome. For substantial changes, please open an issue first to discuss the direction.

Ground rules:
- Keep PHP 8.0 compatibility.
- Don't introduce a runtime dependency on a remote API. The "no external call" guarantee is the plugin's main pitch.
- Document any new filter or hook in the README.
