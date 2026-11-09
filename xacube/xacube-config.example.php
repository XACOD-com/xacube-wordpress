<?php
/**
 * The shape of xacube-config.php. The real file is written by the XACUBE panel into each download
 * (apps/web/src/server/wordpressPlugin.ts) and is never committed: it names one customer's site.
 *
 * The secret key is deliberately not here. The plugin fetches it through the connect flow, and
 * only if the admin turns on recognising signed-in users.
 *
 * @package XACUBE
 */

defined( 'ABSPATH' ) || exit;

return array(
	'site_id'        => '00000000-0000-0000-0000-000000000000',
	'public_key'     => 'xc_pub_XXXXXXXXXXXXXXXXXXXXXXXX',
	'widget_origin'  => 'http://localhost:8787',
	'app_origin'     => 'http://localhost:3100',
	'site_name'      => 'Localhost demo',
	'workspace_name' => 'XACUBE Dev',
	'generated_at'   => '2026-11-04T12:00:00.000Z',
);
