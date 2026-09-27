# PathGuard Redirects

A lightweight WordPress plugin that lets administrators block specific relative URLs and redirect visitors to a custom destination or a 404 page.

## Features

- **Block any relative path** — enter paths one per line (e.g. `/secret-page/`, `/members-only/`)
- **Two redirect actions** — 302 redirect to a custom URL, or serve the theme's native 404 page
- **Exclude Admins** — administrators are bypassed by default; can be unchecked to restrict them too
- **Settings link** — one-click access from the WordPress Plugins list page
- **Clean uninstall** — settings survive deactivation and are deleted from the database when the plugin is deleted
- **Path normalisation** — `/secret-page`, `/secret-page/` and `/Secret-Page/` match the same rule; full URLs are converted to paths on save
- **URL-encoding bypass protection** — `/%73ecret-page/` is decoded and matched correctly; non-ASCII paths work
- **Page cache purge** — supported caches are purged when rules change

## Requirements

| Requirement       | Version  |
|-------------------|----------|
| WordPress         | >= 5.8   |
| PHP               | >= 7.4   |

## Installation

1. Clone or download this repository into `/wp-content/plugins/pathguard-redirects/`.
2. Activate the plugin from **Plugins → Installed Plugins**.
3. Navigate to **Settings → PathGuard Redirects** (or click the **Settings** link on the Plugins page).
4. Add the paths to block, choose a redirect action, and click **Save Settings**.

## Directory Structure

```
pathguard-redirects/
├── pathguard-redirects.php  # Bootstrap: plugin header, constants, activation hook
├── uninstall.php            # Deletes plugin options when the plugin is deleted
├── includes/
│   ├── AdminSettings.php    # Admin menu, save handler, settings page renderer
│   └── URLB_Blocker.php     # Frontend redirect logic (template_redirect hook)
├── templates/
│   └── settings-page.php    # Settings page HTML template
├── README.md
└── readme.txt               # WordPress.org submission readme
```

## Settings

| Setting | Description |
|---|---|
| **Blocked URLs** | Newline-separated list of paths to block, relative to the site home. Matching ignores case, trailing slash and query string. |
| **Redirect Action** | `Custom URL` — 302 redirect to a URL you specify. `Not Found (404 page)` — serve the theme 404 template inline with a 404 status header. |
| **Redirect Destination URL** | Full URL (any domain) or a path relative to the site home. Falls back to 404 if left blank or if it is itself blocked. |
| **Exclude Admins** | When checked (default), users with `manage_options` can always access blocked URLs. Uncheck to restrict admins too. |

## Security

| Measure | Implementation |
|---|---|
| CSRF protection | `wp_nonce_field` + `check_admin_referer` on every save |
| Authorisation | `current_user_can('manage_options')` checked before nonce |
| Input sanitisation | `sanitize_text_field` (per normalised path), `esc_url_raw`, `sanitize_key` |
| Output escaping | `esc_textarea`, `esc_attr`, `esc_html_e`, `selected`, `checked` |
| Redirect destination | Admin-configured only; `wp_redirect` is used so external destinations work |
| URL-encoding bypass | `rawurldecode()` applied to request path before comparison |
| Direct file access | `defined('ABSPATH') \|\| exit` in every PHP file |

## Limitations

- Blocking a URL does not make its content private. Content may still be reachable via the REST API, feeds, search and sitemaps. Use Private or password-protected posts for that.
- Caches outside WordPress (Varnish, Nginx FastCGI cache, CDNs) are not purged automatically. Other page-cache plugins can hook `pathguard_redirects_rules_updated` to purge.

## Changelog

### 1.1.0
- Fix: Custom URL redirects to external domains no longer send visitors to wp-admin
- Fix: Percent-encoded (`/%73ecret-page/`) and non-ASCII (`/café/`) paths are now matched correctly
- Fix: 404 action no longer breaks on classic themes without a `404.php` template
- Fix: Relative redirect destinations such as `/home/` can now be saved
- Fix: Destination URLs keep their percent-encoding when saved
- Change: Settings are kept on deactivation and removed only when the plugin is deleted (`uninstall.php`)
- New: Case-insensitive path matching with subdirectory install support
- New: Full URLs and paths without a leading slash are normalised on save; duplicates are removed
- New: Relative redirect destinations are resolved against the site home
- New: Falls back to the 404 page, with a settings warning, if the destination is itself blocked
- New: Supported page caches are purged when rules change; new `pathguard_redirects_rules_updated` action
- Tweak: Settings page script is now enqueued with `wp_add_inline_script`

### 1.0.0
- Initial release
- Block relative URLs via textarea input (one per line)
- Redirect action: Custom URL (302) or 404 page
- Exclude Admins toggle, pre-enabled on activation
- Settings link on the Plugins list page
- Automatic database cleanup on deactivation
- URL-encoding bypass protection
- CSRF, capability, and sanitisation hardening

## License

[GPL-2.0-or-later](https://www.gnu.org/licenses/gpl-2.0.html)
