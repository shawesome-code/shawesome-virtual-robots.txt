<?php
/**
 * Plugin Name:         Shawesome Virtual Robots.txt – Per-Site robots.txt for Multisite
 * Plugin URI:          https://github.com/shawesome-code/shawesome-virtual-robots.txt
 * Description:         Serves an individual robots.txt for every site of a subdomain/domain-based multisite network from /wp-content/virtual-robots-txt/. The file name is derived from the domain (dots become hyphens), e.g. www.example.com → www-example-com.txt.
 * Version:             1.1.0
 * Requires at least:   5.1
 * Requires PHP:        7.4
 * Author:              Shawesome
 * Author URI:          https://shawesome.de/
 * License:             GPL-2.0-or-later
 * License URI:         https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:         shawesome-virtual-robots-txt
 * Network:             true
 *
 * @package Shawesome_Virtual_Robots_Txt
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Directory holding the per-site robots.txt files.
 *
 * @return string Absolute path with trailing slash.
 */
function shawesome_vrt_dir() {
	return WP_CONTENT_DIR . '/virtual-robots-txt/';
}

/**
 * On activation, create the directory for the virtual robots.txt files
 * if it does not exist yet.
 */
register_activation_hook(
	__FILE__,
	function () {
		$robots_dir = shawesome_vrt_dir();

		if ( ! file_exists( $robots_dir ) ) {
			wp_mkdir_p( $robots_dir );
		}
	}
);

add_filter(
	'robots_txt',
	function ( $output, $public ) {

		// 1. Only act on multisite installations based on domains/subdomains
		//    (not on paths/subdirectories).
		if ( ! is_multisite() ) {
			return $output;
		}

		if ( ! defined( 'SUBDOMAIN_INSTALL' ) || ! SUBDOMAIN_INSTALL ) {
			// Path-based multisite -> do nothing.
			return $output;
		}

		$current_host = isset( $_SERVER['HTTP_HOST'] ) ? strtolower( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';
		if ( '' === $current_host ) {
			return $output;
		}

		// 2. Only consider the current site, and only if it matches the requested host.
		$site = get_site();
		if ( ! $site || strtolower( $site->domain ) !== $current_host ) {
			return $output;
		}

		// 2.1 Derive the file name from the domain (dots -> hyphens).
		$filepath = shawesome_vrt_dir() . str_replace( '.', '-', $site->domain ) . '.txt';

		// 2.2 If the file exists, return its contents.
		if ( is_readable( $filepath ) ) {
			$content = file_get_contents( $filepath );
			if ( false !== $content ) {
				return $content;
			}
		}

		// No match -> keep WordPress' default behavior.
		return $output;
	},
	10,
	2
);
