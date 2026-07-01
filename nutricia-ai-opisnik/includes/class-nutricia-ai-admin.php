<?php
/**
 * Admin settings page under Tools, plus form/action handling.
 *
 * @package NutriciaAiOpisnik
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Nutricia_AI_Admin {

	const PAGE_SLUG = 'nutricia-ai-opisnik';
	const CAPABILITY = 'manage_woocommerce';

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle_actions' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_filter( 'plugin_action_links_' . NUTRICIA_AI_BASENAME, array( __CLASS__, 'action_links' ) );
	}

	/**
	 * Capability used for the page. Falls back to manage_options if WC is absent.
	 *
	 * @return string
	 */
	public static function capability() {
		return current_user_can( self::CAPABILITY ) ? self::CAPABILITY : 'manage_options';
	}

	/**
	 * Add the Tools submenu page.
	 */
	public static function add_menu() {
		add_management_page(
			__( 'Nutricia AI Opisnik', 'nutricia-ai-opisnik' ),
			__( 'Nutricia AI Opisnik', 'nutricia-ai-opisnik' ),
			self::capability(),
			self::PAGE_SLUG,
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Settings link on the plugins screen.
	 *
	 * @param array $links Links.
	 * @return array
	 */
	public static function action_links( $links ) {
		$url = admin_url( 'tools.php?page=' . self::PAGE_SLUG );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Podešavanja', 'nutricia-ai-opisnik' ) . '</a>' );
		return $links;
	}

	/**
	 * Enqueue admin CSS/JS on our page only.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public static function enqueue_assets( $hook ) {
		if ( 'tools_page_' . self::PAGE_SLUG !== $hook ) {
			return;
		}
		wp_enqueue_style(
			'nutricia-ai-admin',
			NUTRICIA_AI_URL . 'assets/css/admin.css',
			array(),
			NUTRICIA_AI_VERSION
		);
	}

	/**
	 * Handle form submissions and action buttons.
	 */
	public static function handle_actions() {
		if ( ! isset( $_GET['page'] ) || self::PAGE_SLUG !== $_GET['page'] ) {
			return;
		}
		if ( ! current_user_can( self::capability() ) ) {
			return;
		}

		// Save settings.
		if ( isset( $_POST['nutricia_ai_save_settings'] ) ) {
			check_admin_referer( 'nutricia_ai_settings' );
			$raw   = isset( $_POST['nutricia_ai'] ) ? (array) wp_unslash( $_POST['nutricia_ai'] ) : array();
			// Re-slash: sanitize() calls wp_unslash internally where needed.
			$clean = Nutricia_AI_Settings::sanitize( wp_slash( $raw ) );
			Nutricia_AI_Settings::update( $clean );
			Nutricia_AI_Cron::reschedule();
			self::redirect_with_notice( 'saved' );
		}

		// Test connection.
		if ( isset( $_POST['nutricia_ai_test'] ) ) {
			check_admin_referer( 'nutricia_ai_settings' );
			$result = Nutricia_AI_OpenRouter::test_connection();
			if ( is_wp_error( $result ) ) {
				set_transient( 'nutricia_ai_notice', array( 'type' => 'error', 'text' => $result->get_error_message() ), 60 );
			} else {
				set_transient( 'nutricia_ai_notice', array( 'type' => 'success', 'text' => __( 'Veza sa OpenRouter-om je uspešna.', 'nutricia-ai-opisnik' ) ), 60 );
			}
			self::redirect_with_notice();
		}

		// Process next now.
		if ( isset( $_POST['nutricia_ai_process_now'] ) ) {
			check_admin_referer( 'nutricia_ai_actions' );
			$product_id = Nutricia_AI_Cron::get_next_product_id();
			if ( ! $product_id ) {
				set_transient( 'nutricia_ai_notice', array( 'type' => 'info', 'text' => __( 'Nema proizvoda za obradu.', 'nutricia-ai-opisnik' ) ), 60 );
			} else {
				$result = Nutricia_AI_Processor::process( $product_id );
				if ( is_wp_error( $result ) ) {
					set_transient( 'nutricia_ai_notice', array( 'type' => 'error', 'text' => $result->get_error_message() ), 60 );
				} else {
					set_transient(
						'nutricia_ai_notice',
						array(
							'type' => 'success',
							'text' => sprintf(
								/* translators: %s product title */
								__( 'Obrađen proizvod: %s', 'nutricia-ai-opisnik' ),
								get_the_title( $product_id )
							),
						),
						60
					);
				}
			}
			self::redirect_with_notice();
		}

		// Reset queue.
		if ( isset( $_POST['nutricia_ai_reset'] ) ) {
			check_admin_referer( 'nutricia_ai_actions' );
			$count = Nutricia_AI_Cron::reset_all();
			set_transient(
				'nutricia_ai_notice',
				array(
					'type' => 'success',
					'text' => sprintf(
						/* translators: %d number of products */
						__( 'Status je resetovan. Svi proizvodi (%d meta zapisa) su ponovo u redu za obradu.', 'nutricia-ai-opisnik' ),
						$count
					),
				),
				60
			);
			self::redirect_with_notice();
		}

		// Clear log.
		if ( isset( $_POST['nutricia_ai_clear_log'] ) ) {
			check_admin_referer( 'nutricia_ai_actions' );
			Nutricia_AI_Logger::clear();
			self::redirect_with_notice();
		}
	}

	/**
	 * Redirect back to the page, optionally flagging a saved notice.
	 *
	 * @param string $flag Optional flag.
	 */
	protected static function redirect_with_notice( $flag = '' ) {
		if ( 'saved' === $flag ) {
			set_transient( 'nutricia_ai_notice', array( 'type' => 'success', 'text' => __( 'Podešavanja su sačuvana.', 'nutricia-ai-opisnik' ) ), 60 );
		}
		wp_safe_redirect( admin_url( 'tools.php?page=' . self::PAGE_SLUG ) );
		exit;
	}

	/**
	 * Render the settings/dashboard page.
	 */
	public static function render_page() {
		if ( ! current_user_can( self::capability() ) ) {
			wp_die( esc_html__( 'Nemate dozvolu za pristup ovoj stranici.', 'nutricia-ai-opisnik' ) );
		}

		$s         = Nutricia_AI_Settings::all();
		$intervals = Nutricia_AI_Settings::cron_intervals();
		$stats     = Nutricia_AI_Cron::get_stats();
		$next_run  = Nutricia_AI_Cron::next_run();
		$log       = Nutricia_AI_Logger::get_entries();
		$has_key   = ! empty( $s['api_key'] );

		$notice = get_transient( 'nutricia_ai_notice' );
		if ( $notice ) {
			delete_transient( 'nutricia_ai_notice' );
		}

		$wc_active     = class_exists( 'WooCommerce' );
		$rankmath_active = defined( 'RANK_MATH_VERSION' ) || class_exists( 'RankMath' );

		include NUTRICIA_AI_PATH . 'includes/views/admin-page.php';
	}
}
