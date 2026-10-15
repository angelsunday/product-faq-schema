=== Product FAQ Schema ===
Contributors: angelsunday
Tags: faq schema, json-ld, woocommerce, seo, structured data
Requires at least: 5.5
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later

Automatically generates FAQPage JSON-LD schema for every WooCommerce product.

== Description ==

Product FAQ Schema adds valid FAQPage structured data to each product page, with no manual work.

* Builds questions from the product description and custom fields, plus automatic questions from WooCommerce data: price, availability, SKU, category, weight and dimensions.
* Works with Greek and English (templates follow the site language).
* Settings page: global toggle, sources, templates, maximum FAQs.
* Per-product switch to disable the schema.
* Manual FAQs per product, translatable with WPML (a product without manual FAQs falls back to automatic ones).

== Installation ==

1. Upload the `product-faq-schema` folder to `/wp-content/plugins/`.
2. Activate the plugin from the Plugins menu.
3. Optionally configure it at Settings > Product FAQ Schema.

== Changelog ==

= 0.1.0 =
* Initial release.
