<?php
/**
 * Configuration: what the panel baked into this download, and what the admin chose here.
 *
 * @package XACUBE
 */

defined( 'ABSPATH' ) || exit;

/**
 * The configuration the XACUBE panel wrote into this download (xacube-config.php), checked and
 * normalised. An update replaces the file, so the copy kept in the database stands in for it. Null
 * when neither exists or is malformed: this copy of the plugin did not come from a panel, so there
 * is no site to show the chat for.
 *
 * @return array|null
 */
function xacube_config() {
	static $config = false;
	if ( false !== $config ) {
		return $config;
	}
	$config = null;

	$file = XACUBE_DIR . 'xacube-config.php';
	$raw  = is_readable( $file ) ? include $file : get_option( 'xacube_site_config' );
	if ( ! is_array( $raw ) ) {
		return null;
	}

	$key           = isset( $raw['public_key'] ) ? (string) $raw['public_key'] : '';
	$widget_origin = xacube_origin( isset( $raw['widget_origin'] ) ? (string) $raw['widget_origin'] : '' );
	$app_origin    = xacube_origin( isset( $raw['app_origin'] ) ? (string) $raw['app_origin'] : '' );
	// Same shape the server accepts for a public key in the connect flow.
	if ( ! preg_match( '/^xc_pub_[A-Za-z0-9_-]{8,56}$/', $key ) || ! $widget_origin || ! $app_origin ) {
		return null;
	}

	$config = array(
		'site_id'        => isset( $raw['site_id'] ) && preg_match( '/^[0-9a-f-]{36}$/', (string) $raw['site_id'] ) ? (string) $raw['site_id'] : '',
		'public_key'     => $key,
		'widget_origin'  => $widget_origin,
		'app_origin'     => $app_origin,
		'site_name'      => isset( $raw['site_name'] ) ? (string) $raw['site_name'] : '',
		'workspace_name' => isset( $raw['workspace_name'] ) ? (string) $raw['workspace_name'] : '',
		'generated_at'   => isset( $raw['generated_at'] ) ? (string) $raw['generated_at'] : '',
	);

	return $config;
}

/**
 * The XACUBE panel this plugin talks to: the connected site's, or the default before it connects.
 *
 * @return string
 */
function xacube_app_origin() {
	$config = xacube_config();

	return $config ? $config['app_origin'] : XACUBE_APP_ORIGIN;
}

/**
 * Whether the configuration comes from a xacube-config.php shipped with the plugin. Such a copy
 * cannot be switched to another website from WordPress.
 *
 * @return bool
 */
function xacube_config_is_file() {
	return is_readable( XACUBE_DIR . 'xacube-config.php' );
}

/**
 * Scheme, host and port of a URL, with nothing after them. Empty when it is not http(s).
 *
 * @param string $url Any URL.
 * @return string
 */
function xacube_origin( $url ) {
	$parts = wp_parse_url( $url );
	if ( ! is_array( $parts ) || empty( $parts['host'] ) || empty( $parts['scheme'] ) || ! in_array( strtolower( $parts['scheme'] ), array( 'http', 'https' ), true ) ) {
		return '';
	}

	return strtolower( $parts['scheme'] ) . '://' . strtolower( $parts['host'] ) . ( isset( $parts['port'] ) ? ':' . (int) $parts['port'] : '' );
}

/**
 * The origin visitors see this site on. It is what their browser sends as Origin, so it is what
 * the site's domain allowlist has to contain.
 *
 * @return string
 */
function xacube_home_origin() {
	return xacube_origin( home_url( '/' ) );
}

/**
 * Defaults: the chat on for everyone, identity off until the admin connects.
 *
 * @return array
 */
function xacube_default_settings() {
	return array(
		'enabled'         => true,
		'hide_for_admins' => false,
		'identity'        => false,
	);
}

/**
 * The admin's choices, with defaults filled in.
 *
 * @return array
 */
function xacube_settings() {
	$saved = get_option( 'xacube_settings', array() );

	return array_merge( xacube_default_settings(), is_array( $saved ) ? array_intersect_key( $saved, xacube_default_settings() ) : array() );
}

/**
 * Updates some of the settings.
 *
 * @param array $changes Keys from xacube_default_settings() and their new boolean values.
 */
function xacube_update_settings( array $changes ) {
	$settings = xacube_settings();
	foreach ( $changes as $name => $value ) {
		if ( array_key_exists( $name, $settings ) ) {
			$settings[ $name ] = (bool) $value;
		}
	}
	update_option( 'xacube_settings', $settings, true );
}

/**
 * The secret key fetched by the connect flow, and what it was fetched for.
 *
 * The record keeps the public key it came with. If this WordPress later gets a copy of the plugin
 * made for another XACUBE website, the old secret would sign identities that website rejects, so
 * it is treated as absent until the admin connects again.
 *
 * @return array|null { secret, public_key, workspace_name, connected_at } or null.
 */
function xacube_identity() {
	$config = xacube_config();
	$record = get_option( 'xacube_identity', null );
	if ( ! $config || ! is_array( $record ) || empty( $record['secret'] ) || empty( $record['public_key'] ) ) {
		return null;
	}

	return hash_equals( $config['public_key'], (string) $record['public_key'] ) ? $record : null;
}

/**
 * Whether a secret key is stored but belongs to a different XACUBE website than this download's.
 *
 * @return bool
 */
function xacube_identity_is_stale() {
	$record = get_option( 'xacube_identity', null );

	return is_array( $record ) && ! empty( $record['secret'] ) && null === xacube_identity();
}

/**
 * Persian digits and comma on a Persian admin. Only for numbers people read (times, dates), never
 * for keys, codes or domains, which keep Latin digits.
 *
 * @param string $text Text that may contain digits.
 * @return string
 */
function xacube_digits( $text ) {
	if ( 0 !== strpos( determine_locale(), 'fa' ) ) {
		return $text;
	}

	return strtr( $text, array( '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹', ',' => '،' ) );
}
