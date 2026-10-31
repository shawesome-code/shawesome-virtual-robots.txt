<?php
/**
 * Load the newest copy of the shawesome admin among all active plugins.
 *
 * Every plugin bundles its own copy of this repository, possibly in different
 * versions. Each copy only announces itself here; admin.php of the newest one
 * is loaded once all plugins are, so an old copy cannot replace a newer one.
 *
 * Copies of this file live on in released plugins, so it must keep working
 * together with them. Change the version on every release, but nothing else
 * unless there is no other way.
 *
 * @package Shawesome_Admin
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$shawesome_admin_version = '1.0.1';

if ( ! isset( $GLOBALS['shawesome_admin'] ) || version_compare( $shawesome_admin_version, $GLOBALS['shawesome_admin']['version'], '>' ) ) {
	$GLOBALS['shawesome_admin'] = array(
		'version' => $shawesome_admin_version,
		'file'    => __DIR__ . '/admin.php',
	);
}

unset( $shawesome_admin_version );

if ( ! function_exists( 'shawesome_admin_load' ) ) {
	/**
	 * Load the newest copy.
	 */
	function shawesome_admin_load() {
		require_once $GLOBALS['shawesome_admin']['file'];
	}
	add_action( 'plugins_loaded', 'shawesome_admin_load', 1 );
}
