<?php
/**
 * Uninstall routine: removes all plugin options when the plugin is deleted.
 *
 * Settings are intentionally kept on deactivation so temporarily disabling the
 * plugin (e.g. while troubleshooting) does not lose the block list.
 *
 * @package URLBlocker
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'urlb_blocked_urls' );
delete_option( 'urlb_redirect_url' );
delete_option( 'urlb_redirect_type' );
delete_option( 'urlb_exclude_admins' );
