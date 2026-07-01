<?php
/**
 * Plugin Name:       Nutricia AI Opisnik
 * Plugin URI:        https://nutriciamarket.com/
 * Description:       Automatska SEO i CRO optimizacija WooCommerce proizvoda pomoću AI-a (OpenRouter). Generiše prodajni kratki opis, SEO dugi opis, alt tekst slike, tagove i Rank Math meta naslov/opis.
 * Version:           1.0.0
 * Author:            Nutricia Market
 * Author URI:        https://nutriciamarket.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       nutricia-ai-opisnik
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * WC requires at least: 5.0
 *
 * @package NutriciaAiOpisnik
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No direct access.
}

define( 'NUTRICIA_AI_VERSION', '1.0.0' );
define( 'NUTRICIA_AI_FILE', __FILE__ );
define( 'NUTRICIA_AI_PATH', plugin_dir_path( __FILE__ ) );
define( 'NUTRICIA_AI_URL', plugin_dir_url( __FILE__ ) );
define( 'NUTRICIA_AI_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Load plugin classes.
 */
require_once NUTRICIA_AI_PATH . 'includes/class-nutricia-ai-logger.php';
require_once NUTRICIA_AI_PATH . 'includes/class-nutricia-ai-settings.php';
require_once NUTRICIA_AI_PATH . 'includes/class-nutricia-ai-openrouter.php';
require_once NUTRICIA_AI_PATH . 'includes/class-nutricia-ai-processor.php';
require_once NUTRICIA_AI_PATH . 'includes/class-nutricia-ai-cron.php';
require_once NUTRICIA_AI_PATH . 'includes/class-nutricia-ai-admin.php';
require_once NUTRICIA_AI_PATH . 'includes/class-nutricia-ai-product-ui.php';
require_once NUTRICIA_AI_PATH . 'includes/class-nutricia-ai.php';

/**
 * Bootstrap the plugin.
 */
function nutricia_ai() {
	return Nutricia_AI::instance();
}

// Kick things off.
nutricia_ai();

/**
 * Activation hook.
 */
register_activation_hook( __FILE__, array( 'Nutricia_AI', 'activate' ) );

/**
 * Deactivation hook.
 */
register_deactivation_hook( __FILE__, array( 'Nutricia_AI', 'deactivate' ) );
