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
        return Access::can_scan();
    } // End current_user_can_use_shortcode()


    /**
     * Render the shortcode output
     *
     * @return string
     */
    public function render_shortcode() : string {
        if ( ! $this->current_user_can_use_shortcode() ) {
            return '<p class="ptscanner-front-notice">' . esc_html__( 'You do not have permission to use this tool.', 'prohibited-terms-scanner' ) . '</p>';
        }

        $this->enqueue_scanner_assets();

        ob_start();
        require Bootstrap::path( 'views/shortcode-scanner.php' );

        return (string) ob_get_clean();
    } // End render_shortcode()


    /**
     * Enqueue the same styles and scanner scripts used on the Scanner tab
     *
     * @return void
     */
    private function enqueue_scanner_assets() : void {
        Helpers::enqueue_styles( 'frontend' );
        Helpers::enqueue_core_script();
        Helpers::enqueue_scripts( [ 'terms-ui', 'results', 'scanner' ] );
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
        $script_version = Bootstrap::script_version();

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