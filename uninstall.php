<?php
/**
 * Uninstall handler
 *
 * Fires only when the plugin is deleted via the Plugins screen (not on
 * deactivation). Removes the custom table and all options this plugin
 * created, across all sites on a multisite install.
 */

namespace PluginRx\ProhibitedTermsScanner;

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) exit;

/**
 * Option keys this plugin has ever registered
 *
 * @return array
 */
function ptscanner_uninstall_option_keys() : array {
    return [
        'ptscanner_schema_version',
        'ptscanner_terms',
        'ptscanner_warning_terms',
        'ptscanner_warning_enabled',
        'ptscanner_location_types_enabled',
        'ptscanner_post_types_enabled',
        'ptscanner_batch_size',
        'ptscanner_snippet_padding',
        'ptscanner_default_case_sensitive',
        'ptscanner_default_strict',
        'ptscanner_shortcode_roles',
        'ptscanner_omits',
        'ptscanner_error_log',
    ];
} // End ptscanner_uninstall_option_keys()


/**
 * Drop the custom results table and delete all plugin options for the
 * current site (called once per site on multisite)
 *
 * @return void
 */
function ptscanner_uninstall_single_site() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'ptscanner_results';

    $wpdb->query( "DROP TABLE IF EXISTS {$table_name}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

    foreach ( ptscanner_uninstall_option_keys() as $option_key ) {
        delete_option( $option_key );
    }
} // End ptscanner_uninstall_single_site()


if ( is_multisite() ) {
    $site_ids = get_sites( [ 'fields' => 'ids' ] );

    foreach ( $site_ids as $site_id ) {
        switch_to_blog( $site_id );
        ptscanner_uninstall_single_site();
        restore_current_blog();
    }
} else {
    ptscanner_uninstall_single_site();
}