<?php
/**
 * ERI File Library integration
 *
 * Registers ERI File Library's custom post type as scannable locations,
 * only if that plugin is active. Uses ERI's own PostType class to resolve
 * file paths/URLs correctly, respecting its custom folder settings.
 */

namespace PluginRx\ProhibitedTermsScanner\Integrations;

use PluginRx\ProhibitedTermsScanner\Scanner;
use PluginRx\ProhibitedTermsScanner\Omits;
use PluginRx\ProhibitedTermsScanner\ErrorLog;

if ( ! defined( 'ABSPATH' ) ) exit;

class EriFileLibrary {


    /**
     * The single instance of the class
     *
     * @var self|null
     */
    private static ?EriFileLibrary $instance = null;


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
        add_filter( 'ptscanner_location_types', [ $this, 'register_types' ] );
    } // End __construct()


    /**
     * Whether ERI File Library is active and its PostType class is available
     *
     * @return bool
     */
    private function is_available() : bool {
        return class_exists( '\Apos37\EriFileLibrary\PostType' );
    } // End is_available()


    /**
     * Get an instance of ERI's own PostType class
     *
     * @return object|null
     */
    private function eri_post_type() {
        if ( ! $this->is_available() ) {
            return null;
        }

        return new \Apos37\EriFileLibrary\PostType();
    } // End eri_post_type()


    /**
     * Register ERI location types into the shared registry
     *
     * @param array $types
     * @return array
     */
    public function register_types( array $types ) : array {
        if ( ! $this->is_available() ) {
            return $types;
        }

        $types[ 'eri_filename' ] = [
            'label'           => __( 'ERI File Library Filename', 'prohibited-terms-scanner' ),
            'group'           => 'files',
            'source_callback' => [ $this, 'scan_eri_filenames' ],
            'link_callback'   => [ $this, 'link_eri_file' ],
            'default_enabled' => true,
        ];

        $types[ 'eri_description' ] = [
            'label'           => __( 'ERI File Library Description', 'prohibited-terms-scanner' ),
            'group'           => 'files',
            'source_callback' => [ $this, 'scan_eri_descriptions' ],
            'link_callback'   => [ $this, 'link_eri_file' ],
            'default_enabled' => true,
        ];

        $types[ 'eri_file_content' ] = [
            'label'           => __( 'ERI File Library Content', 'prohibited-terms-scanner' ),
            'group'           => 'files',
            'source_callback' => [ $this, 'scan_eri_file_contents' ],
            'link_callback'   => [ $this, 'link_eri_file' ],
            'default_enabled' => false,
        ];

        return $types;
    } // End register_types()


    /**
     * Get a batch of ERI file post IDs
     *
     * @param int $offset
     * @param int $limit
     * @return array
     */
    private function get_eri_batch( $offset, $limit ) : array {
        $query = new \WP_Query( [
            'post_type'      => 'erifl-files',
            'post_status'    => 'publish',
            'posts_per_page' => $limit,
            'offset'         => $offset,
            'orderby'        => 'ID',
            'order'          => 'ASC',
            'fields'         => 'ids',
            'no_found_rows'  => true,
        ] );

        return $query->posts;
    } // End get_eri_batch()


    /**
     * Scan ERI filenames
     *
     * @param array $terms
     * @param int   $offset
     * @param int   $limit
     * @return array
     */
    public function scan_eri_filenames( $terms, $offset, $limit ) : array {
        $post_ids = $this->get_eri_batch( $offset, $limit );
        $rows     = [];
        $scanner  = Scanner::instance();
        $eri      = $this->eri_post_type();

        foreach ( $post_ids as $post_id ) {
            if ( Omits::instance()->is_omitted( 'eri_file', $post_id ) ) {
                continue;
            }

            $filename = get_post_meta( $post_id, $eri->meta_key_url, true );
            $matches  = $scanner->match_terms( $filename, $terms );

            foreach ( $matches as $match ) {
                $rows[] = $this->build_row( $scanner, $match, 'eri_filename', $post_id );
            }
        }

        return [ 'rows' => $rows, 'done' => count( $post_ids ) < $limit ];
    } // End scan_eri_filenames()


    /**
     * Scan ERI file descriptions
     *
     * @param array $terms
     * @param int   $offset
     * @param int   $limit
     * @return array
     */
    public function scan_eri_descriptions( $terms, $offset, $limit ) : array {
        $post_ids = $this->get_eri_batch( $offset, $limit );
        $rows     = [];
        $scanner  = Scanner::instance();
        $eri      = $this->eri_post_type();

        foreach ( $post_ids as $post_id ) {
            if ( Omits::instance()->is_omitted( 'eri_file', $post_id ) ) {
                continue;
            }

            $description = get_post_meta( $post_id, $eri->meta_key_description, true );
            $matches     = $scanner->match_terms( $description, $terms );

            foreach ( $matches as $match ) {
                $rows[] = $this->build_row( $scanner, $match, 'eri_description', $post_id );
            }
        }

        return [ 'rows' => $rows, 'done' => count( $post_ids ) < $limit ];
    } // End scan_eri_descriptions()


    /**
     * Scan ERI file contents, reusing Scanner's own extraction methods
     * (docx/pdf/plain text) since the file types are the same
     *
     * @param array $terms
     * @param int   $offset
     * @param int   $limit
     * @return array
     */
    public function scan_eri_file_contents( $terms, $offset, $limit ) : array {
        $post_ids = $this->get_eri_batch( $offset, $limit );
        $rows     = [];
        $scanner  = Scanner::instance();
        $eri      = $this->eri_post_type();
        $use_pages = \PluginRx\ProhibitedTermsScanner\Settings::instance()->get_pdf_page_lookup();

        foreach ( $post_ids as $post_id ) {
            if ( Omits::instance()->is_omitted( 'eri_file', $post_id ) ) {
                continue;
            }

            $file_path = $eri->file_url( $post_id, true );

            if ( ! $file_path || ! file_exists( $file_path ) ) {
                continue;
            }

            $ext = strtolower( pathinfo( $file_path, PATHINFO_EXTENSION ) );
            $url = $this->link_eri_file( $post_id );

            if ( 'pdf' === $ext && $use_pages ) {
                $rows = array_merge( $rows, $scanner->scan_pdf_by_page( $file_path, $terms, 'eri_file_content', 'eri_file', $post_id, $url ) );
                continue;
            }

            try {
                $content = $scanner->extract_file_content_for_path( $file_path );
            } catch ( \Throwable $e ) {
                ErrorLog::instance()->log( 'eri_file_content', 'Failed to extract content for ERI file ' . $post_id . ': ' . $e->getMessage() );

                continue;
            }

            $matches = $scanner->match_terms( $content, $terms );

            foreach ( $matches as $match ) {
                $rows[] = $this->build_row( $scanner, $match, 'eri_file_content', $post_id );
            }
        }

        return [ 'rows' => $rows, 'done' => count( $post_ids ) < $limit ];
    } // End scan_eri_file_contents()


    /**
     * Build a standardized result row
     *
     * @param Scanner $scanner
     * @param array   $match
     * @param string  $location_type
     * @param int     $post_id
     * @return array
     */
    private function build_row( Scanner $scanner, array $match, string $location_type, int $post_id ) : array {
        return [
            'term'            => $match[ 'term' ],
            'location_type'   => $location_type,
            'source_type'     => 'eri_file',
            'source_id'       => $post_id,
            'source_url'      => $this->link_eri_file( $post_id ),
            'context_snippet' => $match[ 'snippet' ],
            'file_page'       => null,
            'match_hash'      => $scanner->build_match_hash( $match[ 'term' ], $location_type, $post_id, $match[ 'snippet' ] ),
        ];
    } // End build_row()


    /**
     * Link callback: ERI file (links to its edit screen, since ERI files
     * redirect singular views straight to the file itself, not a readable page)
     *
     * @param int $post_id
     * @return string
     */
    public function link_eri_file( $post_id ) : string {
        return (string) get_edit_post_link( $post_id, '' );
    } // End link_eri_file()

}


EriFileLibrary::instance();