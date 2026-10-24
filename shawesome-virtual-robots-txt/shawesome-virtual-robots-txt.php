<?php
/**
 * Plugin Name:         Shawesome Virtual Robots.txt – Per-Site robots.txt for Multisite
 * Plugin URI:          https://github.com/shawesome-code/shawesome-virtual-robots.txt
 * Description:         An individual robots.txt for every site of a subdomain/domain-based multisite network – edited in the network admin, or deployed as a file to /wp-content/virtual-robots-txt/ (e.g. www.example.com → www-example-com.txt), which takes precedence.
 * Version:             1.3.0
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

define( 'SHAWESOME_VRT_VERSION', '1.3.0' );
define( 'SHAWESOME_VRT_OPTION', 'shawesome_vrt_settings' );
define( 'SHAWESOME_VRT_CONTENT_OPTION', 'shawesome_vrt_content' );

/**
 * Directory holding the per-site robots.txt files.
 *
 * Can be changed with the constant SHAWESOME_VRT_DIR (e.g. in wp-config.php)
 * or the filter 'shawesome_vrt_dir'.
 *
 * @return string Absolute path with trailing slash.
 */
function shawesome_vrt_dir() {
	$dir = defined( 'SHAWESOME_VRT_DIR' ) ? SHAWESOME_VRT_DIR : WP_CONTENT_DIR . '/virtual-robots-txt/';

	return trailingslashit( apply_filters( 'shawesome_vrt_dir', $dir ) );
}

/**
 * Whether the plugin can work on this installation: only multisite networks
 * based on domains/subdomains (not on paths/subdirectories).
 *
 * @return bool
 */
function shawesome_vrt_is_supported() {
	return is_multisite() && is_subdomain_install();
}

/**
 * Network-wide settings, merged with the defaults.
 *
 * - mode 'exact': the file is delivered as is, after all SEO plugins
 *   (Yoast SEO, All in One SEO …) have run.
 * - mode 'base': the file replaces the WordPress default, SEO plugins may
 *   still add their rules and sitemaps.
 * - respect_public: sites set to "Discourage search engines" keep
 *   WordPress' "Disallow: /" instead of the file.
 *
 * @return array{mode: string, respect_public: bool}
 */
function shawesome_vrt_get_settings() {
	$defaults = array(
		'mode'           => 'exact',
		'respect_public' => true,
	);

	$settings = wp_parse_args( (array) get_site_option( SHAWESOME_VRT_OPTION, array() ), $defaults );

	if ( ! in_array( $settings['mode'], array( 'exact', 'base' ), true ) ) {
		$settings['mode'] = $defaults['mode'];
	}
	$settings['respect_public'] = (bool) $settings['respect_public'];

	return $settings;
}

/**
 * Candidate file names for a domain, in order of precedence.
 *
 * The hyphenated name (www-example-com.txt) comes first for backwards
 * compatibility. The name with dots (www.example.com.txt) is unambiguous,
 * because a-b.example and a.b.example both map to a-b-example.txt.
 *
 * @param string $domain Site domain.
 * @return string[] Absolute file paths.
 */
function shawesome_vrt_candidate_files( $domain ) {
	$dir    = shawesome_vrt_dir();
	$domain = strtolower( $domain );

	$files = array(
		$dir . str_replace( '.', '-', $domain ) . '.txt',
		$dir . $domain . '.txt',
	);

	return (array) apply_filters( 'shawesome_vrt_candidate_files', $files, $domain );
}

/**
 * First readable robots.txt file for a domain.
 *
 * @param string $domain Site domain.
 * @return string|null Absolute file path or null.
 */
function shawesome_vrt_find_file( $domain ) {
	foreach ( shawesome_vrt_candidate_files( $domain ) as $file ) {
		if ( is_readable( $file ) ) {
			return $file;
		}
	}

	return null;
}

/**
 * Normalize robots.txt content: strip a UTF-8 BOM (it would break the first
 * directive for crawlers) and normalize line endings to "\n".
 *
 * @param string $content Raw content.
 * @return string
 */
function shawesome_vrt_normalize( $content ) {
	if ( 0 === strpos( $content, "\xEF\xBB\xBF" ) ) {
		$content = substr( $content, 3 );
	}

	return str_replace( array( "\r\n", "\r" ), "\n", $content );
}

/**
 * Read a robots.txt file.
 *
 * @param string $file Absolute file path.
 * @return string|null Normalized file contents or null on failure.
 */
function shawesome_vrt_read_file( $file ) {
	$content = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

	return false === $content ? null : shawesome_vrt_normalize( $content );
}

/**
 * robots.txt content stored in the database for a site.
 *
 * @param int $site_id Site ID.
 * @return string Content, empty if none.
 */
function shawesome_vrt_get_db_content( $site_id ) {
	return (string) get_blog_option( $site_id, SHAWESOME_VRT_CONTENT_OPTION, '' );
}

