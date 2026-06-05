<?php
/**
 * Core bootstrap: wires up admin, REST, and the block-editor assets.
 *
 * @package VoiceWriter_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main plugin controller.
 */
class Voicewriter_AI {

	/**
	 * Settings handler.
	 *
	 * @var Voicewriter_AI_Settings
	 */
	private $settings;

	/**
	 * REST controller.
	 *
	 * @var Voicewriter_AI_REST
	 */
	private $rest;

	/**
	 * Constructor — register hooks.
	 */
	public function __construct() {
		$this->settings = new Voicewriter_AI_Settings();
		$this->rest     = new Voicewriter_AI_REST();

		$this->settings->register();
		$this->rest->register();

		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_assets' ) );
		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_filter( 'plugin_action_links_' . VOICEWRITER_AI_BASENAME, array( $this, 'action_links' ) );
	}

	/**
	 * Load translations.
	 *
	 * @return void
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'voicewriter-ai', false, dirname( VOICEWRITER_AI_BASENAME ) . '/languages' );
	}

	/**
	 * Add a Settings link on the plugins list row.
	 *
	 * @param array $links Existing action links.
	 * @return array
	 */
	public function action_links( $links ) {
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'options-general.php?page=voicewriter-ai' ) ),
			esc_html__( 'Settings', 'voicewriter-ai' )
		);
		array_unshift( $links, $settings_link );
		return $links;
	}

	/**
	 * Enqueue the Gutenberg sidebar script + styles.
	 *
	 * @return void
	 */
	public function enqueue_editor_assets() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		wp_enqueue_script(
			'voicewriter-ai-editor',
			VOICEWRITER_AI_URL . 'admin/js/voicewriter-ai-editor.js',
			array(
				'wp-plugins',
				'wp-edit-post',
				'wp-element',
				'wp-components',
				'wp-data',
				'wp-blocks',
				'wp-api-fetch',
				'wp-i18n',
				'wp-notices',
			),
			VOICEWRITER_AI_VERSION,
			true
		);

		$settings   = get_option( VOICEWRITER_AI_OPTION_SETTINGS, array() );
		$has_key    = ! empty( $settings['api_key'] );
		$profile    = get_option( VOICEWRITER_AI_OPTION_PROFILE, array() );
		$has_voice  = ! empty( $profile['summary'] );

		wp_localize_script(
			'voicewriter-ai-editor',
			'voicewriterAI',
			array(
				'restUrl'      => esc_url_raw( rest_url( 'voicewriter-ai/v1' ) ),
				'nonce'        => wp_create_nonce( 'wp_rest' ),
				'hasApiKey'    => (bool) $has_key,
				'hasVoice'     => (bool) $has_voice,
				'settingsUrl'  => esc_url_raw( admin_url( 'options-general.php?page=voicewriter-ai' ) ),
				'freeLimit'    => (int) VOICEWRITER_AI_FREE_MONTHLY_LIMIT,
			)
		);

		wp_set_script_translations( 'voicewriter-ai-editor', 'voicewriter-ai' );

		wp_enqueue_style(
			'voicewriter-ai-editor',
			VOICEWRITER_AI_URL . 'admin/css/voicewriter-ai-editor.css',
			array(),
			VOICEWRITER_AI_VERSION
		);
	}
}
