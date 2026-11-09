<?php
/**
 * Removes everything the plugin stored, the secret key included, when it is deleted from the
 * Plugins screen. The site itself stays in XACUBE: revoking it is done from the panel.
 *
 * @package XACUBE
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Deletes this plugin's options and transients on the current site.
 */
function xacube_uninstall_site() {
	global $wpdb;

	delete_option( 'xacube_settings' );
	delete_option( 'xacube_identity' );
	delete_option( 'xacube_inbox' );
	delete_option( 'xacube_site_config' );
	delete_transient( 'xacube_status' );
	delete_transient( 'xacube_activation_redirect' );

	// Connect nonces are stored per admin (xacube_connect_<user id>), so they are found by prefix.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- a one-off cleanup with no API equivalent.
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
			$wpdb->esc_like( '_transient_xacube_' ) . '%',
			$wpdb->esc_like( '_transient_timeout_xacube_' ) . '%'
		)
	);
	wp_cache_flush();
}

if ( is_multisite() ) {
	foreach ( get_sites( array( 'fields' => 'ids' ) ) as $xacube_blog_id ) {
		switch_to_blog( $xacube_blog_id );
		xacube_uninstall_site();
		restore_current_blog();
	}
} else {
	xacube_uninstall_site();
}
