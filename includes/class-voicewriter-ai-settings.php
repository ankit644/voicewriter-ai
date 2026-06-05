<?php
/**
 * Admin settings page (Settings > VoiceWriter AI).
 *
 * Stores the BYOK Anthropic API key and the chosen Claude model.
 * Uses the WordPress Settings API, which handles the nonce/referer check
 * for the options.php form submission.
 *
 * @package VoiceWriter_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings page controller.
 */
class Voicewriter_AI_Settings {

	const PAGE_SLUG  = 'voicewriter-ai';
	const GROUP      = 'voicewriter_ai_settings_group';

	/**
	 * Available Claude models offered in the dropdown.
	 *
	 * @return array
	 */
	private function models() {
		return array(
			'claude-opus-4-8'   => __( 'Claude Opus 4.8 (highest quality)', 'voicewriter-ai' ),
			'claude-sonnet-4-6' => __( 'Claude Sonnet 4.6 (balanced — recommended)', 'voicewriter-ai' ),
			'claude-haiku-4-5'  => __( 'Claude Haiku 4.5 (fastest, lowest cost)', 'voicewriter-ai' ),
		);
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Add the settings submenu under Settings.
	 *
	 * @return void
	 */
	public function add_menu() {
		add_options_page(
			__( 'VoiceWriter AI', 'voicewriter-ai' ),
			__( 'VoiceWriter AI', 'voicewriter-ai' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Register the setting + sanitizer.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			self::GROUP,
			VOICEWRITER_AI_OPTION_SETTINGS,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => array(
					'api_key' => '',
					'model'   => 'claude-sonnet-4-6',
				),
			)
		);
	}

	/**
	 * Sanitize submitted settings.
	 *
	 * @param array $input Raw input.
	 * @return array
	 */
	public function sanitize( $input ) {
		$output = array();

		$output['api_key'] = isset( $input['api_key'] )
			? sanitize_text_field( $input['api_key'] )
			: '';

		$model            = isset( $input['model'] ) ? sanitize_text_field( $input['model'] ) : 'claude-sonnet-4-6';
		$allowed          = array_keys( $this->models() );
		$output['model']  = in_array( $model, $allowed, true ) ? $model : 'claude-sonnet-4-6';

		return $output;
	}

	/**
	 * Render the settings page.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = get_option( VOICEWRITER_AI_OPTION_SETTINGS, array() );
		$api_key  = isset( $settings['api_key'] ) ? $settings['api_key'] : '';
		$model    = isset( $settings['model'] ) ? $settings['model'] : 'claude-sonnet-4-6';

		$profile      = new Voicewriter_AI_Voice_Profile();
		$has_voice    = $profile->exists();
		$profile_data = $profile->get();
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'VoiceWriter AI', 'voicewriter-ai' ); ?></h1>
			<p>
				<?php echo esc_html__( 'VoiceWriter AI learns your site\'s own writing voice and drafts on-brand content. It uses your own Anthropic Claude API key (BYOK) — nothing is sent to the plugin author.', 'voicewriter-ai' ); ?>
			</p>

			<form action="options.php" method="post">
				<?php settings_fields( self::GROUP ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="voicewriter_ai_api_key"><?php echo esc_html__( 'Anthropic API key', 'voicewriter-ai' ); ?></label>
						</th>
						<td>
							<input
								type="password"
								id="voicewriter_ai_api_key"
								name="<?php echo esc_attr( VOICEWRITER_AI_OPTION_SETTINGS ); ?>[api_key]"
								value="<?php echo esc_attr( $api_key ); ?>"
								class="regular-text"
								autocomplete="off"
							/>
							<p class="description">
								<?php
								printf(
									/* translators: %s: URL to the Anthropic console. */
									esc_html__( 'Get a key from the %s. Your key is stored only in your site\'s database.', 'voicewriter-ai' ),
									'<a href="https://console.anthropic.com/settings/keys" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Anthropic Console', 'voicewriter-ai' ) . '</a>'
								);
								?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="voicewriter_ai_model"><?php echo esc_html__( 'Model', 'voicewriter-ai' ); ?></label>
						</th>
						<td>
							<select id="voicewriter_ai_model" name="<?php echo esc_attr( VOICEWRITER_AI_OPTION_SETTINGS ); ?>[model]">
								<?php foreach ( $this->models() as $value => $label ) : ?>
									<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $model, $value ); ?>>
										<?php echo esc_html( $label ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>

			<hr />

			<h2><?php echo esc_html__( 'Brand voice', 'voicewriter-ai' ); ?></h2>
			<?php if ( $has_voice ) : ?>
				<p>
					<span class="dashicons dashicons-yes" style="color:#46b450;"></span>
					<?php
					printf(
						/* translators: %d: number of posts used for training. */
						esc_html__( 'Voice profile trained from %d posts.', 'voicewriter-ai' ),
						isset( $profile_data['sample_count'] ) ? (int) $profile_data['sample_count'] : 0
					);
					?>
				</p>
				<details>
					<summary><?php echo esc_html__( 'View learned voice profile', 'voicewriter-ai' ); ?></summary>
					<p style="max-width:46em;white-space:pre-wrap;"><?php echo esc_html( $profile_data['summary'] ); ?></p>
				</details>
			<?php else : ?>
				<p>
					<?php echo esc_html__( 'No voice profile yet. Open any post in the block editor, then use the VoiceWriter AI panel to train it from your existing posts.', 'voicewriter-ai' ); ?>
				</p>
			<?php endif; ?>

			<hr />
			<h2><?php echo esc_html__( 'About data & the AI service', 'voicewriter-ai' ); ?></h2>
			<p class="description" style="max-width:46em;">
				<?php
				printf(
					/* translators: 1: Anthropic terms URL, 2: Anthropic privacy URL. */
					esc_html__( 'When you train or generate, the relevant post text and your instructions are sent to Anthropic (Claude) using your API key, in order to produce the result. Review Anthropic\'s %1$s and %2$s.', 'voicewriter-ai' ),
					'<a href="https://www.anthropic.com/legal/consumer-terms" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Terms', 'voicewriter-ai' ) . '</a>',
					'<a href="https://www.anthropic.com/legal/privacy" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Privacy Policy', 'voicewriter-ai' ) . '</a>'
				);
				?>
			</p>
		</div>
		<?php
	}
}
