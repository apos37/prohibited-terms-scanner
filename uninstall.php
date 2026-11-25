<?php
/**
 * UNINSTALL
 *
 * Fires only on full plugin deletion via the WordPress Plugins screen. The scheduled scan is
 * always unscheduled. Tables, options, transients and post meta are removed only on sites
 * that opted in via Settings > Advanced > Remove All Plugin Data on Uninstall.
 */

namespace PluginRx\ProhibitedTermsScanner;

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) exit;


class Uninstall {

    /**
     * Options this plugin stores
     */
    public const OPTIONS = [
        'ptscanner_activated_time',
        'ptscanner_schema_version',
        'ptscanner_ext_links_schema_version',
        'ptscanner_terms',
        'ptscanner_warning_terms',
        'ptscanner_warning_enabled',
        'ptscanner_location_types_enabled',
        'ptscanner_post_types_enabled',
        'ptscanner_taxonomies_enabled',
        'ptscanner_public_only',
        'ptscanner_batch_size',
        'ptscanner_pdf_page_lookup',
        'ptscanner_snippet_padding',
        'ptscanner_default_case_sensitive',
        'ptscanner_default_strict',
        'ptscanner_view_roles',
        'ptscanner_scan_roles',
        'ptscanner_shortcode_roles',
        'ptscanner_omits',
        'ptscanner_error_log',
        'ptscanner_flagged_count',
        'ptscanner_last_processed',
        'ptscanner_last_scan_duration',
        'ptscanner_last_scan_time',
        'ptscanner_cron_enabled',
        'ptscanner_cron_frequency',
        'ptscanner_cron_last_run',
        'ptscanner_cron_last_result',
        'ptscanner_clear_data_on_uninstall',
    ];


    /**
     * Custom tables, without the site prefix
     */
    public const TABLES = [
        'ptscanner_results',
        'ptscanner_external_links',
    ];


    /**
     * Run for every site on a network, or the single site
     *
     * @return void
     */
    public static function run() : void {
        if ( ! is_multisite() ) {
            self::uninstall_site();
            return;
        }

        foreach ( get_sites( [ 'fields' => 'ids', 'number' => 0 ] ) as $site_id ) {
            switch_to_blog( $site_id );
            self::uninstall_site();
            restore_current_blog();
        }
    } // End run()


    /**
     * Unschedule the scan, then remove this site's data if it opted in
     *
     * @return void
     */
    private static function uninstall_site() : void {
        global $wpdb;

        wp_clear_scheduled_hook( 'ptscanner_scheduled_scan' );

        if ( ! get_option( 'ptscanner_clear_data_on_uninstall' ) ) {
            return;
        }

        foreach ( self::TABLES as $table ) {
            $table_name = esc_sql( $wpdb->prefix . $table );
            $wpdb->query( "DROP TABLE IF EXISTS `{$table_name}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- table names are constants; identifiers can't be prepared.
        }

        foreach ( self::OPTIONS as $option ) {
            delete_option( $option );
        }

        delete_post_meta_by_key( '_ptscanner_content_warning' );

        $transients = $wpdb->esc_like( '_transient_ptscanner_' ) . '%';
        $timeouts   = $wpdb->esc_like( '_transient_timeout_ptscanner_' ) . '%';
        $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", $transients, $timeouts ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time cleanup of this plugin's transients on uninstall.
    } // End uninstall_site()

}


Uninstall::run();
