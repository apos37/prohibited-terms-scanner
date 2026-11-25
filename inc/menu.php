<?php
/**
 * MENU
 *
 * Registers the admin menu and tabs, renders the shared header and subheader,
 * and enqueues the admin assets for each tab.
 */

namespace PluginRx\ProhibitedTermsScanner;

if ( ! defined( 'ABSPATH' ) ) exit;


class Menu {

    /**
     * Top-level menu slug. Matches the text domain so existing links keep working.
     */
    public const MENU_SLUG = 'prohibited-terms-scanner';


    /**
     * @var array|null Cached tab definitions
     */
    private static ?array $tabs = null;


    /**
     * @var Menu|null Singleton instance
     */
    private static ?Menu $instance = null;


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
        add_action( 'admin_menu', [ $this, 'register_menu' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'in_admin_header', [ $this, 'maybe_render_header' ] );
        add_action( 'ptscanner_subheader_right', [ $this, 'render_settings_links' ] );
    } // End __construct()


    /**
     * Get the tabs in order.
     *
     * Each tab: label, access (view|scan|manage), and render callback.
     *
     * @return array Page slug => tab definition.
     */
    public static function get_tabs() : array {
        if ( null !== self::$tabs ) {
            return self::$tabs;
        }

        $tabs = [
            self::MENU_SLUG                     => [
                'label'    => __( 'Scanner', 'prohibited-terms-scanner' ),
                'access'   => 'scan',
                'callback' => [ self::class, 'render_scanner_page' ],
            ],
            self::MENU_SLUG . '_results'        => [
                'label'    => __( 'Results', 'prohibited-terms-scanner' ),
                'access'   => 'view',
                'callback' => [ self::class, 'render_results_page' ],
                'count'    => true,
            ],
            self::MENU_SLUG . '_external_links' => [
                'label'    => __( 'External Links', 'prohibited-terms-scanner' ),
                'access'   => 'view',
                'callback' => [ self::class, 'render_external_links_page' ],
            ],
            self::MENU_SLUG . '_omitted'        => [
                'label'    => __( 'Omitted Sources', 'prohibited-terms-scanner' ),
                'access'   => 'scan',
                'callback' => [ self::class, 'render_omitted_page' ],
            ],
            self::MENU_SLUG . '_errors'         => [
                'label'    => __( 'Errors', 'prohibited-terms-scanner' ),
                'access'   => 'manage',
                'callback' => [ self::class, 'render_errors_page' ],
            ],
            self::MENU_SLUG . '_settings'       => [
                'label'    => __( 'Settings', 'prohibited-terms-scanner' ),
                'access'   => 'manage',
                'callback' => [ self::class, 'render_settings_page' ],
            ],
        ];

        /**
         * Filter the admin tabs. Add a tab with a unique slug, a label, an access level
         * (view, scan or manage) and a callable that outputs the tab's content.
         *
         * @param array $tabs Page slug => [ label, access, callback ].
         */
        $tabs = (array) apply_filters( 'ptscanner_admin_tabs', $tabs );

        self::$tabs = array_filter( $tabs, function( $tab ) {
            return is_array( $tab ) && ! empty( $tab[ 'label' ] ) && isset( $tab[ 'callback' ] ) && is_callable( $tab[ 'callback' ] );
        } );

        return self::$tabs;
    } // End get_tabs()


    /**
     * Check whether the current user can open a tab.
     *
     * @param array $tab
     * @return bool
     */
    public static function user_can_access( array $tab ) : bool {
        switch ( $tab[ 'access' ] ?? 'manage' ) {
            case 'view':
                return Access::can_view();
            case 'scan':
                return Access::can_scan();
            default:
                return Access::can_manage();
        }
    } // End user_can_access()


    /**
     * Register the top-level menu and the tab submenus.
     *
     * @return void
     */
    public function register_menu() : void {
        if ( ! Access::can_view() ) {
            return;
        }

        $count = DB::instance()->get_flagged_count();
        $bubble = $count > 0 ? ' <span class="update-plugins count-' . absint( $count ) . '"><span class="update-count">' . esc_html( number_format_i18n( $count ) ) . '</span></span>' : '';
        $tabs = array_filter( self::get_tabs(), [ self::class, 'user_can_access' ] );

        if ( empty( $tabs ) ) {
            return;
        }

        $first_slug = array_key_first( $tabs );

        add_menu_page( Bootstrap::name(), __( 'Prohibited Terms Scanner', 'prohibited-terms-scanner' ) . $bubble, 'read', $first_slug, [ $this, 'render_current_tab' ], 'dashicons-search', 80 );

        foreach ( $tabs as $slug => $tab ) {
            $label = ! empty( $tab[ 'count' ] ) ? $tab[ 'label' ] . $bubble : $tab[ 'label' ];

            add_submenu_page( $first_slug, $tab[ 'label' ], $label, 'read', $slug, [ $this, 'render_current_tab' ] );
        }
    } // End register_menu()


