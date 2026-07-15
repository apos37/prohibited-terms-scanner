<?php
/**
 * General-purpose error log
 *
 * Any part of the plugin can log a caught error here (scanner failures,
 * import/export issues, AJAX exceptions, etc.) for later review on the
 * Errors admin page, instead of failing silently or only to PHP's log.
 */

namespace PluginRx\ProhibitedTermsScanner;

if ( ! defined( 'ABSPATH' ) ) exit;

class ErrorLog {


    /**
     * Option key
     *
     * @var string
     */
    private string $option_key = 'ptscanner_error_log';


    /**
     * Maximum entries retained
     *
     * @var int
     */
    private int $max_entries = 100;


    /**
     * The single instance of the class
     *
     * @var self|null
     */
    private static ?ErrorLog $instance = null;


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
        add_action( 'wp_ajax_ptscanner_clear_errors', [ $this, 'ajax_clear_errors' ] );
    } // End __construct()


    /**
     * Log an error
     *
     * @param string $context Short identifier, e.g. 'shortcode', 'import', 'file_content'
     * @param string $message
     * @param array  $extra Optional extra context, e.g. [ 'post_id' => 5 ]
     * @return void
     */
    public function log( $context, $message, array $extra = [] ) {
        $errors = $this->get_errors();

        $errors[] = [
            'context' => sanitize_key( $context ),
            'message' => sanitize_text_field( $message ),
            'extra'   => $extra,
            'time'    => current_time( 'mysql' ),
        ];

        if ( count( $errors ) > $this->max_entries ) {
            $errors = array_slice( $errors, -$this->max_entries );
        }

        update_option( $this->option_key, $errors, false );
    } // End log()


    /**
     * Get all logged errors, most recent first
     *
     * @return array
     */
    public function get_errors() : array {
        $errors = get_option( $this->option_key, [] );

        if ( ! is_array( $errors ) ) {
            return [];
        }

        return array_reverse( $errors );
    } // End get_errors()


    /**
     * Clear all logged errors
     *
     * @return void
     */
    public function clear() {
        delete_option( $this->option_key );
    } // End clear()


    /**
     * AJAX: clear all errors
     *
     * @return void
     */
    public function ajax_clear_errors() {
        check_ajax_referer( 'ptscanner_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'prohibited-terms-scanner' ) ] );
        }

        $this->clear();

        wp_send_json_success( [ 'message' => __( 'Error log cleared.', 'prohibited-terms-scanner' ) ] );
    } // End ajax_clear_errors()

}


ErrorLog::instance();