<?php
/**
 * Remove the plugin settings on uninstall. The robots.txt files in
 * /wp-content/virtual-robots-txt/ are kept on purpose.
 *
 * @package Shawesome_Virtual_Robots_Txt
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_site_option( 'shawesome_vrt_settings' );
