<?php
/**
 * Provider-agnostic AI client (BYOK).
 *
 * Routes a single-turn completion to whichever provider the site owner has
 * configured — Anthropic (Claude), OpenAI (ChatGPT), or Google (Gemini) —
 * using the owner's own API key. Nothing is sent to the plugin author.
 *
 * @package VoiceWriter_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Multi-provider AI client.
 */
class Voicewriter_AI_AI_Client {

	const ANTHROPIC_VERSION = '2023-06-01';

	/**
	 * Supported providers and their default models.
	 *
	 * @return array
	 */
	public static function providers() {
		return array(
			'anthropic' => array(
				'label'   => __( 'Anthropic (Claude)', 'voicewriter-ai' ),
				'default' => 'claude-sonnet-4-6',
				'models'  => array( 'claude-opus-4-8', 'claude-sonnet-4-6', 'claude-haiku-4-5' ),
				'keys_url' => 'https://console.anthropic.com/settings/keys',
			),
			'openai'    => array(
				'label'   => __( 'OpenAI (ChatGPT)', 'voicewriter-ai' ),
				'default' => 'gpt-4o-mini',
				'models'  => array( 'gpt-4o', 'gpt-4o-mini', 'gpt-4.1', 'gpt-4.1-mini' ),
				'keys_url' => 'https://platform.openai.com/api-keys',
			),
			'gemini'    => array(
				'label'   => __( 'Google (Gemini) — has a free tier', 'voicewriter-ai' ),
				'default' => 'gemini-1.5-flash',
				'models'  => array( 'gemini-1.5-pro', 'gemini-1.5-flash', 'gemini-2.0-flash' ),
				'keys_url' => 'https://aistudio.google.com/app/apikey',
			),
		);
	}

	/**
	 * Human-readable provider label.
	 *
	 * @param string $provider Provider key.
	 * @return string
	 */
	public static function provider_label( $provider ) {
		$providers = self::providers();
		return isset( $providers[ $provider ]['label'] ) ? $providers[ $provider ]['label'] : $provider;
	}

	/**
	 * Send a single-turn message and return the text.
	 *
	 * @param string $system     System / instruction prompt.
	 * @param string $user       User content.
	 * @param int    $max_tokens Max tokens to generate.
	 * @return string|WP_Error
	 */
	public function complete( $system, $user, $max_tokens = 2000 ) {
		$settings = get_option( VOICEWRITER_AI_OPTION_SETTINGS, array() );
		$provider = isset( $settings['provider'] ) ? $settings['provider'] : 'anthropic';
		$api_key  = isset( $settings['api_key'] ) ? trim( $settings['api_key'] ) : '';

		$providers = self::providers();
		if ( ! isset( $providers[ $provider ] ) ) {
			$provider = 'anthropic';
		}

		$model = isset( $settings['model'] ) && '' !== trim( $settings['model'] )
			? trim( $settings['model'] )
			: $providers[ $provider ]['default'];

		if ( '' === $api_key ) {
			return new WP_Error(
				'voicewriter_ai_no_key',
				__( 'No API key is set. Add your AI provider key in VoiceWriter AI settings.', 'voicewriter-ai' )
			);
		}

		switch ( $provider ) {
			case 'openai':
				return $this->complete_openai( $api_key, $model, $system, $user, $max_tokens );
			case 'gemini':
				return $this->complete_gemini( $api_key, $model, $system, $user, $max_tokens );
			case 'anthropic':
			default:
				return $this->complete_anthropic( $api_key, $model, $system, $user, $max_tokens );
		}
	}

	/**
	 * Anthropic Claude — Messages API.
	 *
	 * @param string $api_key    Key.
	 * @param string $model      Model id.
	 * @param string $system     System prompt.
	 * @param string $user       User content.
	 * @param int    $max_tokens Max tokens.
	 * @return string|WP_Error
	 */
	private function complete_anthropic( $api_key, $model, $system, $user, $max_tokens ) {
		$response = wp_remote_post(
			'https://api.anthropic.com/v1/messages',
			array(
				'timeout' => 60,
				'headers' => array(
					'content-type'      => 'application/json',
					'x-api-key'         => $api_key,
					'anthropic-version' => self::ANTHROPIC_VERSION,
				),
				'body'    => wp_json_encode(
					array(
						'model'      => $model,
						'max_tokens' => (int) $max_tokens,
						'system'     => $system,
						'messages'   => array(
							array(
								'role'    => 'user',
								'content' => $user,
							),
						),
					)
				),
			)
		);

		$data = $this->parse_response( $response );
		if ( is_wp_error( $data ) ) {
			return $data;
		}

		$text = '';
		if ( isset( $data['content'] ) && is_array( $data['content'] ) ) {
			foreach ( $data['content'] as $block ) {
				if ( isset( $block['type'], $block['text'] ) && 'text' === $block['type'] ) {
					$text .= $block['text'];
				}
			}
		}

		return $this->finalize_text( $text );
	}

