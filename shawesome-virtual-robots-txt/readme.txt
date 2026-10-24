=== Shawesome Virtual Robots.txt – Per-Site robots.txt for Multisite ===
Contributors: shawesome
Tags: robots.txt, multisite, seo, network, crawler
Requires at least: 5.1
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Network: true

An individual robots.txt for every site of a domain-based multisite network – maintained as plain text files.

== Description ==

WordPress generates one virtual robots.txt for every site. In a multisite network you often need different rules per domain – e.g. to block a staging domain or to point each site to its own sitemap.

Shawesome Virtual Robots.txt serves the contents of a plain text file from `/wp-content/virtual-robots-txt/` for each site, if a matching file exists. The file name is derived from the site's domain, with dots replaced by hyphens:

* `www.example.com` → `www-example-com.txt`
* `shop.example.com` → `shop-example-com.txt`

Sites without a file keep WordPress' default robots.txt (including output from other plugins). As an unambiguous alternative, the domain with dots is accepted as well (`www.example.com.txt`); the hyphenated name takes precedence.

**Works with Yoast SEO and All in One SEO**

Neither Yoast SEO nor All in One SEO can serve a separate robots.txt file per domain in a multisite network the way this plugin does. Both change the robots.txt output, though. Under *Network Admin → Settings → Virtual Robots.txt* you choose how the file is combined with them:

* **Exact** (default) – the file is delivered as is, after all SEO plugins have run.
* **Base** – the file replaces the WordPress default; Yoast SEO appends its sitemap, All in One SEO rebuilds the rules (comments and Sitemap lines from the file are dropped). Duplicate Sitemap lines are removed.

**Network overview**

The settings page lists all sites with their expected file name, whether a file is active, which SEO plugins run on the site and a link to the live robots.txt. It warns about a physical `robots.txt` in the web root, a missing directory and SEO plugins that would change the file.

**Search engine visibility**

Sites set to "Discourage search engines from indexing this site" keep WordPress' `Disallow: /` instead of the file, so a staging site does not become indexable by accident. This can be switched off.

**Notes**

* Only active on subdomain/domain-based multisite installations. Single sites and path-based networks are left untouched.
* Only works when no physical `robots.txt` file exists in the web root – otherwise the web server delivers that file and WordPress is never asked.
* A UTF-8 BOM is stripped and line endings are normalized.

**For developers**

* Constant `SHAWESOME_VRT_DIR` or filter `shawesome_vrt_dir` – change the directory.
* Filter `shawesome_vrt_candidate_files` – change the candidate file paths for a domain.
* Filter `shawesome_vrt_priority` – change the priority of the `robots_txt` filter.
* All callbacks are named functions and can be removed with `remove_filter()`.

== Installation ==

1. Upload the folder `shawesome-virtual-robots-txt` to `/wp-content/plugins/` (or upload the ZIP via *Network Admin → Plugins → Add New*).
2. Network-activate the plugin. The directory `/wp-content/virtual-robots-txt/` is created automatically.
3. Place one text file per domain in that directory, e.g. `www-example-com.txt`.
4. Check the result under *Network Admin → Settings → Virtual Robots.txt* or open `https://www.example.com/robots.txt`.

== Frequently Asked Questions ==

= My file is not delivered. =

The settings page under *Network Admin → Settings → Virtual Robots.txt* shows the expected file name and status for every site. Also check that there is no physical `robots.txt` in the web root, that the file is readable by the web server and that the site is not set to "Discourage search engines".

= Yoast SEO or All in One SEO changes my file. =

Select the mode "Exact" in the settings. The file is then delivered after all SEO plugins have run.

= Does it work for path-based networks (example.com/site-a/)? =

No. robots.txt is always served from the root of a host, so per-site files only make sense for networks with their own (sub)domains.

== Changelog ==

= 1.2.0 =
* New: settings page in the network admin with an overview of all sites, file status, detected SEO plugins and warnings (physical robots.txt, missing directory).
* New: modes "Exact" (delivered after Yoast SEO / All in One SEO) and "Base" (SEO plugins may add rules; duplicate Sitemap lines are removed). Default is "Exact".
* New: sites hidden from search engines keep "Disallow: /" (can be switched off).
* New: file name with dots (www.example.com.txt) as an unambiguous alternative.
* New: constant SHAWESOME_VRT_DIR and filters shawesome_vrt_dir, shawesome_vrt_candidate_files, shawesome_vrt_priority.
* Fix: hosts with a port (example.com:8080) no longer prevent the file from being delivered.
* Fix: a UTF-8 BOM is stripped and line endings are normalized.
* Callbacks are named functions and can be removed with remove_filter().

= 1.1.0 =
* Published as Shawesome Virtual Robots.txt under GPLv2 or later.
* Only the current site is looked up (previously all sites were iterated, which missed sites beyond the first 100).
* Host comparison is case-insensitive.

= 1.0.0 =
* Initial release.
