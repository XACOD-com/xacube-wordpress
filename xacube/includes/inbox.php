<?php
/**
 * The XACUBE inbox inside WordPress admin. The page is a frame served by the XACUBE panel; it holds
 * no credentials. This server keeps a revocable token (never sent to a browser) and trades it for a
 * one-minute ticket each time the frame asks, for administrators only.
 *
 * @package XACUBE
 */

defined( 'ABSPATH' ) || exit;

add_action( 'admin_menu', 'xacube_inbox_menu', 20 );
add_action( 'admin_enqueue_scripts', 'xacube_inbox_assets' );
add_action( 'wp_ajax_xacube_inbox_ticket', 'xacube_inbox_ticket' );
add_action( 'admin_post_xacube_inbox_disconnect', 'xacube_inbox_disconnect' );

/**
 * What the connect flow stored for the inbox, if it was for the website this download serves.
 *
 * @return array|null { token, public_key, connected_at } or null.
 */
function xacube_inbox_grant() {
	$config = xacube_config();
	$grant  = get_option( 'xacube_inbox' );
	if ( ! $config || ! is_array( $grant ) || empty( $grant['token'] ) || empty( $grant['public_key'] ) || ! hash_equals( $config['public_key'], (string) $grant['public_key'] ) ) {
		return null;
	}

	return $grant;
}

/**
 * Adds "Conversations" and keeps "Settings" as the name of the menu's own page.
 */
function xacube_inbox_menu() {
	$GLOBALS['xacube_inbox_hook'] = add_submenu_page( 'xacube', __( 'Conversations', 'xacube' ), __( 'Conversations', 'xacube' ), 'manage_options', 'xacube-inbox', 'xacube_render_inbox', 0 );
	add_submenu_page( 'xacube', __( 'Settings', 'xacube' ), __( 'Settings', 'xacube' ), 'manage_options', 'xacube', 'xacube_render_page', 1 );
}

/**
 * The inbox page's styles, and its script when there is a connection to use.
 *
 * @param string $hook_suffix The current admin page.
 */
function xacube_inbox_assets( $hook_suffix ) {
	if ( empty( $GLOBALS['xacube_inbox_hook'] ) || $GLOBALS['xacube_inbox_hook'] !== $hook_suffix ) {
		return;
	}
	wp_enqueue_style( 'xacube-admin', XACUBE_URL . 'assets/admin.css', array(), XACUBE_VERSION );

	$config = xacube_config();
	if ( ! $config || ! xacube_inbox_grant() ) {
		wp_enqueue_script( 'xacube-admin', XACUBE_URL . 'assets/admin.js', array(), XACUBE_VERSION, array( 'in_footer' => true ) );

		return;
	}
	wp_enqueue_script( 'xacube-inbox', XACUBE_URL . 'assets/inbox.js', array(), XACUBE_VERSION, array( 'in_footer' => true ) );
	wp_localize_script(
		'xacube-inbox',
		'xacubeInbox',
		array(
			'appOrigin' => $config['app_origin'],
			'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
			'nonce'     => wp_create_nonce( 'xacube_inbox' ),
		)
	);
}

/**
 * The page: the inbox when connected, otherwise what connecting means and the button to do it.
 */
function xacube_render_inbox() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$config = xacube_config();
	if ( ! $config ) {
		echo '<div class="wrap xacube">';
		xacube_render_unconfigured();
		echo '</div>';

		return;
	}

	if ( xacube_inbox_grant() ) {
		printf(
			'<iframe id="xc-inbox-frame" class="xc-inbox-frame" src="%1$s" title="%2$s" allow="autoplay; clipboard-write"></iframe>',
			esc_url( $config['app_origin'] . '/embed/inbox' ),
			esc_attr__( 'XACUBE conversations', 'xacube' )
		);

		return;
	}
	?>
	<div class="wrap xacube">
		<h1 class="screen-reader-text"><?php esc_html_e( 'Conversations', 'xacube' ); ?></h1>
		<?php xacube_render_header( $config ); ?>
		<?php xacube_render_notice(); ?>
		<section class="xc-card xc-empty">
			<span class="xc-empty__icon"><?php xacube_the_icon( 'chat' ); ?></span>
			<h2 class="xc-empty__title"><?php esc_html_e( 'Answer your chats right here in WordPress', 'xacube' ); ?></h2>
			<p><?php esc_html_e( 'Connect once and your XACUBE inbox opens on this page: read conversations, reply and send images without leaving WordPress.', 'xacube' ); ?></p>
			<p class="xc-muted"><?php esc_html_e( 'Every administrator of this WordPress will be able to read and answer all conversations in your XACUBE workspace, under the name of the person who approves this. You can disconnect at any time.', 'xacube' ); ?></p>
			<?php xacube_form_open( 'xacube_connect', array( 'data-xc-pending' => __( 'Connecting…', 'xacube' ) ) ); ?>
				<input type="hidden" name="purpose" value="inbox">
				<button type="submit" class="xc-button xc-button--primary">
					<?php xacube_the_icon( 'key' ); ?>
					<span><?php esc_html_e( 'Connect your inbox', 'xacube' ); ?></span>
				</button>
			</form>
		</section>
	</div>
	<?php
}

