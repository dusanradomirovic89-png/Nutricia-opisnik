<?php
/**
 * Product-level UI: admin column, row/bulk actions and edit-screen meta box.
 *
 * @package NutriciaAiOpisnik
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Nutricia_AI_Product_UI {

	/**
	 * Register hooks.
	 */
	public static function init() {
		// Products list column.
		add_filter( 'manage_edit-product_columns', array( __CLASS__, 'add_column' ), 20 );
		add_action( 'manage_product_posts_custom_column', array( __CLASS__, 'render_column' ), 10, 2 );

		// Bulk action.
		add_filter( 'bulk_actions-edit-product', array( __CLASS__, 'add_bulk_action' ) );
		add_filter( 'handle_bulk_actions-edit-product', array( __CLASS__, 'handle_bulk_action' ), 10, 3 );
		add_action( 'admin_notices', array( __CLASS__, 'bulk_notice' ) );

		// Row action link.
		add_filter( 'post_row_actions', array( __CLASS__, 'row_action' ), 10, 2 );
		add_action( 'admin_post_nutricia_ai_process_single', array( __CLASS__, 'handle_single_action' ) );

		// Meta box on the product edit screen.
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_box' ) );
	}

	/**
	 * Add the AI status column to the products list.
	 *
	 * @param array $columns Columns.
	 * @return array
	 */
	public static function add_column( $columns ) {
		$columns['nutricia_ai'] = __( 'AI opis', 'nutricia-ai-opisnik' );
		return $columns;
	}

	/**
	 * Render the AI status column.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Product ID.
	 */
	public static function render_column( $column, $post_id ) {
		if ( 'nutricia_ai' !== $column ) {
			return;
		}
		$status = get_post_meta( $post_id, Nutricia_AI_Settings::META_STATUS, true );
		echo wp_kses_post( self::status_badge( $status ) );

		if ( 'error' === $status ) {
			$err = get_post_meta( $post_id, Nutricia_AI_Settings::META_ERROR, true );
			if ( $err ) {
				echo '<br><small class="nutricia-ai-err">' . esc_html( wp_trim_words( $err, 12 ) ) . '</small>';
			}
		}
	}

	/**
	 * Return an HTML badge for a status value.
	 *
	 * @param string $status Status.
	 * @return string
	 */
	public static function status_badge( $status ) {
		switch ( $status ) {
			case 'done':
				return '<span style="color:#1a7f37;font-weight:600;">● ' . esc_html__( 'Obrađeno', 'nutricia-ai-opisnik' ) . '</span>';
			case 'processing':
				return '<span style="color:#996800;font-weight:600;">◐ ' . esc_html__( 'U obradi', 'nutricia-ai-opisnik' ) . '</span>';
			case 'error':
				return '<span style="color:#cf222e;font-weight:600;">▲ ' . esc_html__( 'Greška', 'nutricia-ai-opisnik' ) . '</span>';
			case 'queued':
				return '<span style="color:#0969da;">○ ' . esc_html__( 'U redu', 'nutricia-ai-opisnik' ) . '</span>';
			default:
				return '<span style="color:#8c8f94;">— ' . esc_html__( 'Nije obrađeno', 'nutricia-ai-opisnik' ) . '</span>';
		}
	}

	/**
	 * Add the bulk action.
	 *
	 * @param array $actions Bulk actions.
	 * @return array
	 */
	public static function add_bulk_action( $actions ) {
		$actions['nutricia_ai_process'] = __( 'Obradi AI-om (Nutricia)', 'nutricia-ai-opisnik' );
		return $actions;
	}

	/**
	 * Handle the bulk action. Processes synchronously (kept modest for timeouts).
	 *
	 * @param string $redirect Redirect URL.
	 * @param string $action   Action name.
	 * @param array  $post_ids Selected IDs.
	 * @return string
	 */
	public static function handle_bulk_action( $redirect, $action, $post_ids ) {
		if ( 'nutricia_ai_process' !== $action ) {
			return $redirect;
		}
		if ( ! current_user_can( 'edit_products' ) ) {
			return $redirect;
		}

		$done   = 0;
		$failed = 0;

		foreach ( $post_ids as $post_id ) {
			$result = Nutricia_AI_Processor::process( (int) $post_id, true );
			if ( is_wp_error( $result ) ) {
				$failed++;
			} else {
				$done++;
			}
		}

		return add_query_arg(
			array(
				'nutricia_ai_done'   => $done,
				'nutricia_ai_failed' => $failed,
			),
			$redirect
		);
	}

	/**
	 * Show a notice after a bulk run.
	 */
	public static function bulk_notice() {
		if ( ! isset( $_GET['nutricia_ai_done'] ) ) {
			return;
		}
		$done   = isset( $_GET['nutricia_ai_done'] ) ? absint( $_GET['nutricia_ai_done'] ) : 0;
		$failed = isset( $_GET['nutricia_ai_failed'] ) ? absint( $_GET['nutricia_ai_failed'] ) : 0;

		printf(
			'<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
			esc_attr( $failed ? 'warning' : 'success' ),
			esc_html(
				sprintf(
					/* translators: 1: success count, 2: fail count */
					__( 'Nutricia AI: obrađeno %1$d proizvoda, neuspešno %2$d.', 'nutricia-ai-opisnik' ),
					$done,
					$failed
				)
			)
		);
	}

	/**
	 * Add a row action link to process a single product.
	 *
	 * @param array   $actions Row actions.
	 * @param WP_Post $post    Post.
	 * @return array
	 */
	public static function row_action( $actions, $post ) {
		if ( 'product' !== $post->post_type || ! current_user_can( 'edit_products' ) ) {
			return $actions;
		}
		$url = wp_nonce_url(
			admin_url( 'admin-post.php?action=nutricia_ai_process_single&product_id=' . $post->ID ),
			'nutricia_ai_single_' . $post->ID
		);
		$actions['nutricia_ai'] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Obradi AI-om', 'nutricia-ai-opisnik' ) . '</a>';
		return $actions;
	}

	/**
	 * Handle single product processing from a row action.
	 */
	public static function handle_single_action() {
		$product_id = isset( $_GET['product_id'] ) ? absint( $_GET['product_id'] ) : 0;
		if ( ! $product_id || ! current_user_can( 'edit_products' ) ) {
			wp_die( esc_html__( 'Nedozvoljena radnja.', 'nutricia-ai-opisnik' ) );
		}
		check_admin_referer( 'nutricia_ai_single_' . $product_id );

		$result   = Nutricia_AI_Processor::process( $product_id, true );
		$redirect = wp_get_referer();
		if ( ! $redirect ) {
			$redirect = admin_url( 'edit.php?post_type=product' );
		}

		$redirect = add_query_arg(
			array(
				'nutricia_ai_done'   => is_wp_error( $result ) ? 0 : 1,
				'nutricia_ai_failed' => is_wp_error( $result ) ? 1 : 0,
			),
			$redirect
		);
		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Register the meta box.
	 */
	public static function add_meta_box() {
		add_meta_box(
			'nutricia_ai_box',
			__( 'Nutricia AI Opisnik', 'nutricia-ai-opisnik' ),
			array( __CLASS__, 'render_meta_box' ),
			'product',
			'side',
			'default'
		);
	}

	/**
	 * Render the meta box content.
	 *
	 * @param WP_Post $post Post.
	 */
	public static function render_meta_box( $post ) {
		$status    = get_post_meta( $post->ID, Nutricia_AI_Settings::META_STATUS, true );
		$processed = get_post_meta( $post->ID, Nutricia_AI_Settings::META_PROCESSED, true );
		$error     = get_post_meta( $post->ID, Nutricia_AI_Settings::META_ERROR, true );

		echo '<p><strong>' . esc_html__( 'Status:', 'nutricia-ai-opisnik' ) . '</strong> ' . wp_kses_post( self::status_badge( $status ) ) . '</p>';

		if ( $processed ) {
			echo '<p><small>' . esc_html__( 'Poslednja obrada:', 'nutricia-ai-opisnik' ) . ' ' . esc_html( $processed ) . '</small></p>';
		}
		if ( 'error' === $status && $error ) {
			echo '<p style="color:#cf222e;"><small>' . esc_html( $error ) . '</small></p>';
		}

		$url = wp_nonce_url(
			admin_url( 'admin-post.php?action=nutricia_ai_process_single&product_id=' . $post->ID ),
			'nutricia_ai_single_' . $post->ID
		);
		echo '<a href="' . esc_url( $url ) . '" class="button button-primary" style="width:100%;text-align:center;box-sizing:border-box;">'
			. esc_html__( 'Obradi ovaj proizvod AI-om', 'nutricia-ai-opisnik' ) . '</a>';
		echo '<p class="description" style="margin-top:8px;">' . esc_html__( 'Sačuvajte proizvod pre obrade kako bi AI koristio najnovije podatke.', 'nutricia-ai-opisnik' ) . '</p>';
	}
}
