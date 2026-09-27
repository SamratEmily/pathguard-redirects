<?php
/**
 * Admin class: registers the settings page, handles form saves, and renders
 * the view by loading the template.
 *
 * @package URLBlocker
 */

namespace URLBlocker;

defined( 'ABSPATH' ) || exit;

/**
 * Handles all wp-admin integration: menu registration, form saving, and page rendering.
 */
class AdminSettings {

	/**
	 * Set default option values on plugin activation.
	 *
	 * Uses add_option() so existing values are never overwritten when the plugin
	 * is deactivated and re-activated.
	 */
	public static function activate(): void {
		\add_option( 'urlb_exclude_admins', '1' ); // admins are exempt by default
		\add_option( 'urlb_redirect_type', 'custom' );
		\add_option( 'urlb_blocked_urls', '' );
		\add_option( 'urlb_redirect_url', '' );
	}

	/**
	 * Hook suffix of the settings page, used to scope asset loading.
	 *
	 * @var string
	 */
	private $page_hook = '';

	/**
	 * Wire up WordPress hooks.
	 */
	public function __construct() {
		\add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		\add_action( 'admin_init', array( $this, 'handle_save' ) );
		\add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		\add_filter(
			'plugin_action_links_' . \plugin_basename( URLB_PATH . 'pathguard-redirects.php' ),
			array( $this, 'add_settings_link' )
		);
	}

	/**
	 * Prepend a "Settings" action link on the Plugins list page.
	 *
	 * @param array $links Existing action links for this plugin.
	 * @return array Modified links with Settings prepended.
	 */
	public function add_settings_link( array $links ): array {
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			\esc_url( \admin_url( 'options-general.php?page=pathguard-redirects' ) ),
			\esc_html__( 'Settings', 'pathguard-redirects' )
		);

		array_unshift( $links, $settings_link );

