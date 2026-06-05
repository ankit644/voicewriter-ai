<?php
/**
 * REST API endpoints used by the block-editor sidebar.
 *
 * Namespace: voicewriter-ai/v1
 *   POST /train      — (re)build the brand-voice profile from existing posts
 *   POST /generate   — generate an on-brand draft from a topic
 *   POST /repurpose  — turn the current draft into a social post (free: 1 network)
 *
 * Auth: capability check (edit_posts) + the REST cookie nonce (X-WP-Nonce),
 * which apiFetch sends automatically from the localized nonce.
 *
 * @package VoiceWriter_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST controller.
 */
class Voicewriter_AI_REST {

	const NAMESPACE = 'voicewriter-ai/v1';

	/**
	 * Register the rest_api_init hook.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			self::NAMESPACE,
			'/train',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_train' ),
				'permission_callback' => array( $this, 'permission' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/generate',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_generate' ),
				'permission_callback' => array( $this, 'permission' ),
				'args'                => array(
					'topic' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_textarea_field',
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/repurpose',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_repurpose' ),
				'permission_callback' => array( $this, 'permission' ),
				'args'                => array(
					'content' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_textarea_field',
					),
					'network' => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_key',
					),
				),
			)
		);
	}

	/**
	 * Permission callback — must be able to edit posts.
	 *
	 * @return bool
	 */
	public function permission() {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * POST /train.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_train( WP_REST_Request $request ) {
		$profile = new Voicewriter_AI_Voice_Profile();
		$result  = $profile->train();

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response(
			array(
				'message'      => __( 'Voice profile trained successfully.', 'voicewriter-ai' ),
				'sample_count' => isset( $result['sample_count'] ) ? (int) $result['sample_count'] : 0,
				'summary'      => isset( $result['summary'] ) ? $result['summary'] : '',
			)
		);
	}

	/**
	 * POST /generate.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_generate( WP_REST_Request $request ) {
		$limit_error = $this->enforce_free_limit();
		if ( is_wp_error( $limit_error ) ) {
			return $limit_error;
		}

		$topic = trim( (string) $request->get_param( 'topic' ) );
		if ( '' === $topic ) {
			return new WP_Error(
				'voicewriter_ai_no_topic',
				__( 'Please provide a topic or instruction.', 'voicewriter-ai' ),
				array( 'status' => 400 )
			);
		}

		$profile = new Voicewriter_AI_Voice_Profile();
		$system  = $profile->system_prompt(
			'Write a complete, publish-ready blog draft on the topic the user gives. '
			. 'Match the site voice precisely. Use clear structure with a strong opening, logical sections, '
			. 'and a closing. Return clean text only — no preamble like "Here is your draft", no markdown code fences.'
		);

		$client = new Voicewriter_AI_AI_Client();
		$text   = $client->complete( $system, 'Topic / instruction: ' . $topic, 2500 );

		if ( is_wp_error( $text ) ) {
			return $text;
		}

		$this->increment_usage();

		return rest_ensure_response(
			array(
				'content'   => $text,
				'remaining' => $this->remaining(),
			)
		);
	}

	/**
	 * POST /repurpose.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_repurpose( WP_REST_Request $request ) {
		$limit_error = $this->enforce_free_limit();
		if ( is_wp_error( $limit_error ) ) {
			return $limit_error;
		}

		$content = trim( (string) $request->get_param( 'content' ) );
		if ( '' === $content ) {
			return new WP_Error(
				'voicewriter_ai_no_content',
				__( 'There is no content to repurpose. Write or generate a draft first.', 'voicewriter-ai' ),
				array( 'status' => 400 )
			);
		}

		// Free version supports a single network. Pro unlocks the rest.
		$network = $request->get_param( 'network' );
		$network = $network ? $network : 'linkedin';
		$allowed = array( 'linkedin' );
		if ( ! in_array( $network, $allowed, true ) ) {
			$network = 'linkedin';
		}

		$profile = new Voicewriter_AI_Voice_Profile();
		$system  = $profile->system_prompt(
			'Turn the user\'s blog content into a single engaging LinkedIn post in the site voice. '
			. 'Keep it concise (under 1300 characters), hook first, no hashtags spam (max 3 relevant tags). '
			. 'Return only the post text.'
		);

		$client = new Voicewriter_AI_AI_Client();
		$text   = $client->complete( $system, "Blog content:\n\n" . mb_substr( $content, 0, 6000 ), 800 );

		if ( is_wp_error( $text ) ) {
			return $text;
		}

		$this->increment_usage();

		return rest_ensure_response(
			array(
				'content'   => $text,
				'network'   => $network,
				'remaining' => $this->remaining(),
			)
		);
	}

	/* ----------------------------------------------------------------------
	 * Free-tier usage metering (monthly). Pro removes the cap.
	 * -------------------------------------------------------------------- */

	/**
	 * Current year-month key, e.g. "2026-06".
	 *
	 * @return string
	 */
	private function month_key() {
		return gmdate( 'Y-m' );
	}

	/**
	 * Used count this month.
	 *
	 * @return int
	 */
	private function used() {
		$usage = get_option( VOICEWRITER_AI_OPTION_USAGE, array() );
		$key   = $this->month_key();
		return ( is_array( $usage ) && isset( $usage[ $key ] ) ) ? (int) $usage[ $key ] : 0;
	}

	/**
	 * Generations remaining this month on the free tier.
	 *
	 * @return int
	 */
	private function remaining() {
		$remaining = (int) VOICEWRITER_AI_FREE_MONTHLY_LIMIT - $this->used();
		return $remaining > 0 ? $remaining : 0;
	}

	/**
	 * Block the request if the free monthly cap is reached.
	 *
	 * @return true|WP_Error
	 */
	private function enforce_free_limit() {
		if ( $this->used() >= (int) VOICEWRITER_AI_FREE_MONTHLY_LIMIT ) {
			return new WP_Error(
				'voicewriter_ai_limit',
				sprintf(
					/* translators: %d: monthly free limit. */
					__( 'You have reached the free limit of %d generations this month. Upgrade to Pro for unlimited use.', 'voicewriter-ai' ),
					(int) VOICEWRITER_AI_FREE_MONTHLY_LIMIT
				),
				array( 'status' => 402 )
			);
		}
		return true;
	}

	/**
	 * Increment this month's usage counter.
	 *
	 * @return void
	 */
	private function increment_usage() {
		$usage = get_option( VOICEWRITER_AI_OPTION_USAGE, array() );
		if ( ! is_array( $usage ) ) {
			$usage = array();
		}
		$key           = $this->month_key();
		$usage[ $key ] = ( isset( $usage[ $key ] ) ? (int) $usage[ $key ] : 0 ) + 1;

		// Keep only the current month to avoid unbounded growth.
		$usage = array( $key => $usage[ $key ] );

		update_option( VOICEWRITER_AI_OPTION_USAGE, $usage, false );
	}
}
