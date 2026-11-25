<?php
/**
 * AJAX handlers
 *
 * Handles the batched scan loop, term-list save, and result row actions
 * (mark as OK / clear). All actions require manage_options for the admin
 * scanner; the front-end shortcode uses its own capability check.
 */

namespace PluginRx\ProhibitedTermsScanner;

if ( ! defined( 'ABSPATH' ) ) exit;

class Ajax {


    /**
     * The single instance of the class
     *
     * @var self|null
     */
    private static ?Ajax $instance = null;


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
        add_action( 'wp_ajax_ptscanner_save_terms', [ $this, 'save_terms' ] );
        add_action( 'wp_ajax_ptscanner_run_batch', [ $this, 'run_batch' ] );
        add_action( 'wp_ajax_ptscanner_mark_status', [ $this, 'mark_status' ] );
        add_action( 'wp_ajax_ptscanner_delete_result', [ $this, 'delete_result' ] );
        add_action( 'wp_ajax_ptscanner_get_summary', [ $this, 'get_summary' ] );
        add_action( 'wp_ajax_ptscanner_get_results', [ $this, 'get_results' ] );
        add_action( 'wp_ajax_ptscanner_clear_all', [ $this, 'clear_all' ] );
        add_action( 'wp_ajax_ptscanner_get_pages_list', [ $this, 'get_pages_list' ] );
        add_action( 'wp_ajax_ptscanner_save_note', [ $this, 'save_note' ] );
        add_action( 'wp_ajax_ptscanner_run_external_links_scan', [ $this, 'run_external_links_scan' ] );
        add_action( 'wp_ajax_ptscanner_get_external_links', [ $this, 'get_external_links' ] );
        add_action( 'wp_ajax_ptscanner_save_external_link_note', [ $this, 'save_external_link_note' ] );
        add_action( 'wp_ajax_ptscanner_save_scan_duration', [ $this, 'save_scan_duration' ] );
        add_action( 'wp_ajax_ptscanner_check_media_usage', [ $this, 'check_media_usage' ] );

        // Start buffering as early as possible (plugins_loaded, when this
        // class is instantiated) so any stray output from other plugins'
        // hooks running later (e.g. on admin_init, before our AJAX handler
        // even runs) gets captured and discarded rather than corrupting
        // our JSON response.
        if ( wp_doing_ajax() && isset( $_POST[ 'action' ] ) && 0 === strpos( sanitize_key( wp_unslash( $_POST[ 'action' ] ) ), 'ptscanner_' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
            ob_start();
        }
    } // End __construct()


    /**
     * Send a JSON success response, first discarding any stray output that
     * accumulated in the buffer since this class was constructed
     *
     * @param array $data
     * @return void
     */
    private function send_success( array $data = [] ) {
        $this->discard_stray_output();

        wp_send_json_success( $data );
    } // End send_success()


    /**
     * Send a JSON error response, first discarding any stray output
     *
     * @param array $data
     * @return void
     */
    private function send_error( array $data = [] ) {
        $this->discard_stray_output();

        wp_send_json_error( $data );
    } // End send_error()


    /**
     * Discard any buffered output, logging it if non-empty so stray output
     * from other plugins/hooks is visible on the Errors page for diagnosis
     *
     * @return void
     */
    private function discard_stray_output() {
        if ( ob_get_level() > 0 ) {
            $stray_output = ob_get_clean();

            if ( '' !== trim( $stray_output ) ) {
                ErrorLog::instance()->log( 'stray_output', 'Unexpected output before JSON response: ' . substr( $stray_output, 0, 500 ) );
            }
        }
    } // End discard_stray_output()


    /**
     * Check that the current user has the access level an action needs, sending a
     * JSON error when they don't. Call after check_ajax_referer().
     *
     * @param string $level 'view', 'scan' or 'manage'.
     * @return bool
     */
    public function require_access( string $level ) : bool {
        $allowed = match ( $level ) {
            'manage' => Access::can_manage(),
            'view'   => Access::can_view(),
            default  => Access::can_scan(),
        };

        if ( ! $allowed ) {
            $this->send_error( [ 'message' => __( 'Permission denied.', 'prohibited-terms-scanner' ) ] );
        }

        return $allowed;
    } // End require_access()


