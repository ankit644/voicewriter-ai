<?php
/**
 * Standalone harness for the multi-provider AI client.
 *
 * This is NOT shipped with the plugin (lives in /tests, excluded from SVN).
 * It mocks the handful of WordPress functions the client touches and
 * intercepts wp_remote_post so we can assert the exact request each
 * provider builds, and that responses parse correctly — with no network
 * calls and no API keys.
 *
 * Run:  php tests/test-ai-client.php
 *
 * @package VoiceWriter_AI
 */

// ---- Captured request + canned response, shared with the mock. ----
$GLOBALS['vw_last_request']  = null;
$GLOBALS['vw_next_response'] = null;
$GLOBALS['vw_options']       = array();

// ---- Minimal WordPress shims. ----
define( 'ABSPATH', __DIR__ . '/' );
define( 'VOICEWRITER_AI_OPTION_SETTINGS', 'voicewriter_ai_settings' );

function __( $text, $domain = 'default' ) {
	return $text;
}

function get_option( $name, $default = false ) {
	return isset( $GLOBALS['vw_options'][ $name ] ) ? $GLOBALS['vw_options'][ $name ] : $default;
}

function wp_json_encode( $data ) {
	return json_encode( $data );
}

function add_query_arg( $key, $value, $url ) {
	$sep = ( false === strpos( $url, '?' ) ) ? '?' : '&';
	return $url . $sep . $key . '=' . $value;
}

function wp_remote_post( $url, $args ) {
	$GLOBALS['vw_last_request'] = array(
		'url'  => $url,
		'args' => $args,
	);
	return $GLOBALS['vw_next_response'];
}

function wp_remote_retrieve_response_code( $response ) {
	return isset( $response['response']['code'] ) ? $response['response']['code'] : 0;
}

function wp_remote_retrieve_body( $response ) {
	return isset( $response['body'] ) ? $response['body'] : '';
}

function is_wp_error( $thing ) {
	return $thing instanceof WP_Error;
}

class WP_Error {
	private $code;
	private $message;
	private $data;
	public function __construct( $code = '', $message = '', $data = '' ) {
		$this->code    = $code;
		$this->message = $message;
		$this->data    = $data;
	}
	public function get_error_message() {
		return $this->message;
	}
	public function get_error_code() {
		return $this->code;
	}
}

// ---- Load the class under test. ----
require __DIR__ . '/../includes/class-voicewriter-ai-ai-client.php';

// ---- Tiny assertion helpers. ----
$GLOBALS['vw_pass'] = 0;
$GLOBALS['vw_fail'] = 0;

function check( $label, $cond ) {
	if ( $cond ) {
		$GLOBALS['vw_pass']++;
		echo "  PASS  $label\n";
	} else {
		$GLOBALS['vw_fail']++;
		echo "  FAIL  $label\n";
	}
}

function set_settings( $provider, $key, $model ) {
	$GLOBALS['vw_options']['voicewriter_ai_settings'] = array(
		'provider' => $provider,
		'api_key'  => $key,
		'model'    => $model,
	);
}

function set_response( $code, $bodyArray ) {
	$GLOBALS['vw_next_response'] = array(
		'response' => array( 'code' => $code ),
		'body'     => json_encode( $bodyArray ),
	);
}

$client = new Voicewriter_AI_AI_Client();

echo "\n== Anthropic (Claude) ==\n";
set_settings( 'anthropic', 'sk-ant-test', 'claude-sonnet-4-6' );
set_response( 200, array( 'content' => array( array( 'type' => 'text', 'text' => 'Hello from Claude.' ) ) ) );
$out = $client->complete( 'SYS', 'USER', 1000 );
$req = $GLOBALS['vw_last_request'];
$body = json_decode( $req['args']['body'], true );
check( 'returns text', 'Hello from Claude.' === $out );
check( 'correct endpoint', 'https://api.anthropic.com/v1/messages' === $req['url'] );
check( 'sends x-api-key header', isset( $req['args']['headers']['x-api-key'] ) && 'sk-ant-test' === $req['args']['headers']['x-api-key'] );
check( 'sends anthropic-version', isset( $req['args']['headers']['anthropic-version'] ) );
check( 'system as top-level field', isset( $body['system'] ) && 'SYS' === $body['system'] );
check( 'user message present', 'USER' === $body['messages'][0]['content'] );
check( 'model passed through', 'claude-sonnet-4-6' === $body['model'] );

