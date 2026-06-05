<?php
/**
 * Brand-voice training — the plugin's unique feature.
 *
 * Samples the site's own published posts and asks Claude to distill a
 * reusable "voice profile" (tone, structure, vocabulary). The profile is
 * stored in the site's own database and prepended to every later
 * generation so output sounds like THIS site, not generic AI.
 *
 * @package VoiceWriter_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Voice profile builder + accessor.
 */
class Voicewriter_AI_Voice_Profile {

	/**
	 * How many recent posts to sample when training.
	 */
	const SAMPLE_SIZE = 8;

	/**
	 * Max characters of post text to send per post (keeps token cost sane).
	 */
	const PER_POST_CHARS = 1500;

	/**
	 * Build (or rebuild) the voice profile from existing published posts.
	 *
	 * @return array|WP_Error The stored profile array, or WP_Error.
	 */
	public function train() {
		$posts = get_posts(
			array(
				'post_type'        => 'post',
				'post_status'      => 'publish',
				'numberposts'      => self::SAMPLE_SIZE,
				'orderby'          => 'date',
				'order'            => 'DESC',
				'suppress_filters' => false,
			)
		);

		if ( empty( $posts ) ) {
			return new WP_Error(
				'voicewriter_ai_no_posts',
				__( 'No published posts found to learn from. Publish a few posts first, then train.', 'voicewriter-ai' )
			);
		}

		$samples = array();
		foreach ( $posts as $post ) {
			$content = wp_strip_all_tags( strip_shortcodes( $post->post_content ) );
			$content = trim( preg_replace( '/\s+/', ' ', $content ) );
			if ( '' === $content ) {
				continue;
			}
			$samples[] = '### ' . $post->post_title . "\n" . mb_substr( $content, 0, self::PER_POST_CHARS );
		}

		if ( empty( $samples ) ) {
			return new WP_Error(
				'voicewriter_ai_empty_posts',
				__( 'Your published posts have no readable text to learn from.', 'voicewriter-ai' )
			);
		}

		$system = 'You are a writing-style analyst. You will be given several blog posts from one website. '
			. 'Produce a concise, reusable STYLE GUIDE that captures how this author writes, so another writer could '
			. 'imitate the voice exactly. Cover: overall tone, formality, typical sentence length and rhythm, '
			. 'vocabulary and recurring phrases, paragraph/structure habits, use of headings/lists, and point of view '
			. '(first/second/third person). Be specific and prescriptive. Output plain prose under 350 words. '
			. 'Do not summarize the topics — describe the VOICE only.';

		$user = "Here are sample posts from the site:\n\n" . implode( "\n\n---\n\n", $samples );

		$client  = new Voicewriter_AI_Claude_Client();
		$summary = $client->complete( $system, $user, 1200 );

		if ( is_wp_error( $summary ) ) {
			return $summary;
		}

		$profile = array(
			'summary'      => $summary,
			'sample_count' => count( $samples ),
			'trained_at'   => time(),
		);

		update_option( VOICEWRITER_AI_OPTION_PROFILE, $profile, false );

		return $profile;
	}

	/**
	 * Get the stored profile.
	 *
	 * @return array
	 */
	public function get() {
		$profile = get_option( VOICEWRITER_AI_OPTION_PROFILE, array() );
		return is_array( $profile ) ? $profile : array();
	}

	/**
	 * Whether a usable voice profile exists.
	 *
	 * @return bool
	 */
	public function exists() {
		$profile = $this->get();
		return ! empty( $profile['summary'] );
	}

	/**
	 * Build a system prompt that bakes in the learned voice.
	 *
	 * @param string $task_instructions Task-specific instructions.
	 * @return string
	 */
	public function system_prompt( $task_instructions ) {
		$profile = $this->get();
		$voice   = ! empty( $profile['summary'] )
			? $profile['summary']
			: 'No specific voice profile is available; write clearly and professionally.';

		return "You are a writer for a specific website. Always write in the site's established voice.\n\n"
			. "=== SITE VOICE PROFILE ===\n" . $voice . "\n=== END VOICE PROFILE ===\n\n"
			. $task_instructions;
	}
}
