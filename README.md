# Rockside Regional WooCommerce

**Custom WordPress / WooCommerce plugin for regional storefronts, pricing, product availability, SEO, and Google Merchant Center inventory feeds.**

Built to support city-specific shopping experiences within a single WooCommerce installation, without creating a separate store for each location.

**Stack:** PHP · WordPress · WooCommerce · WordPress hooks & rewrite rules · XML · JavaScript · CSS  
**Plugin version in this repository:** `0.1.0`

## The challenge

An e-commerce store serving multiple cities may need different product prices, assortment visibility, landing pages, and search-engine URLs for each region. A single global WooCommerce catalogue does not handle those requirements on its own.

Rockside Regional WooCommerce adds a region context throughout the storefront while keeping the underlying WordPress and WooCommerce installation shared.

## Key features

| Area | Implementation |
| --- | --- |
| **Regional routing** | City-specific URL prefixes, WordPress rewrite rules, and region-aware navigation |
| **Regional pricing** | Separate product and variation prices with fallback to the regular WooCommerce price |
| **Catalogue visibility** | Per-region product, variation, and category restrictions, including cart validation |
| **Cart consistency** | Regional price and context preservation during cart and session calculations |
| **Regional content** | Linked city-specific pages/posts, relevant URLs, and settings for category visibility |
| **SEO** | Regional canonical URLs, titles/descriptions, Yoast SEO integration, and robots handling |
| **Region selection** | Responsive city switcher and an optional first-visit recommendation popup |
| **Google Merchant Center** | Regional inventory XML feeds derived from an existing primary product feed |
| **Cache behavior** | Region-aware cache headers and protections for query-string region requests |

### Supported regions

| Region | Storefront URL pattern |
| --- | --- |
| Kharkiv | `/` (default region) |
| Kyiv | `/kyiv/` |
| Lviv | `/lviv/` |
| Odesa | `/odesa/` |

The routing also supports regional product pages, product categories, content pages, and configured portfolio/project paths.

### Regional product management

Store managers can configure prices and availability in WooCommerce product settings. The plugin handles simple products and product variations, including regional values for Kyiv, Lviv, and Odesa. Kharkiv uses the base storefront configuration.

Regional rules extend beyond the product page to catalogue queries, variation selection, cart calculations, related products, category navigation, and purchase validation.

### Regional SEO

The plugin provides region-aware links and canonical URLs, supports regional metadata for content pages, and integrates with Yoast SEO filters when Yoast is present. This helps regional pages remain navigable and reduces the risk of conflicting URL variants.

### Google Merchant Center regional feeds

Alongside the primary Google product feed, the plugin exposes regional inventory endpoints:

```text
/regional-feed/kyiv/
/regional-feed/lviv/
/regional-feed/odesa/
```

Each XML feed contains regional product identifiers, prices, and availability values using Google's `g:` namespace. The plugin reads product IDs from the configured primary feed to align inventory entries with the main product catalogue.

These are **regional inventory feeds**, not replacements for the full primary product feed.

## Installation

1. Install and activate **WooCommerce** on WordPress.
2. Copy this repository into `wp-content/plugins/regional-woo/` (keeping `rockside-regional-woo.php` at the plugin root).
3. Activate **Rockside Regional WooCommerce** under **Plugins**.
4. Open **Settings → Rockside Regions** to configure enabled switcher regions, the region recommendation popup, and the primary Google feed URL.
5. Set regional prices and visibility rules on products, variations, and product categories.
6. Add the switcher where needed with the shortcode:

   ```text
   [rockside_region_switcher]
   ```

   An optional `mobile="1"` attribute is available for the mobile-oriented layout.

7. If regional routes do not resolve after activation, resave **Settings → Permalinks** to regenerate WordPress rewrite rules.

**Optional integration:** The region recommendation popup can use the GeoIP Detection function `geoip_detect2_get_info_from_current_ip()` when available. Without it, the code falls back to Kharkiv.

## Project structure

```text
regional-woo/
├── rockside-regional-woo.php     # Plugin bootstrap and WooCommerce check
├── includes/
│   ├── class-plugin.php         # Module initialization
│   ├── class-region-context.php # Region detection and rewrite rules
│   ├── class-pricing.php        # Pricing, visibility, cart rules
│   ├── class-admin.php          # WooCommerce fields and WP settings
│   ├── class-seo.php            # SEO and regional URL handling
│   ├── class-inventory-feed.php # Google regional inventory XML
│   ├── class-switcher.php       # City switcher shortcode
│   ├── class-popup.php          # Region recommendation popup
│   ├── class-cache.php          # Region-specific cache handling
│   └── class-output-fix.php     # Frontend output adjustments
└── README.md
```

## Engineering approach

The plugin is organized into focused PHP classes rather than one large collection of theme functions. It uses standard WordPress and WooCommerce hooks to integrate regional behavior into existing product, cart, navigation, and SEO workflows.

It is a **project-specific implementation**. Before reuse on another store, review its URL structures, category/content relationships, primary-feed format, theme-specific behavior, and caching configuration. No automated compatibility or production test suite is included in this repository.

## Author

Developed by **[Vyacheslav Filimonenko](https://github.com/filimonenkovyacheslav)** — Full-Stack Developer specializing in PHP, Laravel, WordPress/WooCommerce, and API integrations.

*Portfolio project — custom regional e-commerce functionality for WooCommerce.*
