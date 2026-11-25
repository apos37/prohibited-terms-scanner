<?php
/**
 * ACCESS
 *
 * Capability checks for the admin tabs, AJAX actions and the front-end shortcode.
 * Administrators always have full access. Scan roles can run scans and act on results.
 * View roles can see results. Settings and Errors stay administrators only.
 */

namespace PluginRx\ProhibitedTermsScanner;

if ( ! defined( 'ABSPATH' ) ) exit;


class Access {

    /**
     * Capability that always has full access, including Settings.
     */
    public const MANAGE_CAP = 'manage_options';


    /**
     * Get the roles that can be granted access (every role except administrator).
     *
     * @return array Slug => translated name.
     */
    public static function get_assignable_roles() : array {
        $roles = [];

        foreach ( wp_roles()->roles as $slug => $role ) {
            if ( 'administrator' !== $slug ) {
                $roles[ $slug ] = translate_user_role( $role[ 'name' ] );
            }
        }

        return (array) apply_filters( 'ptscanner_assignable_roles', $roles );
    } // End get_assignable_roles()


    /**
     * Check whether the current user has any of the given roles.
     *
     * @param array $roles
     * @return bool
     */
    private static function user_has_role( array $roles ) : bool {
        $user = wp_get_current_user();

        return $user->exists() && ! empty( array_intersect( $roles, (array) $user->roles ) );
    } // End user_has_role()


    /**
     * Check whether the current user can manage settings.
     *
     * @return bool
     */
    public static function can_manage() : bool {
        return current_user_can( self::MANAGE_CAP );
    } // End can_manage()


    /**
     * Check whether the current user can run scans and act on results.
     *
     * @return bool
     */
    public static function can_scan() : bool {
        $can = self::can_manage() || self::user_has_role( Settings::instance()->get_scan_roles() );

        /**
         * Filter whether the current user can run scans and act on results.
         *
         * @param bool $can
         * @param int  $user_id
         */
        return (bool) apply_filters( 'ptscanner_can_scan', $can, get_current_user_id() );
    } // End can_scan()


    /**
     * Check whether the current user can view results.
     *
     * @return bool
     */
    public static function can_view() : bool {
        $can = self::can_scan() || self::user_has_role( Settings::instance()->get_view_roles() );

        /**
         * Filter whether the current user can view results.
         *
         * @param bool $can
         * @param int  $user_id
         */
        return (bool) apply_filters( 'ptscanner_can_view', $can, get_current_user_id() );
    } // End can_view()

} // End class Access
