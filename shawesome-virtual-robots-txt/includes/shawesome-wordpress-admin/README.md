# shawesome WordPress admin

Shared admin code of the shawesome WordPress plugins: the **shawesome** menu in the network admin and the admin of each site, with a start page. Plugins bundle a copy of this repository, so the menu comes with each of them and appears only once.

The start page shows:

- **Active** – the active plugins with the author `shawesome`, with name, description, version and a link to their settings page
- **Recommended** – the other plugins of the author `shawesome` in the WordPress plugin directory, with an install button; only for users who can install plugins and, in a network, only in the network admin; cached for a day

## Add to a plugin

The code lives in the plugin's repository as a [git subtree](https://git-scm.com/docs/git-subtree), so ZIPs and releases need no extra step:

```sh
git subtree add --prefix=<plugin>/includes/shawesome-wordpress-admin https://github.com/shawesome-code/shawesome-wordpress-admin.git main --squash
```

Load it in the admin and add the settings page to the menu:

```php
require_once __DIR__ . '/shawesome-wordpress-admin/loader.php';

add_submenu_page( 'shawesome', $title, $title, 'manage_network_options', 'my-plugin', 'my_plugin_render_page' );
```

Use `network_admin_menu` for a page in the network admin and `admin_menu` for a page in the admin of a site, at the default priority. The plugin's `Author` header must be `shawesome`.

In a network, the menu only appears where a plugin added a page. On a WordPress installation without a network it always appears, with the start page and its recommendations, even if the plugin has no page of its own.

Link the plugin on the start page to its settings page:

```php
function my_plugin_admin_pages( $pages ) {
	$pages['my-plugin/my-plugin.php'] = 'my-plugin';

	return $pages;
}
add_filter( 'shawesome_admin_pages', 'my_plugin_admin_pages' );
```

## Update a plugin

```sh
git subtree pull --prefix=<plugin>/includes/shawesome-wordpress-admin https://github.com/shawesome-code/shawesome-wordpress-admin.git main --squash
```

## Versions

Active plugins may bundle different versions of this code. `loader.php` makes sure that the newest one runs, so:

- Raise `$shawesome_admin_version` in `loader.php` with every change.
- Leave the rest of `loader.php` alone: its copies live on in released plugins.
- `admin.php` must keep working with plugins that were built against an older version.

## License

Copyright © shawesome.

This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version. See [LICENSE](LICENSE).
