=== VoiceWriter AI ===
Contributors: ankit644
Tags: ai, content, writing, brand voice, claude
Requires at least: 6.2
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Learn your site's own writing voice from existing posts, then generate on-brand drafts and social posts. Bring your own Claude API key.

== Description ==

Most AI writing plugins sound the same — generic. **VoiceWriter AI** is different: it reads your *own* published posts, learns how your site writes (tone, rhythm, vocabulary, structure), and then drafts new content that sounds like **you**.

It works right inside the block editor as a sidebar:

1. **Train your brand voice** — one click scans your published posts and builds a reusable voice profile, stored in your own database.
2. **Generate on-brand drafts** — type a topic and get a publish-ready draft in your site's voice, inserted straight into the editor.
3. **Repurpose** — turn a draft into a LinkedIn post in the same voice.

**Bring Your Own Key (BYOK).** VoiceWriter AI uses *your* Anthropic Claude API key. There is no middle-man server and no subscription required to run it — you pay Anthropic directly for what you use, and your content never passes through the plugin author's servers.

= Free vs Pro =

The free version includes brand-voice training, on-brand draft generation, and single-network repurposing (up to a monthly generation limit). A separate Pro upgrade unlocks unlimited generations, multiple voice profiles, more social networks, internal-link and content-gap SEO suggestions, and bulk generation.

== External services ==

This plugin connects to the **Anthropic (Claude) API**, a third-party AI service, to analyze your posts and generate text.

* **What is sent and when:** When you click "Train voice", the text of your recent published posts is sent to Anthropic to build a style profile. When you click "Generate" or "Repurpose", your topic/instruction and (for repurposing) the current draft text are sent to Anthropic to produce the result. Requests are made only when you trigger these actions.
* **Who provides it:** Anthropic, PBC.
* **Authentication:** Requests use the Anthropic API key that you enter in the plugin settings. No key is bundled and nothing is sent without your key.
* **Terms of service:** https://www.anthropic.com/legal/consumer-terms
* **Privacy policy:** https://www.anthropic.com/legal/privacy

You must have your own Anthropic account and API key to use this plugin.

== Installation ==

1. Upload the `voicewriter-ai` folder to `/wp-content/plugins/`, or install it from the Plugins screen.
2. Activate the plugin.
3. Go to **Settings → VoiceWriter AI** and paste your Anthropic API key.
4. Open any post in the block editor, open the **VoiceWriter AI** sidebar, and click **Train voice**.
5. Enter a topic and click **Generate on-brand draft**.

== Frequently Asked Questions ==

= Do I need to pay anything to use it? =

You need your own Anthropic Claude API key, and you pay Anthropic for your usage (typically a few cents per article). The plugin itself is free and there is no subscription required for the free features.

= Where is my data stored? =

Your API key and the learned voice profile are stored only in your own WordPress database. Post text is sent to Anthropic only when you train or generate, in order to produce the result.

= Does it work with the Classic Editor? =

The current version targets the block editor (Gutenberg). Classic Editor support is planned.

== Screenshots ==

1. The VoiceWriter AI sidebar in the block editor.
2. Settings screen for the Anthropic API key and model.

== Changelog ==

= 1.0.0 =
* Initial release: brand-voice training, on-brand draft generation, LinkedIn repurposing (BYOK, Claude).

== Upgrade Notice ==

= 1.0.0 =
First release.
