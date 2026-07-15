<?php
/**
 * Import/Export handling
 */

namespace PluginRx\ProhibitedTermsScanner;

if ( ! defined( 'ABSPATH' ) ) exit;

class ImportExport {


    /**
     * Nonce action
     *
     * @var string
     */
    private string $nonce = 'ptscanner_import_export_nonce';


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
        add_action( 'wp_ajax_ptscanner_export', [ $this, 'handle_export' ] );
        add_action( 'wp_ajax_ptscanner_import', [ $this, 'handle_import' ] );
    } // End __construct()


    /**
     * Get the nonce action name
     *
     * @return string
     */
    public function nonce_action() : string {
        return $this->nonce;
    } // End nonce_action()


    /**
     * Handle export request
     *
     * @return void
     */
    public function handle_export() {
        check_ajax_referer( $this->nonce, 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'prohibited-terms-scanner' ) ] );
        }

        $settings = Settings::instance();

        $export_data = [
            'metadata' => [
                'domain'         => wp_parse_url( home_url(), PHP_URL_HOST ),
                'export_date'    => current_time( 'mysql' ),
                'plugin_version' => Bootstrap::version(),
            ],
            'terms'                  => $settings->get_terms(),
            'warning_terms'          => $settings->get_warning_terms(),
            'warning_enabled'        => $settings->is_warning_enabled(),
            'enabled_location_types' => $settings->get_enabled_location_types(),
            'enabled_post_types'     => $settings->get_enabled_post_types(),
            'batch_size'             => $settings->get_batch_size(),
            'pdf_page_lookup'        => $settings->get_pdf_page_lookup(),
            'snippet_padding'        => $settings->get_snippet_padding(),
            'default_case_sensitive' => $settings->get_default_case_sensitive(),
            'default_strict'         => $settings->get_default_strict(),
            'shortcode_roles'        => $settings->get_shortcode_roles(),
        ];

        wp_send_json_success( [
            'filename' => 'prohibited-terms-scanner-export-' . gmdate( 'Y-m-d' ) . '.json',
            'data'     => $export_data,
        ] );
    } // End handle_export()


    /**
     * Handle import request
     *
     * @return void
     */
    public function handle_import() {
        check_ajax_referer( $this->nonce, 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'prohibited-terms-scanner' ) ] );
        }

        if ( ! isset( $_FILES[ 'import_file' ] ) || empty( $_FILES[ 'import_file' ][ 'tmp_name' ] ) ) {
            wp_send_json_error( [ 'message' => __( 'No file uploaded.', 'prohibited-terms-scanner' ) ] );
        }

        $file_path = sanitize_text_field( wp_unslash( $_FILES[ 'import_file' ][ 'tmp_name' ] ) );

        if ( ! is_uploaded_file( $file_path ) ) {
            wp_send_json_error( [ 'message' => __( 'Invalid file upload.', 'prohibited-terms-scanner' ) ] );
        }

        $json_data = file_get_contents( $file_path );
        $json_data = str_replace( "\xEF\xBB\xBF", '', $json_data );
        $data      = json_decode( trim( $json_data ), true );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            wp_send_json_error( [
                /* translators: %s: JSON error message */
                'message' => sprintf( __( 'JSON Error: %s', 'prohibited-terms-scanner' ), json_last_error_msg() ),
            ] );
        }

        if ( ! is_array( $data ) || ! isset( $data[ 'terms' ] ) ) {
            wp_send_json_error( [ 'message' => __( 'Invalid file structure. This does not look like a Prohibited Terms Scanner export.', 'prohibited-terms-scanner' ) ] );
        }

        $settings = Settings::instance();

        if ( isset( $data[ 'terms' ] ) && is_array( $data[ 'terms' ] ) ) {
            $settings->save_terms( $data[ 'terms' ] );
        }

        if ( isset( $data[ 'warning_terms' ] ) && is_array( $data[ 'warning_terms' ] ) ) {
            $settings->save_warning_terms( $data[ 'warning_terms' ] );
        }

        if ( isset( $data[ 'warning_enabled' ] ) ) {
            update_option( 'ptscanner_warning_enabled', (bool) $data[ 'warning_enabled' ], false );
        }

        if ( isset( $data[ 'enabled_location_types' ] ) && is_array( $data[ 'enabled_location_types' ] ) ) {
            update_option( 'ptscanner_location_types_enabled', array_map( 'sanitize_key', $data[ 'enabled_location_types' ] ), false );
        }

        if ( isset( $data[ 'enabled_post_types' ] ) && is_array( $data[ 'enabled_post_types' ] ) ) {
            update_option( 'ptscanner_post_types_enabled', array_map( 'sanitize_key', $data[ 'enabled_post_types' ] ), false );
        }

        if ( isset( $data[ 'batch_size' ] ) ) {
            update_option( 'ptscanner_batch_size', max( 1, absint( $data[ 'batch_size' ] ) ), false );
        }

        if ( isset( $data[ 'pdf_page_lookup' ] ) ) {
            update_option( 'ptscanner_pdf_page_lookup', (bool) $data[ 'pdf_page_lookup' ], false );
        }

        if ( isset( $data[ 'snippet_padding' ] ) ) {
            update_option( 'ptscanner_snippet_padding', max( 0, absint( $data[ 'snippet_padding' ] ) ), false );
        }

        if ( isset( $data[ 'default_case_sensitive' ] ) ) {
            update_option( 'ptscanner_default_case_sensitive', (bool) $data[ 'default_case_sensitive' ], false );
        }

        if ( isset( $data[ 'default_strict' ] ) ) {
            update_option( 'ptscanner_default_strict', (bool) $data[ 'default_strict' ], false );
        }

        if ( isset( $data[ 'shortcode_roles' ] ) && is_array( $data[ 'shortcode_roles' ] ) ) {
            update_option( 'ptscanner_shortcode_roles', array_map( 'sanitize_key', $data[ 'shortcode_roles' ] ), false );
        }

        wp_send_json_success( [ 'message' => __( 'Settings imported successfully!', 'prohibited-terms-scanner' ) ] );
    } // End handle_import()

}


ImportExport::instance();