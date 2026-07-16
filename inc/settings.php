<?php
/**
 * Plugin settings
 */

namespace PluginRx\ProhibitedTermsScanner;

if ( ! defined( 'ABSPATH' ) ) exit;

class Settings {


    /**
     * Option keys
     */
    private const OPT_TERMS            = 'ptscanner_terms';
    private const OPT_LOCATION_TYPES   = 'ptscanner_location_types_enabled';
    private const OPT_POST_TYPES       = 'ptscanner_post_types_enabled';
    private const OPT_BATCH_SIZE       = 'ptscanner_batch_size';
    private const OPT_SNIPPET_PADDING  = 'ptscanner_snippet_padding';
    private const OPT_DEFAULT_CASE     = 'ptscanner_default_case_sensitive';
    private const OPT_DEFAULT_STRICT   = 'ptscanner_default_strict';
    private const OPT_WARNING_TERMS    = 'ptscanner_warning_terms';
    private const OPT_WARNING_ENABLED  = 'ptscanner_warning_enabled';
    private const OPT_SHORTCODE_ROLES  = 'ptscanner_shortcode_roles';
    private const OPT_PDF_PAGE_LOOKUP = 'ptscanner_pdf_page_lookup';
    private const OPT_CRON_ENABLED    = 'ptscanner_cron_enabled';
    private const OPT_CRON_FREQUENCY  = 'ptscanner_cron_frequency';


    /**
     * Nonce action for settings save/AJAX
     *
     * @var string
     */
    private string $nonce = 'ptscanner_nonce';


