<?php
/**
 * One-click updates, served by XACUBE itself. WordPress asks through the plugin's Update URI, so
 * this plugin never collides with anything of the same name on wordpress.org.
 *
 * @package XACUBE
 */

defined( 'ABSPATH' ) || exit;

add_filter( 'update_plugins_app.xacube.ir', 'xacube_update_check', 10, 3 );
add_filter( 'plugins_api', 'xacube_update_details', 10, 3 );
add_action( 'admin_init', 'xacube_remember_config' );

/**
 * An update replaces the plugin's folder and xacube-config.php with it, so the site's configuration
 * is also kept in the database, where xacube_config() finds it afterwards.
 */
function xacube_remember_config() {
	$config = xacube_config();
	if ( $config && get_option( 'xacube_site_config' ) !== $config ) {
		update_option( 'xacube_site_config', $config, false );
	}
}

/**
 * What XACUBE says about the latest version. Reports this site's version, so the XACUBE team can
 * see which sites are behind. Null on any failure: a missed check is simply tried again later.
 *
 * @return array|null
 */
function xacube_latest_release() {
	$config = xacube_config();
	if ( ! $config ) {
		return null;
	}

	$url      = add_query_arg(
		array(
			'k'    => rawurlencode( $config['public_key'] ),
			'v'    => rawurlencode( XACUBE_VERSION ),
			'lang' => 0 === strpos( determine_locale(), 'fa' ) ? 'fa' : 'en',
		),
		$config['app_origin'] . '/api/plugin/wordpress/update'
	);
	$response = wp_remote_get( $url, array( 'timeout' => 8 ) );
	if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
		return null;
	}

	$release = json_decode( wp_remote_retrieve_body( $response ), true );
	// The package has to come from the same XACUBE this site is connected to, and nowhere else.
	if ( ! is_array( $release ) || empty( $release['version'] ) || ! is_string( $release['version'] ) || empty( $release['package'] ) || ! is_string( $release['package'] ) || 0 !== strpos( $release['package'], $config['app_origin'] . '/' ) ) {
		return null;
	}

	return $release;
}

/**
 * Answers WordPress's update check for this plugin.
 *
 * @param array|false $update      Update data so far.
 * @param array       $plugin_data The plugin's headers.
 * @param string      $plugin_file The plugin being checked.
 * @return array|false
 */
function xacube_update_check( $update, $plugin_data, $plugin_file ) {
	if ( plugin_basename( XACUBE_FILE ) !== $plugin_file ) {
		return $update;
	}
	$release = xacube_latest_release();
	if ( ! $release ) {
		return $update;
	}

	return array(
		'slug'         => 'xacube',
		'version'      => $release['version'],
		'url'          => isset( $release['url'] ) ? (string) $release['url'] : '',
		'package'      => $release['package'],
		'requires'     => isset( $release['requires'] ) ? (string) $release['requires'] : '',
		'requires_php' => isset( $release['requiresPhp'] ) ? (string) $release['requiresPhp'] : '',
		'icons'        => array( '1x' => XACUBE_URL . 'assets/xacube-icon.png' ),
	);
}

/**
 * Fills WordPress's "View details" window, which would otherwise look this plugin up on wordpress.org.
 *
 * @param false|object|array $result Result so far.
 * @param string             $action The kind of request.
 * @param object             $args   Request arguments.
 * @return false|object|array
 */
function xacube_update_details( $result, $action, $args ) {
	if ( 'plugin_information' !== $action || empty( $args->slug ) || 'xacube' !== $args->slug ) {
		return $result;
	}
	$release = xacube_latest_release();
	if ( ! $release ) {
		return $result;
	}

	$changelog = '';
	foreach ( isset( $release['releases'] ) && is_array( $release['releases'] ) ? $release['releases'] : array() as $entry ) {
		if ( ! is_array( $entry ) || empty( $entry['version'] ) ) {
			continue;
		}
		$changelog .= '<h4>' . esc_html( (string) $entry['version'] ) . '</h4><ul>';
		foreach ( isset( $entry['changes'] ) && is_array( $entry['changes'] ) ? $entry['changes'] : array() as $change ) {
			$changelog .= '<li>' . esc_html( (string) $change ) . '</li>';
		}
		$changelog .= '</ul>';
	}

	return (object) array(
		'name'          => 'XACUBE Live Chat',
		'slug'          => 'xacube',
		'version'       => $release['version'],
		'author'        => 'XACUBE',
		'homepage'      => isset( $release['url'] ) ? (string) $release['url'] : '',
		'requires'      => isset( $release['requires'] ) ? (string) $release['requires'] : '',
		'requires_php'  => isset( $release['requiresPhp'] ) ? (string) $release['requiresPhp'] : '',
		'download_link' => $release['package'],
		'sections'      => array( 'changelog' => $changelog ),
	);
}
