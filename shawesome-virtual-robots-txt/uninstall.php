<?php
/**
 * Remove the plugin settings and the robots.txt content stored in the
 * database on uninstall. Files in /wp-content/virtual-robots-txt/ are kept
 * on purpose, as they are usually managed by deployments.
 *
 * @package Shawesome_Virtual_Robots_Txt
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_site_option( 'shawesome_vrt_settings' );

foreach ( get_sites(
	array(
		'number' => 0,
		'fields' => 'ids',
	)
) as $site_id ) {
	delete_blog_option( $site_id, 'shawesome_vrt_content' );
}