echo "\n== OpenAI (ChatGPT) ==\n";
set_settings( 'openai', 'sk-openai-test', 'gpt-4o-mini' );
set_response( 200, array( 'choices' => array( array( 'message' => array( 'content' => 'Hello from GPT.' ) ) ) ) );
$out = $client->complete( 'SYS', 'USER', 1000 );
$req = $GLOBALS['vw_last_request'];
$body = json_decode( $req['args']['body'], true );
check( 'returns text', 'Hello from GPT.' === $out );
check( 'correct endpoint', 'https://api.openai.com/v1/chat/completions' === $req['url'] );
check( 'sends Bearer auth', isset( $req['args']['headers']['Authorization'] ) && 'Bearer sk-openai-test' === $req['args']['headers']['Authorization'] );
check( 'system as first message', 'system' === $body['messages'][0]['role'] && 'SYS' === $body['messages'][0]['content'] );
check( 'user as second message', 'user' === $body['messages'][1]['role'] && 'USER' === $body['messages'][1]['content'] );
check( 'model passed through', 'gpt-4o-mini' === $body['model'] );

echo "\n== Google (Gemini) ==\n";
set_settings( 'gemini', 'AIza-test', 'gemini-2.5-flash' );
set_response( 200, array( 'candidates' => array( array( 'content' => array( 'parts' => array( array( 'text' => 'Hello from Gemini.' ) ) ) ) ) ) );
$out = $client->complete( 'SYS', 'USER', 1000 );
$req = $GLOBALS['vw_last_request'];
$body = json_decode( $req['args']['body'], true );
check( 'returns text', 'Hello from Gemini.' === $out );
check( 'endpoint targets model', false !== strpos( $req['url'], 'models/gemini-2.5-flash:generateContent' ) );
check( 'key in query string', false !== strpos( $req['url'], 'key=AIza-test' ) );
check( 'system_instruction set', 'SYS' === $body['system_instruction']['parts'][0]['text'] );
check( 'user content set', 'USER' === $body['contents'][0]['parts'][0]['text'] );
check( 'maxOutputTokens set', 1000 === $body['generationConfig']['maxOutputTokens'] );

echo "\n== Error handling ==\n";
set_settings( 'anthropic', '', 'claude-sonnet-4-6' );
$out = $client->complete( 'SYS', 'USER', 1000 );
check( 'missing key -> WP_Error', $out instanceof WP_Error && 'voicewriter_ai_no_key' === $out->get_error_code() );

set_settings( 'openai', 'sk-bad', 'gpt-4o-mini' );
set_response( 401, array( 'error' => array( 'message' => 'Invalid API key' ) ) );
$out = $client->complete( 'SYS', 'USER', 1000 );
check( 'HTTP 401 -> WP_Error with provider message', $out instanceof WP_Error && 'Invalid API key' === $out->get_error_message() );

set_settings( 'anthropic', 'sk-ant', 'claude-sonnet-4-6' );
set_response( 200, array( 'content' => array() ) );
$out = $client->complete( 'SYS', 'USER', 1000 );
check( 'empty content -> WP_Error', $out instanceof WP_Error && 'voicewriter_ai_empty' === $out->get_error_code() );

echo "\n== Unknown provider falls back to Anthropic ==\n";
set_settings( 'doesnotexist', 'sk-x', '' );
set_response( 200, array( 'content' => array( array( 'type' => 'text', 'text' => 'fallback ok' ) ) ) );
$out = $client->complete( 'SYS', 'USER', 1000 );
$req = $GLOBALS['vw_last_request'];
$body = json_decode( $req['args']['body'], true );
check( 'routes to anthropic endpoint', 'https://api.anthropic.com/v1/messages' === $req['url'] );
check( 'uses anthropic default model', 'claude-sonnet-4-6' === $body['model'] );

echo "\n----------------------------------------\n";
printf( "RESULT: %d passed, %d failed\n", $GLOBALS['vw_pass'], $GLOBALS['vw_fail'] );
exit( $GLOBALS['vw_fail'] > 0 ? 1 : 0 );
