<?php
/**
 * Blocker class: hooks into template_redirect and either redirects the visitor
 * or serves a 404 when the current request path matches a blocked URL.
 *
 * @package URLBlocker
 */

namespace URLBlocker;

defined( 'ABSPATH' ) || exit;

/**
 * Checks every frontend request against the blocked URL list and blocks matches.
 */
class URLB_Blocker {

	/**
	 * Wire up WordPress hooks.
	 */
	public function __construct() {
		\add_action( 'template_redirect', array( $this, 'maybe_redirect' ) );
	}

	/**
	 * Convert a URL or path into the canonical form used for matching.
	 *
	 * Used both when saving rules and when checking requests, so the two sides
	 * are always compared like for like:
	 * - full URLs are reduced to their path, query strings are dropped;
	 * - percent-encoding is decoded (/%73ecret-page/ → /secret-page/);
	 * - the site's home path is stripped for subdirectory installs;
	 * - the result is lowercased and always has a leading and trailing slash.
	 *
	 * @param string $url A full URL or a path.
	 * @return string Normalised path, or '' if nothing usable remains.
	 */
	public static function normalise_path( string $url ): string {
		$path = (string) \wp_parse_url( trim( $url ), PHP_URL_PATH );
		$path = rawurldecode( $path );

		if ( '' === $path ) {
			return '';
		}

		$path = '/' . ltrim( $path, '/' );

		// Rules are relative to the site home, so /blog/secret-page/ on a site
		// installed at /blog/ matches the rule /secret-page/.
		$home_path = \untrailingslashit( (string) \wp_parse_url( \home_url(), PHP_URL_PATH ) );
		if ( '' !== $home_path ) {
			if ( 0 === stripos( $path . '/', $home_path . '/' ) ) {
				$path = '/' . ltrim( substr( $path, strlen( $home_path ) ), '/' );
			}
		}

		$path = function_exists( 'mb_strtolower' ) ? mb_strtolower( $path, 'UTF-8' ) : strtolower( $path );

		return \trailingslashit( $path );
	}

	/**
	 * Return the saved block list as an array of normalised paths.
	 *
	 * @return string[]
	 */
	public static function get_blocked_paths(): array {
		$blocked_raw = (string) \get_option( 'urlb_blocked_urls', '' );

		return array_values(
			array_unique(
				array_filter(
					array_map( array( self::class, 'normalise_path' ), explode( "\n", $blocked_raw ) )
				)
			)
		);
	}

	/**
	 * Whether the given URL or path is on the block list.
	 *
	 * @param string $url A full URL or a path.
	 */
	public static function is_blocked( string $url ): bool {
		$path = self::normalise_path( $url );

		return '' !== $path && in_array( $path, self::get_blocked_paths(), true );
	}

	/**
	 * Resolve the configured redirect destination to an absolute URL.
	 *
	 * Relative paths (e.g. /home) are resolved against the site home so they
	 * behave the same way as blocked URL rules on subdirectory installs.
	 *
	 * @return string Absolute URL, or '' if none is configured.
	 */
	public static function get_redirect_destination(): string {
		$redirect_to = trim( (string) \get_option( 'urlb_redirect_url', '' ) );

		if ( '' === $redirect_to ) {
			return '';
		}

		if ( '/' === $redirect_to[0] && 0 !== strpos( $redirect_to, '//' ) ) {
			return \home_url( $redirect_to );
		}

		return $redirect_to;
	}

	/**
	 * Block the request if the current path is on the block list.
	 */
	public function maybe_redirect(): void {
		if ( '1' === \get_option( 'urlb_exclude_admins', '1' ) && \current_user_can( 'manage_options' ) ) {
			return;
		}

		// The raw value is only decoded and compared, never output or stored.
		// sanitize_text_field() is deliberately avoided: it strips %xx sequences,
		// which would break decoding and non-ASCII paths.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? \wp_unslash( $_SERVER['REQUEST_URI'] ) : '';

		if ( self::is_blocked( (string) $request_uri ) ) {
			$this->do_redirect();
		}
	}

	/**
	 * Execute the configured action: issue a 302 or serve a 404.
	 *
	 * Always ends with exit so a matched blocked URL is never served.
	 */
	private function do_redirect(): void {
		$redirect_type = \get_option( 'urlb_redirect_type', 'custom' );
		$redirect_to   = self::get_redirect_destination();

		// Fall back to 404 if the destination is itself blocked, to avoid a redirect loop.
		if ( 'custom' === $redirect_type && '' !== $redirect_to && ! $this->is_same_site_blocked( $redirect_to ) ) {
			\nocache_headers();
			// The destination is set by an administrator and may be on another
			// domain, so wp_safe_redirect() (same-host only) is not appropriate.
			\wp_redirect( $redirect_to, 302, 'PathGuard Redirects' ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
			exit;
		}

		$this->serve_404();
	}

	/**
	 * Whether a destination URL points back to a blocked path on this site.
	 *
	 * @param string $url Absolute destination URL.
	 */
	private function is_same_site_blocked( string $url ): bool {
		$dest_host = \wp_parse_url( $url, PHP_URL_HOST );
		$home_host = \wp_parse_url( \home_url(), PHP_URL_HOST );

		if ( $dest_host && strtolower( $dest_host ) !== strtolower( (string) $home_host ) ) {
			return false;
		}

		return self::is_blocked( $url );
	}

	/**
	 * Serve the theme's 404 template with a 404 status and stop.
	 */
	private function serve_404(): void {
		global $wp_query, $post;

		// Discard the matched post(s) so no template can render the blocked content.
		$wp_query->init();
		$wp_query->set_404();
		$post = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		\status_header( 404 );
		\nocache_headers();

		// Classic themes without a 404.php return '' here; fall back to index.php.
		$template = \get_404_template();
		if ( ! $template ) {
			$template = \get_index_template();
		}

		$template = \apply_filters( 'template_include', $template ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound

		if ( $template ) {
			include $template;
		}
		exit;
	}
}
