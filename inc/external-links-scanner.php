<?php
/**
 * External links scanner
 */

namespace PluginRx\ProhibitedTermsScanner;

if ( ! defined( 'ABSPATH' ) ) exit;

class ExternalLinksScanner {


    /**
     * The single instance of the class
     *
     * @var self|null
     */
    private static ?ExternalLinksScanner $instance = null;


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
    private function __construct() {} // End __construct()


    /**
     * Scan all enabled post types for external links in one pass
     *
     * @return array [ 'scanned' => int, 'links_found' => int ]
     */
    public function run_full_scan() : array {
        $post_types = Settings::instance()->get_enabled_post_types();
        $excluded   = Settings::instance()->get_excluded_post_types();
        $post_types = array_diff( $post_types, $excluded );

        if ( empty( $post_types ) ) {
            return [ 'scanned' => 0, 'links_found' => 0 ];
        }

        $query = new \WP_Query( [
            'post_type'        => array_values( $post_types ),
            'post_status'      => 'publish',
            'posts_per_page'   => -1,
            'orderby'          => 'ID',
            'order'            => 'ASC',
            'fields'           => 'ids',
            'no_found_rows'    => true,
            'suppress_filters' => true,
        ] );

        $terms      = Settings::instance()->get_terms();
        $site_host  = wp_parse_url( home_url(), PHP_URL_HOST );
        $started_at = current_time( 'mysql' );
        $links_found = 0;

        foreach ( $query->posts as $post_id ) {
            if ( Omits::instance()->is_omitted( 'post', $post_id ) ) {
                continue;
            }

            $raw_content = get_post_field( 'post_content', $post_id );
            $content     = Scanner::instance()->safe_do_shortcode( $raw_content, $post_id );

            if ( ! preg_match_all( '/<a\s+[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is', $content, $matches, PREG_SET_ORDER ) ) {
                continue;
            }

            foreach ( $matches as $match ) {
                $url  = $match[ 1 ];
                $text = wp_strip_all_tags( $match[ 2 ] );
                $host = wp_parse_url( $url, PHP_URL_HOST );

                if ( ! $host || $host === $site_host ) {
                    continue;
                }

                $post_title   = get_the_title( $post_id );
                $matched_term = $this->find_matched_term( $url, $text, $post_title, $terms );

                $success = ExternalLinksDB::instance()->upsert( [
                    'link'         => $url,
                    'anchor_text'  => $text,
                    'source_type'  => 'post',
                    'source_id'    => $post_id,
                    'is_flagged'   => null !== $matched_term,
                    'matched_term' => $matched_term,
                ] );

                if ( $success ) {
                    $links_found++;
                }
            }
        }

        ExternalLinksDB::instance()->delete_stale( $started_at );

        return [ 'scanned' => count( $query->posts ), 'links_found' => $links_found ];
    } // End run_full_scan()


    /**
     * Check whether a URL, anchor text, and page title contain any
     * prohibited term, honoring each term's case-sensitive/strict settings.
     * The URL's host is excluded from matching entirely; the rest of the
     * URL (path/query) is checked with dashes, slashes, dots, and
     * underscores treated as word boundaries for strict matching.
     *
     * @param string $url
     * @param string $anchor_text
     * @param string $page_title
     * @param array  $terms
     * @return string|null
     */
    private function find_matched_term( string $url, string $anchor_text, string $page_title, array $terms ) : ?string {
        $host      = wp_parse_url( $url, PHP_URL_HOST );
        $url_path  = $host ? substr( $url, strpos( $url, $host ) + strlen( $host ) ) : $url;
        $text_blob = $anchor_text . ' ' . $page_title;

        foreach ( $terms as $term_data ) {
            $term           = $term_data[ 'term' ];
            $case_sensitive = ! empty( $term_data[ 'case_sensitive' ] );
            $strict         = ! empty( $term_data[ 'strict' ] );
            $flags          = $case_sensitive ? '' : 'i';

            $text_pattern = $strict ? '\b' . preg_quote( $term, '/' ) . '\b' : preg_quote( $term, '/' );

            if ( preg_match( '/' . $text_pattern . '/' . $flags . 'u', $text_blob ) ) {
                return $term;
            }

            $url_pattern = $strict
                ? '(?:^|[\/\-_.])' . preg_quote( $term, '/' ) . '(?:$|[\/\-_.?&=])'
                : preg_quote( $term, '/' );

            if ( preg_match( '/' . $url_pattern . '/' . $flags . 'u', $url_path ) ) {
                return $term;
            }
        }

        return null;
    } // End find_matched_term()

}


ExternalLinksScanner::instance();