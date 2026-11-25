<?php
/**
 * INTEGRATION: DEADBOLT
 *
 * Uses Deadbolt's access rules to decide whether a post is public-facing: a post is
 * public when a logged-out visitor can open it from a plain link, or when it uses
 * URL Parameter access (anyone with the link can see it).
 */

namespace PluginRx\ProhibitedTermsScanner;

if ( ! defined( 'ABSPATH' ) ) exit;


if ( ! Integrations::is_active( 'deadbolt/deadbolt.php' ) || ! class_exists( '\PluginRx\Deadbolt\Access' ) ) {
    return;
}


/**
 * Mark posts that Deadbolt restricts as not public.
 *
 * @param bool $is_public
 * @param int  $post_id
 * @return bool
 */
function ptscanner_deadbolt_is_public( bool $is_public, int $post_id ) : bool {
    if ( ! $is_public ) {
        return false;
    }

    $rule = \PluginRx\Deadbolt\Access::get_rule( $post_id );

    if ( 'url-param' === ( $rule[ 'access' ] ?? '' ) ) {
        return (bool) apply_filters( 'ptscanner_deadbolt_url_param_is_public', true, $post_id );
    }

    return \PluginRx\Deadbolt\Access::user_has_access( $post_id, 0 );
} // End ptscanner_deadbolt_is_public()

add_filter( 'ptscanner_is_public_post', __NAMESPACE__ . '\\ptscanner_deadbolt_is_public', 10, 2 );
