<?php
/**
 * Settings storage, defaults and helpers.
 *
 * @package NutriciaAiOpisnik
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Nutricia_AI_Settings {

	const OPTION_KEY = 'nutricia_ai_settings';

	/**
	 * Meta keys used to store per-product processing state.
	 */
	const META_STATUS    = '_nutricia_ai_status';   // queued|processing|done|error.
	const META_PROCESSED = '_nutricia_ai_processed_at';
	const META_ERROR     = '_nutricia_ai_last_error';
	const META_ATTEMPTS  = '_nutricia_ai_attempts';

	/**
	 * Available cron intervals in seconds keyed by slug.
	 *
	 * @return array
	 */
	public static function cron_intervals() {
		return array(
			'1min'  => array( 'label' => __( '1 minut', 'nutricia-ai-opisnik' ), 'seconds' => MINUTE_IN_SECONDS ),
			'2min'  => array( 'label' => __( '2 minuta', 'nutricia-ai-opisnik' ), 'seconds' => 2 * MINUTE_IN_SECONDS ),
			'5min'  => array( 'label' => __( '5 minuta', 'nutricia-ai-opisnik' ), 'seconds' => 5 * MINUTE_IN_SECONDS ),
			'10min' => array( 'label' => __( '10 minuta', 'nutricia-ai-opisnik' ), 'seconds' => 10 * MINUTE_IN_SECONDS ),
			'30min' => array( 'label' => __( '30 minuta', 'nutricia-ai-opisnik' ), 'seconds' => 30 * MINUTE_IN_SECONDS ),
			'1h'    => array( 'label' => __( '1 sat', 'nutricia-ai-opisnik' ), 'seconds' => HOUR_IN_SECONDS ),
			'2h'    => array( 'label' => __( '2 sata', 'nutricia-ai-opisnik' ), 'seconds' => 2 * HOUR_IN_SECONDS ),
		);
	}

	/**
	 * Default settings.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'api_key'            => '',
			'model'              => 'openai/gpt-4o-mini',
			'cron_interval'      => '5min',
			'auto_enabled'       => 0,
			'vision_mode'        => 'auto', // never|always|auto.
			'vision_threshold'   => 350,    // chars of combined text below which "auto" sends the image.
			'composition_meta'   => 'sastav',
			'overwrite_desc'     => 1,      // overwrite existing descriptions.
			'max_tags'           => 5,
			'max_attempts'       => 3,
			'temperature'        => 0.6,
			'set_focus_keyword'  => 1,
			'site_context'       => self::default_site_context(),
		);
	}

	/**
	 * Default site context text (Nutricia Market).
	 *
	 * @return string
	 */
	public static function default_site_context() {
		return "Nutricia Market je online prodavnica zdrave hrane koja posluje od 2005. godine. "
			. "Asortiman obuhvata: sportsku suplementaciju (proteini, aminokiseline, kreatin), vitamine i minerale, "
			. "biljne dodatke, osnovne namirnice (integralne žitarice, bezglutenski proizvodi, ulja, koštunjavo voće, "
			. "semenke, med), čajeve i biljne napitke, prirodnu kozmetiku i sredstva za higijenu. "
			. "Ton je stručan, poverljiv i orijentisan na zdravlje i prirodna rešenja. "
			. "Ciljna publika su kupci u Srbiji koji vode računa o zdravlju. Jezik komunikacije je srpski.";
	}

	/**
	 * Get all settings merged with defaults.
	 *
	 * @return array
	 */
	public static function all() {
		$saved = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		return wp_parse_args( $saved, self::defaults() );
	}

	/**
	 * Get a single setting.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Fallback.
	 * @return mixed
	 */
	public static function get( $key, $default = null ) {
		$all = self::all();
		if ( array_key_exists( $key, $all ) ) {
			return $all[ $key ];
		}
		return $default;
	}

	/**
	 * Persist a full settings array (already sanitized).
	 *
	 * @param array $settings Settings.
	 */
	public static function update( array $settings ) {
		update_option( self::OPTION_KEY, $settings, false );
	}

	/**
	 * Sanitize a raw $_POST settings array against defaults.
	 * Note: an empty api_key keeps the previously stored key.
	 *
	 * @param array $input Raw input.
	 * @return array
	 */
	public static function sanitize( array $input ) {
		$current  = self::all();
		$defaults = self::defaults();
		$clean    = array();

		// API key: keep existing if left blank so it is never wiped by an empty submit.
		$posted_key = isset( $input['api_key'] ) ? trim( (string) wp_unslash( $input['api_key'] ) ) : '';
		if ( '' === $posted_key ) {
			$clean['api_key'] = $current['api_key'];
		} else {
			$clean['api_key'] = sanitize_text_field( $posted_key );
		}

		$clean['model'] = isset( $input['model'] ) ? sanitize_text_field( wp_unslash( $input['model'] ) ) : $defaults['model'];
		if ( '' === $clean['model'] ) {
			$clean['model'] = $defaults['model'];
		}

		$intervals               = self::cron_intervals();
		$clean['cron_interval']  = isset( $input['cron_interval'] ) && isset( $intervals[ $input['cron_interval'] ] )
			? sanitize_key( $input['cron_interval'] )
			: $defaults['cron_interval'];

		$clean['auto_enabled'] = empty( $input['auto_enabled'] ) ? 0 : 1;

		$vision_allowed        = array( 'never', 'always', 'auto' );
		$clean['vision_mode']  = ( isset( $input['vision_mode'] ) && in_array( $input['vision_mode'], $vision_allowed, true ) )
			? $input['vision_mode']
			: $defaults['vision_mode'];

		$clean['vision_threshold'] = isset( $input['vision_threshold'] ) ? absint( $input['vision_threshold'] ) : $defaults['vision_threshold'];

		$clean['composition_meta'] = isset( $input['composition_meta'] ) ? sanitize_text_field( wp_unslash( $input['composition_meta'] ) ) : $defaults['composition_meta'];
		if ( '' === $clean['composition_meta'] ) {
			$clean['composition_meta'] = $defaults['composition_meta'];
		}

		$clean['overwrite_desc']    = empty( $input['overwrite_desc'] ) ? 0 : 1;
		$clean['set_focus_keyword'] = empty( $input['set_focus_keyword'] ) ? 0 : 1;

		$clean['max_tags']     = isset( $input['max_tags'] ) ? max( 1, min( 10, absint( $input['max_tags'] ) ) ) : $defaults['max_tags'];
		$clean['max_attempts'] = isset( $input['max_attempts'] ) ? max( 1, min( 10, absint( $input['max_attempts'] ) ) ) : $defaults['max_attempts'];

		$temp = isset( $input['temperature'] ) ? (float) $input['temperature'] : $defaults['temperature'];
		$clean['temperature'] = max( 0, min( 2, $temp ) );

		$clean['site_context'] = isset( $input['site_context'] )
			? sanitize_textarea_field( wp_unslash( $input['site_context'] ) )
			: $defaults['site_context'];

		return $clean;
	}

	/**
	 * Whether the plugin has the minimum config to run (API key + model).
	 *
	 * @return bool
	 */
	public static function is_configured() {
		$s = self::all();
		return ! empty( $s['api_key'] ) && ! empty( $s['model'] );
	}
}
