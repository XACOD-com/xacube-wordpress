<?php
/**
 * The connect flow (docs/connect-flow.md in the XACUBE repository): how this plugin gets its site's
 * secret key without anyone copying it. The public key is already in the download; the secret is
 * fetched only when the admin turns on recognising signed-in users.
 *
 * @package XACUBE
 */

defined( 'ABSPATH' ) || exit;

define( 'XACUBE_CONNECT_TTL', 10 * MINUTE_IN_SECONDS );

add_action( 'admin_post_xacube_connect', 'xacube_connect_start' );
add_action( 'admin_post_xacube_connect_return', 'xacube_connect_finish' );
add_action( 'admin_post_xacube_forget', 'xacube_forget_secret' );

/**
 * Stops a request from anyone who cannot manage options. Every admin action starts with this.
 */
function xacube_require_admin() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to do this.', 'xacube' ), '', array( 'response' => 403 ) );
	}
}

/**
 * Back to the settings page with one of the fixed messages it knows. Only a code travels in the
 * URL, never text, so the page cannot be made to print something it did not write.
 *
 * @param string $notice A key of xacube_notices(), or empty for none.
 */
function xacube_back( $notice = '' ) {
	$args = array( 'page' => 'xacube' );
	if ( '' !== $notice ) {
		$args['xacube_notice'] = $notice;
	}
	wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
	exit;
}

/**
 * Random bytes, base64url-encoded without padding: the shape the connect page accepts for a nonce.
 *
 * @return string
 */
