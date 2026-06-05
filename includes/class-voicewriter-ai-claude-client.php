<?php
/**
 * Thin wrapper around the Anthropic Claude Messages API.
 *
 * BYOK: the request uses the site owner's own API key stored in settings.
 * No data is sent to the plugin author's servers — only to Anthropic.
 *
 * @package VoiceWriter_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Anthropic Claude API client.
 */
class Voicewriter_AI_Claude_Client {

	const API_ENDPOINT    = 'https://api.anthropic.com/v1/messages';
	const ANTHROPIC_VERSION = '2023-06-01';

	/**
	 * Send a single-turn message to Claude.
	 *
	 * @param string $system     System prompt (instructions / brand voice).
	 * @param string $user       User message content.
	 * @param int    $max_tokens Maximum tokens to generate.
	 * @return string|WP_Error   Generated text, or WP_Error on failure.
	 */
	public function complete( $system, $user, $max_tokens = 2000 ) {
		$settings = get_option( VOICEWRITER_AI_OPTION_SETTINGS, array() );
		$api_key  = isset( $settings['api_key'] ) ? trim( $settings['api_key'] ) : '';
		$model    = isset( $settings['model'] ) ? $settings['model'] : 'claude-sonnet-4-6';

		if ( '' === $api_key ) {
			return new WP_Error(
				'voicewriter_ai_no_key',
				__( 'No Anthropic API key is set. Add your key in VoiceWriter AI settings.', 'voicewriter-ai' )
			);
		}

		$body = array(
			'model'      => $model,
			'max_tokens' => (int) $max_tokens,
			'system'     => $system,
			'messages'   => array(
				array(
					'role'    => 'user',
					'content' => $user,
				),
			),
		);

		$response = wp_remote_post(
			self::API_ENDPOINT,
			array(
				'timeout' => 60,
				'headers' => array(
					'content-type'      => 'application/json',
					'x-api-key'         => $api_key,
					'anthropic-version' => self::ANTHROPIC_VERSION,
				),
				'body'    => wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$raw  = wp_remote_retrieve_body( $response );
		$data = json_decode( $raw, true );

		if ( $code < 200 || $code >= 300 ) {
			$message = __( 'The Claude API returned an error.', 'voicewriter-ai' );
			if ( isset( $data['error']['message'] ) ) {
				$message = $data['error']['message'];
			}
			return new WP_Error( 'voicewriter_ai_api_error', $message, array( 'status' => $code ) );
		}

		// Concatenate any text content blocks from the response.
		$text = '';
		if ( isset( $data['content'] ) && is_array( $data['content'] ) ) {
			foreach ( $data['content'] as $block ) {
				if ( isset( $block['type'], $block['text'] ) && 'text' === $block['type'] ) {
					$text .= $block['text'];
				}
			}
		}

		if ( '' === $text ) {
			return new WP_Error(
				'voicewriter_ai_empty',
				__( 'Claude returned an empty response. Try again.', 'voicewriter-ai' )
			);
		}

		return $text;
	}
}
