=== VoiceWriter AI ===
Contributors: ankit644
Tags: ai, content, writing, brand voice, chatgpt
Requires at least: 6.2
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Learn your site's own writing voice from existing posts, then generate on-brand drafts. Bring your own AI key — Claude, ChatGPT, or Gemini.

== Description ==

Most AI writing plugins sound the same — generic. **VoiceWriter AI** is different: it reads your *own* published posts, learns how your site writes (tone, rhythm, vocabulary, structure), and then drafts new content that sounds like **you**.

It works right inside the block editor as a sidebar:

1. **Train your brand voice** — one click scans your published posts and builds a reusable voice profile, stored in your own database.
2. **Generate on-brand drafts** — type a topic and get a publish-ready draft in your site's voice, inserted straight into the editor.
3. **Repurpose** — turn a draft into a LinkedIn post in the same voice.

**Bring Your Own Key (BYOK).** VoiceWriter AI uses *your own* API key from the provider you choose — **Anthropic (Claude)**, **OpenAI (ChatGPT)**, or **Google (Gemini)**. There is no middle-man server and no subscription required to run it — you pay your chosen provider directly for what you use (Google Gemini even has a free tier), and your content never passes through the plugin author's servers.

= Free vs Pro =

The free version includes brand-voice training, on-brand draft generation, and single-network repurposing (up to a monthly generation limit). A separate Pro upgrade unlocks unlimited generations, multiple voice profiles, more social networks, internal-link and content-gap SEO suggestions, and bulk generation.

== External services ==

This plugin connects to a third-party AI provider that **you** select and authenticate with your own API key, in order to analyze your posts and generate text. Depending on your choice, the provider is one of the following.

**What is sent and when (all providers):** When you click "Train voice", the text of your recent published posts is sent to the selected provider to build a style profile. When you click "Generate" or "Repurpose", your topic/instruction and (for repurposing) the current draft text are sent to produce the result. Requests are made only when you trigger these actions, and only when you have entered a key — nothing is bundled and nothing is sent without your key.

**1. Anthropic (Claude)**

* Provider: Anthropic, PBC.
* Terms of service: https://www.anthropic.com/legal/consumer-terms
* Privacy policy: https://www.anthropic.com/legal/privacy

**2. OpenAI (ChatGPT)**

* Provider: OpenAI, L.L.C.
* Terms of service: https://openai.com/policies/terms-of-use
* Privacy policy: https://openai.com/policies/privacy-policy

**3. Google (Gemini)**

* Provider: Google LLC (Google AI / Gemini API).
* Terms of service: https://ai.google.dev/gemini-api/terms
* Privacy policy: https://policies.google.com/privacy

You must have your own account and API key with whichever provider you select.

== Installation ==

1. Upload the `voicewriter-ai` folder to `/wp-content/plugins/`, or install it from the Plugins screen.
2. Activate the plugin.
3. Go to **Settings → VoiceWriter AI**, pick your AI provider (Claude, ChatGPT, or Gemini), and paste your API key.
4. Open any post in the block editor, open the **VoiceWriter AI** sidebar, and click **Train voice**.
5. Enter a topic and click **Generate on-brand draft**.

== Frequently Asked Questions ==

= Do I need to pay anything to use it? =

You need your own AI provider API key. You can use Anthropic (Claude), OpenAI (ChatGPT), or Google (Gemini), and you pay that provider for your usage (typically a few cents per article). Google Gemini offers a free API tier if you do not have a paid subscription. The plugin itself is free and there is no subscription required for the free features.

= Where is my data stored? =

Your API key and the learned voice profile are stored only in your own WordPress database. Post text is sent to your selected provider only when you train or generate, in order to produce the result.

= Does it work with the Classic Editor? =

The current version targets the block editor (Gutenberg). Classic Editor support is planned.

== Screenshots ==

1. The VoiceWriter AI sidebar in the block editor.
2. Settings screen for the Anthropic API key and model.

== Changelog ==

= 1.0.0 =
* Initial release: brand-voice training, on-brand draft generation, LinkedIn repurposing (BYOK — Claude, ChatGPT, or Gemini).

== Upgrade Notice ==

= 1.0.0 =
First release.
