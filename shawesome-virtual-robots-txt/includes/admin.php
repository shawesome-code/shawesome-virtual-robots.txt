<?php
/**
 * Network admin: settings, overview of all sites, per-site editor and
 * SEO plugin detection. Site admin: the same editor for the current site, if
 * enabled in the settings.
 *
 * @package Shawesome_Virtual_Robots_Txt
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/shawesome-wordpress-admin/loader.php';

/**
 * Load the bundled translations. Translations from translate.wordpress.org
 * in wp-content/languages/plugins/ take precedence.
 */
function shawesome_vrt_load_textdomain() {
	load_plugin_textdomain( 'shawesome-virtual-robots-txt', false, dirname( plugin_basename( dirname( __DIR__ ) . '/shawesome-virtual-robots-txt.php' ) ) . '/languages' );
}
add_action( 'init', 'shawesome_vrt_load_textdomain' );

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
 * URL of the settings page in the current admin, optionally for one site's
 * editor.
 *
 * @param array $args Query arguments.
 * @return string
 */
function shawesome_vrt_page_url( $args = array() ) {
	return add_query_arg( $args, is_network_admin() ? network_admin_url( 'admin.php?page=shawesome-vrt' ) : admin_url( 'admin.php?page=shawesome-vrt' ) );
}

/**
 * URL the editor posts an action to: network/edit.php in the network admin,
 * admin-post.php in the admin of a site.
 *
 * @param string $action Action without the prefix.
 * @return string
 */
function shawesome_vrt_action_url( $action ) {
	$action = 'shawesome_vrt_' . $action;

	return is_network_admin() ? network_admin_url( 'edit.php?action=' . $action ) : admin_url( 'admin-post.php?action=' . $action );
}

/**
 * Add the settings page under Network Admin → shawesome.
 */
function shawesome_vrt_admin_menu() {
	add_submenu_page(
		'shawesome',
		__( 'Virtual Robots.txt', 'shawesome-virtual-robots-txt' ),
		__( 'Virtual Robots.txt', 'shawesome-virtual-robots-txt' ),
		'manage_network_options',
		'shawesome-vrt',
		'shawesome_vrt_render_page'
	);
}
add_action( 'network_admin_menu', 'shawesome_vrt_admin_menu' );

/**
 * Add the editor of the current site under shawesome in the site's admin.
 */
function shawesome_vrt_site_admin_menu() {
	if ( ! shawesome_vrt_can_edit_site() ) {
		return;
	}

	add_submenu_page(
		'shawesome',
		__( 'Virtual Robots.txt', 'shawesome-virtual-robots-txt' ),
		__( 'Virtual Robots.txt', 'shawesome-virtual-robots-txt' ),
		'manage_options',
		'shawesome-vrt',
		'shawesome_vrt_render_site_page'
	);
}
add_action( 'admin_menu', 'shawesome_vrt_site_admin_menu' );

/**
 * Link the plugin on the start page of the shawesome menu to the settings page.
 *
 * @param array<string, string> $pages Plugin file => menu slug.
 * @return array<string, string>
 */
function shawesome_vrt_admin_pages( $pages ) {
	$pages[ plugin_basename( dirname( __DIR__ ) . '/shawesome-virtual-robots-txt.php' ) ] = 'shawesome-vrt';

	return $pages;
}
add_filter( 'shawesome_admin_pages', 'shawesome_vrt_admin_pages' );

/**
 * Add a "Settings" link to the plugin row in Network Admin → Plugins.
 *
 * @param string[] $links Action links.
 * @return string[]
 */
function shawesome_vrt_action_links( $links ) {
	array_unshift( $links, '<a href="' . esc_url( shawesome_vrt_page_url() ) . '">' . esc_html__( 'Settings', 'shawesome-virtual-robots-txt' ) . '</a>' );

	return $links;
}
add_filter( 'network_admin_plugin_action_links_' . plugin_basename( dirname( __DIR__ ) . '/shawesome-virtual-robots-txt.php' ), 'shawesome_vrt_action_links' );

/**
 * Check capability for a network admin action, die otherwise.
 */
function shawesome_vrt_check_cap() {
	if ( ! current_user_can( 'manage_network_options' ) ) {
		wp_die( esc_html__( 'Sorry, you are not allowed to manage options for this network.', 'shawesome-virtual-robots-txt' ) );
	}
}

