<?php
/**
 * SETTINGS
 *
 * Stores and sanitizes every plugin option, and saves the Settings tab over AJAX.
 */

namespace PluginRx\ProhibitedTermsScanner;

if ( ! defined( 'ABSPATH' ) ) exit;


class Settings {

    /**
     * Option keys
     */
    public const OPT_TERMS = 'ptscanner_terms';
    public const OPT_LOCATION_TYPES = 'ptscanner_location_types_enabled';
    public const OPT_POST_TYPES = 'ptscanner_post_types_enabled';
    public const OPT_TAXONOMIES = 'ptscanner_taxonomies_enabled';
    public const OPT_PUBLIC_ONLY = 'ptscanner_public_only';
    public const OPT_BATCH_SIZE = 'ptscanner_batch_size';
    public const OPT_SNIPPET_PADDING = 'ptscanner_snippet_padding';
    public const OPT_DEFAULT_CASE = 'ptscanner_default_case_sensitive';
    public const OPT_DEFAULT_STRICT = 'ptscanner_default_strict';
    public const OPT_WARNING_TERMS = 'ptscanner_warning_terms';
    public const OPT_WARNING_ENABLED = 'ptscanner_warning_enabled';
    public const OPT_VIEW_ROLES = 'ptscanner_view_roles';
    public const OPT_SCAN_ROLES = 'ptscanner_scan_roles';
    public const OPT_LEGACY_SHORTCODE_ROLES = 'ptscanner_shortcode_roles';
    public const OPT_PDF_PAGE_LOOKUP = 'ptscanner_pdf_page_lookup';
    public const OPT_CRON_ENABLED = 'ptscanner_cron_enabled';
    public const OPT_CRON_FREQUENCY = 'ptscanner_cron_frequency';
    public const OPT_CLEAR_ON_UNINSTALL = 'ptscanner_clear_data_on_uninstall';


    /**
     * Allowed cron frequencies.
     */
    public const CRON_FREQUENCIES = [ 'daily', 'weekly', 'monthly' ];


    /**
     * Nonce action for the Settings tab.
     */
    public const NONCE = 'ptscanner_settings_nonce';


    /**
     * @var Settings|null Singleton instance
     */
    private static ?Settings $instance = null;


    /**
     * Get instance
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
        add_action( 'wp_ajax_ptscanner_save_settings', [ $this, 'ajax_save' ] );
        add_action( 'wp_ajax_ptscanner_reset_settings', [ $this, 'ajax_reset' ] );
        add_action( 'ptscanner_subheader_left', [ $this, 'render_subheader_buttons' ] );
    } // End __construct()


    /**
     * Options reset by "Reset Settings". Terms, results, notes and omits are kept.
     *
     * @return array
     */
    public static function resettable_options() : array {
        $options = [
            self::OPT_LOCATION_TYPES,
            self::OPT_POST_TYPES,
            self::OPT_TAXONOMIES,
            self::OPT_PUBLIC_ONLY,
            self::OPT_BATCH_SIZE,
            self::OPT_SNIPPET_PADDING,
            self::OPT_DEFAULT_CASE,
            self::OPT_DEFAULT_STRICT,
            self::OPT_WARNING_TERMS,
            self::OPT_WARNING_ENABLED,
            self::OPT_VIEW_ROLES,
            self::OPT_SCAN_ROLES,
            self::OPT_LEGACY_SHORTCODE_ROLES,
            self::OPT_PDF_PAGE_LOOKUP,
            self::OPT_CRON_ENABLED,
            self::OPT_CRON_FREQUENCY,
            self::OPT_CLEAR_ON_UNINSTALL,
        ];

        return (array) apply_filters( 'ptscanner_resettable_options', $options );
    } // End resettable_options()


