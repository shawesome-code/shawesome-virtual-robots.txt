# Shawesome Virtual Robots.txt – Per-Site robots.txt for Multisite

An individual robots.txt for every site of a domain-based WordPress multisite network – maintained as plain text files.

By [Shawesome](https://shawesome.de/) · Open source under the [GNU General Public License v2 or later](LICENSE).

## How it works

For each request to `/robots.txt`, the plugin looks for a file in `wp-content/virtual-robots-txt/` whose name is derived from the site's domain (dots become hyphens):

| Domain             | File                    |
| ------------------ | ----------------------- |
| `www.example.com`  | `www-example-com.txt`   |
| `shop.example.com` | `shop-example-com.txt`  |

If the file exists, its contents are served. Otherwise WordPress' default robots.txt is kept. The domain with dots (`www.example.com.txt`) is accepted as an unambiguous alternative.

- Only active on subdomain/domain-based multisite
- Requires that no physical `robots.txt` exists in the web root
- Sites set to "Discourage search engines" keep `Disallow: /` (can be switched off)

## Yoast SEO and All in One SEO

Neither plugin can serve a separate robots.txt file per domain in a multisite network the way this plugin does, but both change the robots.txt output: Yoast SEO appends its rules and sitemap, All in One SEO rebuilds the rules and drops comments and Sitemap lines. Under *Network Admin → Settings → Virtual Robots.txt* you choose the mode:

- **Exact** (default) – the file is delivered as is, after all SEO plugins have run
- **Base** – the file replaces the WordPress default, SEO plugins may add to it; duplicate Sitemap lines are removed

The same page lists all sites with their expected file name, status, detected SEO plugins and a link to the live robots.txt.

## For developers

- Constant `SHAWESOME_VRT_DIR` / filter `shawesome_vrt_dir` – directory
- Filter `shawesome_vrt_candidate_files` – candidate file paths per domain
- Filter `shawesome_vrt_priority` – priority of the `robots_txt` filter

## Requirements

- WordPress 5.1+ (multisite)
- PHP 7.4+

## Installation

Copy the folder `shawesome-virtual-robots-txt/` to `wp-content/plugins/` (or upload it as a ZIP) and network-activate the plugin. The directory `wp-content/virtual-robots-txt/` is created on activation. See [`readme.txt`](shawesome-virtual-robots-txt/readme.txt) for FAQ and changelog.

## License

Copyright © Shawesome.

This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version. See [LICENSE](LICENSE).