		return $links;
	}

	/**
	 * Register the Settings > PathGuard Redirects submenu page.
	 */
	public function add_settings_page(): void {
		$this->page_hook = (string) \add_options_page(
			\__( 'PathGuard Redirects', 'pathguard-redirects' ),
			\__( 'PathGuard Redirects', 'pathguard-redirects' ),
			'manage_options',
			'pathguard-redirects',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Toggle the destination URL row based on the selected action (settings page only).
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		if ( $hook_suffix !== $this->page_hook ) {
			return;
		}

		\wp_register_script( 'urlb-settings', false, array(), URLB_VERSION, true );
		\wp_enqueue_script( 'urlb-settings' );
		\wp_add_inline_script(
			'urlb-settings',
			"( function () {
	var select    = document.getElementById( 'urlb_redirect_type' );
	var customRow = document.getElementById( 'urlb_custom_url_row' );

	if ( ! select || ! customRow ) {
		return;
	}

	function toggleCustomRow() {
		customRow.style.display = ( 'custom' === select.value ) ? '' : 'none';
	}

	select.addEventListener( 'change', toggleCustomRow );
	toggleCustomRow();
}() );"
		);
	}

	/**
	 * Normalise the submitted block list: one path per line, decoded,
	 * lowercased, with leading/trailing slashes and duplicates removed.
	 *
	 * @param string $raw Raw textarea contents.
	 * @return string Newline-separated list of normalised paths.
	 */
	private function sanitize_blocked_urls( string $raw ): string {
		$paths = array();

		foreach ( preg_split( '/\r\n|\r|\n/', $raw ) as $line ) {
			// Normalise first: sanitize_text_field() would strip %xx sequences
			// before they could be decoded.
			$path = \sanitize_text_field( URLB_Blocker::normalise_path( $line ) );

			if ( '' !== $path ) {
				$paths[] = $path;
			}
		}

		return implode( "\n", array_unique( $paths ) );
	}

	/**
	 * Process the settings form submission using the PRG pattern.
	 */
	public function handle_save(): void {
		if ( ! isset( $_POST['urlb_save'] ) ) {
			return;
		}

		// Capability check first — reject unauthorised users before touching the nonce.
		if ( ! \current_user_can( 'manage_options' ) ) {
			\wp_die( \esc_html__( 'You do not have permission to do that.', 'pathguard-redirects' ) );
		}

		\check_admin_referer( 'urlb_save_settings', 'urlb_nonce' );

		// Sanitised line by line in sanitize_blocked_urls().
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$blocked_urls = $this->sanitize_blocked_urls( (string) \wp_unslash( $_POST['urlb_blocked_urls'] ?? '' ) );

		// esc_url_raw() alone: sanitize_text_field() would strip %xx sequences from the URL.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$redirect_to = \esc_url_raw( trim( (string) \wp_unslash( $_POST['urlb_redirect_url'] ?? '' ) ) );

		$exclude_admins = isset( $_POST['urlb_exclude_admins'] ) ? '1' : '0';

		// Validate redirect type against allowed values.
		$allowed_types = array( '404', 'custom' );
		$raw_type      = isset( $_POST['urlb_redirect_type'] ) ? \sanitize_key( \wp_unslash( $_POST['urlb_redirect_type'] ) ) : 'custom';
		$redirect_type = in_array( $raw_type, $allowed_types, true ) ? $raw_type : 'custom';

		$changed = \update_option( 'urlb_blocked_urls', $blocked_urls );
		$changed = \update_option( 'urlb_redirect_url', $redirect_to ) || $changed;
		$changed = \update_option( 'urlb_redirect_type', $redirect_type ) || $changed;
		$changed = \update_option( 'urlb_exclude_admins', $exclude_admins ) || $changed;

		if ( $changed ) {
			$this->purge_page_caches();
		}

		\wp_safe_redirect(
			\add_query_arg(
				array(
					'page'    => 'pathguard-redirects',
					'updated' => '1',
				),
				\admin_url( 'options-general.php' )
			)
		);
		exit;
	}

	/**
	 * Purge full-page caches after the rules change.
	 *
	 * Page-cache plugins that serve HTML before WordPress reaches
	 * template_redirect would otherwise keep serving copies of newly blocked
	 * pages that were cached before the rule existed.
	 */
	private function purge_page_caches(): void {
		/**
		 * Fires after PathGuard Redirects settings change, so page caches and
		 * CDNs can purge stale copies of newly blocked pages.
		 */
		\do_action( 'pathguard_redirects_rules_updated' );

		if ( function_exists( 'wp_cache_clear_cache' ) ) {
			\wp_cache_clear_cache(); // WP Super Cache.
		}
		if ( function_exists( 'rocket_clean_domain' ) ) {
			\rocket_clean_domain(); // WP Rocket.
		}
		if ( function_exists( 'w3tc_flush_all' ) ) {
			\w3tc_flush_all(); // W3 Total Cache.
		}
		\do_action( 'litespeed_purge_all' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- LiteSpeed Cache's public purge action.
	}

	/**
	 * Fetch saved values and load the settings page template.
	 */
	public function render_settings_page(): void {
		if ( ! \current_user_can( 'manage_options' ) ) {
			return;
		}

		$blocked_urls   = \get_option( 'urlb_blocked_urls', '' );
		$redirect_url   = \get_option( 'urlb_redirect_url', '' );
		$redirect_type  = \get_option( 'urlb_redirect_type', 'custom' );
		$exclude_admins = \get_option( 'urlb_exclude_admins', '1' );

		$redirect_dest = URLB_Blocker::get_redirect_destination();
		$redirect_loop = 'custom' === $redirect_type
			&& '' !== $redirect_dest
			&& strtolower( (string) \wp_parse_url( $redirect_dest, PHP_URL_HOST ) ) === strtolower( (string) \wp_parse_url( \home_url(), PHP_URL_HOST ) )
			&& URLB_Blocker::is_blocked( $redirect_dest );

		include URLB_PATH . 'templates/settings-page.php';
	}
}
