<?php
/**
 * Cron scheduling and queue handling.
 *
 * @package NutriciaAiOpisnik
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Nutricia_AI_Cron {

	const HOOK          = 'nutricia_ai_process_event';
	const SCHEDULE_SLUG = 'nutricia_ai_interval';
	const LOCK_KEY      = 'nutricia_ai_lock';

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_filter( 'cron_schedules', array( __CLASS__, 'add_schedule' ) );
		add_action( self::HOOK, array( __CLASS__, 'run' ) );
	}

	/**
	 * Add a dynamic schedule based on the configured interval.
	 *
	 * @param array $schedules Existing schedules.
	 * @return array
	 */
	public static function add_schedule( $schedules ) {
		$intervals = Nutricia_AI_Settings::cron_intervals();
		$slug      = Nutricia_AI_Settings::get( 'cron_interval', '5min' );
		$seconds   = isset( $intervals[ $slug ] ) ? $intervals[ $slug ]['seconds'] : 5 * MINUTE_IN_SECONDS;

		$schedules[ self::SCHEDULE_SLUG ] = array(
			'interval' => $seconds,
			'display'  => __( 'Nutricia AI interval', 'nutricia-ai-opisnik' ),
		);

		return $schedules;
	}

	/**
	 * (Re)schedule the recurring event. Called after settings change and on activation.
	 */
	public static function reschedule() {
		self::unschedule();

		if ( ! Nutricia_AI_Settings::get( 'auto_enabled' ) ) {
			return;
		}

		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, self::SCHEDULE_SLUG, self::HOOK );
		}
	}

	/**
	 * Remove the scheduled event.
	 */
	public static function unschedule() {
		$timestamp = wp_next_scheduled( self::HOOK );
		while ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::HOOK );
			$timestamp = wp_next_scheduled( self::HOOK );
		}
		wp_clear_scheduled_hook( self::HOOK );
	}

	/**
	 * Cron callback: process the next product in the queue.
	 */
	public static function run() {
		if ( ! Nutricia_AI_Settings::get( 'auto_enabled' ) ) {
			return;
		}
		if ( ! Nutricia_AI_Settings::is_configured() ) {
			return;
		}

		// Prevent overlapping runs (long API calls vs. short intervals).
		if ( get_transient( self::LOCK_KEY ) ) {
			return;
		}
		set_transient( self::LOCK_KEY, 1, 10 * MINUTE_IN_SECONDS );

		$product_id = self::get_next_product_id();

		if ( $product_id ) {
			Nutricia_AI_Processor::process( $product_id );
		}

		delete_transient( self::LOCK_KEY );
	}

	/**
	 * Find the next product that still needs processing.
	 * Considers products with no status or status = queued. Errored products
	 * are retried until they reach the max attempt count.
	 *
	 * @return int 0 if none pending.
	 */
	public static function get_next_product_id() {
		$max_attempts = (int) Nutricia_AI_Settings::get( 'max_attempts', 3 );

		// First: products that have never been touched or are explicitly queued.
		$query = new WP_Query(
			array(
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_query'     => array(
					'relation' => 'OR',
					array(
						'key'     => Nutricia_AI_Settings::META_STATUS,
						'compare' => 'NOT EXISTS',
					),
					array(
						'key'     => Nutricia_AI_Settings::META_STATUS,
						'value'   => 'queued',
						'compare' => '=',
					),
				),
			)
		);

		if ( ! empty( $query->posts ) ) {
			return (int) $query->posts[0];
		}

		// Second: retry errored products under the attempt limit.
		$retry = new WP_Query(
			array(
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_query'     => array(
					'relation' => 'AND',
					array(
						'key'     => Nutricia_AI_Settings::META_STATUS,
						'value'   => 'error',
						'compare' => '=',
					),
					array(
						'key'     => Nutricia_AI_Settings::META_ATTEMPTS,
						'value'   => $max_attempts,
						'compare' => '<',
						'type'    => 'NUMERIC',
					),
				),
			)
		);

		if ( ! empty( $retry->posts ) ) {
			return (int) $retry->posts[0];
		}

		return 0;
	}

	/**
	 * Count products by processing status. Returns totals used by the dashboard.
	 *
	 * @return array
	 */
	public static function get_stats() {
		$total = (int) wp_count_posts( 'product' )->publish;

		$done = self::count_by_status( 'done' );
		$error = self::count_by_status( 'error' );

		// Pending = published products that are not done and not permanently errored.
		$pending = max( 0, $total - $done - $error );

		return array(
			'total'   => $total,
			'done'    => $done,
			'error'   => $error,
			'pending' => $pending,
		);
	}

	/**
	 * Count published products with a given AI status.
	 *
	 * @param string $status Status value.
	 * @return int
	 */
	protected static function count_by_status( $status ) {
		$query = new WP_Query(
			array(
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => array(
					array(
						'key'     => Nutricia_AI_Settings::META_STATUS,
						'value'   => $status,
						'compare' => '=',
					),
				),
			)
		);
		return (int) $query->found_posts;
	}

	/**
	 * Reset processing state for all products (so everything is re-queued).
	 *
	 * @return int Number of products reset.
	 */
	public static function reset_all() {
		global $wpdb;

		$count = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->postmeta} WHERE meta_key IN ( %s, %s, %s, %s )",
				Nutricia_AI_Settings::META_STATUS,
				Nutricia_AI_Settings::META_PROCESSED,
				Nutricia_AI_Settings::META_ERROR,
				Nutricia_AI_Settings::META_ATTEMPTS
			)
		);

		return (int) $count;
	}

	/**
	 * Next scheduled run timestamp (0 if not scheduled).
	 *
	 * @return int
	 */
	public static function next_run() {
		$ts = wp_next_scheduled( self::HOOK );
		return $ts ? (int) $ts : 0;
	}
}
