<?php
/**
 * Front-end shortcode + asset handling
 */

namespace PluginRx\ProhibitedTermsScanner;

if ( ! defined( 'ABSPATH' ) ) exit;

class FrontEnd {


    /**
     * Shortcode tag
     *
     * @var string
     */
    private string $shortcode_tag = 'prohibited_terms_scanner';


    /**
     * The single instance of the class
     *
     * @var self|null
     */
    private static ?FrontEnd $instance = null;


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
        add_shortcode( $this->shortcode_tag, [ $this, 'render_shortcode' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_highlight_script' ] );
    } // End __construct()


    /**
     * Whether the current user may use the shortcode
     *
     * @return bool
     */
    private function current_user_can_use_shortcode() : bool {
        if ( current_user_can( 'manage_options' ) ) {
            return true;
        }

        $user = wp_get_current_user();

        if ( ! $user || ! $user->exists() ) {
            return false;
        }

        $allowed_roles = Settings::instance()->get_shortcode_roles();

        return (bool) array_intersect( $allowed_roles, (array) $user->roles );
    } // End current_user_can_use_shortcode()


    /**
     * Render the shortcode output
     *
     * @param array $atts
     * @return string
     */
    public function render_shortcode( $atts ) : string {
        if ( ! $this->current_user_can_use_shortcode() ) {
            return '<p class="ptscanner-front-notice">' . esc_html__( 'You do not have permission to use this tool.', 'prohibited-terms-scanner' ) . '</p>';
        }

        $this->enqueue_scanner_assets();

        ob_start();
        require Bootstrap::path( 'views/shortcode-scanner.php' );

        return (string) ob_get_clean();
    } // End render_shortcode()


    /**
     * Enqueue the same scanner UI assets used on the admin page, front-end context
     *
     * @return void
     */
    private function enqueue_scanner_assets() {
        $textdomain     = Bootstrap::textdomain();
        $script_version = Bootstrap::version();

        wp_enqueue_style(
            $textdomain . '-admin',
            Bootstrap::url( 'inc/css/admin.css' ),
            [],
            $script_version
        );

        // Core handle: anchor for wp_localize_script, same pattern as admin-menu.php.
        wp_register_script( $textdomain . '-core', false, [ 'jquery' ], $script_version, true );
        wp_enqueue_script( $textdomain . '-core' );

        wp_localize_script( $textdomain . '-core', 'ptscanner_data', [
            'ajaxUrl'              => admin_url( 'admin-ajax.php' ),
            'nonce'                => wp_create_nonce( 'ptscanner_nonce' ),
            'savedTerms'           => Settings::instance()->get_terms(),
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
            ],
        ] );

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
    } // End enqueue_scanner_assets()


    /**
     * Conditionally enqueue the highlight/blink script on singular content pages
     *
     * @return void
     */
    public function enqueue_highlight_script() {
        if ( ! is_singular() ) {
            return;
        }

        // Only load the script if the query var is actually present, avoiding
        // an unnecessary script on every single page load site-wide.
        if ( ! isset( $_GET[ 'ptscanner_term' ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            return;
        }

        $textdomain     = Bootstrap::textdomain();
        $script_version = Bootstrap::version();

        wp_enqueue_style(
            $textdomain . '-highlight',
            Bootstrap::url( 'inc/css/highlight.css' ),
            [],
            $script_version
        );

        wp_enqueue_script(
            $textdomain . '-front-highlight',
            Bootstrap::url( 'inc/js/front-highlight.js' ),
            [ 'jquery' ],
            $script_version,
            true
        );
    } // End enqueue_highlight_script()

}


FrontEnd::instance();