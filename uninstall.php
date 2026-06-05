<?php
/**
 * Uninstall cleanup. Runs only when the user deletes the plugin.
 *
 * @package VoiceWriter_AI
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'voicewriter_ai_settings' );
delete_option( 'voicewriter_ai_voice_profile' );
delete_option( 'voicewriter_ai_usage' );
