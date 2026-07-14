<?php
/**
 * Admin menu registration
 */

namespace PluginRx\ProhibitedTermsScanner;

if ( ! defined( 'ABSPATH' ) ) exit;

class AdminMenu {


    /**
     * The single instance of the class
     *
     * @var self|null
     */
    private static ?AdminMenu $instance = null;


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
        add_action( 'admin_menu', [ $this, 'register_menu' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
    } // End __construct()


    /**
     * Register the top-level menu and submenus
     *
     * @return void
     */
    public function register_menu() {
        $textdomain = Bootstrap::textdomain();

        add_menu_page(
            Bootstrap::name(),
            __( 'Term Scanner', 'prohibited-terms-scanner' ),
            'manage_options',
            $textdomain,
            [ $this, 'render_scanner_page' ],
            'dashicons-search',
            80
        );

        add_submenu_page(
            $textdomain,
            __( 'Scanner', 'prohibited-terms-scanner' ),
            __( 'Scanner', 'prohibited-terms-scanner' ),
            'manage_options',
            $textdomain,
            [ $this, 'render_scanner_page' ]
        );

        add_submenu_page(
            $textdomain,
            __( 'Results', 'prohibited-terms-scanner' ),
            __( 'Results', 'prohibited-terms-scanner' ),
            'manage_options',
            $textdomain . '_results',
            [ $this, 'render_results_page' ]
        );

        add_submenu_page(
            $textdomain,
            __( 'Settings', 'prohibited-terms-scanner' ),
            __( 'Settings', 'prohibited-terms-scanner' ),
            'manage_options',
            $textdomain . '_settings',
            [ $this, 'render_settings_page' ]
        );

        add_submenu_page(
            $textdomain,
            __( 'Import/Export', 'prohibited-terms-scanner' ),
            __( 'Import/Export', 'prohibited-terms-scanner' ),
            'manage_options',
            $textdomain . '_import_export',
            [ $this, 'render_import_export_page' ]
        );
    } // End register_menu()


    /**
     * Get this plugin's admin screen IDs, used to gate asset enqueuing
     *
     * @return array
     */
    private function screen_ids() : array {
        $textdomain = Bootstrap::textdomain();

        return [
            'toplevel_page_' . $textdomain,
            $textdomain . '_page_' . $textdomain . '_results',
            $textdomain . '_page_' . $textdomain . '_settings',
            $textdomain . '_page_' . $textdomain . '_import_export',
        ];
    } // End screen_ids()


    /**
     * Enqueue admin scripts/styles, only on this plugin's screens
     *
     * @param string $hook
     * @return void
     */
    public function enqueue_scripts( $hook ) {
        global $current_screen;

        if ( ! $current_screen || ! in_array( $current_screen->id, $this->screen_ids(), true ) ) {
            return;
        }

        $textdomain     = Bootstrap::textdomain();
        $script_version = Bootstrap::version();

        wp_enqueue_style(
            $textdomain . '-admin',
            Bootstrap::url( 'inc/css/admin.css' ),
            [],
            $script_version
        );

        // Core handle: no real JS, just an anchor for wp_localize_script so
        // ptscanner_data is available before any screen-specific script runs.
        wp_register_script( $textdomain . '-core', false, [ 'jquery' ], $script_version, true );
        wp_enqueue_script( $textdomain . '-core' );

        wp_localize_script( $textdomain . '-core', 'ptscanner_data', [
            'ajaxUrl'              => admin_url( 'admin-ajax.php' ),
            'nonce'                => wp_create_nonce( 'ptscanner_nonce' ),
            'importExportNonce'    => wp_create_nonce( ImportExport::instance()->nonce_action() ),
            'savedTerms'           => Settings::instance()->get_terms(),
            'savedWarningTerms'    => Settings::instance()->get_warning_terms(),
            'locationTypes'        => TypeRegistry::instance()->get_grouped_types(),
            'enabledTypes'         => Settings::instance()->get_enabled_location_types(),
            'defaultCaseSensitive' => Settings::instance()->get_default_case_sensitive(),
            'defaultStrict'        => Settings::instance()->get_default_strict(),
            'strings'              => [
                'scanning'           => __( 'Scanning', 'prohibited-terms-scanner' ),
                'done'               => __( 'Scan complete.', 'prohibited-terms-scanner' ),
                'noTerms'            => __( 'Add at least one term or phrase first.', 'prohibited-terms-scanner' ),
                'confirmClear'       => __( 'Clear this result? This cannot be undone.', 'prohibited-terms-scanner' ),
                'duplicateTerm'      => __( 'Already in the list, skipped.', 'prohibited-terms-scanner' ),
                'requestFailed'      => __( 'Request failed (network or server error).', 'prohibited-terms-scanner' ),
                'cancelling'         => __( 'Cancelling…', 'prohibited-terms-scanner' ),
                'cancelled'          => __( 'Scan cancelled. Results so far are saved.', 'prohibited-terms-scanner' ),
                'caseSensitiveLabel' => __( 'Case Sensitive', 'prohibited-terms-scanner' ),
                'strictLabel'        => __( 'Strict', 'prohibited-terms-scanner' ),
                'loading'            => __( 'Loading…', 'prohibited-terms-scanner' ),
                'noResults'          => __( 'No results found.', 'prohibited-terms-scanner' ),
                'selectFile'         => __( 'Please select a file first.', 'prohibited-terms-scanner' ),
                'confirmImport'      => __( 'This will overwrite your current terms and settings. Continue?', 'prohibited-terms-scanner' ),
            ],
        ] );

        // Terms UI + scanner: scanner page and results page (results page needs
        // terms-ui/scanner absent, but keeping the check simple, only load on scanner page).
        if ( $current_screen->id === 'toplevel_page_' . $textdomain ) {
            wp_enqueue_script(
                $textdomain . '-terms-ui',
                Bootstrap::url( 'inc/js/terms-ui.js' ),
                [ $textdomain . '-core' ],
                $script_version,
                true
            );

            wp_enqueue_script(
                $textdomain . '-results',
                Bootstrap::url( 'inc/js/results.js' ),
                [ $textdomain . '-core' ],
                $script_version,
                true
            );

            wp_enqueue_script(
                $textdomain . '-scanner',
                Bootstrap::url( 'inc/js/scanner.js' ),
                [ $textdomain . '-core', $textdomain . '-terms-ui', $textdomain . '-results' ],
                $script_version,
                true
            );
        }

        // Results page.
        if ( $current_screen->id === $textdomain . '_page_' . $textdomain . '_results' ) {
            wp_enqueue_script(
                $textdomain . '-results',
                Bootstrap::url( 'inc/js/results.js' ),
                [ $textdomain . '-core' ],
                $script_version,
                true
            );
        }

        // Settings page.
        if ( $current_screen->id === $textdomain . '_page_' . $textdomain . '_settings' ) {
            wp_enqueue_script(
                $textdomain . '-warning-terms-ui',
                Bootstrap::url( 'inc/js/warning-terms-ui.js' ),
                [ $textdomain . '-core' ],
                $script_version,
                true
            );
        }

        // Import/Export page.
        if ( $current_screen->id === $textdomain . '_page_' . $textdomain . '_import_export' ) {
            wp_enqueue_script(
                $textdomain . '-import-export',
                Bootstrap::url( 'inc/js/import-export.js' ),
                [ $textdomain . '-core' ],
                $script_version,
                true
            );
        }
    } // End enqueue_scripts()


    /**
     * Render: Scanner page
     *
     * @return void
     */
    public function render_scanner_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        require Bootstrap::path( 'views/scanner-page.php' );
    } // End render_scanner_page()


    /**
     * Render: Results page
     *
     * @return void
     */
    public function render_results_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $status = isset( $_GET[ 'status' ] ) ? sanitize_key( wp_unslash( $_GET[ 'status' ] ) ) : 'flagged'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $status = in_array( $status, [ 'flagged', 'ignored' ], true ) ? $status : 'flagged';
        $page   = isset( $_GET[ 'paged' ] ) ? absint( wp_unslash( $_GET[ 'paged' ] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $page   = max( 1, $page );

        $data = ResultsPageData::instance()->get_page( $status, $page );

        require Bootstrap::path( 'views/results-page.php' );
    } // End render_results_page()


    /**
     * Render: Settings page
     *
     * @return void
     */
    public function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        require Bootstrap::path( 'views/settings-page.php' );
    } // End render_settings_page()


    /**
     * Render: Import/Export page
     *
     * @return void
     */
    public function render_import_export_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        require Bootstrap::path( 'views/import-export-page.php' );
    } // End render_import_export_page()

}


AdminMenu::instance();