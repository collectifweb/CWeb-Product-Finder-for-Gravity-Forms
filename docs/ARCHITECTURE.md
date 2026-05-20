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
│   ├── class-product-source.php        # Interface + factory
│   ├── class-cpt-source.php            # Source impl: built-in CPT
│   ├── class-woo-source.php            # Source impl: WooCommerce
│   ├── class-recommendation-engine.php # Tag-based scoring
│   ├── class-gf-integration.php        # Hooks gform_entry_post_save + gform_confirmation
│   ├── class-shortcode-handler.php     # Shortcode [gravity_recommender]
│   └── class-admin-onboarding.php      # 5-step setup wizard
├── templates/
│   └── example-form.json               # GF export, importable from the wizard
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
| `_gr_tags`        | string  | Comma-separated tag list, lowercased on read       |

### Product (WooCommerce)

The WooCommerce source reads native Woo fields (title, price HTML, image, add-to-cart URL) and treats the WooCommerce **product tags taxonomy** (`product_tag`) as the source of scoring tags.

### Scoring rules — option `gr_scoring_rules`

Stored as an array of rule rows:

```php
[
    [
        'field_id' => '20',          // Gravity Form field ID (string)
        'match'    => '20000',       // substring matched case-insensitively
        'action'   => 'boost',       // boost | exclude | require
        'tag'      => 'high-traffic',
        'points'   => 20,            // used for boost only
    ],
    ...
]
```

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
2. For each rule, fetch the relevant field's value from the GF entry (concatenating sub-fields for checkbox-type fields, e.g. `21.1`, `21.2`).
3. If the rule's `match` substring is found in the answer:
   - `boost` → add `points` to every product carrying the tag.
   - `exclude` → subtract 10,000 from every product carrying the tag (effectively hard-filter).
   - `require` → add 1,000 to products carrying the tag, subtract 1,000 from those without (hard preference).
4. Sort by score descending.
5. Drop products hard-excluded.
6. Return the top product, plus the next two as alternatives.

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

| Filter                            | Purpose                                                  |
|-----------------------------------|----------------------------------------------------------|
| `gr_form_id`                      | Override the listened Gravity Form ID                    |
| `gr_field_id`                     | Override the hidden field that stores the JSON           |
| `gr_recommendation_explanation`   | Replace the default explanation copy                     |
| `gr_card_disclaimer`              | Inject a disclaimer line under the cards (defaults empty)|
| `gr_fallback_contact_url`         | URL used on the error fallback "Contact us" button       |

## Requirements

- WordPress 6.0+
- PHP 8.0+
- Gravity Forms 2.9+
- (optional) WooCommerce for the Woo source

## Customizing for your site

1. **Add products** under **Recommender → All Products** (CPT) or **Products** (WooCommerce). Tag each product with the keywords you'll match against in your rules.
2. **Run the setup wizard** at **Recommender → Setup** to pick the form, the hidden field, and define rules.
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
