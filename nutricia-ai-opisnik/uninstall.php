<?php
/**
 * Uninstall cleanup.
 *
 * Removes plugin options, the activity log and per-product processing meta.
 * Product content (descriptions, tags, Rank Math meta, image alt) is left
 * intact on purpose — that is real content the store keeps.
 *
 * @package NutriciaAiOpisnik
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Options.
delete_option( 'nutricia_ai_settings' );
delete_option( 'nutricia_ai_log' );

// Transients.
delete_transient( 'nutricia_ai_lock' );
delete_transient( 'nutricia_ai_notice' );

// Per-product processing meta.
$meta_keys = array(
	'_nutricia_ai_status',
	'_nutricia_ai_processed_at',
	'_nutricia_ai_last_error',
	'_nutricia_ai_attempts',
);

foreach ( $meta_keys as $key ) {
	$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => $key ) );
}

// Clear any scheduled event.
wp_clear_scheduled_hook( 'nutricia_ai_process_event' );
