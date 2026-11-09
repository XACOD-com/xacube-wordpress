<?php
/**
 * Is the chat actually live? Asked the way a visitor's browser asks, so the answer is the one
 * visitors get.
 *
 * @package XACUBE
 */

defined( 'ABSPATH' ) || exit;

add_action( 'admin_post_xacube_recheck', 'xacube_recheck' );

/**
 * The chat's state on this site, from the widget server, cached for five minutes so the settings
 * page does not call out on every load.
 *
 * The request is the one w.js makes from a visitor's page: a frame ticket for this key, with this
 * site's origin as Origin. The server checks that origin against the site's allowed domains, so a
 * 200 here means visitors on this host get the chat.
 *
 * @param bool $fresh Skip the cache.
 * @return array { state: live|origin_not_allowed|workspace_unverified|unknown_site|busy|unreachable|unexpected, host, checked_at }
 */
function xacube_status( $fresh = false ) {
	$config = xacube_config();
	if ( ! $config ) {
		return null;
	}

	$origin = xacube_home_origin();
	// Tied to everything the answer depends on, so a new download or a moved site is checked anew.
	$fingerprint = md5( $config['widget_origin'] . '|' . $config['public_key'] . '|' . $origin );
	$cached      = $fresh ? false : get_transient( 'xacube_status' );
	if ( is_array( $cached ) && isset( $cached['fingerprint'] ) && $cached['fingerprint'] === $fingerprint ) {
		return $cached;
	}

	$response = wp_remote_get(
		add_query_arg( 'k', rawurlencode( $config['public_key'] ), $config['widget_origin'] . '/v1/frame-ticket' ),
		array(
			'timeout'     => 8,
			'redirection' => 0,
			'headers'     => array(
				'Origin' => $origin,
				'Accept' => 'application/json',
			),
		)
	);

	if ( is_wp_error( $response ) ) {
		$state = 'unreachable';
	} else {
		$code  = (int) wp_remote_retrieve_response_code( $response );
		$body  = json_decode( wp_remote_retrieve_body( $response ), true );
		$error = is_array( $body ) && isset( $body['error'] ) ? (string) $body['error'] : '';

		if ( 200 === $code && is_array( $body ) && ! empty( $body['ticket'] ) ) {
			$state = 'live';
		} elseif ( in_array( $error, array( 'origin_not_allowed', 'workspace_unverified', 'unknown_site' ), true ) ) {
			$state = $error;
		} elseif ( 429 === $code || 'rate_limited' === $error ) {
			$state = 'busy';
		} else {
			$state = 'unexpected';
		}
	}

	$status = array(
		'fingerprint' => $fingerprint,
		'state'       => $state,
		'host'        => (string) wp_parse_url( $origin, PHP_URL_HOST ),
		'checked_at'  => time(),
	);
	set_transient( 'xacube_status', $status, 5 * MINUTE_IN_SECONDS );

	return $status;
}

/**
 * "Check again": drop the cached answer and go back to the page, which asks afresh.
 */
function xacube_recheck() {
	xacube_require_admin();
	check_admin_referer( 'xacube_recheck' );

	delete_transient( 'xacube_status' );
	xacube_back();
}
