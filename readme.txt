=== Gemini Product Autocomplete ===
Contributors: yourname
Tags: woocommerce, ai, gemini, product-description, seo
Requires at least: 6.0
Tested up to: 6.9
Stable tag: 1.2.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Auto-generate WooCommerce product descriptions and technical features using Google Gemini AI.

== Description ==

**Gemini Product Autocomplete** connects your WooCommerce store to Google's advanced Gemini AI models (Flash/Pro) to generate high-quality content in seconds.

Stop writing product descriptions manually! Whether you have 10 products or 10,000, this plugin automates the routine work. It finds technical specifications, writes engaging marketing copy, and creates feature lists based solely on the product title.

### FEATURES (FREE VERSION)

* **One-Click Generation:** Just enter a product title (e.g., "Samsung Galaxy S24") and click Generate.
* **Intelligent Descriptions:** Generates a concise Short Description and a detailed Long Description.
* **Technical Specs Finder:** Automatically searches for and lists technical features (Processor, Material, Dimensions, etc.).
* **Category Templates:** Define mandatory features for specific categories (e.g., ensure all "Laptops" have "RAM" and "CPU" fields).
* **Multi-language Support:** Works in English, Ukrainian, Polish, German, Spanish, French, and many more.
* **Clean Data:** AI is trained to remove marketing fluff from specs (e.g., cleans "2.2 GHz Octa-core" to just "Helio G99").

### PRO FEATURES (Available on our website)

Upgrade to Pro for advanced tools like bulk generation and SEO integration. Visit [https://your-site.com/pro](https://your-site.com/pro) for details and purchase.

* **Bulk Generation (Smart Queue):** Select 50+ products in the list and let the AI process them in the background without timeouts.
* **Native WooCommerce Attributes:** Automatically creates real, filterable attributes (Global Attributes) instead of just text.
* **SEO Optimization:** Generates Focus Keywords, Meta Titles, and Meta Descriptions for Yoast SEO / RankMath.
* **Social Media Ready:** Generates ready-to-post captions for Instagram/Facebook.
* **Niche Presets:** Optimized prompts for **Apparel, Electronics, Beauty, Automotive, and Home** goods.
* **Tone of Voice:** Choose between Neutral, Persuasive (Sales), Playful, or Luxury styles.
* **Advanced Model Selection:** Access to Gemini Pro models for deeper reasoning.

### FREE VS PRO

| Feature | Free Version | PRO Version |
| :--- | :---: | :---: |
| **Generation Mode** | Single Product (Manual) | **Bulk Generation (Automatic)** |
| **Attributes** | Visual Text Table | **Real Filterable Attributes** |
| **SEO Meta Tags** | -- | ** Yoast / RankMath** |
| **SMM Posts** | -- | ** Instagram / Facebook** |
| **Tone of Voice** | Neutral Only | **Selectable (Sales, Luxury, etc)** |
| **Niche Presets** | Generic | **Optimized (Fashion, Tech, Auto)** |
| **Limits** | Standard API Limits | **Smart Queue (No Timeouts)** |

== Installation ==

1.  Upload the plugin files to the `/wp-content/plugins/gemini-product-autocomplete` directory, or install the plugin through the WordPress plugins screen.
2.  Activate the plugin through the 'Plugins' screen in WordPress.
3.  Go to **WooCommerce > Gemini Autocomplete** to enter your API Key.

**How to get a FREE API Key:**
1.  Go to [Google AI Studio](https://aistudio.google.com/app/apikey).
2.  Click "Create API Key".
3.  Copy the key and paste it into the plugin settings.

== Frequently Asked Questions ==

= Is the API free? =
Yes! Google currently offers a generous free tier for the Gemini API (approx. 15 requests/minute), which is sufficient for most stores. Heavy usage may require a paid plan from Google.

= Does it support variable products? =
It generates descriptions and attributes for the parent product. You can then use the generated attributes (Pro version) to create variations manually.

= Does it work with Yoast SEO? =
Yes (Pro version only). It automatically fills the Focus Keyword, Meta Title, and Meta Description fields. Available at [https://your-site.com/pro](https://your-site.com/pro).

= How accurate is the data? =
The plugin uses Google Search grounding to find real specs. However, AI can occasionally hallucinate. We recommend reviewing the data before publishing, especially for medical or safety-critical products.

= Where can I get the Pro version? =
The Pro version with advanced features is available for purchase on our website: https://checkout.freemius.com/plugin/23001/plan/38599/.

== Changelog ==

= 1.2.0 =
* **New:** Added "Smart Queue" for Bulk Generation (prevents timeouts on large batches).
* **New:** Option to save features as native WooCommerce Attributes (Pro).
* **New:** Niche Presets (Apparel, Electronics, Beauty, Automotive).
* **New:** Social Media Post generation.
* **Improved:** Prompt logic for cleaner technical specifications.
* **Fix:** UI improvements for mobile devices.

= 1.1.0 =
* Added Category Templates support.
* Added support for Ukrainian and Polish languages.
* Improved JSON parsing stability.

= 1.0.0 =
* Initial Release.