	/**
	 * OpenAI ChatGPT — Chat Completions API.
	 *
	 * @param string $api_key    Key.
	 * @param string $model      Model id.
	 * @param string $system     System prompt.
	 * @param string $user       User content.
	 * @param int    $max_tokens Max tokens.
	 * @return string|WP_Error
	 */
	private function complete_openai( $api_key, $model, $system, $user, $max_tokens ) {
		$response = wp_remote_post(
			'https://api.openai.com/v1/chat/completions',
			array(
				'timeout' => 60,
				'headers' => array(
					'content-type'  => 'application/json',
					'Authorization' => 'Bearer ' . $api_key,
				),
				'body'    => wp_json_encode(
					array(
						'model'       => $model,
						'max_tokens'  => (int) $max_tokens,
						'messages'    => array(
							array(
								'role'    => 'system',
								'content' => $system,
							),
							array(
								'role'    => 'user',
								'content' => $user,
							),
						),
					)
				),
			)
		);

		$data = $this->parse_response( $response );
		if ( is_wp_error( $data ) ) {
			return $data;
		}

		$text = isset( $data['choices'][0]['message']['content'] )
			? $data['choices'][0]['message']['content']
			: '';

		return $this->finalize_text( $text );
	}

	/**
	 * Google Gemini — generateContent API.
	 *
	 * @param string $api_key    Key.
	 * @param string $model      Model id.
	 * @param string $system     System prompt.
	 * @param string $user       User content.
	 * @param int    $max_tokens Max tokens.
	 * @return string|WP_Error
	 */
	private function complete_gemini( $api_key, $model, $system, $user, $max_tokens ) {
		$url = add_query_arg(
			'key',
			rawurlencode( $api_key ),
			'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode( $model ) . ':generateContent'
		);

		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 60,
				'headers' => array(
					'content-type' => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'system_instruction' => array(
							'parts' => array( array( 'text' => $system ) ),
						),
						'contents'          => array(
							array(
								'role'  => 'user',
								'parts' => array( array( 'text' => $user ) ),
							),
						),
						'generationConfig'  => array(
							'maxOutputTokens' => (int) $max_tokens,
						),
					)
				),
			)
		);

		$data = $this->parse_response( $response );
		if ( is_wp_error( $data ) ) {
			return $data;
		}

		$text = '';
		if ( isset( $data['candidates'][0]['content']['parts'] ) && is_array( $data['candidates'][0]['content']['parts'] ) ) {
			foreach ( $data['candidates'][0]['content']['parts'] as $part ) {
				if ( isset( $part['text'] ) ) {
					$text .= $part['text'];
				}
			}
		}

		return $this->finalize_text( $text );
	}

	/**
	 * Validate transport, HTTP status, and decode JSON.
	 *
	 * @param array|WP_Error $response wp_remote_post result.
	 * @return array|WP_Error
	 */
	private function parse_response( $response ) {
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$raw  = wp_remote_retrieve_body( $response );
		$data = json_decode( $raw, true );

		if ( $code < 200 || $code >= 300 ) {
			$message = __( 'The AI provider returned an error.', 'voicewriter-ai' );
			if ( isset( $data['error']['message'] ) ) {
				$message = $data['error']['message'];
			} elseif ( isset( $data['error'] ) && is_string( $data['error'] ) ) {
				$message = $data['error'];
			}
			return new WP_Error( 'voicewriter_ai_api_error', $message, array( 'status' => $code ) );
		}

		if ( null === $data ) {
			return new WP_Error(
				'voicewriter_ai_bad_json',
				__( 'Could not read the AI provider response.', 'voicewriter-ai' )
			);
		}

		return $data;
	}

	/**
	 * Trim and guard against empty output.
	 *
	 * @param string $text Raw text.
	 * @return string|WP_Error
	 */
	private function finalize_text( $text ) {
		$text = is_string( $text ) ? trim( $text ) : '';
		if ( '' === $text ) {
			return new WP_Error(
				'voicewriter_ai_empty',
				__( 'The AI returned an empty response. Try again.', 'voicewriter-ai' )
			);
		}
		return $text;
	}
}
