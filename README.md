# VoiceWriter AI

A free WordPress plugin that learns your site's **own writing voice** from your existing posts, then drafts on-brand content and social repurposes — using **your own** Anthropic Claude API key (BYOK), so there are no monthly fees and your content never touches a third-party server beyond Anthropic itself.

> **"Voice" = your site's writing voice/tone, not microphone dictation.**

## Why it's different

Generic AI writers all wrap the same models and produce the same generic output. VoiceWriter AI's differentiator — its moat — is **brand-voice training**: a one-click scan of your published posts builds a reusable style profile that is prepended to every generation, so drafts sound like *your* site.

## Features (free MVP)

- 🧠 **Brand-voice training** — learns tone, rhythm, vocabulary, and structure from your published posts.
- ✍️ **On-brand draft generation** — type a topic, get a publish-ready draft inserted into the block editor.
- 🔁 **Repurposing** — turn a draft into a LinkedIn post in the same voice.
- 🔐 **BYOK** — your Anthropic key, stored only in your DB. No author server, no subscription to run.

## Planned (Pro)

Unlimited generations · multiple voice profiles · X/newsletter/IG repurposing · internal-link & content-gap SEO · bulk generation. Sold as a one-time/yearly license via Freemius — all runs locally via BYOK (zero server cost).

## Requirements

- WordPress 6.2+
- PHP 7.4+
- An [Anthropic Claude API key](https://console.anthropic.com/settings/keys)

## Install (local dev)

1. Copy this folder to `wp-content/plugins/voicewriter-ai`.
2. Activate **VoiceWriter AI** in the Plugins screen.
3. **Settings → VoiceWriter AI** → paste your Anthropic API key.
4. Open a post → **VoiceWriter AI** sidebar → **Train voice** → **Generate**.

## Structure

```
voicewriter-ai/
├── voicewriter-ai.php                       # main plugin file (header, constants, boot)
├── readme.txt                               # WordPress.org readme (with AI-service disclosure)
├── uninstall.php                            # option cleanup on delete
├── includes/
│   ├── class-voicewriter-ai.php             # core: hooks, asset enqueue
│   ├── class-voicewriter-ai-settings.php    # Settings API page (BYOK key + model)
│   ├── class-voicewriter-ai-claude-client.php # Anthropic Messages API wrapper
│   ├── class-voicewriter-ai-voice-profile.php # the brand-voice trainer (moat)
│   └── class-voicewriter-ai-rest.php        # /train, /generate, /repurpose
└── admin/
    ├── js/voicewriter-ai-editor.js          # Gutenberg sidebar (no build step)
    └── css/voicewriter-ai-editor.css
```

## WordPress.org compliance

Built to pass manual review: ABSPATH guards, unique `voicewriter_ai_` prefix, sanitized input / escaped output / nonces (REST cookie nonce + capability checks), `$wpdb` not used directly, no remote-loaded assets, GPLv2, and the required external-service (Anthropic) disclosure in `readme.txt`.

> **Note:** GitHub uses this repo; WordPress.org uses its own SVN. `README.md` and `.gitignore` stay in Git only — they are not committed to the WordPress.org SVN `/trunk/`.

## License

GPLv2 or later.