    /**
     * The single instance of the class
     *
     * @var self|null
     */
    private static ?Settings $instance = null;


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
        add_action( 'wp_ajax_ptscanner_save_settings', [ $this, 'handle_save' ] );
    } // End __construct()


    /**
     * Post types that manage their own dedicated scan location types (like
     * ERI File Library) and so are excluded from the generic post-type
     * scan list only — they still get their own Omit action, just under a
     * different internal type key (handled by resolve_omit_type()).
     *
     * @return array
     */
    public function get_integration_managed_post_types() : array {
        $excluded = [ 'erifl-files', 'eri-files' ];

        /**
         * Filter post types that are scanned via a dedicated integration
         * rather than the generic title/content/excerpt scanners.
         *
         * @param array $post_types Post type slugs.
         */
        return apply_filters( 'ptscanner_integration_managed_post_types', $excluded );
    } // End get_integration_managed_post_types()


    /**
     * Post types fully excluded from Settings' post type list AND from the
     * Omit row action / bulk actions entirely — for post types that
     * shouldn't be scanned or omitted at all (e.g. non-content post types
     * used internally by other plugins).
     *
     * @return array
     */
    public function get_excluded_post_types() : array {
        $excluded = [ 'help-doc-imports', 'help-docs', 'x-portfolio', 'mailpoet_page' ];

        /**
         * Filter the list of post type slugs fully excluded from scanning,
         * Settings, and the Omit action.
         *
         * @param array $excluded Post type slugs to exclude.
         */
        return apply_filters( 'ptscanner_excluded_post_types', $excluded );
    } // End get_excluded_post_types()


    /**
     * Get the nonce action name
     *
     * @return string
     */
    public function nonce_action() : string {
        return $this->nonce;
    } // End nonce_action()


    /**
     * Get the saved term list (JSON-decoded)
     *
     * @return array Array of [ 'term', 'case_sensitive', 'strict' ]
     */
    public function get_terms() : array {
        $terms = get_option( self::OPT_TERMS, [] );

        return is_array( $terms ) ? $terms : [];
    } // End get_terms()


    /**
     * Save the term list
     *
     * @param array $terms
     * @return bool
     */
    public function save_terms( array $terms ) : bool {
        $sanitized = $this->sanitize_terms( $terms );

        return update_option( self::OPT_TERMS, $sanitized, false );
    } // End save_terms()


    /**
     * Get the warning (monitored) term list
     *
     * @return array
     */
    public function get_warning_terms() : array {
        $terms = get_option( self::OPT_WARNING_TERMS, [] );

        return is_array( $terms ) ? $terms : [];
    } // End get_warning_terms()


    /**
     * Save the warning term list
     *
     * @param array $terms
     * @return bool
     */
    public function save_warning_terms( array $terms ) : bool {
        $sanitized = $this->sanitize_terms( $terms );

        return update_option( self::OPT_WARNING_TERMS, $sanitized, false );
    } // End save_warning_terms()


    /**
     * Sanitize a raw term list array (shared by both term list options)
     *
     * @param array $terms
     * @return array
     */
    private function sanitize_terms( array $terms ) : array {
        $sanitized = [];
        $seen      = [];

        foreach ( $terms as $term_data ) {
            if ( ! isset( $term_data[ 'term' ] ) ) {
                continue;
            }

            $term = sanitize_text_field( $term_data[ 'term' ] );
            $term = trim( preg_replace( '/\s+/', ' ', $term ) );

            if ( '' === $term ) {
                continue;
            }

            $dedup_key = mb_strtolower( $term );

            if ( isset( $seen[ $dedup_key ] ) ) {
                continue;
            }

            $seen[ $dedup_key ] = true;

            $sanitized[] = [
                'term'           => $term,
                'case_sensitive' => ! empty( $term_data[ 'case_sensitive' ] ),
                'strict'         => ! empty( $term_data[ 'strict' ] ),
            ];
        }

        return $sanitized;
    } // End sanitize_terms()


    /**
     * Is warning-on-save/upload enabled
     *
     * @return bool
     */
    public function is_warning_enabled() : bool {
        return filter_var( get_option( self::OPT_WARNING_ENABLED, false ), FILTER_VALIDATE_BOOLEAN );
    } // End is_warning_enabled()


    /**
     * Get enabled location type slugs
     *
     * @return array
     */
    public function get_enabled_location_types() : array {
        $saved = get_option( self::OPT_LOCATION_TYPES, null );

        if ( null === $saved ) {
            $enabled = [];

            foreach ( TypeRegistry::instance()->get_types() as $slug => $type ) {
                if ( ! empty( $type[ 'default_enabled' ] ) ) {
                    $enabled[] = $slug;
                }
            }

            return $enabled;
        }

        return is_array( $saved ) ? $saved : [];
    } // End get_enabled_location_types()


    /**
     * Get enabled post types for scanning
     *
     * @return array
     */
    public function get_enabled_post_types() : array {
        $saved = get_option( self::OPT_POST_TYPES, null );

        if ( null === $saved ) {
            $all_public = get_post_types( [ 'public' => true ], 'names' );
            $to_remove  = array_merge( $this->get_integration_managed_post_types(), $this->get_excluded_post_types() );

            return array_values( array_diff( $all_public, $to_remove ) );
        }

        return is_array( $saved ) ? $saved : [];
    } // End get_enabled_post_types()


    /**
     * Get batch size
     *
     * @return int
     */
    public function get_batch_size() : int {
        $size = absint( get_option( self::OPT_BATCH_SIZE, 20 ) );

        return max( 1, $size );
    } // End get_batch_size()


    /**
     * Get snippet padding (characters on each side of a match)
     *
     * @return int
     */
    public function get_snippet_padding() : int {
        $padding = get_option( self::OPT_SNIPPET_PADDING, '' );

        if ( '' === $padding ) {
            return 60;
        }

        return absint( $padding );
    } // End get_snippet_padding()


    /**
     * Get global default case-sensitivity flag
     *
     * @return bool
     */
    public function get_default_case_sensitive() : bool {
        return filter_var( get_option( self::OPT_DEFAULT_CASE, false ), FILTER_VALIDATE_BOOLEAN );
    } // End get_default_case_sensitive()


    /**
     * Get global default strict-matching flag
     *
     * @return bool
     */
    public function get_default_strict() : bool {
        return filter_var( get_option( self::OPT_DEFAULT_STRICT, false ), FILTER_VALIDATE_BOOLEAN );
    } // End get_default_strict()


    /**
     * Get roles allowed to use the front-end shortcode
     *
     * @return array
     */
    public function get_shortcode_roles() : array {
        $roles = get_option( self::OPT_SHORTCODE_ROLES, [ 'administrator' ] );

        return is_array( $roles ) ? $roles : [ 'administrator' ];
    } // End get_shortcode_roles()


    /**
     * Whether PDF page-number lookup is enabled (heavier per-page parsing)
     *
     * @return bool
     */
    public function get_pdf_page_lookup() : bool {
        return filter_var( get_option( self::OPT_PDF_PAGE_LOOKUP, false ), FILTER_VALIDATE_BOOLEAN );
    } // End get_pdf_page_lookup()


    /**
     * Handle the settings form submission (AJAX)
     *
     * @return void
     */
    public function handle_save() {
        check_ajax_referer( $this->nonce, $this->nonce . '_field' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'prohibited-terms-scanner' ) ] );
        }

        // Location types.
        $location_types = isset( $_POST[ 'location_types' ] ) ? array_map( 'sanitize_key', wp_unslash( (array) $_POST[ 'location_types' ] ) ) : [];
        update_option( self::OPT_LOCATION_TYPES, $location_types, false );

        // Post types.
        $post_types = isset( $_POST[ 'post_types' ] ) ? array_map( 'sanitize_key', wp_unslash( (array) $_POST[ 'post_types' ] ) ) : [];
        update_option( self::OPT_POST_TYPES, $post_types, false );

        // Batch size.
        $batch_size = isset( $_POST[ 'batch_size' ] ) ? absint( wp_unslash( $_POST[ 'batch_size' ] ) ) : 20;
        update_option( self::OPT_BATCH_SIZE, max( 1, $batch_size ), false );

        // Snippet padding.
        $snippet_padding = isset( $_POST[ 'snippet_padding' ] ) ? absint( wp_unslash( $_POST[ 'snippet_padding' ] ) ) : 60;
        update_option( self::OPT_SNIPPET_PADDING, max( 0, $snippet_padding ), false );

        // Default case/strict.
        update_option( self::OPT_DEFAULT_CASE, isset( $_POST[ 'default_case_sensitive' ] ), false );
        update_option( self::OPT_DEFAULT_STRICT, isset( $_POST[ 'default_strict' ] ), false );

        // Warning toggle + terms.
        update_option( self::OPT_WARNING_ENABLED, isset( $_POST[ 'warning_enabled' ] ), false );

        // PDF page lookup.
        update_option( self::OPT_PDF_PAGE_LOOKUP, isset( $_POST[ 'pdf_page_lookup' ] ), false );

        if ( isset( $_POST[ 'warning_terms_json' ] ) ) {
            $raw_json = wp_unslash( $_POST[ 'warning_terms_json' ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $raw_json = is_string( $raw_json ) ? $raw_json : '';
            $decoded  = json_decode( $raw_json, true );

            if ( is_array( $decoded ) ) {
                $this->save_warning_terms( $decoded );
            } elseif ( '' !== trim( $raw_json ) ) {
                ErrorLog::instance()->log( 'settings_save', 'Failed to decode warning_terms_json: ' . json_last_error_msg() );
            }
        }

        // Shortcode roles.
        $shortcode_roles = isset( $_POST[ 'shortcode_roles' ] ) ? array_map( 'sanitize_key', wp_unslash( (array) $_POST[ 'shortcode_roles' ] ) ) : [];
        update_option( self::OPT_SHORTCODE_ROLES, $shortcode_roles, false );

        // Cron schedule
        update_option( self::OPT_CRON_ENABLED, isset( $_POST[ 'cron_enabled' ] ), false );

        $cron_frequency = isset( $_POST[ 'cron_frequency' ] ) ? sanitize_key( wp_unslash( $_POST[ 'cron_frequency' ] ) ) : 'daily';
        update_option( self::OPT_CRON_FREQUENCY, in_array( $cron_frequency, [ 'daily', 'weekly', 'monthly' ], true ) ? $cron_frequency : 'daily', false );

        // Reschedule if settings changed.
        do_action( 'ptscanner_reschedule_cron' );

        wp_send_json_success( [ 'message' => __( 'Settings saved.', 'prohibited-terms-scanner' ) ] );
    } // End handle_save()


    /**
     * Whether scheduled cron scanning is enabled
     *
     * @return bool
     */
    public function is_cron_enabled() : bool {
        return filter_var( get_option( self::OPT_CRON_ENABLED, false ), FILTER_VALIDATE_BOOLEAN );
    } // End is_cron_enabled()


    /**
     * Get the cron scan frequency (daily|weekly)
     *
     * @return string
     */
    public function get_cron_frequency() : string {
        $frequency = get_option( self::OPT_CRON_FREQUENCY, 'daily' );

        return in_array( $frequency, [ 'daily', 'weekly', 'monthly' ], true ) ? $frequency : 'daily';
    } // End get_cron_frequency()

}


Settings::instance();