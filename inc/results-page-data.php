<?php
/**
 * Results page data retrieval
 */

namespace PluginRx\ProhibitedTermsScanner;

if ( ! defined( 'ABSPATH' ) ) exit;

class ResultsPageData {


    /**
     * Default rows per page
     *
     * @var int
     */
    private int $per_page = 20;


    /**
     * The single instance of the class
     *
     * @var self|null
     */
    private static ?ResultsPageData $instance = null;


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
    private function __construct() {}


    /**
     * Get a page of results
     *
     * @param string $status flagged|ignored
     * @param int    $page
     * @return array [ 'rows' => array, 'total' => int, 'total_pages' => int ]
     */
    public function get_page( $status, $page ) : array {
        global $wpdb;

        $table_name = DB::instance()->table();
        $offset     = ( max( 1, $page ) - 1 ) * $this->per_page;

        $total = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table_name} WHERE status = %s",
                $status
            )
        );

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE status = %s ORDER BY created_at DESC LIMIT %d OFFSET %d",
                $status,
                $this->per_page,
                $offset
            ),
            ARRAY_A
        );

        $rows = array_map( [ $this, 'enrich_row' ], $rows ?: [] );

        return [
            'rows'        => $rows,
            'total'       => $total,
            'total_pages' => (int) ceil( $total / $this->per_page ),
        ];
    } // End get_page()


    /**
     * Enrich a raw DB row with a display link and a highlighted context snippet
     *
     * @param array $row
     * @return array
     */
    private function enrich_row( array $row ) : array {
        $type = TypeRegistry::instance()->get_type( $row[ 'location_type' ] );

        $row[ 'location_label' ] = $type[ 'label' ] ?? ucwords( str_replace( '_', ' ', $row[ 'location_type' ] ) );

        // For file content results, append the file type, e.g. "File Content (PDF)".
        if ( 'file_content' === $row[ 'location_type' ] && ! empty( $row[ 'source_id' ] ) ) {
            $file = get_attached_file( $row[ 'source_id' ] );

            if ( $file ) {
                $ext = strtoupper( pathinfo( $file, PATHINFO_EXTENSION ) );

                if ( '' !== $ext ) {
                    /* translators: %1$s: location label, %2$s: file type/extension */
                    $row[ 'location_label' ] = sprintf( __( '%1$s (%2$s)', 'prohibited-terms-scanner' ), $row[ 'location_label' ], $ext );
                }
            }
        } elseif ( 'eri_file_content' === $row[ 'location_type' ] && ! empty( $row[ 'source_id' ] ) && class_exists( '\Apos37\EriFileLibrary\PostType' ) ) {
            $eri      = new \Apos37\EriFileLibrary\PostType();
            $filename = get_post_meta( $row[ 'source_id' ], $eri->meta_key_url, true );
            $ext      = $filename ? strtoupper( pathinfo( $filename, PATHINFO_EXTENSION ) ) : '';

            if ( '' !== $ext ) {
                /* translators: %1$s: location label, %2$s: file type/extension */
                $row[ 'location_label' ] = sprintf( __( '%1$s (%2$s)', 'prohibited-terms-scanner' ), $row[ 'location_label' ], $ext );
            }
        }
        
        $row[ 'context_highlighted' ] = $this->highlight_term( $row[ 'context_snippet' ], $row[ 'term' ] );

        // Taxonomy term links point to admin-only management screens for some
        // registered taxonomies, or may not resolve to a public archive at all
        // depending on the taxonomy's rewrite settings — restrict to admins.
        if ( 'term' === $row[ 'source_type' ] && ! current_user_can( 'manage_options' ) ) {
            $row[ 'display_link' ]   = '';
            $row[ 'highlight_link' ] = '';

            return $row;
        }

        $link = '';

        if ( $type && isset( $type[ 'link_callback' ] ) && is_callable( $type[ 'link_callback' ] ) ) {
            try {
                $link = (string) call_user_func( $type[ 'link_callback' ], $row[ 'source_id' ] );
            } catch ( \Throwable $e ) {
                $link = '';
            }
        }

        if ( '' === $link && ! empty( $row[ 'source_url' ] ) ) {
            $link = $row[ 'source_url' ];
        }

        $row[ 'display_link' ] = $link;

        if ( '' !== $link && in_array( $row[ 'source_type' ], [ 'post', 'comment', 'term' ], true ) ) {
            $row[ 'highlight_link' ] = add_query_arg( 'ptscanner_term', rawurlencode( $row[ 'term' ] ), $link );
        } else {
            $row[ 'highlight_link' ] = $link;
        }

        return $row;
    } // End enrich_row()


    /**
     * Wrap the first case-insensitive occurrence of a term in a snippet with
     * a bold/highlighted span, escaping everything else safely
     *
     * @param string $snippet
     * @param string $term
     * @return string
     */
    private function highlight_term( $snippet, $term ) : string {
        $escaped_snippet = esc_html( $snippet );
        $escaped_term    = esc_html( $term );

        if ( '' === $escaped_term ) {
            return $escaped_snippet;
        }

        $pattern = '/' . preg_quote( $escaped_term, '/' ) . '/i';

        return preg_replace( $pattern, '<strong class="ptscanner-highlighted-term">$0</strong>', $escaped_snippet, 1 );
    } // End highlight_term()

}