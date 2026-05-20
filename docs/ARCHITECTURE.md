# Gravity Recommender — Architecture

## Overview

A WordPress plugin that scores a set of products against a Gravity Forms submission and renders the best match on the form confirmation page. All scoring runs locally in PHP — no external API.

```
Visitor submits the Gravity Form
    │
    ▼
GR_GF_Integration::process_entry()
  ↳ reads the active rules (`gr_scoring_rules`)
  ↳ asks GR_Recommendation_Engine for a result
  ↳ writes the JSON result into the form's hidden field
    │
    ▼
GR_GF_Integration::customize_confirmation()
  ↳ rewrites the [gravity_recommender] shortcode in the confirmation,
    injecting the entry_id so the shortcode reads from THIS submission
    │
    ▼
GR_Shortcode_Handler::render_shortcode()
  ↳ pulls the JSON back from the entry
  ↳ resolves product IDs via the active GR_Product_Source
  ↳ renders styled cards
```

## File layout

```
gravity-recommender/
├── gravity-recommender.php             # Plugin header + bootstrap
├── readme.txt                          # wordpress.org-format readme
├── includes/
│   ├── class-product-cpt.php           # CPT `gr_product` + meta box
│   ├── class-product-source.php        # Source interface + factory
│   ├── class-cpt-source.php            # Source impl: built-in CPT
│   ├── class-woo-source.php            # Source impl: WooCommerce
│   ├── class-recommendation-engine.php # Rule-based scoring (direct product targeting)
│   ├── class-gf-integration.php        # Hooks gform_entry_post_save + gform_confirmation
│   ├── class-shortcode-handler.php     # Shortcode [gravity_recommender]
│   ├── class-admin-onboarding.php      # 5-step setup wizard
│   ├── class-admin-rules.php           # Dedicated Scoring Rules page
│   └── class-admin-help.php            # Help & About page
├── templates/
│   └── example-form.json               # Sample GF (SaaS Plan Picker)
└── assets/
    └── css/
        └── product-cards.css           # `.gr-*` scoped styles, theme via CSS vars
```

## Data model

### Product (built-in CPT `gr_product`)

| Meta key          | Type    | Notes                                              |
|-------------------|---------|----------------------------------------------------|
| `_gr_price_label` | string  | Free-form, displayed on the card                   |
| `_gr_features`    | string  | Newline-separated list                             |
| `_gr_description` | string  | Short blurb                                        |
| `_gr_page_url`    | url     | Product page                                       |
| `_gr_payment_url` | url     | Cart / checkout link (falls back to `page_url`)    |
| `_gr_cta_label`   | string  | Button label (default "Add to cart")               |

### Product (WooCommerce)

The WooCommerce source reads native Woo fields (title, price HTML, image, add-to-cart URL). Products are referenced in rules by their post ID directly — no taxonomy involved.

### Scoring rules — option `gr_scoring_rules`

Each rule pairs a list of **conditions** (combined with AND or OR) with a list of **effects** that target specific products by ID:

```php
[
    [
        'id'              => 'rule_xyz',
        'name'            => 'Team of 1, low budget → push Starter',
        'condition_logic' => 'all', // 'all' (AND) or 'any' (OR)
        'conditions'      => [
            ['field_id' => '1', 'operator' => 'equals',  'value' => 'solo'],
            ['field_id' => '3', 'operator' => 'equals',  'value' => 'low'],
        ],
        'effects' => [
            ['action' => 'boost',   'product_id' => 42, 'points' => 30],
            ['action' => 'penalty', 'product_id' => 99, 'points' => 15],
            ['action' => 'exclude', 'product_id' => 17, 'points' => 0],
            ['action' => 'require', 'product_id' => 42, 'points' => 0],
        ],
    ],
]
```

Supported condition operators: `equals` (case-insensitive equality, with checkbox sub-field handling), `not_equals`, `contains` (substring), `not_empty`.

The rule builder in the admin UI only exposes Gravity Forms fields with restricted answers (radio, dropdown, checkbox, multiselect) and pre-populates the value selector with that field's actual choices.

### Form configuration — option `gr_form_config`

```php
[
    'form_id'  => 5,
    'field_id' => '44',  // hidden field that receives the JSON
]
```

### Source selector — option `gr_product_source`

`'cpt'` (default) or `'woocommerce'`.

## Scoring algorithm

1. Initialize every product to score 0.
2. For each rule:
   - Evaluate every condition against the GF entry (concatenating sub-fields for checkbox-type values, e.g. `2.1`, `2.2`).
   - If `condition_logic = 'all'`, the rule matches when every condition is true; if `'any'`, when at least one is true.
3. When a rule matches, apply each of its effects to the specified product:
   - `boost` → add `points` to that product's score.
   - `penalty` → subtract `points` from that product's score.
   - `exclude` → mark the product as hard-filtered.
   - `require` → mark the product as required.
4. Apply hard filters:
   - Drop excluded products.
   - If at least one product is required, drop every non-required product too.
5. Sort the eligible products by score descending (ties broken by post ID for stability).
6. If no product is eligible, run the `gr_ai_fallback_recommendation` filter — if a plugged-in AI returns a recommendation, use it; otherwise fall through to the error message.
7. Return the top product plus the next two as alternatives.

## Result format

The engine returns:

```json
{
  "recommended_product_id": 42,
  "alternative_products":   [17, 28],
  "explanation": "Based on your answers, we recommend Product Name."
}
```

This is written verbatim into the hidden GF field configured at setup. The shortcode reads it back at render time.

## Filters

| Filter                              | Purpose                                                            |
|-------------------------------------|--------------------------------------------------------------------|
| `gr_form_id`                        | Override the listened Gravity Form ID                              |
| `gr_field_id`                       | Override the hidden field that stores the JSON                     |
| `gr_recommendation_explanation`     | Replace the default "Based on your answers..." copy                |
| `gr_ai_fallback_recommendation`     | Plug an AI service to pick a product when no rule matches          |
| `gr_card_disclaimer`                | Inject a disclaimer line under the cards (defaults to empty)       |
| `gr_fallback_contact_url`           | URL used on the error fallback "Contact us" button                 |
| `gr_gravityforms_affiliate_url`     | URL used by the "Get Gravity Forms" CTA on step 1 of the wizard    |

## Requirements

- WordPress 6.0+
- PHP 8.0+
- Gravity Forms 2.9+
- (optional) WooCommerce for the Woo source

## Customizing for your site

1. **Add products** under **Recommender → All Products** (CPT) or **Products** (WooCommerce). Each rule will reference these products directly by ID.
2. **Run the setup wizard** at **Recommender → Setup** to pick the source, products, the form, and the hidden field. Then go to **Recommender → Scoring Rules** to define the "When → Then" rules.
3. **Override styling** by setting CSS variables in your theme:
   ```css
   :root {
       --gr-primary:        #1f2937;
       --gr-secondary:      #3b82f6;
       --gr-accent:         #f59e0b;
       --gr-font-heading:   'Inter', sans-serif;
       --gr-font-body:      'Inter', sans-serif;
   }
   ```
4. **Override scoring** through the filters above, or by calling `GR_Recommendation_Engine::recommend($entry)` from your own integration.
