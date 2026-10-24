<?php
/**
 * Network admin: settings, overview of all sites and SEO plugin detection.
 *
 * @package Shawesome_Virtual_Robots_Txt
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SEO plugins that change the robots.txt output, keyed by plugin file.
 *
 * @return array<string, string> Plugin file => name.
 */
function shawesome_vrt_seo_plugins() {
	return array(
		'wordpress-seo/wp-seo.php'                        => 'Yoast SEO',
		'all-in-one-seo-pack/all_in_one_seo_pack.php'     => 'All in One SEO',
		'all-in-one-seo-pack-pro/all_in_one_seo_pack.php' => 'All in One SEO Pro',
	);
}

/**
 * SEO plugins active on a site (network-wide or per site).
 *
 * @param int $site_id Site ID.
 * @return string[] Plugin names.
 */
function shawesome_vrt_active_seo_plugins( $site_id ) {
	$network_active = array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) );
	$site_active    = (array) get_blog_option( $site_id, 'active_plugins', array() );
	$active         = array_merge( $network_active, $site_active );

	$names = array();
	foreach ( shawesome_vrt_seo_plugins() as $file => $name ) {
		if ( in_array( $file, $active, true ) ) {
			$names[] = $name;
		}
	}

	return $names;
}

/**
 * Add the settings page under Network Admin → Settings.
 */
function shawesome_vrt_admin_menu() {
	add_submenu_page(
		'settings.php',
		__( 'Virtual Robots.txt', 'shawesome-virtual-robots-txt' ),
		__( 'Virtual Robots.txt', 'shawesome-virtual-robots-txt' ),
		'manage_network_options',
		'shawesome-vrt',
		'shawesome_vrt_render_page'
	);
}
add_action( 'network_admin_menu', 'shawesome_vrt_admin_menu' );

/**
 * Add a "Settings" link to the plugin row in Network Admin → Plugins.
 *
 * @param string[] $links Action links.
 * @return string[]
 */
function shawesome_vrt_action_links( $links ) {
	$url = network_admin_url( 'settings.php?page=shawesome-vrt' );
	array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'shawesome-virtual-robots-txt' ) . '</a>' );

	return $links;
}
add_filter( 'network_admin_plugin_action_links_' . plugin_basename( dirname( __DIR__ ) . '/shawesome-virtual-robots-txt.php' ), 'shawesome_vrt_action_links' );

/**
 * Save the settings (posted to network/edit.php?action=shawesome_vrt_save).
 */
