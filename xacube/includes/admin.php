<?php
/**
 * The settings page, its menu entry and its actions. Loaded on admin requests only.
 *
 * @package XACUBE
 */

defined( 'ABSPATH' ) || exit;

add_action( 'admin_menu', 'xacube_admin_menu' );
add_action( 'admin_init', 'xacube_activation_redirect' );
add_action( 'admin_enqueue_scripts', 'xacube_admin_assets' );
add_action( 'admin_post_xacube_save', 'xacube_save_settings' );
add_filter( 'plugin_action_links_' . plugin_basename( XACUBE_FILE ), 'xacube_action_links' );

/**
 * The menu icon: a speech bubble. Filled rather than stroked, because WordPress repaints data-URI
 * menu icons to the admin colour scheme by rewriting their fill.
 *
 * @return string
 */
function xacube_menu_icon() {
	$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path fill="black" fill-rule="evenodd" d="M4 2.5h12a2.5 2.5 0 0 1 2.5 2.5v8a2.5 2.5 0 0 1-2.5 2.5H9l-3.4 2.9a.6.6 0 0 1-1-.46V15.5H4A2.5 2.5 0 0 1 1.5 13V5A2.5 2.5 0 0 1 4 2.5zM4.75 9a1.25 1.25 0 1 0 2.5 0a1.25 1.25 0 1 0-2.5 0zM8.75 9a1.25 1.25 0 1 0 2.5 0a1.25 1.25 0 1 0-2.5 0zM12.75 9a1.25 1.25 0 1 0 2.5 0a1.25 1.25 0 1 0-2.5 0z"/></svg>';

	return 'data:image/svg+xml;base64,' . base64_encode( $svg ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
}

/**
 * Adds the top-level XACUBE menu.
 */
function xacube_admin_menu() {
	add_menu_page(
		__( 'XACUBE Live Chat', 'xacube' ),
		'XACUBE',
		'manage_options',
		'xacube',
		'xacube_render_page',
		xacube_menu_icon(),
		26
	);
}

/**
 * Adds "Settings" to the plugin's row on the Plugins screen.
 *
 * @param array $links Existing action links.
 * @return array
 */
function xacube_action_links( $links ) {
	array_unshift(
		$links,
		sprintf( '<a href="%s">%s</a>', esc_url( admin_url( 'admin.php?page=xacube' ) ), esc_html__( 'Settings', 'xacube' ) )
	);

	return $links;
}

/**
 * Opens the settings page once, right after activation, so the customer lands on "Chat is live".
 * Not after a bulk activation, where the admin is in the middle of something else.
 */
function xacube_activation_redirect() {
	if ( ! get_transient( 'xacube_activation_redirect' ) ) {
		return;
	}
	delete_transient( 'xacube_activation_redirect' );

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only check of WordPress's own query flag.
	if ( wp_doing_ajax() || is_network_admin() || isset( $_GET['activate-multi'] ) || ! current_user_can( 'manage_options' ) ) {
		return;
	}
	wp_safe_redirect( admin_url( 'admin.php?page=xacube' ) );
	exit;
}

/**
 * The page's stylesheet, fonts and a few lines of script, on this page only.
 *
 * @param string $hook_suffix The current admin page.
 */
function xacube_admin_assets( $hook_suffix ) {
	if ( 'toplevel_page_xacube' !== $hook_suffix && 'xacube_page_xacube' !== $hook_suffix ) {
		return;
	}
	wp_enqueue_style( 'xacube-admin', XACUBE_URL . 'assets/admin.css', array(), XACUBE_VERSION );
	wp_enqueue_script( 'xacube-admin', XACUBE_URL . 'assets/admin.js', array(), XACUBE_VERSION, array( 'in_footer' => true ) );
}

/**
 * Saves the switches. Recognising signed-in users can only be switched on here once a secret key
 * has been fetched; until then the page offers the connect button instead.
 */
function xacube_save_settings() {
	xacube_require_admin();
	check_admin_referer( 'xacube_save' );

	// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified above.
	$form    = isset( $_POST['xacube_form'] ) ? sanitize_key( wp_unslash( $_POST['xacube_form'] ) ) : '';
	$changes = array();
	if ( 'chat' === $form ) {
		$changes['enabled']         = ! empty( $_POST['enabled'] );
		$changes['hide_for_admins'] = ! empty( $_POST['hide_for_admins'] );
	} elseif ( 'identity' === $form ) {
		$changes['identity'] = ! empty( $_POST['identity'] ) && null !== xacube_identity();
	}
	// phpcs:enable

	xacube_update_settings( $changes );
	xacube_back( 'saved' );
}

/**
 * The messages the page can show after an action, by the code in its URL.
 *
 * @return array code => [ tone, text ]
 */
function xacube_notices() {
	return array(
		'saved'        => array( 'success', __( 'Your changes are saved.', 'xacube' ) ),
		'inbox_disconnected' => array( 'info', __( 'The inbox is disconnected from this WordPress.', 'xacube' ) ),
		'connected'    => array( 'success', __( 'Connected. Your inbox now shows who signed-in users are.', 'xacube' ) ),
		'forgotten'    => array( 'info', __( 'The secret key has been removed from this site, and signed-in users are no longer recognised.', 'xacube' ) ),
		'no_config'    => array( 'danger', __( 'This copy of the plugin is not set up for a website. Download it from your XACUBE panel.', 'xacube' ) ),
		'err_state'    => array( 'danger', __( 'That connection request has expired or did not start on this site. Please try again.', 'xacube' ) ),
		'err_exchange' => array( 'danger', __( 'XACUBE did not accept the connection. The link may have expired; please try again.', 'xacube' ) ),
		'err_busy'     => array( 'warning', __( 'Too many attempts in a short time. Please wait a minute and try again.', 'xacube' ) ),
		'err_network'  => array( 'danger', __( 'Your server could not reach XACUBE. Please try again in a few minutes.', 'xacube' ) ),
		'err_mismatch' => array( 'danger', __( 'That key belongs to a different XACUBE website, so it was not saved. Download the plugin again from this website’s page in your panel.', 'xacube' ) ),
		'err_host'     => array( 'danger', __( 'Your WordPress Address and Site Address are on different domains, so XACUBE cannot send you back here. Put them on the same domain under Settings → General, then try again.', 'xacube' ) ),
	);
}

/**
 * An inline SVG icon in the dashboard's style. The markup is fixed here, never built from input.
 *
 * @param string $name  Icon name.
 * @param string $class Extra class.
 * @return string
 */
function xacube_icon( $name, $class = '' ) {
	$paths = array(
		'live'     => '<circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/>',
		'warning'  => '<path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4"/><path d="M12 17h.01"/>',
		'danger'   => '<circle cx="12" cy="12" r="10"/><path d="m15 9-6 6"/><path d="m9 9 6 6"/>',
		'offline'  => '<path d="m2 2 20 20"/><path d="M5.782 5.782A7 7 0 0 0 9 19h8.5a4.5 4.5 0 0 0 1.307-.193"/><path d="M21.532 16.5A4.5 4.5 0 0 0 17.5 10h-1.79A7.008 7.008 0 0 0 10 5.07"/>',
		'off'      => '<path d="M12 2v10"/><path d="M18.4 6.6a9 9 0 1 1-12.77.04"/>',
		'info'     => '<circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/>',
		'external' => '<path d="M15 3h6v6"/><path d="M10 14 21 3"/><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>',
		'refresh'  => '<path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"/><path d="M8 16H3v5"/>',
		'chat'     => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
		'user'     => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="m16 11 2 2 4-4"/>',
		'key'      => '<path d="M2.586 17.414A2 2 0 0 0 2 18.828V21a1 1 0 0 0 1 1h3a1 1 0 0 0 1-1v-1a1 1 0 0 1 1-1h1a1 1 0 0 0 1-1v-1a1 1 0 0 1 1-1h.172a2 2 0 0 0 1.414-.586l.814-.814a6.5 6.5 0 1 0-4-4z"/><circle cx="16.5" cy="7.5" r=".5"/>',
		'download' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m7 10 5 5 5-5"/><path d="M12 15V3"/>',
		'cube'     => '<path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/>',
	);
	if ( ! isset( $paths[ $name ] ) ) {
		return '';
	}

	return '<svg class="xc-icon ' . esc_attr( $class ) . '" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $paths[ $name ] . '</svg>';
}

/**
 * Prints an icon. Separate from xacube_icon() so the one unescaped echo in this file is here.
 *
 * @param string $name  Icon name.
 * @param string $class Extra class.
 */
function xacube_the_icon( $name, $class = '' ) {
	echo xacube_icon( $name, $class ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed markup from xacube_icon().
}

/**
 * Opens a form that posts to one of this plugin's admin-post actions, with its nonce.
 *
 * @param string $action The admin-post action, also the nonce action.
 * @param array  $attrs  Extra attributes for the form element.
 */
function xacube_form_open( $action, $attrs = array() ) {
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"';
	foreach ( $attrs as $name => $value ) {
		echo ' ' . esc_attr( $name ) . '="' . esc_attr( $value ) . '"';
	}
	echo '>';
	echo '<input type="hidden" name="action" value="' . esc_attr( $action ) . '">';
	wp_nonce_field( $action );
}

/**
 * A link that opens the XACUBE panel in a new tab.
 *
 * @param string $url   Where.
 * @param string $label What it says.
 * @param string $class Its class.
 */
function xacube_panel_link( $url, $label, $class = 'xc-link' ) {
	printf(
		'<a class="%1$s" href="%2$s" target="_blank" rel="noopener noreferrer">%3$s%4$s<span class="screen-reader-text"> %5$s</span></a>',
		esc_attr( $class ),
		esc_url( $url ),
		esc_html( $label ),
		xacube_icon( 'external', 'xc-icon--flip' ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed markup.
		esc_html__( '(opens in a new tab)', 'xacube' )
	);
}

/**
 * "2 minutes ago", in the admin's language and digits.
 *
 * @param int $timestamp Unix time.
 * @return string
 */
function xacube_ago( $timestamp ) {
	if ( time() - $timestamp < MINUTE_IN_SECONDS ) {
		return __( 'just now', 'xacube' );
	}

	/* translators: %s: a length of time, such as "2 mins". */
	return xacube_digits( sprintf( __( '%s ago', 'xacube' ), human_time_diff( $timestamp ) ) );
}

/**
 * The status card's content for each state: tone, icon, title, body and an optional panel link.
 *
 * @param array $config The download's configuration.
 * @param array $status From xacube_status(), or null when the chat is switched off.
 * @return array
 */
function xacube_status_view( $config, $status ) {
	$site_url = $config['site_id'] ? $config['app_origin'] . '/sites/' . rawurlencode( $config['site_id'] ) : $config['app_origin'] . '/sites';
	$host     = '<bdi dir="ltr">' . esc_html( $status ? $status['host'] : '' ) . '</bdi>';

	if ( ! $status ) {
		return array(
			'tone'  => 'neutral',
			'icon'  => 'off',
			'title' => __( 'The chat is switched off', 'xacube' ),
			'body'  => esc_html__( 'Visitors do not see the chat window on this site. Switch it back on below whenever you are ready.', 'xacube' ),
		);
	}

	switch ( $status['state'] ) {
		case 'live':
			return array(
				'tone'  => 'success',
				'icon'  => 'live',
				'title' => __( 'Chat is live on this site', 'xacube' ),
				/* translators: %s: the site's domain, e.g. shop.example.com. */
				'body'  => sprintf( esc_html__( 'Visitors on %s can chat with you, and their messages arrive in your inbox.', 'xacube' ), $host ),
			);
		case 'origin_not_allowed':
			return array(
				'tone'  => 'warning',
				'icon'  => 'warning',
				'title' => __( 'This domain is not allowed yet', 'xacube' ),
				/* translators: %s: the site's domain, e.g. shop.example.com. */
				'body'  => sprintf( esc_html__( '%s is not in this site’s allowed domains, so the chat does not load here. Add it in your panel.', 'xacube' ), $host ),
				'link'  => array( $site_url, __( 'Add the domain in your panel', 'xacube' ) ),
			);
		case 'workspace_unverified':
			return array(
				'tone'  => 'warning',
				'icon'  => 'warning',
				'title' => __( 'Verify your email to switch the chat on', 'xacube' ),
				'body'  => esc_html__( 'The chat starts working once the owner of your XACUBE workspace has verified their email address. Open the link we emailed, or send a new one from your panel.', 'xacube' ),
				'link'  => array( $config['app_origin'] . '/inbox', __( 'Open your panel', 'xacube' ) ),
			);
		case 'unknown_site':
			return array(
				'tone'  => 'danger',
				'icon'  => 'danger',
				'title' => __( 'This plugin’s key no longer works', 'xacube' ),
				'body'  => esc_html__( 'The website this copy was made for has been removed or disconnected in XACUBE. Download a fresh copy of the plugin from your panel and install it in place of this one.', 'xacube' ),
				'link'  => array( $config['app_origin'] . '/sites', __( 'Go to your websites', 'xacube' ) ),
			);
		case 'busy':
			return array(
				'tone'  => 'neutral',
				'icon'  => 'info',
				'title' => __( 'Too many checks in a short time', 'xacube' ),
				'body'  => esc_html__( 'XACUBE asked us to slow down. Please check again in a minute.', 'xacube' ),
			);
		case 'unreachable':
			return array(
				'tone'  => 'neutral',
				'icon'  => 'offline',
				'title' => __( 'Couldn’t reach XACUBE', 'xacube' ),
				'body'  => esc_html__( 'Your server could not connect to XACUBE just now, so we cannot tell whether the chat is live. This is usually temporary.', 'xacube' ),
			);
		default:
			return array(
				'tone'  => 'neutral',
				'icon'  => 'info',
				'title' => __( 'We could not check the chat', 'xacube' ),
				'body'  => esc_html__( 'XACUBE gave an answer we did not expect. Please check again in a few minutes.', 'xacube' ),
			);
	}
}

/**
 * The settings page.
 */
function xacube_render_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	echo '<div class="wrap xacube">';
	// WordPress places other plugins' notices after the first heading in .wrap.
	echo '<h1 class="screen-reader-text">' . esc_html__( 'XACUBE Live Chat', 'xacube' ) . '</h1>';

	$config = xacube_config();
	if ( ! $config ) {
		xacube_render_unconfigured();
		echo '</div>';
		return;
	}

	$settings = xacube_settings();
	xacube_render_header( $config );
	xacube_render_notice();
	xacube_render_status( $config, $settings );

	echo '<div class="xc-grid">';
	xacube_render_chat_card( $settings );
	xacube_render_identity_card( $config, $settings );
	xacube_render_inbox_card();
	echo '</div>';

	xacube_render_details( $config );
	echo '</div>';
}

/**
 * Shown when this copy has no xacube-config.php: it did not come from a panel.
 */
function xacube_render_unconfigured() {
	?>
	<header class="xc-header">
		<div class="xc-brand">
			<span class="xc-brand__mark"><?php xacube_the_icon( 'cube' ); ?></span>
			<span class="xc-brand__name">XACUBE</span>
		</div>
	</header>
	<section class="xc-card xc-empty">
		<span class="xc-empty__icon"><?php xacube_the_icon( 'download' ); ?></span>
		<h2 class="xc-empty__title"><?php esc_html_e( 'Download the plugin from your XACUBE panel', 'xacube' ); ?></h2>
		<p><?php esc_html_e( 'This copy of the plugin is not linked to any of your websites, so the chat is not shown.', 'xacube' ); ?></p>
		<ol class="xc-steps">
			<li><?php esc_html_e( 'Sign in to your XACUBE panel and open Websites.', 'xacube' ); ?></li>
			<li><?php esc_html_e( 'Choose this website, or add it, and download the WordPress plugin from its page.', 'xacube' ); ?></li>
			<li><?php esc_html_e( 'Install that copy in place of this one. It comes already set up, so the chat goes live as soon as you activate it.', 'xacube' ); ?></li>
		</ol>
	</section>
	<?php
}

/**
 * The page header: which XACUBE website and workspace this plugin serves, and the way to the inbox.
 *
 * @param array $config The download's configuration.
 */
function xacube_render_header( $config ) {
	?>
	<header class="xc-header">
		<div class="xc-brand">
			<span class="xc-brand__mark"><?php xacube_the_icon( 'cube' ); ?></span>
			<div>
				<span class="xc-brand__name">XACUBE</span>
				<p class="xc-header__sub">
					<?php
					printf(
						/* translators: 1: the XACUBE website's name, 2: the workspace's name. */
						esc_html__( 'Connected to %1$s · %2$s', 'xacube' ),
						'<bdi>' . esc_html( $config['site_name'] ) . '</bdi>',
						'<bdi>' . esc_html( $config['workspace_name'] ) . '</bdi>'
					);
					?>
				</p>
			</div>
		</div>
		<?php xacube_panel_link( $config['app_origin'] . '/inbox', __( 'Open your inbox', 'xacube' ), 'xc-button xc-button--primary' ); ?>
	</header>
	<?php
}

/**
 * The message for the action that just happened, if any.
 */
function xacube_render_notice() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a display code looked up in a fixed list.
	$code    = isset( $_GET['xacube_notice'] ) ? sanitize_key( wp_unslash( $_GET['xacube_notice'] ) ) : '';
	$notices = xacube_notices();
	if ( ! isset( $notices[ $code ] ) ) {
		return;
	}
	list( $tone, $text ) = $notices[ $code ];
	$icon                = array(
		'success' => 'live',
		'warning' => 'warning',
		'danger'  => 'danger',
	);
	printf(
		'<div class="xc-notice xc-notice--%1$s" role="%2$s">%3$s<p>%4$s</p></div>',
		esc_attr( $tone ),
		'danger' === $tone ? 'alert' : 'status',
		xacube_icon( isset( $icon[ $tone ] ) ? $icon[ $tone ] : 'info' ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed markup.
		esc_html( $text )
	);
}

/**
 * The status card: whether visitors on this host get the chat, and what to do if not.
 *
 * @param array $config   The download's configuration.
 * @param array $settings The admin's settings.
 */
function xacube_render_status( $config, $settings ) {
	$status = $settings['enabled'] ? xacube_status() : null;
	$view   = xacube_status_view( $config, $status );
	?>
	<section class="xc-card xc-status xc-status--<?php echo esc_attr( $view['tone'] ); ?>" aria-labelledby="xc-status-title">
		<span class="xc-status__icon"><?php xacube_the_icon( $view['icon'] ); ?></span>
		<div class="xc-status__body">
			<h2 id="xc-status-title" class="xc-status__title"><?php echo esc_html( $view['title'] ); ?></h2>
			<p><?php echo wp_kses( $view['body'], array( 'bdi' => array( 'dir' => true ) ) ); ?></p>
			<?php if ( $status ) : ?>
				<p class="xc-meta">
					<?php
					/* translators: %s: when the check ran, e.g. "2 mins ago". */
					echo esc_html( sprintf( __( 'Checked %s', 'xacube' ), xacube_ago( $status['checked_at'] ) ) );
					?>
				</p>
			<?php endif; ?>
		</div>
		<div class="xc-status__actions">
			<?php
			if ( ! empty( $view['link'] ) ) {
				xacube_panel_link( $view['link'][0], $view['link'][1], 'xc-button xc-button--primary' );
			}
			if ( $status ) {
				xacube_form_open( 'xacube_recheck', array( 'data-xc-pending' => __( 'Checking…', 'xacube' ) ) );
				echo '<button type="submit" class="xc-button xc-button--secondary">';
				xacube_the_icon( 'refresh' );
				echo '<span>' . esc_html__( 'Check again', 'xacube' ) . '</span></button></form>';
			}
			?>
		</div>
	</section>
	<?php
}

/**
 * A switch: a real checkbox, drawn as a toggle.
 *
 * @param string $name        Field name.
 * @param bool   $checked     Whether it is on.
 * @param string $label       What it switches.
 * @param string $description What that means.
 */
function xacube_toggle( $name, $checked, $label, $description ) {
	$id = 'xc-' . $name;
	?>
	<div class="xc-toggle">
		<input type="checkbox" class="xc-toggle__input" role="switch" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" value="1" aria-describedby="<?php echo esc_attr( $id . '-desc' ); ?>" <?php checked( $checked ); ?>>
		<label class="xc-toggle__text" for="<?php echo esc_attr( $id ); ?>">
			<span class="xc-toggle__label"><?php echo esc_html( $label ); ?></span>
			<span class="xc-toggle__desc" id="<?php echo esc_attr( $id . '-desc' ); ?>"><?php echo esc_html( $description ); ?></span>
		</label>
	</div>
	<?php
}

/**
 * The chat card: on or off, and whether administrators see it.
 *
 * @param array $settings The admin's settings.
 */
function xacube_render_chat_card( $settings ) {
	?>
	<section class="xc-card" aria-labelledby="xc-chat-title">
		<div class="xc-card__head">
			<span class="xc-card__icon"><?php xacube_the_icon( 'chat' ); ?></span>
			<h2 id="xc-chat-title" class="xc-card__title"><?php esc_html_e( 'Chat window', 'xacube' ); ?></h2>
		</div>
		<?php xacube_form_open( 'xacube_save', array( 'data-xc-pending' => __( 'Saving…', 'xacube' ) ) ); ?>
			<input type="hidden" name="xacube_form" value="chat">
			<?php
			xacube_toggle( 'enabled', $settings['enabled'], __( 'Show the chat on this site', 'xacube' ), __( 'Visitors see the chat button on every page of your site.', 'xacube' ) );
			xacube_toggle( 'hide_for_admins', $settings['hide_for_admins'], __( 'Hide it from administrators', 'xacube' ), __( 'Browse your own site without the chat button. Visitors still see it.', 'xacube' ) );
			?>
			<div class="xc-card__foot">
				<button type="submit" class="xc-button xc-button--primary"><?php esc_html_e( 'Save changes', 'xacube' ); ?></button>
			</div>
		</form>
	</section>
	<?php
}

/**
 * The identity card. Off, it explains and offers to connect; on, it shows which secret key is
 * stored (its last four characters only) and lets the admin switch it off or forget the key.
 *
 * @param array $config   The download's configuration.
 * @param array $settings The admin's settings.
 */
function xacube_render_identity_card( $config, $settings ) {
	$identity = xacube_identity();
	$stale    = xacube_identity_is_stale();
	?>
	<section class="xc-card" aria-labelledby="xc-identity-title">
		<div class="xc-card__head">
			<span class="xc-card__icon"><?php xacube_the_icon( 'user' ); ?></span>
			<h2 id="xc-identity-title" class="xc-card__title"><?php esc_html_e( 'Recognise signed-in users', 'xacube' ); ?></h2>
			<?php if ( $identity && $settings['identity'] ) : ?>
				<span class="xc-badge xc-badge--success"><?php esc_html_e( 'On', 'xacube' ); ?></span>
			<?php else : ?>
				<span class="xc-badge"><?php esc_html_e( 'Off', 'xacube' ); ?></span>
			<?php endif; ?>
		</div>

		<?php if ( ! $identity ) : ?>
			<p class="xc-card__text"><?php esc_html_e( 'When someone signed in to your WordPress starts a chat, your inbox shows their name and email instead of an anonymous visitor. Each visitor’s identity is signed with your site’s secret key, so no one can pretend to be one of your users.', 'xacube' ); ?></p>
			<?php if ( $stale ) : ?>
				<div class="xc-notice xc-notice--warning xc-notice--inline"><?php xacube_the_icon( 'warning' ); ?><p><?php esc_html_e( 'The secret key saved here belongs to a different XACUBE website. Connect again to fetch this website’s key.', 'xacube' ); ?></p></div>
			<?php endif; ?>
			<p class="xc-card__text xc-muted"><?php esc_html_e( 'Turning it on takes you to XACUBE to confirm, then brings you straight back. The secret key is fetched by your server and is never shown in a page.', 'xacube' ); ?></p>
			<div class="xc-card__foot">
				<?php xacube_form_open( 'xacube_connect', array( 'data-xc-pending' => __( 'Connecting…', 'xacube' ) ) ); ?>
					<button type="submit" class="xc-button xc-button--primary">
						<?php xacube_the_icon( 'key' ); ?>
						<span><?php $stale ? esc_html_e( 'Connect again', 'xacube' ) : esc_html_e( 'Connect and turn on', 'xacube' ); ?></span>
					</button>
				</form>
				<?php if ( $stale ) : ?>
					<?php xacube_render_forget_form(); ?>
				<?php endif; ?>
			</div>
		<?php else : ?>
			<?php xacube_form_open( 'xacube_save', array( 'id' => 'xc-identity-form', 'data-xc-pending' => __( 'Saving…', 'xacube' ) ) ); ?>
				<input type="hidden" name="xacube_form" value="identity">
				<?php xacube_toggle( 'identity', $settings['identity'], __( 'Recognise signed-in users', 'xacube' ), __( 'Your inbox shows the name and email of visitors who are signed in to this site.', 'xacube' ) ); ?>
			</form>
			<dl class="xc-facts">
				<div>
					<dt><?php esc_html_e( 'Secret key', 'xacube' ); ?></dt>
					<dd><code dir="ltr">xc_sec_••••<?php echo esc_html( substr( $identity['secret'], -4 ) ); ?></code></dd>
				</div>
				<?php if ( ! empty( $identity['connected_at'] ) ) : ?>
					<div>
						<dt><?php esc_html_e( 'Connected', 'xacube' ); ?></dt>
						<dd><?php echo esc_html( xacube_ago( (int) $identity['connected_at'] ) ); ?></dd>
					</div>
				<?php endif; ?>
			</dl>
			<div class="xc-card__foot">
				<button type="submit" form="xc-identity-form" class="xc-button xc-button--primary"><?php esc_html_e( 'Save changes', 'xacube' ); ?></button>
				<?php xacube_render_forget_form(); ?>
			</div>
		<?php endif; ?>
	</section>
	<?php
}

/**
 * "Forget secret key". Local only: the site keeps its keys at XACUBE until revoked in the panel.
 */
function xacube_render_forget_form() {
	xacube_form_open(
		'xacube_forget',
		array(
			'class'           => 'xc-push',
			'data-xc-confirm' => __( 'Forget the secret key on this site? Signed-in users will no longer be recognised until you connect again. Your XACUBE website keeps working.', 'xacube' ),
		)
	);
	echo '<button type="submit" class="xc-button xc-button--ghost-danger">' . esc_html__( 'Forget secret key', 'xacube' ) . '</button></form>';
}

/**
 * What this download is: useful when talking to support, and the way to the site in the panel.
 *
 * @param array $config The download's configuration.
 */
function xacube_render_details( $config ) {
	$generated = $config['generated_at'] ? strtotime( $config['generated_at'] ) : false;
	?>
	<section class="xc-card xc-details" aria-labelledby="xc-details-title">
		<div class="xc-card__head">
			<h2 id="xc-details-title" class="xc-card__title"><?php esc_html_e( 'About this copy', 'xacube' ); ?></h2>
			<?php
			if ( $config['site_id'] ) {
				xacube_panel_link( $config['app_origin'] . '/sites/' . rawurlencode( $config['site_id'] ), __( 'Manage this website in your panel', 'xacube' ), 'xc-link xc-push' );
			}
			?>
		</div>
		<dl class="xc-facts xc-facts--grid">
			<div>
				<dt><?php esc_html_e( 'Website', 'xacube' ); ?></dt>
				<dd><bdi><?php echo esc_html( $config['site_name'] ); ?></bdi></dd>
			</div>
			<div>
				<dt><?php esc_html_e( 'Public key', 'xacube' ); ?></dt>
				<dd><code dir="ltr"><?php echo esc_html( $config['public_key'] ); ?></code></dd>
			</div>
			<?php if ( $generated ) : ?>
				<div>
					<dt><?php esc_html_e( 'Downloaded', 'xacube' ); ?></dt>
					<dd><?php echo esc_html( xacube_digits( wp_date( get_option( 'date_format' ), $generated ) ) ); ?></dd>
				</div>
			<?php endif; ?>
			<div>
				<dt><?php esc_html_e( 'Plugin version', 'xacube' ); ?></dt>
				<dd><code dir="ltr"><?php echo esc_html( XACUBE_VERSION ); ?></code></dd>
			</div>
		</dl>
	</section>
	<?php
}
