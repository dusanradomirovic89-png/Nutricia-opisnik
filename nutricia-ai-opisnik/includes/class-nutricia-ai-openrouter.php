<?php
/**
 * OpenRouter API client (chat completions, with optional vision).
 *
 * @package NutriciaAiOpisnik
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Nutricia_AI_OpenRouter {

	const ENDPOINT = 'https://openrouter.ai/api/v1/chat/completions';

	/**
	 * Perform a chat completion request.
	 *
	 * @param string $system_prompt System message.
	 * @param array  $user_content  Content array (text + optional image parts).
	 * @param array  $args          Optional overrides (model, temperature, response_format...).
	 * @return array|WP_Error       Decoded assistant JSON content on success.
	 */
	public static function chat( $system_prompt, $user_content, $args = array() ) {
		$settings = Nutricia_AI_Settings::all();

		$api_key = $settings['api_key'];
		if ( empty( $api_key ) ) {
			return new WP_Error( 'nutricia_no_key', __( 'OpenRouter API ključ nije podešen.', 'nutricia-ai-opisnik' ) );
		}

		$model       = ! empty( $args['model'] ) ? $args['model'] : $settings['model'];
		$temperature = isset( $args['temperature'] ) ? (float) $args['temperature'] : (float) $settings['temperature'];

		$body = array(
			'model'       => $model,
			'temperature' => $temperature,
			'messages'    => array(
				array(
					'role'    => 'system',
					'content' => $system_prompt,
				),
				array(
					'role'    => 'user',
					'content' => $user_content,
				),
			),
			// Ask providers that support it to return strict JSON.
			'response_format' => array( 'type' => 'json_object' ),
		);

		$site_url = home_url();

		$response = wp_remote_post(
			self::ENDPOINT,
			array(
				'timeout' => 90,
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
					'HTTP-Referer'  => $site_url,
					'X-Title'       => 'Nutricia AI Opisnik',
				),
				'body'    => wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$raw  = wp_remote_retrieve_body( $response );

		if ( $code < 200 || $code >= 300 ) {
			$message = self::extract_error_message( $raw );
			return new WP_Error(
				'nutricia_api_error',
				sprintf(
					/* translators: 1: HTTP status, 2: error message */
					__( 'OpenRouter greška (HTTP %1$d): %2$s', 'nutricia-ai-opisnik' ),
					(int) $code,
					$message
				)
			);
		}

		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) || empty( $data['choices'][0]['message']['content'] ) ) {
			return new WP_Error( 'nutricia_bad_response', __( 'Neočekivan odgovor od OpenRouter-a.', 'nutricia-ai-opisnik' ) );
		}

		$content = $data['choices'][0]['message']['content'];
		$parsed  = self::parse_json_content( $content );

		if ( null === $parsed ) {
			return new WP_Error(
				'nutricia_parse_error',
				__( 'Nije moguće pročitati JSON iz AI odgovora.', 'nutricia-ai-opisnik' )
			);
		}

		return $parsed;
	}

	/**
	 * Lightweight connectivity / credentials test.
	 *
	 * @param string $model Optional model to test.
	 * @return true|WP_Error
	 */
	public static function test_connection( $model = '' ) {
		$args = array( 'temperature' => 0 );
		if ( $model ) {
			$args['model'] = $model;
		}

		$result = self::chat(
			'Ti si pomoćnik koji odgovara isključivo u JSON formatu.',
			'Vrati JSON objekat oblika {"ok": true}.',
			$args
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return true;
	}

	/**
	 * Try hard to decode a JSON object from the model output, tolerating
	 * code fences or leading/trailing prose.
	 *
	 * @param string $content Raw assistant content.
	 * @return array|null
	 */
	protected static function parse_json_content( $content ) {
		$content = trim( (string) $content );

		// Strip ```json ... ``` fences if present.
		if ( 0 === strpos( $content, '```' ) ) {
			$content = preg_replace( '/^```[a-zA-Z]*\s*/', '', $content );
			$content = preg_replace( '/\s*```$/', '', $content );
			$content = trim( $content );
		}

		$decoded = json_decode( $content, true );
		if ( is_array( $decoded ) ) {
			return $decoded;
		}

		// Fallback: grab the first {...} block.
		$start = strpos( $content, '{' );
		$end   = strrpos( $content, '}' );
		if ( false !== $start && false !== $end && $end > $start ) {
			$candidate = substr( $content, $start, $end - $start + 1 );
			$decoded   = json_decode( $candidate, true );
			if ( is_array( $decoded ) ) {
				return $decoded;
			}
		}

		return null;
	}

	/**
	 * Extract a readable error message from an API error body.
	 *
	 * @param string $raw Raw body.
	 * @return string
	 */
	protected static function extract_error_message( $raw ) {
		$data = json_decode( $raw, true );
		if ( is_array( $data ) ) {
			if ( isset( $data['error']['message'] ) ) {
				return sanitize_text_field( $data['error']['message'] );
			}
			if ( isset( $data['error'] ) && is_string( $data['error'] ) ) {
				return sanitize_text_field( $data['error'] );
			}
			if ( isset( $data['message'] ) ) {
				return sanitize_text_field( $data['message'] );
			}
		}
		$raw = wp_strip_all_tags( (string) $raw );
		return $raw ? mb_substr( $raw, 0, 300 ) : __( 'Nepoznata greška.', 'nutricia-ai-opisnik' );
	}
}