    /**
     * Render the current tab's content, after checking access.
     *
     * @return void
     */
    public function render_current_tab() : void {
        $tabs = self::get_tabs();
        $slug = Helpers::current_page_slug();

        if ( ! isset( $tabs[ $slug ] ) || ! self::user_can_access( $tabs[ $slug ] ) ) {
            wp_die( esc_html__( 'You do not have permission to view this page.', 'prohibited-terms-scanner' ) );
        }

        call_user_func( $tabs[ $slug ][ 'callback' ] );
    } // End render_current_tab()


    /**
     * Render the header and subheader on in_admin_header, before any admin notices, but only on our tabs.
     *
     * @return void
     */
    public function maybe_render_header() : void {
        if ( ! Helpers::is_plugin_screen() ) {
            return;
        }

        $active_page = Helpers::current_page_slug();
        $tabs = self::get_tabs();

        self::render_header( $active_page );
        self::render_subheader( $tabs[ $active_page ][ 'label' ] ?? '', $active_page );
    } // End maybe_render_header()


    /**
     * Render the shared top header: logo, title, and tabs.
     *
     * @param string $active_page
     * @return void
     */
    public static function render_header( string $active_page ) : void {
        $count = DB::instance()->get_flagged_count();
        /* translators: %s: plugin version number. */
        $version = sprintf( __( 'Version %s', 'prohibited-terms-scanner' ), Bootstrap::version() );
        ?>
        <div id="ptscanner-header">
            <img src="<?php echo esc_url( self::get_logo() ); ?>" alt="" class="logo" title="<?php echo esc_attr( $version ); ?>">

            <div class="title-cont">
                <h1 title="<?php echo esc_attr( $version ); ?>"><?php echo esc_html( Bootstrap::name() ); ?></h1>
            </div>

            <div class="tabs-wrapper">
                <?php foreach ( self::get_tabs() as $slug => $tab ) : ?>
                    <?php
                    if ( ! self::user_can_access( $tab ) ) {
                        continue;
                    }

                    $class = ( $active_page === $slug ) ? 'ptscanner-tab ptscanner-tab-active' : 'ptscanner-tab';
                    ?>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $slug ) ); ?>" class="<?php echo esc_attr( $class ); ?>">
                        <?php echo esc_html( $tab[ 'label' ] ); ?>
                        <?php if ( ! empty( $tab[ 'count' ] ) && $count > 0 ) : ?>
                            <span class="ptscanner-tab-count"><?php echo esc_html( number_format_i18n( $count ) ); ?></span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    } // End render_header()


    /**
     * Get the header logo URL.
     *
     * @return string
     */
    public static function get_logo() : string {
        $default = add_query_arg( 'ver', Bootstrap::script_version(), Bootstrap::url( 'inc/img/logo.png' ) );

        return (string) apply_filters( 'ptscanner_header_logo', $default );
    } // End get_logo()


    /**
     * Render the white subheader bar: tab title on the left, actions on the right.
     *
     * @param string $title
     * @param string $active_page
     * @return void
     */
    public static function render_subheader( string $title, string $active_page ) : void {
        ?>
        <div id="ptscanner-subheader">
            <div class="subheader-left">
                <h2 class="tab-title"><?php echo esc_html( $title ); ?></h2>
                <?php do_action( 'ptscanner_subheader_left', $active_page ); ?>
            </div>
            <div class="subheader-right">
                <?php do_action( 'ptscanner_subheader_right', $active_page ); ?>
            </div>
        </div>
        <?php
    } // End render_subheader()


    /**
     * Render the version and the guide, docs and support links on the Settings tab.
     *
     * @param string $active_page
     * @return void
     */
    public function render_settings_links( string $active_page ) : void {
        if ( self::MENU_SLUG . '_settings' !== $active_page ) {
            return;
        }
        ?>
        <span id="ptscanner-version">
            <?php
            /* translators: %s: plugin version number. */
            echo esc_html( sprintf( __( 'Version %s', 'prohibited-terms-scanner' ), Bootstrap::version() ) );
            ?>
        </span>
        <?php
        foreach ( PluginPage::links() as $link ) {
            ?>
            <a href="<?php echo esc_url( $link[ 'url' ] ); ?>" target="_blank" rel="noopener noreferrer" class="ptscanner-button ptscanner-external-link"><?php echo esc_html( $link[ 'label' ] ); ?> <span class="dashicons dashicons-external"></span></a>
            <?php
        }
    } // End render_settings_links()


    /**
     * Enqueue the shared theme and each tab's own assets.
     *
     * @return void
     */
    public function enqueue_assets() : void {
        if ( ! Helpers::is_plugin_screen() ) {
            return;
        }

        $page = Helpers::current_page_slug();

        Helpers::enqueue_styles( 'admin' );

        Helpers::enqueue_core_script( self::script_data() );

        $scripts = [
            self::MENU_SLUG                     => [ 'terms-ui', 'results', 'scanner' ],
            self::MENU_SLUG . '_results'        => [ 'results' ],
            self::MENU_SLUG . '_errors'         => [ 'results' ],
            self::MENU_SLUG . '_omitted'        => [ 'results' ],
            self::MENU_SLUG . '_external_links' => [ 'results', 'external-links' ],
            self::MENU_SLUG . '_settings'       => [ 'warning-terms-ui', 'settings' ],
        ];

        Helpers::enqueue_scripts( $scripts[ $page ] ?? [] );

        do_action( 'ptscanner_admin_enqueue', $page );
    } // End enqueue_assets()


    /**
     * Admin-only data merged over Helpers::script_data() for ptscanner_data.
     *
     * @return array
     */
    private static function script_data() : array {
        $data = [
            'savedWarningTerms' => Settings::instance()->get_warning_terms(),
        ];

        if ( self::MENU_SLUG . '_settings' === Helpers::current_page_slug() && Access::can_manage() ) {
            $data[ 'settings' ] = [
                'nonce'         => wp_create_nonce( Settings::NONCE ),
                'savingText'    => __( 'Saving', 'prohibited-terms-scanner' ),
                'savedText'     => __( 'Settings saved successfully.', 'prohibited-terms-scanner' ),
                'errorText'     => __( 'Error saving settings. Please try again.', 'prohibited-terms-scanner' ),
                'unsavedText'   => __( 'You have unsaved changes.', 'prohibited-terms-scanner' ),
                'importError'   => __( 'That file is not a valid Prohibited Terms Scanner export.', 'prohibited-terms-scanner' ),
                'resetOk'       => __( 'Settings reset. Reloading...', 'prohibited-terms-scanner' ),
                'confirmReset'  => __( 'Reset all settings to their defaults? Your terms, scan results, notes and omitted sources are kept.', 'prohibited-terms-scanner' ),
                'confirmImport' => __( 'Importing will overwrite your current terms and settings. Continue?', 'prohibited-terms-scanner' ),
            ];
        }

        return (array) apply_filters( 'ptscanner_admin_script_data', $data );
    } // End script_data()


    /**
     * Render: Scanner tab
     *
     * @return void
     */
    public static function render_scanner_page() : void {
        require Bootstrap::path( 'views/scanner-page.php' );
    } // End render_scanner_page()


    /**
     * Render: Results tab
     *
     * @return void
     */
    public static function render_results_page() : void {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only filters and pagination.
        $status = isset( $_GET[ 'status' ] ) ? sanitize_key( wp_unslash( $_GET[ 'status' ] ) ) : 'flagged';
        $status = in_array( $status, [ 'flagged', 'ignored' ], true ) ? $status : 'flagged';
        $page = isset( $_GET[ 'paged' ] ) ? max( 1, absint( wp_unslash( $_GET[ 'paged' ] ) ) ) : 1;
        $term_filter = isset( $_GET[ 'term_filter' ] ) ? sanitize_text_field( wp_unslash( $_GET[ 'term_filter' ] ) ) : '';
        $location_filter = isset( $_GET[ 'location_filter' ] ) ? sanitize_text_field( wp_unslash( $_GET[ 'location_filter' ] ) ) : '';
        // phpcs:enable WordPress.Security.NonceVerification.Recommended

        $data = ResultsPageData::instance()->get_page( $status, $page, $term_filter, $location_filter );
        $data[ 'distinct_terms' ] = DB::instance()->get_distinct_terms( $status );
        $data[ 'distinct_locations' ] = DB::instance()->get_distinct_location_combos( $status );

        require Bootstrap::path( 'views/results-page.php' );
    } // End render_results_page()


    /**
     * Render: External Links tab
     *
     * @return void
     */
    public static function render_external_links_page() : void {
        require Bootstrap::path( 'views/external-links-page.php' );
    } // End render_external_links_page()


    /**
     * Render: Omitted Sources tab
     *
     * @return void
     */
    public static function render_omitted_page() : void {
        require Bootstrap::path( 'views/omitted-sources-page.php' );
    } // End render_omitted_page()


    /**
     * Render: Errors tab
     *
     * @return void
     */
    public static function render_errors_page() : void {
        $errors = ErrorLog::instance()->get_errors();

        require Bootstrap::path( 'views/errors-page.php' );
    } // End render_errors_page()


    /**
     * Render: Settings tab
     *
     * @return void
     */
    public static function render_settings_page() : void {
        require Bootstrap::path( 'views/settings-page.php' );
    } // End render_settings_page()

} // End class Menu

Menu::instance();
