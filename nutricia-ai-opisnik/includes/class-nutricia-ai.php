<?php
/**
 * Main plugin class (singleton).
 *
 * @package NutriciaAiOpisnik
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Nutricia_AI {

	/**
	 * Singleton instance.
	 *
	 * @var Nutricia_AI|null
	 */
	protected static $instance = null;

	/**
	 * Get the instance.
	 *
	 * @return Nutricia_AI
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor: wire up submodules.
	 */
	protected function __construct() {
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );

		Nutricia_AI_Cron::init();

		if ( is_admin() ) {
			Nutricia_AI_Admin::init();
			Nutricia_AI_Product_UI::init();
			add_action( 'admin_notices', array( $this, 'maybe_woocommerce_notice' ) );
		}
	}

	/**
	 * Load translations.
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'nutricia-ai-opisnik', false, dirname( NUTRICIA_AI_BASENAME ) . '/languages' );
	}

	/**
	 * Warn if WooCommerce is not active.
	 */
	public function maybe_woocommerce_notice() {
		if ( class_exists( 'WooCommerce' ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( $screen && 'tools_page_' . Nutricia_AI_Admin::PAGE_SLUG === $screen->id ) {
			return; // The page shows its own notice.
		}
		echo '<div class="notice notice-warning"><p>'
			. esc_html__( 'Nutricia AI Opisnik zahteva aktivan WooCommerce za obradu proizvoda.', 'nutricia-ai-opisnik' )
			. '</p></div>';
	}

	/**
	 * Activation.
	 */
	public static function activate() {
		// Ensure defaults exist.
		if ( false === get_option( Nutricia_AI_Settings::OPTION_KEY, false ) ) {
			Nutricia_AI_Settings::update( Nutricia_AI_Settings::defaults() );
		}
		Nutricia_AI_Cron::reschedule();
	}

	/**
	 * Deactivation.
	 */
	public static function deactivate() {
		Nutricia_AI_Cron::unschedule();
		delete_transient( Nutricia_AI_Cron::LOCK_KEY );
	}
}
