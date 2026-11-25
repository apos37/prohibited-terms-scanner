<?php
/**
 * INTEGRATIONS
 *
 * Helpers used by the integration files in /inc/integrations/{slug}/integration.php,
 * plus the core public-visibility check that integrations can refine.
 */

namespace PluginRx\ProhibitedTermsScanner;

if ( ! defined( 'ABSPATH' ) ) exit;


class Integrations {

    /**
     * Check if a plugin is active.
     *
     * @param string $plugin_file Plugin basename, e.g. admin-help-docs/admin-help-docs.php.
     * @return bool
     */
    public static function is_active( string $plugin_file ) : bool {
        if ( ! function_exists( 'is_plugin_active' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        return is_plugin_active( $plugin_file );
    } // End is_active()


    /**
     * Check whether a post is public-facing. Every published post is public unless an
     * integration (such as Deadbolt) or a developer says otherwise.
     *
     * @param int $post_id
     * @return bool
     */
    public static function is_public_post( int $post_id ) : bool {
        /**
         * Filter whether a post counts as public-facing.
         *
         * @param bool $is_public
         * @param int  $post_id
         */
        return (bool) apply_filters( 'ptscanner_is_public_post', true, $post_id );
    } // End is_public_post()

} // End class Integrations
