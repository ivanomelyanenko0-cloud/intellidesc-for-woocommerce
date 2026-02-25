=== IntelliDesc for WooCommerce ===
Contributors: lukystile
Tags: woocommerce, ai, gemini, product-description, seo
Requires at least: 6.0
Tested up to: 6.9
Stable tag: 1.3.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Auto-generate WooCommerce product descriptions and intelligently extract technical features using AI.

== Description ==

**IntelliDesc for WooCommerce** connects your store to advanced AI models (powered by Google Gemini) to instantly generate high-quality product content and extract technical specifications.

Stop writing product descriptions manually! Whether you have 10 products or 10,000, this plugin automates the routine work. It doesn't just write text — its Smart Specs Extractor finds technical specifications and creates clean feature lists based solely on the product title.

== External services ==

This plugin relies on a third-party service, the Google Gemini API, to generate intelligent product descriptions and extract technical features. 
When a user clicks the "Generate Content" button, the plugin sends the Product Title to the Google Gemini API (`https://generativelanguage.googleapis.com/v1beta/models/`) to process the request and return the generated text and features. No personal user data or customer information is sent to this service.

This service is provided by Google LLC. 
* Google APIs Terms of Service: https://developers.google.com/terms
* Google Privacy Policy: https://policies.google.com/privacy

### FEATURES (FREE VERSION)

* **Smart Specs Extractor:** Automatically searches for and extracts technical features (Processor, Material, Dimensions, etc.) into a clean table.
* **Intelligent Descriptions:** Generates a concise Short Description and a detailed Long Description.
* **One-Click Generation:** Just enter a product title (e.g., "Samsung Galaxy S24") and click Generate.
* **Category Templates:** Define mandatory features for specific categories (e.g., ensure all "Laptops" have "RAM" and "CPU" fields).
* **Clean Data:** AI is trained to remove marketing fluff from specs (e.g., cleans "2.2 GHz Octa-core" to just "Helio G99").
* **Multi-language Support:** Works in English, Ukrainian, Polish, German, Spanish, French, and many more.

### PRO FEATURES (Available on our website)

Upgrade to Pro for advanced workflow automation and deeper WooCommerce integration. Visit [freemius](https://checkout.freemius.com/plugin/23001/plan/38599/) for details.

* **Native WooCommerce Attributes:** Automatically creates real, filterable Global Attributes instead of just visual tables.
* **Bulk Generation (Smart Queue):** Select 50+ products in the list and let the AI process them in the background without timeouts.
* **SEO Optimization:** Generates Focus Keywords, Meta Titles, and Meta Descriptions for Yoast SEO / RankMath.
* **Social Media Ready:** Generates ready-to-post captions for Instagram/Facebook.
* **Niche Presets:** Optimized AI instructions for Apparel, Electronics, Beauty, Automotive, and Home goods.
* **Tone of Voice:** Choose between Neutral, Persuasive (Sales), Playful, or Luxury styles.

### FREE VS PRO

| Feature | Free Version | PRO Version |
| :--- | :---: | :---: |
| **Generation Mode** | Single Product (Manual) | **Bulk Generation (Automatic)** |
| **Features Storage** | Visual Text Table | **Real Filterable Attributes** |
| **SEO Meta Tags** | -- | **Yoast / RankMath Integration** |
| **SMM Posts** | -- | **Instagram / Facebook Ready** |
| **Tone of Voice** | Neutral Only | **Selectable (Sales, Luxury, etc)** |
| **Niche Presets** | Generic | **Optimized (Fashion, Tech, Auto)** |
| **Queue System** | Standard API Limits | **Smart Queue (Prevents Timeouts)** |

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/intellidesc-for-woocommerce` directory, or install the plugin through the WordPress plugins screen.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Go to **WooCommerce > IntelliDesc** to enter your API Key.

**How to get a FREE API Key:**
1. Go to [Google AI Studio](https://aistudio.google.com/app/apikey).
2. Click "Create API Key".
3. Copy the key and paste it into the plugin settings.

== Frequently Asked Questions ==

= Is the API free? =
Yes! Google currently offers a generous free tier for the Gemini Flash models (approx. 15 requests/minute), which is sufficient for most stores. Heavy usage may require a paid plan directly from Google.

= Does it support variable products? =
It generates descriptions and attributes for the parent product. You can then use the generated attributes (in the Pro version) to create variations.

= Does it work with Yoast SEO? =
Yes, in the Pro version. It automatically fills the Focus Keyword, Meta Title, and Meta Description fields.

= How accurate is the data? =
The plugin uses Google Search grounding to find real specs. However, AI can occasionally hallucinate. We highly recommend reviewing the data before publishing, especially for medical or safety-critical products.

== Changelog ==

= 1.3.0 =
* Important: Plugin renamed to IntelliDesc for WooCommerce.
* Improved: Better compliance with WordPress trademark guidelines.
* Improved: UI and prefix structure updated.

= 1.2.0 =
* New: Added "Smart Queue" for Bulk Generation (Pro).
* New: Option to save features as native WooCommerce Attributes (Pro).
* New: Social Media Post generation.

= 1.1.0 =
* Added Category Templates support.
* Added support for Ukrainian and Polish languages.

= 1.0.0 =
* Initial Release.