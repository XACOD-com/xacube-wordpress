<?php
/**
 * Plugin Name:       XACUBE Live Chat
 * Description:       Live chat on your website, answered from your XACUBE inbox or right inside WordPress.
 * Version:           1.4.1
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Update URI:        https://app.xacube.ir/api/plugin/wordpress/update
 * Author:            XACUBE
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       xacube
 * Domain Path:       /languages
 *
 * @package XACUBE
 */

defined( 'ABSPATH' ) || exit;

define( 'XACUBE_VERSION', '1.4.1' );
defined( 'XACUBE_APP_ORIGIN' ) || define( 'XACUBE_APP_ORIGIN', 'https://app.xacube.ir' );
define( 'XACUBE_FILE', __FILE__ );
define( 'XACUBE_DIR', plugin_dir_path( __FILE__ ) );
define( 'XACUBE_URL', plugin_dir_url( __FILE__ ) );

require_once XACUBE_DIR . 'includes/config.php';
require_once XACUBE_DIR . 'includes/frontend.php';
require_once XACUBE_DIR . 'includes/connect.php';
require_once XACUBE_DIR . 'includes/status.php';
require_once XACUBE_DIR . 'includes/updater.php';

if ( is_admin() ) {
	require_once XACUBE_DIR . 'includes/admin.php';
	require_once XACUBE_DIR . 'includes/inbox.php';
}

/*
 * Translations are loaded on init and not earlier: since WordPress 6.7 asking for a string before
 * init logs a "loaded too early" notice. Nothing in this plugin translates before then.
 */
add_action(
	'init',
	static function () {
		load_plugin_textdomain( 'xacube', false, dirname( plugin_basename( XACUBE_FILE ) ) . '/languages' );
	}
);

register_activation_hook( XACUBE_FILE, 'xacube_activate' );

/**
 * Seeds the settings and asks admin_init to open the settings page once, so the first thing a
 * customer sees after activating is whether the chat is live.
 */
function xacube_activate() {
	add_option( 'xacube_settings', xacube_default_settings() );
	set_transient( 'xacube_activation_redirect', 1, 60 );
}