    /**
     * Save the working term list (used by both admin scanner and shortcode)
     *
     * @return void
     */
    public function save_terms() {
        check_ajax_referer( 'ptscanner_nonce', 'nonce' );

        if ( ! $this->require_access( 'scan' ) ) {
            return;
        }

        $raw     = isset( $_POST[ 'terms' ] ) ? wp_unslash( $_POST[ 'terms' ] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $raw     = is_string( $raw ) ? $raw : '';
        $decoded = json_decode( $raw, true );

        if ( ! is_array( $decoded ) ) {
            $this->send_error( [ 'message' => __( 'Invalid term list format.', 'prohibited-terms-scanner' ) ] );
            return;
        }

        Settings::instance()->save_terms( $decoded );

        $this->send_success( [ 'message' => __( 'Terms saved.', 'prohibited-terms-scanner' ) ] );
    } // End save_terms()


    /**
     * Run one batch of the scan for a given location type
     *
     * Request params: location_type, offset, is_first_batch (bool)
     *
     * @return void
     */
    public function run_batch() {
        check_ajax_referer( 'ptscanner_nonce', 'nonce' );

        if ( ! $this->require_access( 'scan' ) ) {
            return;
        }

        $location_type  = isset( $_POST[ 'location_type' ] ) ? sanitize_key( wp_unslash( $_POST[ 'location_type' ] ) ) : '';
        $offset         = isset( $_POST[ 'offset' ] ) ? absint( wp_unslash( $_POST[ 'offset' ] ) ) : 0;
        $is_first_batch = isset( $_POST[ 'is_first_batch' ] ) && 'true' === sanitize_text_field( wp_unslash( $_POST[ 'is_first_batch' ] ) );

        if ( '' === $location_type ) {
            $this->send_error( [ 'message' => __( 'Missing location type.', 'prohibited-terms-scanner' ) ] );
            return;
        }

        $type = TypeRegistry::instance()->get_type( $location_type );

        if ( null === $type ) {
            $this->send_error( [ 'message' => __( 'Unknown location type.', 'prohibited-terms-scanner' ) ] );
            return;
        }

        $terms = Settings::instance()->get_terms();

        if ( empty( $terms ) ) {
            $this->send_error( [ 'message' => __( 'No terms to search for.', 'prohibited-terms-scanner' ) ] );
            return;
        }

        // Only a full admin scan may wipe prior flagged results; shortcode
        // scans always append, never wipe, to avoid discarding shared history.
        $can_wipe = Access::can_manage();

        if ( $is_first_batch && $can_wipe ) {
            DB::instance()->wipe_flagged();
        }

        $batch_size     = Settings::instance()->get_batch_size();
        $ignored_hashes = DB::instance()->get_match_hashes( 'ignored' );
        $flagged_hashes = DB::instance()->get_match_hashes( 'flagged' );

        try {
            $result = Scanner::instance()->run_batch( $location_type, $terms, $offset, $batch_size );
        } catch ( \Throwable $e ) {
            ErrorLog::instance()->log( 'scan_batch', $e->getMessage() );

            $this->send_error( [
                /* translators: %s is the error message. */
                'message' => sprintf( __( 'Scan error for this location type, skipping remainder: %s', 'prohibited-terms-scanner' ), $e->getMessage() ),
                'done'    => true,
            ] );
            return;
        }

        $inserted = 0;

        foreach ( $result[ 'rows' ] as $row ) {
            if ( isset( $ignored_hashes[ $row[ 'match_hash' ] ] ) ) {
                continue;
            }

            if ( isset( $flagged_hashes[ $row[ 'match_hash' ] ] ) ) {
                continue;
            }

            DB::instance()->insert( $row );
            $inserted++;
        }

        $this->send_success( [
            'inserted'    => $inserted,
            'done'        => $result[ 'done' ],
            'next_offset' => $offset + $batch_size,
        ] );
    } // End run_batch()


    /**
     * Mark a result row's status (e.g. ignored)
     *
     * @return void
     */
    public function mark_status() {
        check_ajax_referer( 'ptscanner_nonce', 'nonce' );

        if ( ! $this->require_access( 'scan' ) ) {
            return;
        }

        $id     = isset( $_POST[ 'id' ] ) ? absint( wp_unslash( $_POST[ 'id' ] ) ) : 0;
        $status = isset( $_POST[ 'status' ] ) ? sanitize_key( wp_unslash( $_POST[ 'status' ] ) ) : '';

        $allowed_statuses = [ 'flagged', 'ignored' ];

        if ( ! $id || ! in_array( $status, $allowed_statuses, true ) ) {
            $this->send_error( [ 'message' => __( 'Invalid request.', 'prohibited-terms-scanner' ) ] );
            return;
        }

        $updated = DB::instance()->set_status( $id, $status );

        if ( ! $updated ) {
            $this->send_error( [ 'message' => __( 'Could not update status.', 'prohibited-terms-scanner' ) ] );
            return;
        }

        $this->send_success( [ 'message' => __( 'Status updated.', 'prohibited-terms-scanner' ) ] );
    } // End mark_status()


    /**
     * Delete (clear) a single result row
     *
     * @return void
     */
    public function delete_result() {
        check_ajax_referer( 'ptscanner_nonce', 'nonce' );

        if ( ! $this->require_access( 'scan' ) ) {
            return;
        }

        $id = isset( $_POST[ 'id' ] ) ? absint( wp_unslash( $_POST[ 'id' ] ) ) : 0;

        if ( ! $id ) {
            $this->send_error( [ 'message' => __( 'Invalid request.', 'prohibited-terms-scanner' ) ] );
            return;
        }

        $deleted = DB::instance()->delete( $id );

        if ( ! $deleted ) {
            $this->send_error( [ 'message' => __( 'Could not delete result.', 'prohibited-terms-scanner' ) ] );
            return;
        }

        $this->send_success( [ 'message' => __( 'Result cleared.', 'prohibited-terms-scanner' ) ] );
    } // End delete_result()


    /**
     * Get the per-term summary counts for the current flagged results
     *
     * @return void
     */
    public function get_summary() {
        check_ajax_referer( 'ptscanner_nonce', 'nonce' );

        if ( ! $this->require_access( 'view' ) ) {
            return;
        }

        DB::instance()->clear_flagged_count_cache();

        $summary = DB::instance()->get_flagged_summary();

        $this->send_success( [ 'summary' => $summary ] );
    } // End get_summary()


    /**
     * Get a page of results for rendering (admin table or front-end shortcode)
     *
     * @return void
     */
    public function get_results() {
        check_ajax_referer( 'ptscanner_nonce', 'nonce' );

        if ( ! $this->require_access( 'view' ) ) {
            return;
        }

        $status = isset( $_POST[ 'status' ] ) ? sanitize_key( wp_unslash( $_POST[ 'status' ] ) ) : 'flagged';
        $status = in_array( $status, [ 'flagged', 'ignored' ], true ) ? $status : 'flagged';
        $page   = isset( $_POST[ 'page' ] ) ? absint( wp_unslash( $_POST[ 'page' ] ) ) : 1;

        $term_filter     = isset( $_POST[ 'term_filter' ] ) ? sanitize_text_field( wp_unslash( $_POST[ 'term_filter' ] ) ) : '';
        $location_filter = isset( $_POST[ 'location_filter' ] ) ? sanitize_text_field( wp_unslash( $_POST[ 'location_filter' ] ) ) : '';

        $data = ResultsPageData::instance()->get_page( $status, max( 1, $page ), $term_filter, $location_filter );

        $data[ 'distinct_terms' ] = DB::instance()->get_distinct_terms( $status );

        $data[ 'distinct_locations' ] = DB::instance()->get_distinct_location_combos( $status );

        $this->send_success( $data );
    } // End get_results()


    /**
     * Clear all results matching a given status
     *
     * @return void
     */
    public function clear_all() {
        check_ajax_referer( 'ptscanner_nonce', 'nonce' );

        if ( ! $this->require_access( 'scan' ) ) {
            return;
        }

        $status = isset( $_POST[ 'status' ] ) ? sanitize_key( wp_unslash( $_POST[ 'status' ] ) ) : '';
        $status = in_array( $status, [ 'flagged', 'ignored' ], true ) ? $status : '';

        if ( '' === $status ) {
            $this->send_error( [ 'message' => __( 'Invalid status.', 'prohibited-terms-scanner' ) ] );
            return;
        }

        $deleted = DB::instance()->delete_all_by_status( $status );

        $this->send_success( [
            /* translators: %d: number of results cleared */
            'message' => sprintf( __( 'Cleared %d result(s).', 'prohibited-terms-scanner' ), $deleted ),
            'deleted' => $deleted,
        ] );
    } // End clear_all()


    /**
     * Get a page of the Pages List tab (admin or front-end shortcode)
     *
     * @return void
     */
    public function get_pages_list() {
        check_ajax_referer( 'ptscanner_nonce', 'nonce' );

        if ( ! $this->require_access( 'scan' ) ) {
            return;
        }

        $page = isset( $_POST[ 'page' ] ) ? absint( wp_unslash( $_POST[ 'page' ] ) ) : 1;

        $data = PagesList::instance()->get_page( max( 1, $page ) );

        $this->send_success( $data );
    } // End get_pages_list()


    /**
     * Save (or clear) a note on a result row
     *
     * @return void
     */
    public function save_note() {
        check_ajax_referer( 'ptscanner_nonce', 'nonce' );

        if ( ! $this->require_access( 'scan' ) ) {
            return;
        }

        $id   = isset( $_POST[ 'id' ] ) ? absint( wp_unslash( $_POST[ 'id' ] ) ) : 0;
        $note = isset( $_POST[ 'note' ] ) ? sanitize_textarea_field( wp_unslash( $_POST[ 'note' ] ) ) : '';

        if ( ! $id ) {
            $this->send_error( [ 'message' => __( 'Invalid request.', 'prohibited-terms-scanner' ) ] );
            return;
        }

        $updated = DB::instance()->update_note( $id, $note );

        if ( ! $updated ) {
            $this->send_error( [ 'message' => __( 'Could not save note.', 'prohibited-terms-scanner' ) ] );
            return;
        }

        $this->send_success( [ 'message' => __( 'Note saved.', 'prohibited-terms-scanner' ) ] );
    } // End save_note()


    /**
     * Run a full external links scan (single pass, no batching)
     *
     * @return void
     */
    public function run_external_links_scan() {
        check_ajax_referer( 'ptscanner_nonce', 'nonce' );

        if ( ! $this->require_access( 'scan' ) ) {
            return;
        }

        $result = ExternalLinksScanner::instance()->run_full_scan();

        $this->send_success( $result );
    } // End run_external_links_scan()


    /**
     * Get a page of external links for rendering
     *
     * @return void
     */
    public function get_external_links() {
        check_ajax_referer( 'ptscanner_nonce', 'nonce' );

        if ( ! $this->require_access( 'view' ) ) {
            return;
        }

        $page   = isset( $_POST[ 'page' ] ) ? absint( wp_unslash( $_POST[ 'page' ] ) ) : 1;
        $filter = isset( $_POST[ 'filter' ] ) ? sanitize_key( wp_unslash( $_POST[ 'filter' ] ) ) : 'all';
        $filter = in_array( $filter, [ 'all', 'flagged' ], true ) ? $filter : 'all';

        $data = ExternalLinksDB::instance()->get_page( max( 1, $page ), 50, $filter );

        foreach ( $data[ 'rows' ] as &$row ) {
            $row[ 'source_title' ] = html_entity_decode( get_the_title( $row[ 'source_id' ] ), ENT_QUOTES, 'UTF-8' );
            $row[ 'source_link' ]  = get_permalink( $row[ 'source_id' ] );
            $row[ 'anchor_text' ]  = html_entity_decode( $row[ 'anchor_text' ], ENT_QUOTES, 'UTF-8' );
        }

        $this->send_success( $data );
    } // End get_external_links()


    /**
     * Save (or clear) a note on an external link row
     *
     * @return void
     */
    public function save_external_link_note() {
        check_ajax_referer( 'ptscanner_nonce', 'nonce' );

        if ( ! $this->require_access( 'scan' ) ) {
            return;
        }

        $id   = isset( $_POST[ 'id' ] ) ? absint( wp_unslash( $_POST[ 'id' ] ) ) : 0;
        $note = isset( $_POST[ 'note' ] ) ? sanitize_textarea_field( wp_unslash( $_POST[ 'note' ] ) ) : '';

        if ( ! $id ) {
            $this->send_error( [ 'message' => __( 'Invalid request.', 'prohibited-terms-scanner' ) ] );
            return;
        }

        $updated = ExternalLinksDB::instance()->update_note( $id, $note );

        if ( ! $updated ) {
            $this->send_error( [ 'message' => __( 'Could not save note.', 'prohibited-terms-scanner' ) ] );
            return;
        }

        $this->send_success( [ 'message' => __( 'Note saved.', 'prohibited-terms-scanner' ) ] );
    } // End save_external_link_note()


    /**
     * Save the duration of the most recently completed/cancelled scan
     *
     * @return void
     */
    public function save_scan_duration() {
        check_ajax_referer( 'ptscanner_nonce', 'nonce' );

        if ( ! $this->require_access( 'scan' ) ) {
            return;
        }

        $seconds = isset( $_POST[ 'seconds' ] ) ? absint( wp_unslash( $_POST[ 'seconds' ] ) ) : 0;

        update_option( 'ptscanner_last_scan_duration', $seconds, false );
        update_option( 'ptscanner_last_scan_time', current_time( 'mysql' ), false );

        $this->send_success( [ 'message' => __( 'Duration saved.', 'prohibited-terms-scanner' ) ] );
    } // End save_scan_duration()


    /**
     * Check whether a media attachment is used anywhere on the site
     *
     * @return void
     */
    public function check_media_usage() {
        check_ajax_referer( 'ptscanner_nonce', 'nonce' );

        if ( ! $this->require_access( 'view' ) ) {
            return;
        }

        $attachment_id = isset( $_POST[ 'attachment_id' ] ) ? absint( wp_unslash( $_POST[ 'attachment_id' ] ) ) : 0;

        if ( ! $attachment_id ) {
            $this->send_error( [ 'message' => __( 'Invalid request.', 'prohibited-terms-scanner' ) ] );
            return;
        }

        $result = PagesList::instance()->check_media_usage( $attachment_id );

        $this->send_success( $result );
    } // End check_media_usage()

}


Ajax::instance();