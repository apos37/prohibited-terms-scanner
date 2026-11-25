<?php
/**
 * DYNAMIC CSS
 *
 * Serves the settings-driven CSS (theme colors) as a real stylesheet so it can be
 * enqueued instead of printed inline. The URL carries a hash of the content, so
 * browsers cache it until something changes.
 */

namespace PluginRx\ProhibitedTermsScanner;

if ( ! defined( 'ABSPATH' ) ) exit;


class DynamicCss {

    /**
     * AJAX action name.
     */
    public const ACTION = 'ptscanner_css';


    /**
     * Allowed stylesheet types.
     */
    public const TYPES = [ 'admin', 'frontend' ];


    /**
     * @var DynamicCss|null Singleton instance
     */
    private static ?DynamicCss $instance = null;


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
        add_action( 'wp_ajax_' . self::ACTION, [ $this, 'serve' ] );
        add_action( 'wp_ajax_nopriv_' . self::ACTION, [ $this, 'serve' ] );
    } // End __construct()


    /**
     * Get the stylesheet URL for a type.
     *
     * @param string $type
     * @return string
     */
    public static function url( string $type ) : string {
        return add_query_arg( [ 'action' => self::ACTION, 'type' => $type ], admin_url( 'admin-ajax.php' ) );
    } // End url()


    /**
     * Get the version string for a type, which changes whenever the CSS changes.
     *
     * @param string $type
     * @return string
     */
    public static function version( string $type ) : string {
        return substr( md5( self::build( $type ) ), 0, 12 );
    } // End version()


    /**
     * Enqueue a dynamic stylesheet.
     *
     * @param string $handle
     * @param string $type
     * @param array  $deps
     * @return void
     */
    public static function enqueue( string $handle, string $type, array $deps = [] ) : void {
        wp_enqueue_style( $handle, self::url( $type ), $deps, self::version( $type ) );
    } // End enqueue()


    /**
     * Build the CSS for a type. Both types currently carry the theme color variables.
     *
     * @param string $type
     * @return string
     */
    public static function build( string $type ) : string {
        $css = self::theme_css();

        /**
         * Filter the dynamic CSS for a stylesheet type.
         *
         * @param string $css
         * @param string $type admin|frontend
         */
        return (string) apply_filters( 'ptscanner_dynamic_css', $css, $type );
    } // End build()


    /**
     * Get theme color overrides. Empty by default; the Admin Help Docs integration
     * hooks 'ptscanner_theme_colors' to provide matching colors.
     *
     * Expected keys: header-bg, header-font, header-tab, header-tab-link,
     * accent, button, button-font, button-hover.
     *
     * @return array
     */
    public static function get_theme_colors() : array {
        return (array) apply_filters( 'ptscanner_theme_colors', [] );
    } // End get_theme_colors()


    /**
     * Build the CSS variables for the theme colors.
     *
     * @return string
     */
    public static function theme_css() : string {
        $colors = self::get_theme_colors();
        $declarations = [];

        foreach ( $colors as $var => $value ) {
            $value = Helpers::hex( $value );

            if ( '' !== $value ) {
                $declarations[] = '--ptscanner-color-' . sanitize_key( $var ) . ':' . $value . ';';
            }
        }

        $header_bg = Helpers::hex( $colors[ 'header-bg' ] ?? '', '#1D2327' );
        $declarations[] = '--ptscanner-header-text:' . Helpers::text_color( $header_bg ) . ';';

        return ':root{' . implode( '', $declarations ) . '}';
    } // End theme_css()


    /**
     * AJAX: output a stylesheet.
     *
     * @return void
     */
    public function serve() : void {
        $type = isset( $_GET[ 'type' ] ) ? sanitize_key( wp_unslash( $_GET[ 'type' ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public, read-only stylesheet.

        if ( ! in_array( $type, self::TYPES, true ) ) {
            status_header( 404 );
            exit;
        }

        header( 'Content-Type: text/css; charset=UTF-8' );
        header( 'Cache-Control: public, max-age=31536000' );
        header_remove( 'Pragma' );
        header_remove( 'Expires' );

        echo wp_strip_all_tags( self::build( $type ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from validated hex colors, with tags stripped.
        exit;
    } // End serve()

} // End class DynamicCss

DynamicCss::instance();
