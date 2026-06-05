<?php
/**
 * Plugin Name:       VoiceWriter AI
 * Plugin URI:        https://github.com/ankit644/voicewriter-ai
 * Description:       Learns your site's own writing voice from your existing posts, then generates on-brand drafts and social repurposes. Bring your own Anthropic Claude API key (BYOK) — no monthly fees, your data stays in your hands.
 * Version:           1.0.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            Ankit Kumar Singh
 * Author URI:        https://github.com/ankit644
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       voicewriter-ai
 * Domain Path:       /languages
 *
 * @package VoiceWriter_AI
 */

// Block direct access (WordPress.org requirement).
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants. Unique long prefix per WordPress.org guidelines.
define( 'VOICEWRITER_AI_VERSION', '1.0.0' );
define( 'VOICEWRITER_AI_FILE', __FILE__ );
define( 'VOICEWRITER_AI_DIR', plugin_dir_path( __FILE__ ) );
define( 'VOICEWRITER_AI_URL', plugin_dir_url( __FILE__ ) );
define( 'VOICEWRITER_AI_BASENAME', plugin_basename( __FILE__ ) );

// Option keys.
define( 'VOICEWRITER_AI_OPTION_SETTINGS', 'voicewriter_ai_settings' );
define( 'VOICEWRITER_AI_OPTION_PROFILE', 'voicewriter_ai_voice_profile' );
define( 'VOICEWRITER_AI_OPTION_USAGE', 'voicewriter_ai_usage' );

// Free-tier monthly generation cap. Pro removes this.
define( 'VOICEWRITER_AI_FREE_MONTHLY_LIMIT', 5 );

// Load core classes.
require_once VOICEWRITER_AI_DIR . 'includes/class-voicewriter-ai-claude-client.php';
require_once VOICEWRITER_AI_DIR . 'includes/class-voicewriter-ai-voice-profile.php';
require_once VOICEWRITER_AI_DIR . 'includes/class-voicewriter-ai-settings.php';
require_once VOICEWRITER_AI_DIR . 'includes/class-voicewriter-ai-rest.php';
require_once VOICEWRITER_AI_DIR . 'includes/class-voicewriter-ai.php';

/**
 * Boot the plugin.
 *
 * @return Voicewriter_AI
 */
function voicewriter_ai() {
	static $instance = null;
	if ( null === $instance ) {
		$instance = new Voicewriter_AI();
	}
	return $instance;
}

// Activation: set sane defaults without overwriting existing config.
register_activation_hook(
	__FILE__,
	function () {
		if ( false === get_option( VOICEWRITER_AI_OPTION_SETTINGS ) ) {
			add_option(
				VOICEWRITER_AI_OPTION_SETTINGS,
				array(
					'api_key' => '',
					'model'   => 'claude-sonnet-4-6',
				)
			);
		}
	}
);

// Go.
add_action( 'plugins_loaded', 'voicewriter_ai' );
