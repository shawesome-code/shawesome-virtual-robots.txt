# Shawesome Virtual Robots.txt – Per-Site robots.txt for Multisite

An individual robots.txt for every site of a domain-based WordPress multisite network – maintained as plain text files.

By [Shawesome](https://shawesome.de/) · Open source under the [GNU General Public License v2 or later](LICENSE).

## How it works

For each request to `/robots.txt`, the plugin looks for a file in `wp-content/virtual-robots-txt/` whose name is derived from the site's domain (dots become hyphens):

| Domain             | File                    |
| ------------------ | ----------------------- |
| `www.example.com`  | `www-example-com.txt`   |
| `shop.example.com` | `shop-example-com.txt`  |

If the file exists, its contents are served. Otherwise WordPress' default robots.txt is kept.

- Only active on subdomain/domain-based multisite (`SUBDOMAIN_INSTALL`)
- Requires that no physical `robots.txt` exists in the web root
- No settings page, no database entries

## Requirements

- WordPress 5.1+ (multisite)
- PHP 7.4+

## Installation

Copy the folder `shawesome-virtual-robots-txt/` to `wp-content/plugins/` (or upload it as a ZIP) and network-activate the plugin. The directory `wp-content/virtual-robots-txt/` is created on activation. See [`readme.txt`](shawesome-virtual-robots-txt/readme.txt) for FAQ and changelog.

## License

Copyright © Shawesome.

This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version. See [LICENSE](LICENSE).
