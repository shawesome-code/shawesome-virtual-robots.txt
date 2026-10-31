<?php
/**
 * The "shawesome" menu in the network admin and the admin of each site, with
 * a start page: the active shawesome plugins and recommendations from the
 * WordPress plugin directory.
 *
 * Loaded by loader.php, newest copy only. Plugins add their page with
 * add_submenu_page( 'shawesome', ... ), on 'network_admin_menu' for the
 * network admin and on 'admin_menu' for a site. In a network, the menu is not
 * shown without such a page.
 *
 * This code belongs to no single plugin, so it has no text domain: the texts
 * are the ones WordPress translates itself.
 *
 * @package Shawesome_Admin
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.WP.I18n.MissingArgDomain -- texts of WordPress itself.

/**
 * Capability for the start page in the current admin.
 *
 * @return string
 */
function shawesome_admin_capability() {
	return is_network_admin() ? 'manage_network_options' : 'manage_options';
}

/**
 * The Bildmarke as a data URI.
 *
 * @param string $color  Color of the three fixed modules.
 * @param string $accent Color of the offset module.
 * @return string
 */
function shawesome_admin_icon( $color, $accent ) {
	$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"><g fill="' . $color . '"><rect x="5" y="5" width="17" height="17" rx="4.5"/><rect x="26" y="5" width="17" height="17" rx="4.5"/><rect x="5" y="26" width="17" height="17" rx="4.5"/></g><rect x="28.5" y="28.5" width="17" height="17" rx="4.5" fill="' . $accent . '"/></svg>';

	return 'data:image/svg+xml;base64,' . base64_encode( $svg ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
}

/**
 * Add the menu after Settings, before the plugins add their pages at the
 * default priority. WordPress repaints the icon to match the admin color
 * scheme, so it is in one color here.
 */
function shawesome_admin_menu() {
	$hook = add_menu_page(
		'shawesome',
		'shawesome',
		shawesome_admin_capability(),
		'shawesome',
		'shawesome_admin_render_page',
		shawesome_admin_icon( '#a7aaad', '#a7aaad' ),
		is_network_admin() ? 30 : 81
	);

	add_submenu_page( 'shawesome', 'shawesome', __( 'Overview' ), shawesome_admin_capability(), 'shawesome' );

	add_action( 'load-' . $hook, 'shawesome_admin_load_page' );
}
add_action( 'network_admin_menu', 'shawesome_admin_menu', 9 );
add_action( 'admin_menu', 'shawesome_admin_menu', 9 );

/**
 * Remove the menu in a network if no plugin added a page to it: network
 * plugins may have no page in the admin of a site. Outside of a network, every
 * plugin bundling this code is active here and the recommendations are useful.
 */
function shawesome_admin_remove_empty_menu() {
	global $submenu;

	if ( is_multisite() && ( empty( $submenu['shawesome'] ) || 1 === count( $submenu['shawesome'] ) ) ) {
		remove_menu_page( 'shawesome' );
		unset( $submenu['shawesome'] );
	}
}
add_action( 'network_admin_menu', 'shawesome_admin_remove_empty_menu', 99 );
add_action( 'admin_menu', 'shawesome_admin_remove_empty_menu', 99 );

/**
 * Prepare the start page: the install buttons and the details modal of the
 * recommended plugins need WordPress' plugin installer.
 */
function shawesome_admin_load_page() {
	require_once ABSPATH . 'wp-admin/includes/plugin-install.php';

	add_thickbox();
	wp_enqueue_script( 'plugin-install' );
}

/**
 * Plugins by shawesome that are active in the current admin: network-activated
 * ones in the network admin, all active ones in the admin of a site.
 *
 * @return array<string, array> Plugin file => plugin data, plus 'Page', the
 *                              menu slug of its settings page or ''.
 */
function shawesome_admin_active_plugins() {
	global $submenu;

	/**
	 * Filter the settings pages of the plugins on the start page.
	 *
	 * @param array<string, string> $pages Plugin file => menu slug.
	 */
	$pages   = (array) apply_filters( 'shawesome_admin_pages', array() );
	$slugs   = isset( $submenu['shawesome'] ) ? wp_list_pluck( $submenu['shawesome'], 2 ) : array();
	$plugins = array();

	foreach ( get_plugins() as $file => $data ) {
		$active = is_network_admin() ? is_plugin_active_for_network( $file ) : is_plugin_active( $file );

		if ( ! $active || 0 !== strcasecmp( $data['Author'], 'shawesome' ) ) {
			continue;
		}

		// Unlike get_plugins(), this translates the name and the description.
		$plugins[ $file ]         = get_plugin_data( WP_PLUGIN_DIR . '/' . $file, false );
		$plugins[ $file ]['Page'] = isset( $pages[ $file ] ) && in_array( $pages[ $file ], $slugs, true ) ? $pages[ $file ] : '';
	}

	return $plugins;
}

/**
 * Plugins by shawesome in the WordPress plugin directory, except for the given
 * ones. Empty if the directory cannot be reached, for users who cannot install
 * plugins, and in the admin of a site in a network, where plugins cannot be
 * installed.
 *
 * @param string[] $exclude Plugin slugs.
 * @return array[] Plugin data of the directory.
 */
function shawesome_admin_recommended_plugins( $exclude ) {
	if ( ! current_user_can( 'install_plugins' ) || ( is_multisite() && ! is_network_admin() ) ) {
		return array();
	}

	$key     = 'shawesome_admin_recommended_' . get_user_locale();
	$plugins = get_site_transient( $key );

	if ( ! is_array( $plugins ) ) {
		$response = plugins_api(
			'query_plugins',
			array(
				'author'   => 'shawesome',
				'per_page' => 30,
				'fields'   => array( 'icons' => true ),
			)
		);
		$failed   = is_wp_error( $response ) || empty( $response->plugins );
		$plugins  = $failed ? array() : (array) $response->plugins;

		set_site_transient( $key, $plugins, $failed ? HOUR_IN_SECONDS : DAY_IN_SECONDS );
	}

	$recommended = array();
	foreach ( $plugins as $plugin ) {
		$plugin = (array) $plugin;

		if ( ! in_array( $plugin['slug'], $exclude, true ) ) {
			$recommended[] = $plugin;
		}
	}

	return $recommended;
}

/**
 * Render a plugin card, with the markup and styles of Plugins → Add New.
 *
 * @param string   $title       Name, escaped.
 * @param string   $icon        Icon URL or data URI.
 * @param string   $description Description, plain text.
 * @param string[] $actions     Action links and buttons, escaped.
 * @param string   $meta        Line below the description, plain text.
 */
function shawesome_admin_render_card( $title, $icon, $description, $actions, $meta = '' ) {
	// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by the callers.
	?>
	<div class="plugin-card">
		<div class="plugin-card-top">
			<div class="name column-name">
				<h3><?php echo $title; ?><img src="<?php echo esc_attr( $icon ); ?>" class="plugin-icon" alt=""></h3>
			</div>
			<?php if ( $actions ) : ?>
				<div class="action-links">
					<ul class="plugin-action-buttons"><li><?php echo implode( '</li><li>', $actions ); ?></li></ul>
				</div>
			<?php endif; ?>
			<div class="desc column-description">
				<p><?php echo esc_html( $description ); ?></p>
				<?php if ( '' !== $meta ) : ?>
					<p class="authors"><?php echo esc_html( $meta ); ?></p>
				<?php endif; ?>
			</div>
		</div>
	</div>
	<?php
	// phpcs:enable
}

/**
 * Render the card of an active plugin.
 *
 * @param array $plugin Plugin data.
 */
function shawesome_admin_render_active_card( $plugin ) {
	$actions = array();

	if ( '' !== $plugin['Page'] ) {
		$actions[] = sprintf(
			'<a class="button button-compact" href="%s">%s</a>',
			esc_url( self_admin_url( 'admin.php?page=' . $plugin['Page'] ) ),
			esc_html__( 'Settings' )
		);
	}

	shawesome_admin_render_card(
		esc_html( $plugin['Name'] ),
		shawesome_admin_icon( '#1f2328', '#0366d6' ),
		$plugin['Description'],
		$actions,
		/* translators: %s: Plugin version number. */
		sprintf( __( 'Version %s' ), $plugin['Version'] )
	);
}

/**
 * Render the card of a recommended plugin.
 *
 * @param array $plugin Plugin data of the directory.
 */
function shawesome_admin_render_recommended_card( $plugin ) {
	$name    = wp_strip_all_tags( $plugin['name'] );
	$details = self_admin_url( 'plugin-install.php?tab=plugin-information&plugin=' . $plugin['slug'] . '&TB_iframe=true&width=600&height=550' );

	if ( ! empty( $plugin['icons']['svg'] ) ) {
		$icon = $plugin['icons']['svg'];
	} elseif ( ! empty( $plugin['icons']['2x'] ) ) {
		$icon = $plugin['icons']['2x'];
	} elseif ( ! empty( $plugin['icons']['1x'] ) ) {
		$icon = $plugin['icons']['1x'];
	} else {
		$icon = isset( $plugin['icons']['default'] ) ? $plugin['icons']['default'] : '';
	}

	$actions = array(
		wp_get_plugin_action_button(
			$name,
			$plugin,
			is_php_version_compatible( isset( $plugin['requires_php'] ) ? $plugin['requires_php'] : null ),
			is_wp_version_compatible( isset( $plugin['requires'] ) ? $plugin['requires'] : null )
		),
		sprintf(
			'<a href="%s" class="thickbox open-plugin-details-modal" aria-label="%s" data-title="%s">%s</a>',
			esc_url( $details ),
			/* translators: %s: Plugin name and version. */
			esc_attr( sprintf( __( 'More information about %s' ), $name ) ),
			esc_attr( $name ),
			esc_html__( 'More Details' )
		),
	);

	shawesome_admin_render_card(
		sprintf( '<a href="%s" class="thickbox open-plugin-details-modal">%s</a>', esc_url( $details ), esc_html( $name ) ),
		esc_url( $icon ),
		wp_strip_all_tags( $plugin['short_description'] ),
		array_filter( $actions )
	);
}

/**
 * Render the start page.
 */
function shawesome_admin_render_page() {
	if ( ! current_user_can( shawesome_admin_capability() ) ) {
		return;
	}

	$active      = shawesome_admin_active_plugins();
	$recommended = shawesome_admin_recommended_plugins( array_map( 'dirname', array_keys( $active ) ) );
	?>
	<div class="wrap">
		<h1>shawesome</h1>

		<?php if ( $active ) : ?>
			<h2><?php echo esc_html_x( 'Active', 'plugin' ); ?></h2>
			<div style="display:flex;flex-wrap:wrap;gap:16px;">
				<?php
				foreach ( $active as $plugin ) {
					shawesome_admin_render_active_card( $plugin );
				}
				?>
			</div>
		<?php endif; ?>

		<?php if ( $recommended ) : ?>
			<h2><?php echo esc_html_x( 'Recommended', 'Plugin Installer' ); ?></h2>
			<div style="display:flex;flex-wrap:wrap;gap:16px;">
				<?php
				foreach ( $recommended as $plugin ) {
					shawesome_admin_render_recommended_card( $plugin );
				}
				?>
			</div>
		<?php endif; ?>
	</div>
	<?php
}
