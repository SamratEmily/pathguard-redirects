=== PathGuard Redirects ===
Contributors:      emily50
Tags:              redirect, url-blocker, access-control, security, block-pages
Requires at least: 5.8
Tested up to:      7.1
Stable tag:        1.1.0
Requires PHP:      7.4
License:           GPLv2 or later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

Block specific relative URLs on your site and redirect visitors to a custom destination or your theme's 404 page.

== Description ==

PathGuard Redirects is a lightweight, developer-friendly plugin that lets site administrators block any relative URL on their WordPress site and control exactly what happens when a visitor tries to access it.

**How it works**

Add the relative paths you want to block (one per line) and choose what should happen when someone visits them:

* **Custom URL redirect** — send visitors to any destination URL with a 302 redirect.
* **404 Not Found** — serve your theme's native 404 page with a proper HTTP 404 status header (no redirect, the URL stays the same).

**Key features**

* Block any number of relative paths (e.g. `/secret-page/`, `/members-only/`).
* Choose the redirect action per-site: custom URL or 404 page.
* **Exclude Admins** — logged-in administrators are bypassed by default so they always have access. The option can be unchecked to restrict admins too.
* One-click access via the **Settings** link on the Plugins list page.
* Settings are kept when the plugin is deactivated and removed from the database when it is deleted.
* Paths are matched with or without a trailing slash and regardless of case — `/secret-page`, `/secret-page/` and `/Secret-Page/` all match.
* URL-encoded paths are decoded before matching, preventing bypass attempts like `/%73ecret-page/`. Non-ASCII paths (e.g. `/café/`) are supported.
* Full URLs pasted into the list are converted to paths on save.
* Page caches are purged when the rules change (WP Super Cache, WP Rocket, W3 Total Cache, LiteSpeed Cache; other caches can use the `pathguard_redirects_rules_updated` action).

**Security**

* CSRF protection on every save using WordPress nonces.
* Strict capability check (`manage_options`) before processing any form data.
* All input is sanitised (`sanitize_text_field`, `esc_url_raw`, `sanitize_key`).
* All output is escaped (`esc_textarea`, `esc_attr`, `esc_html_e`).
* The redirect destination can only be set by administrators; visitors cannot influence where they are sent.

== Installation ==

1. Upload the `pathguard-redirects` folder to `/wp-content/plugins/`.
2. Activate the plugin through the **Plugins** screen in WordPress.
3. Go to **Settings → PathGuard Redirects** (or click the **Settings** link on the Plugins page).
4. Enter the relative URLs you want to block, choose a redirect action, and click **Save Settings**.

== Frequently Asked Questions ==

= What URL format should I use in the blocked URLs list? =

Enter paths relative to your site home, one per line. Example:

`/secret-page/`
`/members-only/`
`/private-area/`

If you paste a full URL, it is converted to its path when you save. Paths are matched with or without a trailing slash and regardless of case.

If WordPress is installed in a subdirectory (e.g. `https://example.com/blog/`), enter paths relative to that directory: `/secret-page/`, not `/blog/secret-page/`.

= Will administrators be blocked? =

No — by default the **Exclude Admins** option is enabled, which means logged-in users with the `manage_options` capability can always access blocked URLs. You can uncheck this option to apply blocking to administrators as well.

= What is the difference between the two redirect actions? =

* **Custom URL (302 redirect)** — the visitor's browser is redirected to the URL you specify. The blocked URL disappears from the address bar.
* **Not Found (404 page)** — the browser stays on the blocked URL but receives an HTTP 404 status and sees your theme's 404 template. No redirect occurs.

= Can I redirect to another website? =

Yes. Enter any full URL (e.g. `https://example.com/`). You can also enter a path such as `/home/`, which is resolved against your site home.

= What happens if I choose "Custom URL" but leave the destination field blank? =

The plugin falls back to serving the 404 page so the blocked URL is never accidentally left accessible. The same happens if the destination is itself a blocked URL, which would otherwise cause a redirect loop; the settings page shows a warning in that case.

= Does this plugin affect REST API or admin requests? =

No. The block logic runs on the `template_redirect` hook which only fires for standard frontend page requests. REST API, WP-CLI, and admin requests are unaffected.

= Does blocking a URL make its content private? =

No. PathGuard Redirects blocks the URL, not the content. A blocked page's content can still be reachable through the REST API (e.g. `/wp-json/wp/v2/pages`), RSS feeds, site search, and XML sitemaps. To keep content private, set the post to Private or password-protect it.

= Does it work with page caching? =

The plugin purges supported page caches when you save, so pages cached before a rule was added are not served. Caches that run outside WordPress (server-level caches such as Varnish or Nginx FastCGI cache, or a CDN) must be purged manually after changing rules.

= Will my settings be lost if I deactivate the plugin? =

No. Settings are kept on deactivation. They are removed from the database only when you delete the plugin from the Plugins screen.

= Does the plugin block query strings? =

No. Only the path portion of the URL is compared (e.g. `/secret-page/`). Query strings like `?preview=true` are ignored, which means `/secret-page/?anything=value` is still blocked by the rule `/secret-page/`.

== Screenshots ==

1. The PathGuard Redirects settings page showing the blocked URLs list, redirect action selector, and Exclude Admins option.
2. The Settings action link on the WordPress Plugins list page.

== Changelog ==

= 1.1.0 =
* Fix: Custom URL redirects to external domains no longer send visitors to wp-admin.
* Fix: Percent-encoded paths (e.g. `/%73ecret-page/`) and non-ASCII paths (e.g. `/café/`) are now matched correctly.
* Fix: 404 action no longer breaks on classic themes without a 404.php template.
* Fix: Relative redirect destinations such as `/home/` can now be saved.
* Fix: Destination URLs keep their percent-encoding when saved.
* Change: Settings are kept on deactivation and removed only when the plugin is deleted.
* New: Path matching is case-insensitive and supports WordPress installed in a subdirectory.
* New: Full URLs and paths without a leading slash are normalised on save; duplicates are removed.
* New: Relative redirect destinations are resolved against the site home.
* New: Falls back to the 404 page, with a settings warning, if the destination is itself blocked.
* New: Supported page caches are purged when rules change; new `pathguard_redirects_rules_updated` action.
* Tweak: Settings page script is now enqueued properly.

= 1.0.0 =
* Initial release.
* Block relative URLs with per-line textarea input.
* Redirect action: Custom URL (302) or 404 page.
* Exclude Admins option, pre-enabled on activation.
* Settings link on the Plugins list page.
* Automatic database cleanup on deactivation.
* URL-encoding bypass protection via `rawurldecode()`.
* CSRF, capability, and sanitisation hardening.

== Upgrade Notice ==

= 1.1.0 =
Fixes external redirects and URL-encoded path matching. Settings are now kept on deactivation. Saved rules are normalised to lowercase paths the next time you save.

= 1.0.0 =
Initial release — no upgrade steps required.
