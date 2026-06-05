<?php
/**
 * Admin settings page (Settings > VoiceWriter AI).
 *
 * Stores the chosen AI provider, the BYOK API key for it, and the model.
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

	const PAGE_SLUG = 'voicewriter-ai';
	const GROUP     = 'voicewriter_ai_settings_group';

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
					'provider' => 'anthropic',
					'api_key'  => '',
					'model'    => 'claude-sonnet-4-6',
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
		$providers = Voicewriter_AI_AI_Client::providers();
		$output    = array();

		$provider           = isset( $input['provider'] ) ? sanitize_key( $input['provider'] ) : 'anthropic';
		$output['provider'] = isset( $providers[ $provider ] ) ? $provider : 'anthropic';

		$output['api_key'] = isset( $input['api_key'] )
			? sanitize_text_field( $input['api_key'] )
			: '';

		// Model is free-text (provider model ids change often); fall back to the provider default.
		$model           = isset( $input['model'] ) ? sanitize_text_field( $input['model'] ) : '';
		$output['model'] = '' !== $model ? $model : $providers[ $output['provider'] ]['default'];

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

		$settings  = get_option( VOICEWRITER_AI_OPTION_SETTINGS, array() );
		$provider  = isset( $settings['provider'] ) ? $settings['provider'] : 'anthropic';
		$api_key   = isset( $settings['api_key'] ) ? $settings['api_key'] : '';
		$model     = isset( $settings['model'] ) ? $settings['model'] : '';
		$providers = Voicewriter_AI_AI_Client::providers();
		if ( ! isset( $providers[ $provider ] ) ) {
			$provider = 'anthropic';
		}

		$profile      = new Voicewriter_AI_Voice_Profile();
		$has_voice    = $profile->exists();
		$profile_data = $profile->get();
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'VoiceWriter AI', 'voicewriter-ai' ); ?></h1>
			<p>
				<?php echo esc_html__( 'VoiceWriter AI learns your site\'s own writing voice and drafts on-brand content. Bring your own AI key (BYOK) — Claude, ChatGPT, or Gemini. Nothing is sent to the plugin author.', 'voicewriter-ai' ); ?>
			</p>

			<form action="options.php" method="post">
				<?php settings_fields( self::GROUP ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="voicewriter_ai_provider"><?php echo esc_html__( 'AI provider', 'voicewriter-ai' ); ?></label>
						</th>
						<td>
							<select id="voicewriter_ai_provider" name="<?php echo esc_attr( VOICEWRITER_AI_OPTION_SETTINGS ); ?>[provider]">
								<?php foreach ( $providers as $key => $info ) : ?>
									<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $provider, $key ); ?>>
										<?php echo esc_html( $info['label'] ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<p class="description">
								<?php echo esc_html__( 'No paid subscription? Google Gemini offers a free API tier.', 'voicewriter-ai' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="voicewriter_ai_api_key"><?php echo esc_html__( 'API key', 'voicewriter-ai' ); ?></label>
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
								<?php echo esc_html__( 'Get a key from your provider:', 'voicewriter-ai' ); ?>
								<?php foreach ( $providers as $key => $info ) : ?>
									<a class="voicewriter-ai-keylink" data-provider="<?php echo esc_attr( $key ); ?>"
										href="<?php echo esc_url( $info['keys_url'] ); ?>" target="_blank" rel="noopener noreferrer"
										style="<?php echo ( $provider === $key ) ? '' : 'display:none;'; ?>">
										<?php echo esc_html( $info['label'] ); ?>
									</a>
								<?php endforeach; ?>
								<br />
								<?php echo esc_html__( 'Your key is stored only in your site\'s database.', 'voicewriter-ai' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="voicewriter_ai_model"><?php echo esc_html__( 'Model', 'voicewriter-ai' ); ?></label>
						</th>
						<td>
							<input
								type="text"
								id="voicewriter_ai_model"
								name="<?php echo esc_attr( VOICEWRITER_AI_OPTION_SETTINGS ); ?>[model]"
								value="<?php echo esc_attr( $model ); ?>"
								class="regular-text"
								list="voicewriter_ai_models"
								placeholder="<?php echo esc_attr( $providers[ $provider ]['default'] ); ?>"
							/>
							<datalist id="voicewriter_ai_models">
								<?php foreach ( $providers[ $provider ]['models'] as $m ) : ?>
									<option value="<?php echo esc_attr( $m ); ?>"></option>
								<?php endforeach; ?>
							</datalist>
							<p class="description">
								<?php echo esc_html__( 'Choose a model offered by your selected provider. Leave blank to use the recommended default.', 'voicewriter-ai' ); ?>
							</p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>

			<?php $this->print_provider_switcher_script( $providers ); ?>

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
					/* translators: %s: selected AI provider name. */
					esc_html__( 'When you train or generate, the relevant post text and your instructions are sent to your selected provider (%s) using your API key, in order to produce the result. Review that provider\'s terms and privacy policy before use.', 'voicewriter-ai' ),
					esc_html( Voicewriter_AI_AI_Client::provider_label( $provider ) )
				);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Inline script: swap the key link, model suggestions, and placeholder
	 * when the provider dropdown changes. Data is JSON-encoded and escaped.
	 *
	 * @param array $providers Provider config.
	 * @return void
	 */
	private function print_provider_switcher_script( $providers ) {
		$map = array();
		foreach ( $providers as $key => $info ) {
			$map[ $key ] = array(
				'default' => $info['default'],
				'models'  => array_values( $info['models'] ),
			);
		}
		?>
		<script>
		( function () {
			var data = <?php echo wp_json_encode( $map ); ?>;
			var sel = document.getElementById( 'voicewriter_ai_provider' );
			var modelInput = document.getElementById( 'voicewriter_ai_model' );
			var datalist = document.getElementById( 'voicewriter_ai_models' );
			var links = document.querySelectorAll( '.voicewriter-ai-keylink' );
			if ( ! sel ) { return; }
			sel.addEventListener( 'change', function () {
				var p = sel.value;
				var info = data[ p ];
				links.forEach( function ( a ) {
					a.style.display = ( a.getAttribute( 'data-provider' ) === p ) ? '' : 'none';
				} );
				if ( info && datalist ) {
					datalist.innerHTML = '';
					info.models.forEach( function ( m ) {
						var o = document.createElement( 'option' );
						o.value = m;
						datalist.appendChild( o );
					} );
				}
				if ( info && modelInput ) {
					modelInput.value = '';
					modelInput.placeholder = info.default;
				}
			} );
		} )();
		</script>
		<?php
	}
}