function shawesome_vrt_save_settings() {
	check_admin_referer( 'shawesome_vrt_save' );

	if ( ! current_user_can( 'manage_network_options' ) ) {
		wp_die( esc_html__( 'Sorry, you are not allowed to manage options for this network.', 'shawesome-virtual-robots-txt' ) );
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above.
	$mode = isset( $_POST['mode'] ) ? sanitize_key( wp_unslash( $_POST['mode'] ) ) : 'exact';

	update_site_option(
		SHAWESOME_VRT_OPTION,
		array(
			'mode'           => in_array( $mode, array( 'exact', 'base' ), true ) ? $mode : 'exact',
			'respect_public' => ! empty( $_POST['respect_public'] ),
		)
	);

	wp_safe_redirect( add_query_arg( 'updated', 'true', network_admin_url( 'settings.php?page=shawesome-vrt' ) ) );
	exit;
}
add_action( 'network_admin_edit_shawesome_vrt_save', 'shawesome_vrt_save_settings' );

/**
 * Render the settings page.
 */
function shawesome_vrt_render_page() {
	if ( ! current_user_can( 'manage_network_options' ) ) {
		return;
	}

	$settings = shawesome_vrt_get_settings();
	$dir      = shawesome_vrt_dir();
	$sites    = get_sites(
		array(
			'number'  => 0,
			'deleted' => 0,
		)
	);

	$seo_sites = array();
	foreach ( $sites as $site ) {
		$seo_sites[ $site->blog_id ] = shawesome_vrt_active_seo_plugins( (int) $site->blog_id );
	}
	$any_seo = (bool) array_filter( $seo_sites );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Virtual Robots.txt', 'shawesome-virtual-robots-txt' ); ?></h1>

		<?php if ( isset( $_GET['updated'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'shawesome-virtual-robots-txt' ); ?></p></div>
		<?php endif; ?>

		<?php if ( ! shawesome_vrt_is_supported() ) : ?>
			<div class="notice notice-error"><p><?php esc_html_e( 'This network uses subdirectories. Per-site robots.txt files only work for networks with their own (sub)domains, so the plugin does nothing here.', 'shawesome-virtual-robots-txt' ); ?></p></div>
		<?php endif; ?>

		<?php if ( file_exists( ABSPATH . 'robots.txt' ) ) : ?>
			<div class="notice notice-error"><p>
				<?php
				printf(
					/* translators: %s: path of the physical robots.txt file */
					esc_html__( 'A physical robots.txt exists at %s. The web server delivers it directly, so WordPress and this plugin are never asked. Delete or rename it to use the virtual files.', 'shawesome-virtual-robots-txt' ),
					'<code>' . esc_html( ABSPATH . 'robots.txt' ) . '</code>'
				);
				?>
			</p></div>
		<?php endif; ?>

		<?php if ( ! is_dir( $dir ) ) : ?>
			<div class="notice notice-warning"><p>
				<?php
				printf(
					/* translators: %s: directory path */
					esc_html__( 'The directory %s does not exist. Create it, or deactivate and reactivate the plugin.', 'shawesome-virtual-robots-txt' ),
					'<code>' . esc_html( $dir ) . '</code>'
				);
				?>
			</p></div>
		<?php endif; ?>

		<?php if ( $any_seo && 'base' === $settings['mode'] ) : ?>
			<div class="notice notice-warning"><p><?php esc_html_e( 'An SEO plugin is active on at least one site. In "Base" mode, Yoast SEO appends its rules and sitemap to the file, and All in One SEO rebuilds it: comments and Sitemap lines from the file are dropped. Use "Exact" mode if the file should be delivered as is.', 'shawesome-virtual-robots-txt' ); ?></p></div>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( network_admin_url( 'edit.php?action=shawesome_vrt_save' ) ); ?>">
			<?php wp_nonce_field( 'shawesome_vrt_save' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Mode', 'shawesome-virtual-robots-txt' ); ?></th>
					<td>
						<fieldset>
							<label>
								<input type="radio" name="mode" value="exact" <?php checked( $settings['mode'], 'exact' ); ?>>
								<strong><?php esc_html_e( 'Exact', 'shawesome-virtual-robots-txt' ); ?></strong> –
								<?php esc_html_e( 'the file is delivered as is. SEO plugins (Yoast SEO, All in One SEO …) cannot change it.', 'shawesome-virtual-robots-txt' ); ?>
							</label><br>
							<label>
								<input type="radio" name="mode" value="base" <?php checked( $settings['mode'], 'base' ); ?>>
								<strong><?php esc_html_e( 'Base', 'shawesome-virtual-robots-txt' ); ?></strong> –
								<?php esc_html_e( 'the file replaces the WordPress default. SEO plugins may add their rules and sitemap; duplicate Sitemap lines are removed.', 'shawesome-virtual-robots-txt' ); ?>
							</label>
						</fieldset>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Search engine visibility', 'shawesome-virtual-robots-txt' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="respect_public" value="1" <?php checked( $settings['respect_public'] ); ?>>
							<?php esc_html_e( 'Ignore the file on sites set to "Discourage search engines from indexing this site" (WordPress keeps "Disallow: /").', 'shawesome-virtual-robots-txt' ); ?>
						</label>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>

		<h2><?php esc_html_e( 'Sites', 'shawesome-virtual-robots-txt' ); ?></h2>
		<p>
			<?php
			printf(
				/* translators: %s: directory path */
				esc_html__( 'Files are read from %s. The hyphenated name takes precedence; the name with dots is an unambiguous alternative.', 'shawesome-virtual-robots-txt' ),
				'<code>' . esc_html( $dir ) . '</code>'
			);
			?>
		</p>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Site', 'shawesome-virtual-robots-txt' ); ?></th>
					<th><?php esc_html_e( 'File name', 'shawesome-virtual-robots-txt' ); ?></th>
					<th><?php esc_html_e( 'Status', 'shawesome-virtual-robots-txt' ); ?></th>
					<th><?php esc_html_e( 'SEO plugins', 'shawesome-virtual-robots-txt' ); ?></th>
					<th><?php esc_html_e( 'robots.txt', 'shawesome-virtual-robots-txt' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $sites as $site ) : ?>
					<?php
					$candidates = array_map( 'basename', shawesome_vrt_candidate_files( $site->domain ) );
					$file       = shawesome_vrt_find_file( $site->domain );
					$is_public  = '0' !== (string) get_blog_option( $site->blog_id, 'blog_public' );
					?>
					<tr>
						<td><strong><?php echo esc_html( $site->domain ); ?></strong></td>
						<td><code><?php echo esc_html( $candidates[0] ); ?></code><?php if ( isset( $candidates[1] ) ) : ?><br><code><?php echo esc_html( $candidates[1] ); ?></code><?php endif; ?></td>
						<td>
							<?php if ( null === $file ) : ?>
								<?php esc_html_e( 'No file – WordPress default', 'shawesome-virtual-robots-txt' ); ?>
							<?php elseif ( ! $is_public && $settings['respect_public'] ) : ?>
								<?php esc_html_e( 'File ignored – site hidden from search engines', 'shawesome-virtual-robots-txt' ); ?>
							<?php else : ?>
								<span style="color:#008a20;">&#10003;</span>
								<?php
								/* translators: %s: file name */
								printf( esc_html__( 'Active: %s', 'shawesome-virtual-robots-txt' ), '<code>' . esc_html( basename( $file ) ) . '</code>' );
								?>
							<?php endif; ?>
						</td>
						<td><?php echo $seo_sites[ $site->blog_id ] ? esc_html( implode( ', ', $seo_sites[ $site->blog_id ] ) ) : '–'; ?></td>
						<td><a href="<?php echo esc_url( get_home_url( $site->blog_id, 'robots.txt' ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Open', 'shawesome-virtual-robots-txt' ); ?></a></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php
}
