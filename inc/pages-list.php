<?php
/**
 * Pages List — lets users quickly see and omit pages without digging through results
 */

namespace PluginRx\ProhibitedTermsScanner;

if ( ! defined( 'ABSPATH' ) ) exit;

class PagesList {


    /**
     * The single instance of the class
     *
     * @var self|null
     */
    private static ?PagesList $instance = null;


    /**
     * Get the singleton instance
     *
     * @return self
     */
    public static function instance() : self {
        return self::$instance ??= new self();
    } // End instance()


    /**
     * Get all pages/posts across enabled post types, with omit status, for the Pages List tab
     *
     * @param int $page
     * @param int $per_page
     * @return array [ 'rows' => array, 'total_pages' => int ]
     */
    public function get_page( int $page = 1, int $per_page = 50 ) : array {
        $post_types = Settings::instance()->get_enabled_post_types();
        $excluded   = Settings::instance()->get_excluded_post_types();
        $post_types = array_diff( $post_types, $excluded );

        if ( empty( $post_types ) ) {
            return [ 'rows' => [], 'total_pages' => 0 ];
        }

        $query = new \WP_Query( [
            'post_type'      => array_values( $post_types ),
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'fields'         => 'ids',
            'no_found_rows'  => true,
        ] );

        $public_ids = array_values( array_filter( $query->posts, function ( $post_id ) {
            return RoleVisibility::instance()->is_public( $post_id );
        } ) );

        $total_pages = (int) ceil( count( $public_ids ) / $per_page );
        $offset      = ( max( 1, $page ) - 1 ) * $per_page;
        $page_ids    = array_slice( $public_ids, $offset, $per_page );

        $rows = [];

        foreach ( $page_ids as $post_id ) {
            $rows[] = [
                'id'         => $post_id,
                'title'      => html_entity_decode( get_the_title( $post_id ), ENT_QUOTES, 'UTF-8' ),
                'post_type'  => get_post_type( $post_id ),
                'permalink'  => get_permalink( $post_id ),
                'is_omitted' => Omits::instance()->is_omitted( 'post', $post_id ),
            ];
        }

        return [ 'rows' => $rows, 'total_pages' => $total_pages ];
    } // End get_page()


    /**
     * Check whether a media attachment is used anywhere on the site —
     * as a featured image, attached via post_parent, or referenced in any
     * enabled post type's content (img src, block markup, or wp-image-{ID} class)
     *
     * @param int $attachment_id
     * @return array [ 'used' => bool, 'locations' => array of [ 'title', 'link' ] ]
     */
    public function check_media_usage( int $attachment_id ) : array {
        $locations = [];

        $featured_on = get_posts( [
            'post_type'      => 'any',
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_key'       => '_thumbnail_id',
            'meta_value'     => $attachment_id,
        ] );

        foreach ( $featured_on as $post_id ) {
            $locations[] = [
                'title'  => get_the_title( $post_id ) . ' (' . __( 'Featured Image', 'prohibited-terms-scanner' ) . ')',
                'link'   => get_permalink( $post_id ),
                'status' => get_post_status( $post_id ),
            ];
        }

        $parent_id = wp_get_post_parent_id( $attachment_id );

        if ( $parent_id ) {
            $locations[] = [
                'title'  => get_the_title( $parent_id ) . ' (' . __( 'Attached To', 'prohibited-terms-scanner' ) . ')',
                'link'   => get_permalink( $parent_id ),
                'status' => get_post_status( $parent_id ),
            ];
        }

        $post_types = Settings::instance()->get_enabled_post_types();
        $excluded   = Settings::instance()->get_excluded_post_types();
        $post_types = array_diff( $post_types, $excluded );

        if ( ! empty( $post_types ) ) {
            $attachment_url = wp_get_attachment_url( $attachment_id );
            $filename       = $attachment_url ? wp_basename( $attachment_url ) : '';

            $query = new \WP_Query( [
                'post_type'      => array_values( $post_types ),
                'post_status'    => 'any',
                'posts_per_page' => -1,
                'orderby'        => 'ID',
                'order'          => 'ASC',
                'fields'         => 'ids',
                'no_found_rows'  => true,
            ] );

            foreach ( $query->posts as $post_id ) {
                $raw_content = get_post_field( 'post_content', $post_id );
                $content     = Scanner::instance()->safe_do_shortcode( $raw_content, $post_id );

                $found = ( '' !== $filename && false !== strpos( $content, $filename ) )
                    || false !== strpos( $content, 'wp-image-' . $attachment_id );

                if ( $found ) {
                    $locations[] = [
                        'title'  => get_the_title( $post_id ) . ' (' . __( 'In Content', 'prohibited-terms-scanner' ) . ')',
                        'link'   => get_permalink( $post_id ),
                        'status' => get_post_status( $post_id ),
                    ];
                }
            }
        }

        return [
            'used'      => ! empty( $locations ),
            'locations' => $locations,
        ];
    } // End check_media_usage()

}


PagesList::instance();