/**
 * Whether the current user can edit the robots.txt of the current site in its
 * admin. Not on path-based networks, where the plugin does nothing.
 *
 * @return bool
 */
function shawesome_vrt_can_edit_site() {
	$settings = shawesome_vrt_get_settings();

	return shawesome_vrt_is_supported() && $settings['site_admins'] && current_user_can( 'manage_options' );
}

/**
 * Check capability for a per-site action, die otherwise.
 */
function shawesome_vrt_check_site_cap() {
	if ( is_network_admin() ) {
		shawesome_vrt_check_cap();
	} elseif ( ! shawesome_vrt_can_edit_site() ) {
		wp_die( esc_html__( 'Sorry, you are not allowed to edit the robots.txt of this site.', 'shawesome-virtual-robots-txt' ) );
	}
}

/**
 * Site of a per-site action: the current site in the admin of a site, so
 * that its administrators cannot reach other sites, otherwise the posted
 * site. Dies if it does not exist.
 *
 * @return WP_Site
 */
function shawesome_vrt_posted_site() {
	if ( ! is_network_admin() ) {
		return get_site();
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified by the caller.
	$site = isset( $_POST['site_id'] ) ? get_site( absint( $_POST['site_id'] ) ) : null;

	if ( ! $site ) {
		wp_die( esc_html__( 'Site not found.', 'shawesome-virtual-robots-txt' ) );
	}

	return $site;
}

/**
 * Save the settings (posted to network/edit.php?action=shawesome_vrt_save).
 */
function shawesome_vrt_save_settings() {
	shawesome_vrt_check_cap();
	check_admin_referer( 'shawesome_vrt_save' );

	$mode = isset( $_POST['mode'] ) ? sanitize_key( wp_unslash( $_POST['mode'] ) ) : 'base';

	update_site_option(
		SHAWESOME_VRT_OPTION,
		array(
			'mode'           => in_array( $mode, array( 'exact', 'base' ), true ) ? $mode : 'base',
			'respect_public' => ! empty( $_POST['respect_public'] ),
			'site_admins'    => ! empty( $_POST['site_admins'] ),
		)
	);

	wp_safe_redirect( shawesome_vrt_page_url( array( 'updated' => 'settings' ) ) );
	exit;
}
add_action( 'network_admin_edit_shawesome_vrt_save', 'shawesome_vrt_save_settings' );

/**
 * Save a site's robots.txt content (posted to network/edit.php or
 * admin-post.php?action=shawesome_vrt_save_site).
 */
function shawesome_vrt_save_site() {
	shawesome_vrt_check_site_cap();
	$site = shawesome_vrt_posted_site();
	check_admin_referer( 'shawesome_vrt_save_site_' . $site->blog_id );

	// robots.txt is served as text/plain and may contain characters such as
	// "%" or "*" that sanitize_textarea_field() would alter, so only invalid
	// UTF-8 and NUL bytes are removed.
	$content = isset( $_POST['content'] ) ? wp_unslash( $_POST['content'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	$content = str_replace( "\0", '', wp_check_invalid_utf8( (string) $content, true ) );

	shawesome_vrt_set_db_content( (int) $site->blog_id, $content );

	wp_safe_redirect(
		shawesome_vrt_page_url(
			array(
				'site'    => $site->blog_id,
				'updated' => 'site',
			)
		)
	);
	exit;
}
add_action( 'network_admin_edit_shawesome_vrt_save_site', 'shawesome_vrt_save_site' );
add_action( 'admin_post_shawesome_vrt_save_site', 'shawesome_vrt_save_site' );

/**
 * Copy a site's file into the database (posted to network/edit.php or
 * admin-post.php?action=shawesome_vrt_import).
 */
function shawesome_vrt_import_file() {
	shawesome_vrt_check_site_cap();
	$site = shawesome_vrt_posted_site();
	check_admin_referer( 'shawesome_vrt_import_' . $site->blog_id );

	$file    = shawesome_vrt_find_file( $site->domain );
	$content = null === $file ? null : shawesome_vrt_read_file( $file );

	if ( null !== $content ) {
		shawesome_vrt_set_db_content( (int) $site->blog_id, $content );
	}

	wp_safe_redirect(
		shawesome_vrt_page_url(
			array(
				'site'    => $site->blog_id,
				'updated' => null === $content ? 'import-failed' : 'imported',
			)
		)
	);
	exit;
}
add_action( 'network_admin_edit_shawesome_vrt_import', 'shawesome_vrt_import_file' );
add_action( 'admin_post_shawesome_vrt_import', 'shawesome_vrt_import_file' );

/**
 * Render the editor of the current site in its admin.
 */
function shawesome_vrt_render_site_page() {
	if ( ! shawesome_vrt_can_edit_site() ) {
		return;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
	$updated = isset( $_GET['updated'] ) ? sanitize_key( wp_unslash( $_GET['updated'] ) ) : '';

	shawesome_vrt_render_editor( get_site(), $updated );
}

/**
 * Render the settings page or, with ?site=ID, the editor of one site.
 */
function shawesome_vrt_render_page() {
	if ( ! current_user_can( 'manage_network_options' ) ) {
		return;
	}

	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- display only.
	$site    = isset( $_GET['site'] ) ? get_site( absint( $_GET['site'] ) ) : null;
	$updated = isset( $_GET['updated'] ) ? sanitize_key( wp_unslash( $_GET['updated'] ) ) : '';
	// phpcs:enable

	if ( $site ) {
		shawesome_vrt_render_editor( $site, $updated );
	} else {
		shawesome_vrt_render_overview( $updated );
	}
}

/**
 * Render a success or error notice.
 *
 * @param string $message Message.
 * @param string $type    'success', 'warning' or 'error'.
 */
function shawesome_vrt_notice( $message, $type = 'success' ) {
	printf( '<div class="notice notice-%s is-dismissible"><p>%s</p></div>', esc_attr( $type ), wp_kses( $message, array( 'code' => array() ) ) );
}

/**
 * Render the editor of one site, in the network admin or the site's admin.
 * Site administrators can do the same as super admins here, but do not see
 * the path of the file on the server.
 *
 * @param WP_Site $site    Site.
 * @param string  $updated Result of the last action.
 */
function shawesome_vrt_render_editor( $site, $updated ) {
	$resolved   = shawesome_vrt_resolve( $site );
	$db_content = shawesome_vrt_get_db_content( (int) $site->blog_id );
	$has_file   = 'file' === $resolved['source'];
	$network    = is_network_admin();
	?>
	<div class="wrap">
		<h1>
			<?php
			/* translators: %s: site domain */
			printf( esc_html__( 'robots.txt for %s', 'shawesome-virtual-robots-txt' ), esc_html( $site->domain ) );
			?>
		</h1>
		<?php if ( $network ) : ?>
			<p><a href="<?php echo esc_url( shawesome_vrt_page_url() ); ?>">&larr; <?php esc_html_e( 'All sites', 'shawesome-virtual-robots-txt' ); ?></a></p>
		<?php endif; ?>

		<?php
		if ( 'site' === $updated ) {
			shawesome_vrt_notice( __( 'robots.txt saved.', 'shawesome-virtual-robots-txt' ) );
		} elseif ( 'imported' === $updated ) {
			shawesome_vrt_notice( __( 'File copied into the database. Delete the file to make the database content active.', 'shawesome-virtual-robots-txt' ) );
		} elseif ( 'import-failed' === $updated ) {
			shawesome_vrt_notice( __( 'The file could not be read.', 'shawesome-virtual-robots-txt' ), 'error' );
		}
		?>

		<?php if ( $has_file ) : ?>
			<div class="notice notice-info inline"><p>
				<?php
				printf(
					/* translators: %s: file path */
					esc_html__( 'This site is overridden by the file %s. The file is delivered; the database content below is only used once the file is removed.', 'shawesome-virtual-robots-txt' ),
					'<code>' . esc_html( $network ? $resolved['file'] : basename( $resolved['file'] ) ) . '</code>'
				);
				?>
			</p></div>

			<h2><?php esc_html_e( 'File content (read-only)', 'shawesome-virtual-robots-txt' ); ?></h2>
			<textarea class="large-text code" rows="12" readonly><?php echo esc_textarea( $resolved['content'] ); ?></textarea>

			<form method="post" action="<?php echo esc_url( shawesome_vrt_action_url( 'import' ) ); ?>">
				<?php wp_nonce_field( 'shawesome_vrt_import_' . $site->blog_id ); ?>
				<input type="hidden" name="site_id" value="<?php echo esc_attr( $site->blog_id ); ?>">
				<?php submit_button( __( 'Copy file into database', 'shawesome-virtual-robots-txt' ), 'secondary', 'submit', false ); ?>
			</form>
		<?php endif; ?>

		<h2><?php esc_html_e( 'Database content', 'shawesome-virtual-robots-txt' ); ?></h2>
		<form method="post" action="<?php echo esc_url( shawesome_vrt_action_url( 'save_site' ) ); ?>">
			<?php wp_nonce_field( 'shawesome_vrt_save_site_' . $site->blog_id ); ?>
			<input type="hidden" name="site_id" value="<?php echo esc_attr( $site->blog_id ); ?>">
			<textarea name="content" class="large-text code" rows="16" placeholder="User-agent: *&#10;Disallow: /wp-admin/&#10;Allow: /wp-admin/admin-ajax.php&#10;&#10;Sitemap: <?php echo esc_attr( get_home_url( $site->blog_id, 'wp-sitemap.xml' ) ); ?>"><?php echo esc_textarea( $db_content ); ?></textarea>
			<p class="description"><?php esc_html_e( 'Leave empty to use the WordPress default (including output from SEO plugins).', 'shawesome-virtual-robots-txt' ); ?></p>
			<?php submit_button(); ?>
		</form>

		<p><a href="<?php echo esc_url( get_home_url( $site->blog_id, 'robots.txt' ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Open live robots.txt', 'shawesome-virtual-robots-txt' ); ?></a></p>
	</div>
	<?php
}

/**
 * Render the settings and the overview of all sites.
 *
 * @param string $updated Result of the last action.
 */
function shawesome_vrt_render_overview( $updated ) {
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

		<?php
		if ( 'settings' === $updated ) {
			shawesome_vrt_notice( __( 'Settings saved.', 'shawesome-virtual-robots-txt' ) );
		}

		if ( ! shawesome_vrt_is_supported() ) {
			shawesome_vrt_notice( __( 'This network uses subdirectories. Per-site robots.txt files only work for networks with their own (sub)domains, so the plugin does nothing here.', 'shawesome-virtual-robots-txt' ), 'error' );
		}

		if ( file_exists( ABSPATH . 'robots.txt' ) ) {
			shawesome_vrt_notice(
				sprintf(
					/* translators: %s: path of the physical robots.txt file */
					esc_html__( 'A physical robots.txt exists at %s. The web server delivers it directly, so WordPress and this plugin are never asked. Delete or rename it to use the virtual robots.txt.', 'shawesome-virtual-robots-txt' ),
					'<code>' . esc_html( ABSPATH . 'robots.txt' ) . '</code>'
				),
				'error'
			);
		}

		if ( $any_seo && 'base' === $settings['mode'] ) {
			shawesome_vrt_notice( __( 'An SEO plugin is active on at least one site. In "Base" mode, Yoast SEO appends its rules and sitemap to the robots.txt, and All in One SEO rebuilds it: comments and Sitemap lines are dropped. Use "Exact" mode if the content should be delivered as is.', 'shawesome-virtual-robots-txt' ), 'warning' );
		}
		?>

		<form method="post" action="<?php echo esc_url( network_admin_url( 'edit.php?action=shawesome_vrt_save' ) ); ?>">
			<?php wp_nonce_field( 'shawesome_vrt_save' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Mode', 'shawesome-virtual-robots-txt' ); ?></th>
					<td>
						<fieldset>
							<label>
								<input type="radio" name="mode" value="base" <?php checked( $settings['mode'], 'base' ); ?>>
								<strong><?php esc_html_e( 'Base', 'shawesome-virtual-robots-txt' ); ?></strong> –
								<?php esc_html_e( 'the content replaces the WordPress default. SEO plugins may add their rules and sitemap; duplicate Sitemap lines are removed.', 'shawesome-virtual-robots-txt' ); ?>
							</label><br>
							<label>
								<input type="radio" name="mode" value="exact" <?php checked( $settings['mode'], 'exact' ); ?>>
								<strong><?php esc_html_e( 'Exact', 'shawesome-virtual-robots-txt' ); ?></strong> –
								<?php esc_html_e( 'the content is delivered as is. SEO plugins (Yoast SEO, All in One SEO …) cannot change it.', 'shawesome-virtual-robots-txt' ); ?>
							</label>
						</fieldset>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Search engine visibility', 'shawesome-virtual-robots-txt' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="respect_public" value="1" <?php checked( $settings['respect_public'] ); ?>>
							<?php esc_html_e( 'Ignore the custom robots.txt on sites set to "Discourage search engines from indexing this site" (WordPress keeps "Disallow: /").', 'shawesome-virtual-robots-txt' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Site administrators', 'shawesome-virtual-robots-txt' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="site_admins" value="1" <?php checked( $settings['site_admins'] ); ?>>
							<?php esc_html_e( 'Let site administrators edit the robots.txt of their site in the site\'s admin, with the same rights as super admins.', 'shawesome-virtual-robots-txt' ); ?>
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
				esc_html__( 'Edit the robots.txt of a site here. Optionally, a file in %s overrides the database content, e.g. for deployments. The hyphenated file name takes precedence; the name with dots is an unambiguous alternative.', 'shawesome-virtual-robots-txt' ),
				'<code>' . esc_html( $dir ) . '</code>'
			);
			?>
		</p>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Site', 'shawesome-virtual-robots-txt' ); ?></th>
					<th><?php esc_html_e( 'Source', 'shawesome-virtual-robots-txt' ); ?></th>
					<th><?php esc_html_e( 'Override file name', 'shawesome-virtual-robots-txt' ); ?></th>
					<th><?php esc_html_e( 'SEO plugins', 'shawesome-virtual-robots-txt' ); ?></th>
					<th><?php esc_html_e( 'robots.txt', 'shawesome-virtual-robots-txt' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $sites as $site ) : ?>
					<?php
					$candidates = array_map( 'basename', shawesome_vrt_candidate_files( $site->domain ) );
					$resolved   = shawesome_vrt_resolve( $site );
					$is_public  = '0' !== (string) get_blog_option( $site->blog_id, 'blog_public' );
					?>
					<tr>
						<td>
							<strong><a href="<?php echo esc_url( shawesome_vrt_page_url( array( 'site' => $site->blog_id ) ) ); ?>"><?php echo esc_html( $site->domain ); ?></a></strong>
							<div class="row-actions visible"><a href="<?php echo esc_url( shawesome_vrt_page_url( array( 'site' => $site->blog_id ) ) ); ?>"><?php esc_html_e( 'Edit', 'shawesome-virtual-robots-txt' ); ?></a></div>
						</td>
						<td>
							<?php if ( null === $resolved['source'] ) : ?>
								<?php esc_html_e( 'WordPress default', 'shawesome-virtual-robots-txt' ); ?>
							<?php else : ?>
								<?php if ( ! $is_public && $settings['respect_public'] ) : ?>
									<?php esc_html_e( 'Ignored – site hidden from search engines', 'shawesome-virtual-robots-txt' ); ?>
								<?php else : ?>
									<span style="color:#008a20;">&#10003;</span>
									<?php
									if ( 'file' === $resolved['source'] ) {
										/* translators: %s: file name */
										printf( esc_html__( 'File: %s', 'shawesome-virtual-robots-txt' ), '<code>' . esc_html( basename( $resolved['file'] ) ) . '</code>' );
									} else {
										esc_html_e( 'Database', 'shawesome-virtual-robots-txt' );
									}
									?>
								<?php endif; ?>
							<?php endif; ?>
						</td>
						<td><code><?php echo esc_html( $candidates[0] ); ?></code><?php if ( isset( $candidates[1] ) ) : ?><br><code><?php echo esc_html( $candidates[1] ); ?></code><?php endif; ?></td>
						<td><?php echo $seo_sites[ $site->blog_id ] ? esc_html( implode( ', ', $seo_sites[ $site->blog_id ] ) ) : '–'; ?></td>
						<td><a href="<?php echo esc_url( get_home_url( $site->blog_id, 'robots.txt' ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Open', 'shawesome-virtual-robots-txt' ); ?></a></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php
}
