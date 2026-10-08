=== IntelliDesc for WooCommerce ===
Contributors: lukystile
Tags: woocommerce, ai, product description, seo, openai
Requires at least: 6.0
Tested up to: 7.1
Stable tag: 1.10.1
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI product description generator for WooCommerce. Auto-write SEO product descriptions & specs with Gemini, Claude, OpenAI, Grok, or OpenRouter.

== Description ==

**IntelliDesc for WooCommerce** is an AI product description generator built specifically for WooCommerce. Give it a product title and it writes a complete, SEO-friendly listing — a short description, a long description, and a clean technical specifications table — in seconds.

Stop writing product descriptions by hand. Whether your catalog has 10 SKUs or 10,000, IntelliDesc automates the most repetitive part of running a store. It doesn't just generate filler text: its Smart Specs Extractor researches the product and pulls out real technical details (Processor, Material, Dimensions, Battery Capacity, and more) into a clean, structured feature list.

**[Try the live demo](https://founder.cognitolab.net/demo/index.html)** — generate a real description right in your browser, no install or API key needed.

= Choose your own AI provider =

Most "AI product description" plugins lock you into a single vendor. IntelliDesc doesn't. Pick the AI engine that powers your content, right from the plugin settings:

* **Google Gemini** — fast and cost-effective, with built-in Google Search grounding for more accurate specs.
* **Anthropic Claude** — for stores that prefer Claude's writing style and reasoning.
* **OpenAI** — the same GPT models that power ChatGPT.
* **xAI Grok** — xAI's latest models.
* **OpenRouter** — one API key, hundreds of models from every major provider, some with a free tier.
* **WordPress AI (WordPress 7.0+)** — use the AI provider your site already has configured under Settings → Connectors, with no separate key in IntelliDesc.

Bring your own API key for any of the five direct providers (or reuse your site's WordPress AI connector), switch providers anytime from **WooCommerce → IntelliDesc**, and stay in full control of your AI costs.

= Built with WooCommerce SEO in mind =

Every generated description is written to help your product pages rank and convert: clean, keyword-relevant short and long descriptions, a structured specs table that reads well to shoppers and search engines alike, and — in the Pro version — direct SEO meta title/description generation for Yoast SEO and RankMath.

== External services ==

This plugin connects to a third-party AI service to generate product descriptions and extract technical features. You choose which service to use under **WooCommerce → IntelliDesc** and supply your own API key for it — nothing is sent anywhere until you click "Generate Content" or "Generate FAQ".

When you click "Generate Content", the plugin sends the product title (and, if present, your existing feature/attribute data and short description notes) to the selected provider's API, which returns the generated description and specs. When you click "Generate FAQ", it sends the product title, its current short/long description and its specs so the questions and answers stay consistent with your listing. No personal customer data, order information, or store data beyond the product content itself is ever sent to these services.

Depending on which provider you select, the plugin talks to one of the following:

**Google Gemini** (default provider)
* Endpoint: `https://generativelanguage.googleapis.com/v1beta/models/`
* Terms of Service: https://developers.google.com/terms
* Privacy Policy: https://policies.google.com/privacy

**Anthropic Claude**
* Endpoint: `https://api.anthropic.com/v1/messages`
* Terms of Service: https://www.anthropic.com/legal/commercial-terms
* Privacy Policy: https://www.anthropic.com/legal/privacy

**OpenAI**
* Endpoint: `https://api.openai.com/v1/chat/completions`
* Terms of Service: https://openai.com/policies/terms-of-use
* Privacy Policy: https://openai.com/policies/privacy-policy

**xAI (Grok)**
* Endpoint: `https://api.x.ai/v1/chat/completions`
* Terms of Service: https://x.ai/legal/terms-of-service
* Privacy Policy: https://x.ai/legal/privacy-policy

**OpenRouter**
* Endpoint: `https://openrouter.ai/api/v1/chat/completions`
* Terms of Service: https://openrouter.ai/terms
* Privacy Policy: https://openrouter.ai/privacy
* Note: OpenRouter itself routes your request to one of many underlying model providers (OpenAI, Anthropic, Google, Meta, and others) depending on the model you select — see OpenRouter's own policies for how it handles data passed to those upstream providers.

**WordPress AI (site connectors, WordPress 7.0+)**
* When you select "WordPress AI (site settings)", IntelliDesc does not contact any AI service itself: it hands the same product content to the WordPress AI Client in WordPress core, which sends it to whichever provider the site owner configured under Settings → Connectors (for example Google or OpenAI). That provider's own terms of service and privacy policy apply.

### FEATURES (FREE VERSION)

* **Multi AI Provider Support:** Generate content with Google Gemini, Anthropic Claude, OpenAI, xAI Grok, or OpenRouter — switch anytime, using your own API key — or with the AI connector configured in WordPress 7.0+ (Settings → Connectors).
* **Product FAQ Block:** One click writes 3 customer questions and answers for a product, based on its description and specs. Edit or remove them, and they appear in a "FAQ" tab on the product page.
* **Shortcodes & Blocks for Page Builders:** Show the FAQ, the specs table or a product description anywhere — Elementor, Divi, Bricks, WPBakery, landing pages — with `[ildesc_faq]`, `[ildesc_specs]` and `[ildesc_field]`, or with the IntelliDesc FAQ and IntelliDesc Specs blocks in the block editor. The AI Features box lists ready-to-copy shortcodes for each product.
* **Smart Specs Extractor:** Automatically searches for and extracts technical features (Processor, Material, Dimensions, etc.) into a clean table.
* **Intelligent Descriptions:** Generates a concise Short Description and a detailed Long Description.
* **One-Click Generation:** Just enter a product title (e.g., "Samsung Galaxy S24") and click Generate.
* **Category Templates:** Define mandatory features for specific categories (e.g., ensure all "Laptops" have "RAM" and "CPU" fields).
* **Unit & Format Rules:** Define a per-feature dictionary of formatting rules (e.g., "Battery Capacity → Output ONLY in mAh"). The AI strictly follows these rules for every generation.
* **Clean Data:** AI is trained to remove marketing fluff from specs (e.g., cleans "2.2 GHz Octa-core" to just "Helio G99").
* **AI Model Selection:** Choose which model to use for generation directly from plugin settings.
* **Generate Descriptions Only:** Skip spec extraction entirely and generate only Short and Long Descriptions — useful for stores that manage attributes manually or want faster, token-light generation.
* **Multi-language Support:** Works in English, Ukrainian, Polish, German, Spanish, French, and many more.
* **Duplicate Content Scan:** Scan your whole catalog for products with identical or near-identical descriptions and see exactly which products are affected (WooCommerce → IntelliDesc Duplicates), so you can fix thin/duplicate content before it hurts your SEO.
* **AI Usage Dashboard:** See how many requests you sent to each AI provider and how many tokens they used over the last 30 days, including failed requests and web searches (WooCommerce → IntelliDesc Usage) — so a bulk run never comes as a surprise on your provider's bill.

### PRO FEATURES (Available on our website)

Upgrade to Pro for advanced workflow automation and deeper WooCommerce integration. Visit [our website](https://cognitolab.net/products/intellidesc-wordpress) for details, or [try the Pro live demo](https://founder.cognitolab.net/demo-pro/index.html) directly.

* **Native WooCommerce Attributes:** Automatically creates real, filterable Global Attributes instead of just visual tables.
* **Bulk Generation (Smart Queue):** Select 50+ products in the list and let the AI process them in the background without timeouts.
* **SEO Optimization:** Generates Focus Keywords, Meta Titles, and Meta Descriptions for Yoast SEO / RankMath.
* **Social Media Ready:** Generates ready-to-post captions for Instagram/Facebook.
* **Niche Presets:** Optimized AI instructions for Apparel, Electronics, Beauty, Automotive, and Home goods.
* **Tone of Voice:** Choose between Neutral, Persuasive (Sales), Playful, or Luxury styles.
* **Extended Duplicate Scan:** Clickable links straight to each affected product, a similarity score for every near-duplicate group, and a separate Thin Content report flagging descriptions under a configurable word count.
* **Bigger FAQ Blocks:** Up to 10 questions per product, add your own questions, FAQPage structured data (JSON-LD) for AI answer engines, and FAQ generation in bulk and WP-CLI runs.
* **Shortcode Extras:** FAQPage structured data also for FAQs shown by shortcode or block outside the product page, striped and compact specs table styles, and `[ildesc_field field="smm"]` for the social post.
* **Spend by Field:** See roughly how much of your AI spend goes to short descriptions, long descriptions, specs, social posts, SEO meta and FAQs.
* **Extended Usage Dashboard:** Pick a 7/30/90-day period, see a daily chart, a per-model breakdown and an estimated cost (from the per-million-token and per-thousand-search prices you enter; OpenRouter's real per-request cost is used as reported), and export everything to CSV.

### FREE VS PRO

The Free version covers everything you need to generate AI product descriptions and specs one product at a time, with your choice of AI provider. The Pro version adds:

* Bulk generation for 50+ products at once via a Smart Queue, instead of one product at a time.
* Features saved as real, filterable WooCommerce Attributes, instead of a visual text table.
* Automatic SEO meta titles/descriptions for Yoast SEO and RankMath.
* Ready-to-post Instagram/Facebook captions generated alongside each product.
* Selectable Tone of Voice (Persuasive, Playful, Luxury, Minimalist) instead of neutral-only copy.
* Duplicate Content Scan results link directly to each product and show a similarity score, plus a separate Thin Content report — instead of just a list of names.

* Up to 10 FAQ questions per product (instead of 3), your own added questions, FAQ schema markup and bulk FAQ generation.
* Shortcode extras: FAQ schema for FAQs placed outside the product page, specs table styles and a social-post shortcode.

Unit & Format Rules, AI Model/Provider Selection, the FAQ tab and the shortcodes/blocks are available in both versions.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/intellidesc-for-woocommerce` directory, or install the plugin through the WordPress plugins screen.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Go to **WooCommerce > IntelliDesc**, choose your AI provider (Gemini, Claude, OpenAI, Grok, or OpenRouter), and enter the matching API key. On WordPress 7.0+ you can instead choose "WordPress AI (site settings)" to use the connector configured under Settings → Connectors.

**How to get a FREE API Key (Google Gemini):**
1. Go to [Google AI Studio](https://aistudio.google.com/app/apikey).
2. Click "Create API Key".
3. Copy the key and paste it into the plugin settings.

Prefer a different provider? Get your API key from [Anthropic Console](https://console.anthropic.com/settings/keys), [OpenAI Platform](https://platform.openai.com/api-keys), [xAI Console](https://console.x.ai), or [OpenRouter](https://openrouter.ai/keys), then select that provider in the plugin settings.

== Frequently Asked Questions ==

= Which AI providers does this plugin support? =
Google Gemini, Anthropic Claude, OpenAI, xAI Grok, and OpenRouter. Pick any of them under WooCommerce → IntelliDesc and supply your own API key — no vendor lock-in. On WordPress 7.0+ you can also use the site-wide WordPress AI connector.

= Can I use the AI provider I already set up in WordPress? =
Yes, on WordPress 7.0 or newer. Configure a connector under Settings → Connectors, then choose "WordPress AI (site settings)" as the AI Provider in IntelliDesc — no API key is stored in the plugin. Web search depends on the connector: if it can't search the web, content is still generated but from the model's own knowledge, so specs are more often marked for review. On a fresh install with no IntelliDesc API key, WordPress AI is picked automatically when it is available; existing setups keep their provider.

= How does the FAQ block work? =
Click "Generate FAQ" in the AI Features box on the product edit screen. The AI writes customer questions and answers based on the product's current description and specs (it is a separate request from "Generate Content"). You can edit the answers before saving, and the FAQ appears in its own "FAQ" tab on the product page (you can turn the tab off in settings). The free version writes 3 questions; PRO lets you choose 3–10, add your own and output FAQPage structured data.

= How do I show the FAQ or specs in Elementor, Divi or another page builder? =
Use the shortcodes: `[ildesc_faq]` for the FAQ, `[ildesc_specs]` for the specs table, and `[ildesc_field field="long"]` or `[ildesc_field field="short"]` for a description. Paste them into your builder's Shortcode widget (or the Shortcode block). On a product page or product template they show the current product; anywhere else add the product ID, e.g. `[ildesc_faq id="123"]` — the AI Features box on each product lists them ready to copy. Optional attributes: `title="..."` adds a heading and `class="..."` a CSS class. In the block editor you can use the IntelliDesc FAQ and IntelliDesc Specs blocks instead. If the FAQ tab is also on, a FAQ placed on the product page itself appears twice — turn the tab off under WooCommerce → IntelliDesc → FAQ. PRO adds `style="striped"` / `style="compact"` for the specs table, `[ildesc_field field="smm"]` for the social post, and FAQ schema for FAQs placed on other pages.

= I'm using xAI Grok — does it fully work? =
Grok support is newer than our Gemini/Claude/OpenAI integrations. It should work the same way, but if you're using it, we'd love to hear how it's going — please leave a review or a note in the support forum letting us know it works for you (or if you hit an issue).

= What is OpenRouter, and why would I use it instead of a direct provider? =
OpenRouter is a single API that gives you access to models from OpenAI, Anthropic, Google, Meta, and many others through one account and one key — handy if you want to try different models without signing up for each provider separately, or if you already have OpenRouter credit for other tools. Select "OpenRouter" as your AI Provider and pick any of its listed models.

= Is the API free? =
It depends on the provider. Google currently offers a generous free tier for the Gemini Flash models (5–15 requests/minute depending on the model version), which is sufficient for most stores. Anthropic, OpenAI, and xAI are pay-as-you-go. OpenRouter is pay-as-you-go too, though it lists some free-tier models. Heavy usage may require a paid plan directly from your chosen provider.

= Does it support variable products? =
It generates descriptions and attributes for the parent product. You can then use the generated attributes (in the Pro version) to create variations.

= Does it work with Yoast SEO? =
Yes, in the Pro version. It automatically fills the Focus Keyword, Meta Title, and Meta Description fields.

= How accurate is the data? =
When using Google Gemini, the plugin uses Google Search grounding to find real specs. However, AI can occasionally hallucinate, regardless of provider. We highly recommend reviewing the data before publishing, especially for medical or safety-critical products.

= Does the Duplicate Content Scan send my product data to an AI provider? =
No. The scan compares your existing product descriptions entirely on your own server — nothing is sent to Gemini, Claude, OpenAI, xAI, OpenRouter, or any other third party. It only calls out to your chosen AI provider when you click "Generate Content".

= What happens to my data if I delete the plugin? =
Deleting the plugin from the Plugins screen removes its settings (including your API keys), usage statistics, the last duplicate-scan report, cached model lists, and the per-product undo history. The AI-generated features, FAQs and social posts saved on your products are kept, since that is your content. If you switch between the Free and PRO editions, nothing is removed while the other edition is still installed, so your settings carry over.

== Screenshots ==

1. Generate a full product description in one click from the product edit screen — powered by your chosen AI provider.
2. Editable AI-generated technical specs table, ready to review before publishing.
3. Choose your AI provider (Gemini, Claude, OpenAI, Grok, or OpenRouter) and manage API keys and models from one settings screen.
4. Catalog-wide Duplicate Content Scan finds exact and near-duplicate product descriptions automatically.

== Changelog ==

= 1.10.1 =
* New: **Shortcodes for page builders** — `[ildesc_faq]`, `[ildesc_specs]` and `[ildesc_field field="short|long"]` show IntelliDesc content in Elementor, Divi, Bricks, WPBakery, custom templates and other pages. Without `id` they show the current product; `title` and `class` are optional.
* New: **IntelliDesc FAQ and IntelliDesc Specs blocks** for the block editor, with a product picker and a live preview (Single Product templates and Query Loops use the current product).
* New: the AI Features box lists ready-to-copy shortcodes for the product.

= 1.10.0 =
* New: **FAQ block** — a "Generate FAQ" button in the AI Features box writes 3 customer questions and answers based on the product's description and specs. Edit or remove them before saving; they are shown in a new "FAQ" tab on the product page (can be turned off under WooCommerce → IntelliDesc → FAQ).
* New: **WordPress AI provider** (WordPress 7.0+) — choose "WordPress AI (site settings)" to generate content through the AI connector configured under Settings → Connectors, without storing an API key in IntelliDesc. Existing setups keep their current provider.
* Improved: the Usage dashboard also counts FAQ requests and WordPress AI requests.

= 1.9.7 =
* New: **AI Usage dashboard** (WooCommerce → IntelliDesc Usage) — shows the number of requests, failed requests, web searches, and input/output tokens for each AI provider over the last 30 days, across all five providers. Usage is counted locally on your site from each provider's API response; nothing extra is sent anywhere. Includes a "Reset statistics" button.
* New: **Clean uninstall** — deleting the plugin now removes its settings (including stored API keys), usage statistics, and cached data. Your AI-generated product content is kept.

= 1.9.5 =
* New: **OpenRouter support** — a fifth AI provider option (WooCommerce → IntelliDesc → AI Provider). One API key gives you access to models from OpenAI, Anthropic, Google, Meta, and many others; useful if you already have OpenRouter credit or want to experiment across providers without juggling multiple accounts.

= 1.9.4 =
* New: **Content History** — every time AI generation overwrites an existing description, the previous short/long description and features are saved. Use the "Undo Last AI Generation" button in the AI Features box to revert your most recent change.
* New: AI generation now uses real web search on **all four providers** — previously only Gemini could search the web; Claude, OpenAI, and Grok now verify facts online too instead of relying purely on the model's training data.
* New: **Generic / unbranded product support** — for items with no findable brand or web listing (bulk goods, craft supplies, generic hardware, etc.), the AI now writes a plausible description from general product knowledge and the details already in the title, instead of returning a weak or empty result. A small notice appears whenever this fallback was used, so you know to double-check the details before publishing.
* Fixed: if the AI returned a completely empty description, the plugin used to silently save it as a "successful" generation. It now shows a clear error instead, so you know to retry or try a different provider/model.

= 1.9.3 =
* New: **Duplicate Content Scan** (WooCommerce → IntelliDesc Duplicates) — scans your whole catalog for products with identical or near-identical AI-generated descriptions and lists the affected products, so you can spot and fix thin/duplicate content before it hurts your SEO.

= 1.9.2 =
* Fixed: The automatic fallback model (used to retry once when your selected model returns a "not found" error) was only ever set for Google Gemini — Claude, OpenAI, and Grok requests had no fallback and simply failed. Every provider now retries against its own known-good fallback model.
* Fixed: The Anthropic and xAI model lists could include non-chat models (e.g. image-generation models) since those two providers had no filtering. All four providers' model lists are now restricted to text/vision chat-completion models only.
* Added: Live demo links (Free and Pro) in this readme and in the Pro upsell banner on the Settings page.

= 1.9.1 =
* New: **Model Advisor** — the Settings page now suggests when a newer model has become available for your currently configured AI provider, with a one-click dismiss.
* New: Added translations — Ukrainian, Polish, German, French, Spanish, and Italian.
* Fixed: A few UI strings (clear-description confirmation, error fallback text, feature-table placeholders) weren't passed through translation.
* Improved: Updated the link to our website in this readme.

= 1.9.0 =
* New: **Currently active model** status line on the Settings page for every provider (Gemini, Claude, OpenAI, Grok) — shows exactly which model id is saved and its human-readable name, even if it's no longer present in the live model list.
* New: Warning shown when your selected model starts failing with a "model not found" error (e.g. a provider deprecates or renames a model) — displayed inline in Settings next to the affected provider, and as a dismissible notice on the Settings page and product edit screens.
* Fixed: Gemini's automatic fallback model was hardcoded to `gemini-2.5-flash`, which Google has since restricted for new accounts — now uses `gemini-3.1-flash-lite`.

= 1.8 =
* New: **Multi AI Provider Support** — choose between Google Gemini, Anthropic Claude, OpenAI, or xAI Grok as the AI engine behind content generation (WooCommerce → IntelliDesc → AI Provider). Bring your own API key for any provider and switch anytime, at no extra cost.
* New: Dedicated API key and model fields for Anthropic Claude, OpenAI, and xAI Grok, with a curated model list per provider.
* Improved: API error messages are now provider-aware — invalid key, rate limit, and server error messages now name the specific provider that returned them (Gemini, Claude, OpenAI, or Grok).
* Improved: Settings and product editor copy no longer hardcodes "Gemini" in generic labels (e.g., "AI Actions" metabox title, "Sending request to AI..." status text) — the plugin now reads correctly regardless of which provider is selected.

= 1.7 =
* New: **AI Model Selection** — choose the Gemini model directly from plugin settings (WooCommerce → IntelliDesc). The model list is fetched live from the Gemini API and cached for 24 hours.
* Improved: Default generation model updated to **Gemini 3.1 Flash Lite** — faster and more cost-efficient. Automatic fallback to **Gemini 2.5 Flash** if the primary model is unavailable.

= 1.6 =
* Improved: Complete admin UI redesign — new CSS design system with CSS custom properties (color tokens, radius, transitions) for consistent look across all plugin pages.
* Improved: "Generate Content" button now uses a branded gradient and includes a superhero icon.
* Improved: Status messages (success/error) now display with a matching Dashicon for faster visual feedback.
* Improved: Features table and settings tables — unified header styling, row hover effect, and fade-out animation when deleting rows.
* Improved: "Add Feature / Add Template / Add Unit Rule" buttons now use a dashed outline style; new rows animate in with a highlight flash.
* Improved: "Remove" row buttons replaced with a compact × icon button with a red hover state.
* Improved: Loader uses a styled info-box (blue background + border) instead of a plain spinner.
* Improved: Asset versions bumped to 1.6 to bust browser cache.
* New: **Generate Descriptions Only** option (WooCommerce → IntelliDesc → Content Generation). When enabled, the AI generates only the Short and Long Descriptions — feature/specs extraction is completely skipped. Useful for stores that manage attributes manually or want faster, token-light generation.

= 1.5 =
* New: Unit Rules. Define the exact unit or format Gemini must use for specific feature values (e.g., "Battery Capacity" → always in "mAh"). Configurable per-store in WooCommerce → IntelliDesc settings.
* Improved: Detailed error messages for all Gemini API failure codes (401, 403, 404, 429, 500, 503) with actionable hints displayed directly in the product editor.
* Improved: Gemini safety filter blocks (SAFETY, RECITATION finish reasons) are now caught and reported with a clear message instead of a generic error.
* Fixed: Undefined variable `$native_features_str` causing a PHP notice on every generation request.
* Fixed: Duplicate constant definitions for `ILDESC_CATEGORY_TEMPLATES` and `ILDESC_CONTENT_LANGUAGE`.
* Fixed: `wc_get_product()` was called twice per request (redundant database query removed).

= 1.4 =
* New: Smart Product Type Detection! The AI now understands whether you are editing a Simple, Variable, Virtual, or Downloadable product, and automatically adjusts its generation logic (e.g., focusing on digital specs for downloadable items or generating variation attributes for variable products).
* New: Context-Aware AI Generation. The plugin now reads the features and attributes you've already entered manually before sending the request. The AI strictly respects your inventory constraints and will no longer hallucinate extra sizes or colors that you don't actually sell!
* Pro: Advanced Native Attributes Integration. AI-generated features are now perfectly saved as real, filterable WooCommerce global attributes. 
* Pro: Variable Products Automation. When saving real attributes for Variable products, the plugin now automatically checks the "Used for variations" box, making it incredibly fast to generate variations straight from AI output.
* Improved: Highly optimized prompt constraints for Gemini to prevent redundant data generation and improve factual accuracy.
* Improved: Significant code refactoring, unified prefixes, and removal of inline scripts for better performance, security, and strict compliance with WordPress.org coding standards.

= 1.3 =
* Important: Plugin renamed to IntelliDesc for WooCommerce.
* Improved: Better compliance with WordPress trademark guidelines.
* Improved: UI and prefix structure updated.

= 1.2 =
* New: Added "Smart Queue" for Bulk Generation (Pro).
* New: Option to save features as native WooCommerce Attributes (Pro).
* New: Social Media Post generation.

= 1.1 =
* Added Category Templates support.
* Added support for Ukrainian and Polish languages.

= 1.0 =
* Initial Release.