    /**
     * Post types that manage their own dedicated scan location types (like
     * ERI File Library), excluded from the generic post-type scan list only.
     *
     * @return array
     */
    public function get_integration_managed_post_types() : array {
        /**
         * Filter post types that are scanned via a dedicated integration
         * rather than the generic title/content/excerpt scanners.
         *
         * @param array $post_types Post type slugs.
         */
        return (array) apply_filters( 'ptscanner_integration_managed_post_types', [ 'erifl-files', 'eri-files' ] );
    } // End get_integration_managed_post_types()


    /**
     * Post types fully excluded from Settings, scanning, and the Omit action.
     *
     * @return array
     */
    public function get_excluded_post_types() : array {
        /**
         * Filter the list of post type slugs fully excluded from scanning,
         * Settings, and the Omit action.
         *
         * @param array $excluded Post type slugs to exclude.
         */
        return (array) apply_filters( 'ptscanner_excluded_post_types', [ 'help-doc-imports', 'help-docs', 'x-portfolio', 'mailpoet_page' ] );
    } // End get_excluded_post_types()


    /**
     * Get the public post types shown in Settings.
     *
     * @return array Slug => WP_Post_Type.
     */
    public function get_selectable_post_types() : array {
        $hidden = array_merge( $this->get_integration_managed_post_types(), $this->get_excluded_post_types() );

        return array_diff_key( get_post_types( [ 'public' => true ], 'objects' ), array_flip( $hidden ) );
    } // End get_selectable_post_types()


    /**
     * Get the nonce action name used by the scanner and results scripts.
     *
     * @return string
     */
    public function nonce_action() : string {
        return 'ptscanner_nonce';
    } // End nonce_action()


    /**
     * Get the saved term list
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
        return update_option( self::OPT_TERMS, $this->sanitize_terms( $terms ), false );
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
        return update_option( self::OPT_WARNING_TERMS, $this->sanitize_terms( $terms ), false );
    } // End save_warning_terms()


    /**
     * Sanitize a raw term list (shared by both term list options)
     *
     * @param array $terms
     * @return array
     */
    public function sanitize_terms( array $terms ) : array {
        $sanitized = [];
        $seen = [];

        foreach ( $terms as $term_data ) {
            if ( ! is_array( $term_data ) || ! isset( $term_data[ 'term' ] ) || ! is_scalar( $term_data[ 'term' ] ) ) {
                continue;
            }

            $term = trim( preg_replace( '/\s+/u', ' ', sanitize_text_field( (string) $term_data[ 'term' ] ) ) );

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
                'case_sensitive' => ! empty( $term_data[ 'case_sensitive' ] ) && 'false' !== $term_data[ 'case_sensitive' ],
                'strict'         => ! empty( $term_data[ 'strict' ] ) && 'false' !== $term_data[ 'strict' ],
            ];
        }

