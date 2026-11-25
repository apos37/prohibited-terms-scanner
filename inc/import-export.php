<?php
/**
 * Settings export and import (Settings > Advanced)
 */

namespace PluginRx\ProhibitedTermsScanner;

if ( ! defined( 'ABSPATH' ) ) exit;

class ImportExport {


    /**
     * Legacy (1.2.x) export keys mapped to the keys Settings::apply() reads
     *
     * @var array
     */
    private const LEGACY_KEYS = [
        'enabled_location_types' => 'location_types',
        'enabled_post_types'     => 'post_types',
        'enabled_taxonomies'     => 'taxonomies',
        'shortcode_roles'        => 'scan_roles',
    ];


    /**
     * The single instance of the class
     *
     * @var self|null
     */
    private static ?ImportExport $instance = null;


    /**
     * Get the singleton instance
     *
     * @return self
     */
    public static function instance() : self {
        return self::$instance ??= new self();
    } // End instance()


    /**
     * Constructor
     */
    private function __construct() {
        add_action( 'wp_ajax_ptscanner_export', [ $this, 'ajax_export' ] );
        add_action( 'wp_ajax_ptscanner_import', [ $this, 'ajax_import' ] );
    } // End __construct()


    /**
     * Verify the settings nonce and manage capability, or end the request.
     *
     * @return void
     */
    private function verify_request() : void {
        check_ajax_referer( Settings::NONCE, 'nonce' );

        if ( ! Access::can_manage() ) {
            wp_send_json_error( __( 'Insufficient permissions.', 'prohibited-terms-scanner' ), 403 );
        }
    } // End verify_request()


    /**
     * AJAX: return the export payload for the browser to download.
     *
     * @return void
     */
    public function ajax_export() : void {
        $this->verify_request();

        $settings = Settings::instance();

        $export = [
            'metadata' => [
                'plugin'         => 'prohibited-terms-scanner',
                'domain'         => wp_parse_url( home_url(), PHP_URL_HOST ),
                'export_date'    => current_time( 'mysql' ),
                'plugin_version' => Bootstrap::version(),
            ],
            'terms'    => $settings->get_terms(),
            'settings' => $settings->current(),
        ];

        /**
         * Filter the settings export payload.
         *
         * @param array $export
         */
        $export = (array) apply_filters( 'ptscanner_export_data', $export );

        wp_send_json_success( [
            'filename' => 'prohibited-terms-scanner-settings-' . gmdate( 'Y-m-d' ) . '.json',
            'data'     => $export,
        ] );
    } // End ajax_export()


    /**
     * AJAX: import a payload read in the browser. Accepts 1.3+ and legacy 1.2.x exports.
     *
     * @return void
     */
    public function ajax_import() : void {
        $this->verify_request();

        $payload = isset( $_POST[ 'payload' ] ) ? wp_unslash( $_POST[ 'payload' ] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce checked in verify_request(); raw JSON, every value is sanitized in Settings::sanitize_terms() and Settings::apply().
        $data    = is_string( $payload ) ? json_decode( trim( str_replace( "\xEF\xBB\xBF", '', $payload ) ), true ) : null;

        if ( ! is_array( $data ) || ! isset( $data[ 'terms' ] ) || ! is_array( $data[ 'terms' ] ) ) {
            wp_send_json_error( __( 'That file is not a valid Prohibited Terms Scanner export.', 'prohibited-terms-scanner' ) );
        }

        $settings = Settings::instance();
        $incoming = isset( $data[ 'settings' ] ) && is_array( $data[ 'settings' ] ) ? $data[ 'settings' ] : $this->map_legacy( $data );

        $settings->save_terms( $data[ 'terms' ] );
        $settings->apply( array_merge( $settings->current(), $incoming ) );

        /**
         * Fires after settings are imported.
         *
         * @param array $data Decoded import file. Sanitize anything you read from it.
         */
        do_action( 'ptscanner_settings_imported', $data );

        wp_send_json_success( __( 'Settings imported. Reloading...', 'prohibited-terms-scanner' ) );
    } // End ajax_import()


    /**
     * Convert a flat 1.2.x export into the keys Settings::apply() reads.
     *
     * @param array $data
     * @return array
     */
    private function map_legacy( array $data ) : array {
        $mapped = $data;

        foreach ( self::LEGACY_KEYS as $old => $new ) {
            if ( isset( $data[ $old ] ) ) {
                $mapped[ $new ] = $data[ $old ];
            }
        }

        if ( isset( $data[ 'warning_terms' ] ) && is_array( $data[ 'warning_terms' ] ) ) {
            $mapped[ 'warning_terms_json' ] = wp_json_encode( $data[ 'warning_terms' ] );
        }

        unset( $mapped[ 'terms' ], $mapped[ 'metadata' ], $mapped[ 'warning_terms' ] );

        return $mapped;
    } // End map_legacy()

}


ImportExport::instance();
