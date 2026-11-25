<?php
/**
 * INTEGRATION: ADMIN HELP DOCS
 *
 * Inherits Admin Help Docs' header, button and accent colors so both plugins look alike.
 */

namespace PluginRx\ProhibitedTermsScanner;

if ( ! defined( 'ABSPATH' ) ) exit;


if ( ! Integrations::is_active( 'admin-help-docs/admin-help-docs.php' ) ) {
    return;
}


/**
 * Pass Admin Help Docs' color theme to Prohibited Terms Scanner's screens.
 *
 * @param array $colors
 * @return array
 */
function ptscanner_ahd_theme_colors( array $colors ) : array {
    $ahd_colors = [];

    if ( class_exists( '\PluginRx\AdminHelpDocs\Colors' ) && method_exists( '\PluginRx\AdminHelpDocs\Colors', 'get' ) ) {
        $ahd_colors = \PluginRx\AdminHelpDocs\Colors::get();
    }

    if ( ! is_array( $ahd_colors ) || empty( $ahd_colors ) ) {
        $ahd_colors = get_option( 'helpdocs_colors', [] );
    }

    if ( ! is_array( $ahd_colors ) || empty( $ahd_colors ) ) {
        return $colors;
    }

    $map = [
        'header_bg'       => 'header-bg',
        'header_font'     => 'header-font',
        'header_tab'      => 'header-tab',
        'header_tab_link' => 'header-tab-link',
        'doc_accent'      => 'accent',
        'button'          => 'button',
        'button_font'     => 'button-font',
        'button_hover'    => 'button-hover',
    ];

    foreach ( $map as $ahd_key => $ptscanner_key ) {
        if ( ! empty( $ahd_colors[ $ahd_key ] ) ) {
            $colors[ $ptscanner_key ] = Helpers::hex( $ahd_colors[ $ahd_key ] );
        }
    }

    return $colors;
} // End ptscanner_ahd_theme_colors()

add_filter( 'ptscanner_theme_colors', __NAMESPACE__ . '\\ptscanner_ahd_theme_colors' );