        return $sanitized;
    } // End sanitize_terms()


    /**
     * Get a boolean option.
     *
     * @param string $option
     * @param bool   $default
     * @return bool
     */
    private function get_bool( string $option, bool $default = false ) : bool {
        return filter_var( get_option( $option, $default ), FILTER_VALIDATE_BOOLEAN );
    } // End get_bool()


    /**
     * Is warning-on-save/upload enabled
     *
     * @return bool
     */
    public function is_warning_enabled() : bool {
        return $this->get_bool( self::OPT_WARNING_ENABLED );
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
            return array_keys( $this->get_selectable_post_types() );
        }

        return is_array( $saved ) ? $saved : [];
    } // End get_enabled_post_types()


    /**
     * Get enabled taxonomy slugs for scanning
     *
     * @return array
     */
    public function get_enabled_taxonomies() : array {
        $saved = get_option( self::OPT_TAXONOMIES, null );

        if ( null === $saved ) {
            return array_keys( get_taxonomies( [ 'public' => true ] ) );
        }

        return is_array( $saved ) ? $saved : [];
    } // End get_enabled_taxonomies()


    /**
     * Whether scans skip posts that aren't public-facing (for example, restricted by Deadbolt).
     *
     * @return bool
     */
    public function is_public_only() : bool {
        return $this->get_bool( self::OPT_PUBLIC_ONLY );
    } // End is_public_only()


    /**
     * Get batch size
     *
     * @return int
     */
    public function get_batch_size() : int {
        return max( 1, absint( get_option( self::OPT_BATCH_SIZE, 20 ) ) );
    } // End get_batch_size()


    /**
     * Get snippet padding (characters on each side of a match)
     *
     * @return int
     */
    public function get_snippet_padding() : int {
        $padding = get_option( self::OPT_SNIPPET_PADDING, '' );

        return '' === $padding ? 60 : absint( $padding );
    } // End get_snippet_padding()


    /**
     * Get global default case-sensitivity flag
     *
     * @return bool
     */
    public function get_default_case_sensitive() : bool {
        return $this->get_bool( self::OPT_DEFAULT_CASE );
    } // End get_default_case_sensitive()


    /**
     * Get global default strict-matching flag
     *
     * @return bool
     */
    public function get_default_strict() : bool {
        return $this->get_bool( self::OPT_DEFAULT_STRICT );
    } // End get_default_strict()


    /**
     * Get the roles that can view results. Administrators always can.
     *
     * @return array
     */
    public function get_view_roles() : array {
        $roles = get_option( self::OPT_VIEW_ROLES, [] );

        return is_array( $roles ) ? $roles : [];
    } // End get_view_roles()


    /**
     * Get the roles that can run scans and act on results, in the admin and with the shortcode.
     * Falls back to the pre-1.3 shortcode roles until the Settings tab is saved.
     *
     * @return array
     */
    public function get_scan_roles() : array {
        $roles = get_option( self::OPT_SCAN_ROLES, null );

        if ( null === $roles ) {
            $roles = get_option( self::OPT_LEGACY_SHORTCODE_ROLES, [] );
        }

        return is_array( $roles ) ? array_values( array_diff( $roles, [ 'administrator' ] ) ) : [];
    } // End get_scan_roles()


    /**
     * Deprecated alias kept for developers who called it before 1.3.
     *
     * @return array
     */
    public function get_shortcode_roles() : array {
        return $this->get_scan_roles();
    } // End get_shortcode_roles()


    /**
     * Whether PDF page-number lookup is enabled (heavier per-page parsing)
     *
     * @return bool
     */
    public function get_pdf_page_lookup() : bool {
        return $this->get_bool( self::OPT_PDF_PAGE_LOOKUP );
    } // End get_pdf_page_lookup()


    /**
     * Whether scheduled cron scanning is enabled
     *
     * @return bool
     */
    public function is_cron_enabled() : bool {
        return $this->get_bool( self::OPT_CRON_ENABLED );
    } // End is_cron_enabled()


    /**
     * Get the cron scan frequency
     *
     * @return string
     */
    public function get_cron_frequency() : string {
        $frequency = get_option( self::OPT_CRON_FREQUENCY, 'daily' );

        return in_array( $frequency, self::CRON_FREQUENCIES, true ) ? $frequency : 'daily';
    } // End get_cron_frequency()


    /**
     * Whether all plugin data is removed when the plugin is deleted.
     *
     * @return bool
     */
    public function clear_data_on_uninstall() : bool {
        return $this->get_bool( self::OPT_CLEAR_ON_UNINSTALL );
    } // End clear_data_on_uninstall()


    /**
     * Current settings in the same shape apply() accepts. Used by export and import.
     *
     * @return array
     */
    public function current() : array {
        $settings = [
            'location_types'          => $this->get_enabled_location_types(),
            'post_types'              => $this->get_enabled_post_types(),
            'taxonomies'              => $this->get_enabled_taxonomies(),
            'public_only'             => $this->is_public_only(),
            'batch_size'              => $this->get_batch_size(),
            'snippet_padding'         => $this->get_snippet_padding(),
            'default_case_sensitive'  => $this->get_default_case_sensitive(),
            'default_strict'          => $this->get_default_strict(),
            'warning_enabled'         => $this->is_warning_enabled(),
            'warning_terms_json'      => wp_json_encode( $this->get_warning_terms() ),
            'view_roles'              => $this->get_view_roles(),
            'scan_roles'              => $this->get_scan_roles(),
            'pdf_page_lookup'         => $this->get_pdf_page_lookup(),
            'cron_enabled'            => $this->is_cron_enabled(),
            'cron_frequency'          => $this->get_cron_frequency(),
            'clear_data_on_uninstall' => $this->clear_data_on_uninstall(),
        ];

        /**
         * Filter the current settings used for export and import merging.
         *
         * @param array $settings Settings keyed the same way Settings::apply() reads them.
         */
        return (array) apply_filters( 'ptscanner_current_settings', $settings );
    } // End current()


    /**
     * Render the scheduled scan status lines (Settings > Scheduled Scanning, refreshed after save).
     *
     * @return void
     */
    public function render_cron_status() : void {
        $status = Cron::instance()->get_status();

        if ( ! $status[ 'enabled' ] ) {
            echo '<em>' . esc_html__( 'Scheduled scanning is currently disabled.', 'prohibited-terms-scanner' ) . '</em>';
            return;
        }

        if ( $status[ 'next_run' ] ) {
            /* translators: %s: date and time of the next scheduled scan */
            echo esc_html( sprintf( __( 'Next scan: %s', 'prohibited-terms-scanner' ), wp_date( 'F j, Y g:i a', $status[ 'next_run' ] ) ) );
        } else {
            esc_html_e( 'Not currently scheduled. Save to schedule it.', 'prohibited-terms-scanner' );
        }

        echo '<br>';

        if ( '' !== $status[ 'last_run' ] ) {
            /* translators: 1: date and time of the last scan, 2: number of results found */
            echo esc_html( sprintf( __( 'Last scan: %1$s (%2$d result(s) found)', 'prohibited-terms-scanner' ), mysql2date( 'F j, Y g:i a', $status[ 'last_run' ] ), (int) $status[ 'last_result' ] ) );
        } else {
            esc_html_e( 'No scheduled scan has run yet.', 'prohibited-terms-scanner' );
        }
    } // End render_cron_status()


    /**
     * Render the Save button and dirty-state reminder in the subheader.
     *
     * @param string $active_page
     * @return void
     */
    public function render_subheader_buttons( string $active_page ) : void {
        if ( Menu::MENU_SLUG . '_settings' !== $active_page || ! Access::can_manage() ) {
            return;
        }
        ?>
        <button type="button" class="ptscanner-button" id="ptscanner-save"><?php esc_html_e( 'Save', 'prohibited-terms-scanner' ); ?></button>
        <span id="ptscanner-save-reminder"><?php esc_html_e( 'Remember to click "Save" after making changes to your settings.', 'prohibited-terms-scanner' ); ?></span>
        <?php
    } // End render_subheader_buttons()


    /**
     * AJAX: save every settings field at once.
     *
     * @return void
     */
    public function ajax_save() : void {
        check_ajax_referer( self::NONCE, 'nonce' );

        if ( ! Access::can_manage() ) {
            wp_send_json_error( __( 'Insufficient permissions.', 'prohibited-terms-scanner' ), 403 );
        }

        $settings = isset( $_POST[ 'settings' ] ) ? wp_unslash( $_POST[ 'settings' ] ) : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- every value is sanitized individually in apply().

        if ( ! is_array( $settings ) ) {
            wp_send_json_error( __( 'Invalid data.', 'prohibited-terms-scanner' ) );
        }

        $this->apply( $settings );

        ob_start();
        $this->render_cron_status();

        wp_send_json_success( [ 'cronStatus' => ob_get_clean() ] );
    } // End ajax_save()


    /**
     * Sanitize and store the Settings tab values. Unchecked checkboxes arrive missing and are saved as off.
     *
     * @param array $settings Raw values keyed by field name.
     * @return void
     */
    public function apply( array $settings ) : void {
        $keys = function( string $field, array $allowed ) use ( $settings ) : array {
            $values = isset( $settings[ $field ] ) ? array_map( 'sanitize_key', (array) $settings[ $field ] ) : [];

            return array_values( array_intersect( $values, $allowed ) );
        };

        $role_slugs = array_keys( Access::get_assignable_roles() );

        update_option( self::OPT_LOCATION_TYPES, $keys( 'location_types', array_keys( TypeRegistry::instance()->get_types() ) ), false );
        update_option( self::OPT_POST_TYPES, $keys( 'post_types', array_keys( $this->get_selectable_post_types() ) ), false );
        update_option( self::OPT_TAXONOMIES, $keys( 'taxonomies', array_keys( get_taxonomies( [ 'public' => true ] ) ) ), false );
        update_option( self::OPT_VIEW_ROLES, $keys( 'view_roles', $role_slugs ), false );
        update_option( self::OPT_SCAN_ROLES, $keys( 'scan_roles', $role_slugs ), false );
        delete_option( self::OPT_LEGACY_SHORTCODE_ROLES );

        $numbers = [
            self::OPT_BATCH_SIZE      => [ 'batch_size', 20, 1, 500 ],
            self::OPT_SNIPPET_PADDING => [ 'snippet_padding', 60, 0, 500 ],
        ];

        foreach ( $numbers as $option => [ $field, $default, $min, $max ] ) {
            $value = isset( $settings[ $field ] ) && is_scalar( $settings[ $field ] ) ? absint( $settings[ $field ] ) : $default;
            update_option( $option, min( $max, max( $min, $value ) ), false );
        }

        $checkboxes = [
            self::OPT_PUBLIC_ONLY        => 'public_only',
            self::OPT_DEFAULT_CASE       => 'default_case_sensitive',
            self::OPT_DEFAULT_STRICT     => 'default_strict',
            self::OPT_WARNING_ENABLED    => 'warning_enabled',
            self::OPT_PDF_PAGE_LOOKUP    => 'pdf_page_lookup',
            self::OPT_CRON_ENABLED       => 'cron_enabled',
            self::OPT_CLEAR_ON_UNINSTALL => 'clear_data_on_uninstall',
        ];

        foreach ( $checkboxes as $option => $field ) {
            update_option( $option, ! empty( $settings[ $field ] ), false );
        }

        $frequency = isset( $settings[ 'cron_frequency' ] ) && is_string( $settings[ 'cron_frequency' ] ) ? sanitize_key( $settings[ 'cron_frequency' ] ) : 'daily';
        update_option( self::OPT_CRON_FREQUENCY, in_array( $frequency, self::CRON_FREQUENCIES, true ) ? $frequency : 'daily', false );

        if ( isset( $settings[ 'warning_terms_json' ] ) && is_string( $settings[ 'warning_terms_json' ] ) ) {
            $decoded = json_decode( $settings[ 'warning_terms_json' ], true );

            if ( is_array( $decoded ) ) {
                $this->save_warning_terms( $decoded );
            } elseif ( '' !== trim( $settings[ 'warning_terms_json' ] ) ) {
                ErrorLog::instance()->log( 'settings_save', 'Failed to decode warning_terms_json: ' . json_last_error_msg() );
            }
        }

        do_action( 'ptscanner_reschedule_cron' );

        /**
         * Fires after the Settings tab is saved.
         *
         * @param array $settings Raw submitted values. Sanitize anything you read from it.
         */
        do_action( 'ptscanner_settings_saved', $settings );
    } // End apply()


    /**
     * AJAX: reset every setting to its default.
     *
     * @return void
     */
    public function ajax_reset() : void {
        check_ajax_referer( self::NONCE, 'nonce' );

        if ( ! Access::can_manage() ) {
            wp_send_json_error( __( 'Insufficient permissions.', 'prohibited-terms-scanner' ), 403 );
        }

        foreach ( self::resettable_options() as $option ) {
            delete_option( $option );
        }

        do_action( 'ptscanner_reschedule_cron' );
        do_action( 'ptscanner_settings_reset' );

        wp_send_json_success();
    } // End ajax_reset()

} // End class Settings

Settings::instance();