/**
 * Store robots.txt content in the database for a site. Empty content
 * deletes the option, so the site falls back to the WordPress default.
 *
 * @param int    $site_id Site ID.
 * @param string $content Content.
 */
function shawesome_vrt_set_db_content( $site_id, $content ) {
	$content = shawesome_vrt_normalize( (string) $content );

	switch_to_blog( $site_id );
	if ( '' === trim( $content ) ) {
		delete_option( SHAWESOME_VRT_CONTENT_OPTION );
	} else {
		// Not autoloaded: only needed when robots.txt is requested.
		update_option( SHAWESOME_VRT_CONTENT_OPTION, $content, false );
	}
	restore_current_blog();
}

/**
 * Where the robots.txt of a site comes from. A file takes precedence over
 * the database, so that deployments keep control.
 *
 * @param WP_Site $site Site.
 * @return array{source: string|null, file: string|null, content: string|null}
 *               source is 'file', 'db' or null (WordPress default).
 */
function shawesome_vrt_resolve( $site ) {
	$file = shawesome_vrt_find_file( $site->domain );
	if ( null !== $file ) {
		$content = shawesome_vrt_read_file( $file );
		if ( null !== $content ) {
			return array(
				'source'  => 'file',
				'file'    => $file,
				'content' => $content,
			);
		}
	}

	$content = shawesome_vrt_get_db_content( (int) $site->blog_id );
	if ( '' !== trim( $content ) ) {
		return array(
			'source'  => 'db',
			'file'    => null,
			'content' => $content,
		);
	}

	return array(
		'source'  => null,
		'file'    => null,
		'content' => null,
	);
}

/**
 * Remove repeated Sitemap lines (case-insensitive), keeping the first one.
 * Used in 'base' mode, when the file and an SEO plugin both add the sitemap.
 *
 * @param string $output robots.txt output.
 * @return string
 */
function shawesome_vrt_dedupe_sitemaps( $output ) {
	$seen  = array();
	$lines = array();

	foreach ( preg_split( '/\r\n|\r|\n/', $output ) as $line ) {
		if ( preg_match( '/^\s*sitemap\s*:\s*(\S+)/i', $line, $match ) ) {
			$url = strtolower( untrailingslashit( $match[1] ) );
			if ( isset( $seen[ $url ] ) ) {
				continue;
			}
			$seen[ $url ] = true;
		}
		$lines[] = $line;
	}

	return implode( "\n", $lines );
}

/**
 * Filter callback for 'robots_txt': replace the output with the site's
 * file or database content.
 *
 * @param string $output robots.txt output so far.
 * @param string $public Value of the blog_public option ('0' = discourage search engines).
 * @return string
 */
function shawesome_vrt_filter_robots_txt( $output, $public ) {
	if ( ! shawesome_vrt_is_supported() ) {
		return $output;
	}

	$settings = shawesome_vrt_get_settings();

	// Keep WordPress' "Disallow: /" for sites hidden from search engines,
	// so that e.g. a staging site does not become indexable by accident.
	if ( $settings['respect_public'] && '0' === (string) $public ) {
		return $output;
	}

	// WordPress has already resolved the current site from the requested host.
	$site = get_site();
	if ( ! $site ) {
		return $output;
	}

	$resolved = shawesome_vrt_resolve( $site );

	// No file and no database content -> keep the default behavior.
	return null === $resolved['content'] ? $output : $resolved['content'];
}

/**
 * Filter callback for 'robots_txt' in 'base' mode, after all SEO plugins.
 *
 * @param string $output robots.txt output.
 * @return string
 */
function shawesome_vrt_filter_dedupe( $output ) {
	return shawesome_vrt_dedupe_sitemaps( $output );
}

/**
 * Register the robots.txt filters according to the selected mode.
 *
 * 'exact' runs at PHP_INT_MAX, after Yoast SEO (99999) and All in One SEO
 * (10000), which would otherwise append to or rebuild the file. 'base' runs
 * at 10, before them. The priority can be changed with the filter
 * 'shawesome_vrt_priority'.
 */
function shawesome_vrt_register_filters() {
	$settings = shawesome_vrt_get_settings();
	$priority = 'exact' === $settings['mode'] ? PHP_INT_MAX : 10;
	$priority = (int) apply_filters( 'shawesome_vrt_priority', $priority, $settings['mode'] );

	add_filter( 'robots_txt', 'shawesome_vrt_filter_robots_txt', $priority, 2 );

	if ( 'base' === $settings['mode'] ) {
		add_filter( 'robots_txt', 'shawesome_vrt_filter_dedupe', PHP_INT_MAX );
	}
}
add_action( 'plugins_loaded', 'shawesome_vrt_register_filters' );

if ( is_admin() ) {
	require_once __DIR__ . '/includes/admin.php';
}
