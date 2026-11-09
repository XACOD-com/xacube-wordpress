<?php
/**
 * The chat script on the public site. This is all the plugin does for visitors: everything else
 * runs on XACUBE's servers.
 *
 * @package XACUBE
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_footer', 'xacube_print_widget', 20 );

/**
 * Whether this request is a public page a visitor reads, as opposed to admin screens, REST and
 * AJAX calls, feeds, embeds and the like, where a chat window has no business.
 *
 * @return bool
 */
function xacube_is_public_page() {
	if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || wp_is_json_request() ) {
		return false;
	}
	if ( ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) ) {
		return false;
	}
	if ( is_feed() || is_embed() || is_robots() || is_trackback() ) {
		return false;
	}

	return ! ( isset( $GLOBALS['pagenow'] ) && 'wp-login.php' === $GLOBALS['pagenow'] );
}

/**
 * Prints the loader:
 *
 *   <script src="{widget_origin}/w.js" data-key="xc_pub_…" async></script>
 *
 * With identity on and someone signed in, it also says who they are, vouched for with an HMAC of
 * their user id under the site's secret key. The secret stays on this server; only the hash
 * reaches the page, and it is worthless for any other user id.
 */
function xacube_print_widget() {
	$config   = xacube_config();
	$settings = xacube_settings();
	if ( ! $config || ! $settings['enabled'] || ! xacube_is_public_page() ) {
		return;
	}
	if ( $settings['hide_for_admins'] && is_user_logged_in() && current_user_can( 'manage_options' ) ) {
		return;
	}

	$attributes = array(
		'src'      => $config['widget_origin'] . '/w.js',
		'data-key' => $config['public_key'],
		'async'    => true,
	);

	$identity = $settings['identity'] ? xacube_identity() : null;
	if ( $identity && is_user_logged_in() ) {
		$user    = wp_get_current_user();
		$user_id = (string) $user->ID;

		$attributes['data-user-id']    = $user_id;
		$attributes['data-user-hash']  = hash_hmac( 'sha256', $user_id, $identity['secret'] );
		$attributes['data-user-name']  = $user->display_name;
		$attributes['data-user-email'] = $user->user_email;
	} elseif ( is_user_logged_in() ) {
		// Identity off, but WordPress still knows who this is. Name and email go to the chat as an
		// unverified hint, so a signed-in customer is never asked for an email the site already has.
		// Without the hash they cannot claim an account; they only fill in the visitor's details.
		$user = wp_get_current_user();

		$attributes['data-user-name']  = $user->display_name;
		$attributes['data-user-email'] = $user->user_email;
	}

	// wp_print_script_tag escapes every attribute and lets CSP plugins add their nonce.
	wp_print_script_tag( $attributes );
}