/**
 * Sends the frame a one-minute ticket. Only an administrator with this page's nonce gets one, and
 * only the fields the frame needs are passed on.
 */
function xacube_inbox_ticket() {
	check_ajax_referer( 'xacube_inbox' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'error' => 'forbidden' ), 403 );
	}

	$config = xacube_config();
	$grant  = xacube_inbox_grant();
	if ( ! $config || ! $grant ) {
		wp_send_json_error( array( 'error' => 'not_connected' ), 400 );
	}

	$response = wp_remote_post(
		$config['app_origin'] . '/api/plugin/inbox-ticket',
		array(
			'timeout'     => 15,
			'redirection' => 0,
			'headers'     => array(
				'Authorization' => 'Bearer ' . $grant['token'],
				'Accept'        => 'application/json',
			),
		)
	);
	if ( is_wp_error( $response ) ) {
		wp_send_json_error( array( 'error' => 'unavailable' ), 502 );
	}

	$status = (int) wp_remote_retrieve_response_code( $response );
	if ( 401 === $status ) {
		// Revoked in the panel, or the person who approved it left: this token is finished.
		delete_option( 'xacube_inbox' );
		wp_send_json_error( array( 'error' => 'revoked' ), 400 );
	}
	$body = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( 200 !== $status || ! is_array( $body ) || empty( $body['ticket'] ) ) {
		wp_send_json_error( array( 'error' => 'unavailable' ), 502 );
	}

	wp_send_json_success(
		array(
			'ticket'      => (string) $body['ticket'],
			'workspaceId' => isset( $body['workspaceId'] ) ? (string) $body['workspaceId'] : '',
			'userId'      => isset( $body['userId'] ) ? (string) $body['userId'] : '',
			'sites'       => isset( $body['sites'] ) && is_array( $body['sites'] ) ? $body['sites'] : array(),
			'freePlan'    => ! empty( $body['freePlan'] ),
		)
	);
}

/**
 * Ends the connection, at XACUBE as well as here.
 */
function xacube_inbox_disconnect() {
	xacube_require_admin();
	check_admin_referer( 'xacube_inbox_disconnect' );

	$config = xacube_config();
	$grant  = xacube_inbox_grant();
	if ( $config && $grant ) {
		wp_remote_post(
			$config['app_origin'] . '/api/plugin/inbox-revoke',
			array(
				'timeout'     => 10,
				'redirection' => 0,
				'headers'     => array( 'Authorization' => 'Bearer ' . $grant['token'] ),
			)
		);
	}
	delete_option( 'xacube_inbox' );
	xacube_back( 'inbox_disconnected' );
}

/**
 * The settings page's card for the inbox.
 */
function xacube_render_inbox_card() {
	$grant = xacube_inbox_grant();
	?>
	<section class="xc-card" aria-labelledby="xc-inbox-title">
		<div class="xc-card__head">
			<span class="xc-card__icon"><?php xacube_the_icon( 'chat' ); ?></span>
			<h2 id="xc-inbox-title" class="xc-card__title"><?php esc_html_e( 'Inbox in WordPress', 'xacube' ); ?></h2>
			<?php if ( $grant ) : ?>
				<span class="xc-badge xc-badge--success"><?php esc_html_e( 'On', 'xacube' ); ?></span>
			<?php else : ?>
				<span class="xc-badge"><?php esc_html_e( 'Off', 'xacube' ); ?></span>
			<?php endif; ?>
		</div>
		<p class="xc-card__text"><?php esc_html_e( 'Read and answer your chats under XACUBE → Conversations. Administrators of this WordPress can see every conversation in your workspace.', 'xacube' ); ?></p>
		<div class="xc-card__foot">
			<a class="xc-button xc-button--primary" href="<?php echo esc_url( admin_url( 'admin.php?page=xacube-inbox' ) ); ?>"><?php esc_html_e( 'Open conversations', 'xacube' ); ?></a>
			<?php if ( $grant ) : ?>
				<?php xacube_form_open( 'xacube_inbox_disconnect', array( 'class' => 'xc-push', 'data-xc-confirm' => __( 'Disconnect the inbox from this WordPress?', 'xacube' ) ) ); ?>
					<button type="submit" class="xc-button xc-button--ghost-danger"><?php esc_html_e( 'Disconnect', 'xacube' ); ?></button>
				</form>
			<?php endif; ?>
		</div>
	</section>
	<?php
}
