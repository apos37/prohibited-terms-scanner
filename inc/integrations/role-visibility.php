<?php
/**
 * Resolves effective page visibility from the Role Visibility plugin (current
 * and legacy versions), used to determine whether a page is public-facing
 */

namespace PluginRx\ProhibitedTermsScanner;

if ( ! defined( 'ABSPATH' ) ) exit;

class RoleVisibility {


    /**
     * Visibility values considered "public" (no login or specific role required)
     *
     * @var array
     */
    private array $public_values = [ 'everyone', 'logged-out' ];


    /**
     * The single instance of the class
     *
     * @var self|null
     */
    private static ?RoleVisibility $instance = null;


    /**
     * Get the singleton instance
     *
     * @return self
     */
    public static function instance() : self {
        return self::$instance ??= new self();
    } // End instance()


    /**
     * Whether a given post is public-facing per Role Visibility settings
     *
     * @param int $post_id
     * @return bool
     */
    public function is_public( int $post_id ) : bool {
        $value = $this->resolve_visibility( $post_id );

        return in_array( $value, $this->public_values, true );
    } // End is_public()


    /**
     * Resolve the effective visibility value for a post, checking current
     * meta/option first, then falling back to the legacy plugin's meta/option
     *
     * @param int $post_id
     * @return string
     */
    private function resolve_visibility( int $post_id ) : string {
        $current = get_post_meta( $post_id, 'role_visibility', true );

        if ( '' !== $current ) {
            if ( 'default' === $current ) {
                return $this->get_current_default( get_post_type( $post_id ) );
            }

            return $current;
        }

        $legacy = get_post_meta( $post_id, '_role_visibility', true );

        if ( '' !== $legacy ) {
            if ( 'default' === $legacy ) {
                return $this->get_legacy_default();
            }

            return $legacy;
        }

        $post_type_default = $this->get_current_default( get_post_type( $post_id ) );

        if ( '' !== $post_type_default ) {
            return $post_type_default;
        }

        return $this->get_legacy_default();
    } // End resolve_visibility()


    /**
     * Get the current-plugin default visibility for a given post type
     *
     * @param string $post_type
     * @return string
     */
    private function get_current_default( string $post_type ) : string {
        $defaults = get_option( 'role_visibility_post_types', [] );

        if ( ! is_array( $defaults ) ) {
            return '';
        }

        foreach ( $defaults as $entry ) {
            if ( isset( $entry[ 'post_type' ], $entry[ 'default' ] ) && $post_type === $entry[ 'post_type' ] ) {
                return (string) $entry[ 'default' ];
            }
        }

        return '';
    } // End get_current_default()


    /**
     * Get the legacy-plugin site-wide default visibility
     *
     * @return string
     */
    private function get_legacy_default() : string {
        $default = get_option( 'eri_role_visibility_def', '' );

        return is_string( $default ) ? $default : '';
    } // End get_legacy_default()

}


RoleVisibility::instance();