function xacube_random_token() {
	return rtrim( strtr( base64_encode( random_bytes( 32 ) ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
}

/**
 * Step 1: remember a fresh nonce for this admin and send them to XACUBE to approve.
 *
 * The return address carries a WordPress nonce too, so the return leg only works for this admin
 * in this session; the XACUBE nonce ties the code that comes back to this one attempt.
 */
function xacube_connect_start() {
	xacube_require_admin();
	check_admin_referer( 'xacube_connect' );

	$config = xacube_config();
	if ( ! $config ) {
		xacube_back( 'no_config' );
	}

	$return = add_query_arg(
		array(
			'action'   => 'xacube_connect_return',
			'_wpnonce' => wp_create_nonce( 'xacube_connect_return' ),
		),
		admin_url( 'admin-post.php' )
	);

	// XACUBE only sends the code back to the host the site is on. A WordPress whose admin lives on a
	// different host from its public pages cannot complete the flow, so say so here, not after a
	// round trip that ends on an error page.
	$site = xacube_home_origin();
	if ( strtolower( (string) wp_parse_url( $return, PHP_URL_HOST ) ) !== strtolower( (string) wp_parse_url( $site, PHP_URL_HOST ) ) ) {
		xacube_back( 'err_host' );
	}

	$nonce = xacube_random_token();
	set_transient( 'xacube_connect_' . get_current_user_id(), $nonce, XACUBE_CONNECT_TTL );

	// What this attempt is for is remembered here: nothing coming back in a URL can change it.
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified at the top of this function.
	$for_inbox = isset( $_POST['purpose'] ) && 'inbox' === sanitize_key( wp_unslash( $_POST['purpose'] ) );
	set_transient( 'xacube_connect_purpose_' . get_current_user_id(), $for_inbox ? 'inbox' : 'identity', XACUBE_CONNECT_TTL );

	$query = http_build_query(
		array_merge(
			array(
				'site'   => $site,
				'nonce'  => $nonce,
				'key'    => $config['public_key'],
				'return' => $return,
			),
			$for_inbox ? array( 'inbox' => '1' ) : array()
		),
		'',
		'&',
		PHP_QUERY_RFC3986
	);

	add_filter( 'allowed_redirect_hosts', 'xacube_allow_app_host' );
	wp_safe_redirect( $config['app_origin'] . '/connect?' . $query );
	exit;
}

/**
 * Lets wp_safe_redirect send the admin to the XACUBE panel named in this download, and nowhere else.
 *
 * @param array $hosts Hosts WordPress may redirect to.
 * @return array
 */
function xacube_allow_app_host( $hosts ) {
	$config = xacube_config();
	if ( $config ) {
		$hosts[] = wp_parse_url( $config['app_origin'], PHP_URL_HOST );
	}

	return $hosts;
}

/**
 * Steps 4 and 6: XACUBE sent the admin back with a one-time code. Check that this admin started
 * this attempt, trade the code for the keys server to server, and keep the secret only if it is
 * the secret of the site this download was made for.
 */
function xacube_connect_finish() {
	xacube_require_admin();

	$wp_nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
	if ( ! wp_verify_nonce( $wp_nonce, 'xacube_connect_return' ) ) {
		xacube_back( 'err_state' );
	}

	$config = xacube_config();
	if ( ! $config ) {
		xacube_back( 'no_config' );
	}

	$nonce = isset( $_GET['nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['nonce'] ) ) : '';
	$code  = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : '';
	if ( ! preg_match( '/^[A-Za-z0-9_-]{16,128}$/', $nonce ) || ! preg_match( '/^[A-Za-z0-9_-]{16,100}$/', $code ) ) {
		xacube_back( 'err_state' );
	}

	// The defence against a planted key: a link crafted by someone else carries their nonce, not
	// the one stored when this admin pressed the button. A mismatch leaves the stored nonce alone,
	// so a stray link cannot cancel an attempt the admin really is in the middle of.
	$key    = 'xacube_connect_' . get_current_user_id();
	$stored = get_transient( $key );
	if ( ! is_string( $stored ) || ! hash_equals( $stored, $nonce ) ) {
		xacube_back( 'err_state' );
	}
	delete_transient( $key );
	$purpose_key = 'xacube_connect_purpose_' . get_current_user_id();
	$for_inbox   = 'inbox' === get_transient( $purpose_key );
	delete_transient( $purpose_key );

	$response = wp_remote_post(
		$config['app_origin'] . '/api/connect/exchange',
		array(
			'timeout'     => 15,
			'redirection' => 0,
			'headers'     => array(
				'Content-Type' => 'application/json',
				'Accept'       => 'application/json',
			),
			'body'        => wp_json_encode(
				array(
					'code'  => $code,
					'nonce' => $nonce,
				)
			),
		)
	);
	if ( is_wp_error( $response ) ) {
		xacube_back( 'err_network' );
	}

	$status = (int) wp_remote_retrieve_response_code( $response );
	$body   = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( 200 !== $status || ! is_array( $body ) ) {
		xacube_back( 429 === $status ? 'err_busy' : 'err_exchange' );
	}

	$public_key = isset( $body['publicKey'] ) ? (string) $body['publicKey'] : '';
	if ( $for_inbox ) {
		$token = isset( $body['inboxToken'] ) ? (string) $body['inboxToken'] : '';
		if ( ! preg_match( '/^xc_inb_[A-Za-z0-9_-]{32,100}$/', $token ) ) {
			xacube_back( 'err_exchange' );
		}
		if ( ! hash_equals( $config['public_key'], $public_key ) ) {
			xacube_back( 'err_mismatch' );
		}
		update_option(
			'xacube_inbox',
			array(
				'token'        => $token,
				'public_key'   => $public_key,
				'connected_at' => time(),
			),
			false
		);
		wp_safe_redirect( admin_url( 'admin.php?page=xacube-inbox' ) );
		exit;
	}

	$secret     = isset( $body['secretKey'] ) ? (string) $body['secretKey'] : '';
	if ( ! preg_match( '/^xc_sec_[A-Za-z0-9_-]{8,120}$/', $secret ) ) {
		xacube_back( 'err_exchange' );
	}
	// This download serves one XACUBE website. A secret for any other would sign identities that
	// website never sees, or worse, belong to someone else's workspace.
	if ( ! hash_equals( $config['public_key'], $public_key ) ) {
		xacube_back( 'err_mismatch' );
	}

	update_option(
		'xacube_identity',
		array(
			'secret'         => $secret,
			'public_key'     => $public_key,
			'workspace_name' => isset( $body['workspaceName'] ) ? sanitize_text_field( (string) $body['workspaceName'] ) : '',
			'connected_at'   => time(),
		),
		false
	);
	xacube_update_settings( array( 'identity' => true ) );
	xacube_back( 'connected' );
}

/**
 * Forgets the secret key here. The site keeps working at XACUBE: revoking it is done from the
 * panel, where the person doing it can see what else it affects.
 */
function xacube_forget_secret() {
	xacube_require_admin();
	check_admin_referer( 'xacube_forget' );

	delete_option( 'xacube_identity' );
	xacube_update_settings( array( 'identity' => false ) );
	xacube_back( 'forgotten' );
}
