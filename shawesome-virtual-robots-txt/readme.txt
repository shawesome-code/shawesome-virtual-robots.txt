=== Shawesome Virtual Robots.txt – Per-Site robots.txt for Multisite ===
Contributors: shawesome
Tags: robots.txt, multisite, seo, network, crawler
Requires at least: 5.1
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Network: true

An individual robots.txt for every site of a domain-based multisite network – maintained as plain text files.

== Description ==

WordPress generates one virtual robots.txt for every site. In a multisite network you often need different rules per domain – e.g. to block a staging domain or to point each site to its own sitemap.

Shawesome Virtual Robots.txt serves the contents of a plain text file from `/wp-content/virtual-robots-txt/` for each site, if a matching file exists. The file name is derived from the site's domain, with dots replaced by hyphens:

* `www.example.com` → `www-example-com.txt`
* `shop.example.com` → `shop-example-com.txt`

Sites without a file keep WordPress' default robots.txt (including output from other plugins).

**Notes**

* Only active on subdomain/domain-based multisite installations (`SUBDOMAIN_INSTALL`). Single sites and path-based networks are left untouched.
* Only works when no physical `robots.txt` file exists in the web root – otherwise the web server delivers that file and WordPress is never asked.
* No settings page, no database entries.

== Installation ==

1. Upload the folder `shawesome-virtual-robots-txt` to `/wp-content/plugins/` (or upload the ZIP via *Network Admin → Plugins → Add New*).
2. Network-activate the plugin. The directory `/wp-content/virtual-robots-txt/` is created automatically.
3. Place one text file per domain in that directory, e.g. `www-example-com.txt`.
4. Open `https://www.example.com/robots.txt` to check the result.

== Frequently Asked Questions ==

= My file is not delivered. =

Check that there is no physical `robots.txt` in the web root, that the file name matches the site's domain exactly as configured in *Network Admin → Sites* (dots replaced by hyphens) and that the file is readable by the web server.

= Does it work for path-based networks (example.com/site-a/)? =

No. robots.txt is always served from the root of a host, so per-site files only make sense for networks with their own (sub)domains.

== Changelog ==

= 1.1.0 =
* Published as Shawesome Virtual Robots.txt under GPLv2 or later.
* Only the current site is looked up (previously all sites were iterated, which missed sites beyond the first 100).
* Host comparison is case-insensitive.

= 1.0.0 =
* Initial release.
