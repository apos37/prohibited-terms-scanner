<?php
/**
 * Scanner engine
 *
 * Handles term matching, batched scanning across all registered location
 * types, and building result rows for insertion into the DB.
 */

namespace PluginRx\ProhibitedTermsScanner;

if ( ! defined( 'ABSPATH' ) ) exit;

class Scanner {


    /**
     * The single instance of the class
     *
     * @var self|null
     */
    private static ?Scanner $instance = null;


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
        register_shutdown_function( [ $this, 'log_fatal_during_scan' ] );
    } // End __construct()


    /**
     * Run a single batch for a given location type
     *
     * @param string $type_slug
     * @param array  $terms Array of [ 'term', 'case_sensitive', 'strict' ]
     * @param int    $offset
     * @param int    $limit
     * @return array [ 'rows' => array, 'done' => bool ]
     */
    public function run_batch( $type_slug, $terms, $offset, $limit ) : array {
        $type = TypeRegistry::instance()->get_type( $type_slug );

        if ( null === $type ) {
            return [ 'rows' => [], 'done' => true ];
        }

        $callback = $type[ 'source_callback' ];

        return call_user_func( $callback, $terms, $offset, $limit );
    } // End run_batch()


    /**
     * Match a text blob against the term list, reporting every occurrence
     *
     * @param string $text
     * @param array  $terms
     * @return array Array of [ 'term', 'snippet' ] for each occurrence found
     */
    public function match_terms( $text, $terms ) : array {
        $matches = [];

        if ( '' === trim( (string) $text ) ) {
            return $matches;
        }

        $plain_text = wp_strip_all_tags( $text );
        $padding    = Settings::instance()->get_snippet_padding();

        foreach ( $terms as $term_data ) {
            $term           = $term_data[ 'term' ];
            $case_sensitive = ! empty( $term_data[ 'case_sensitive' ] );
            $strict         = ! empty( $term_data[ 'strict' ] );

            $pattern = $this->build_pattern( $term, $strict );
            $flags   = $case_sensitive ? 'u' : 'iu';

            if ( preg_match_all( '/' . $pattern . '/' . $flags, $plain_text, $found, PREG_OFFSET_CAPTURE ) ) {
                foreach ( $found[ 0 ] as $occurrence ) {
                    $offset = $occurrence[ 1 ];
                    $length = strlen( $occurrence[ 0 ] );

                    $matches[] = [
                        'term'    => $term,
                        'snippet' => $this->get_context_snippet( $plain_text, $offset, $length, $padding ),
                    ];
                }
            }
        }

        return $matches;
    } // End match_terms()


    /**
     * Build a preg pattern for a term
     *
     * @param string  $term
     * @param boolean $strict
     * @return string
     */
    private function build_pattern( $term, $strict ) : string {
        $escaped = preg_quote( $term, '/' );

        if ( $strict ) {
            return '\b' . $escaped . '\b';
        }

        return $escaped;
    } // End build_pattern()


    /**
     * Extract a context snippet around a match offset
     *
     * @param string $text
     * @param int    $offset
     * @param int    $length
     * @param int    $padding
     * @return string
     */
    private function get_context_snippet( $text, $offset, $length, $padding ) : string {
        $start = max( 0, $offset - $padding );
        $end   = min( strlen( $text ), $offset + $length + $padding );

        $snippet = substr( $text, $start, $end - $start );
        $snippet = trim( $snippet );

        if ( $start > 0 ) {
            $snippet = '…' . $snippet;
        }

        if ( $end < strlen( $text ) ) {
            $snippet .= '…';
        }

        return $snippet;
    } // End get_context_snippet()


    /**
     * Build a match hash used for ignore-persistence across scans
     *
     * @param string $term
     * @param string $location_type
     * @param mixed  $source_id
     * @return string
     */
    public function build_match_hash( $term, $location_type, $source_id, $snippet = '' ) : string {
        return md5( mb_strtolower( $term ) . '|' . $location_type . '|' . $source_id . '|' . md5( $snippet ) );
    } // End build_match_hash()


    /**
     * Scan post titles
     *
     * @param array $terms
     * @param int   $offset
     * @param int   $limit
     * @return array
     */
    public function scan_titles( $terms, $offset, $limit ) : array {
        $post_ids = $this->get_post_batch( $offset, $limit );
        $rows     = [];

        foreach ( $post_ids as $post_id ) {
            if ( Omits::instance()->is_omitted( 'post', $post_id ) ) {
                continue;
            }

            $title   = get_the_title( $post_id );
            $matches = $this->match_terms( $title, $terms );

            foreach ( $matches as $match ) {
                $rows[] = $this->build_row( $match, 'title', 'post', $post_id, get_permalink( $post_id ) );
            }
        }

        return [ 'rows' => $rows, 'done' => count( $post_ids ) < $limit ];
    } // End scan_titles()


    /**
     * Scan post content
     *
     * @param array $terms
     * @param int   $offset
     * @param int   $limit
     * @return array
     */
    public function scan_content( $terms, $offset, $limit ) : array {
        $post_ids = $this->get_post_batch( $offset, $limit );
        $rows     = [];

        foreach ( $post_ids as $post_id ) {
            if ( Omits::instance()->is_omitted( 'post', $post_id ) ) {
                continue;
            }

            update_option( 'ptscanner_last_processed', [
                'type'    => 'content',
                'post_id' => $post_id,
                'time'    => current_time( 'mysql' ),
            ], false );

            $raw_content = get_post_field( 'post_content', $post_id );
            $content     = $this->safe_do_shortcode( $raw_content, $post_id );
            $matches     = $this->match_terms( $content, $terms );

            foreach ( $matches as $match ) {
                $rows[] = $this->build_row( $match, 'content', 'post', $post_id, get_permalink( $post_id ) );
            }
        }

        return [ 'rows' => $rows, 'done' => count( $post_ids ) < $limit ];
    } // End scan_content()


    /**
     * Run do_shortcode() defensively; on error, log it along with the
     * shortcode tags present on the post (for diagnosis) and fall back to
     * the raw (un-expanded) content so the post still gets scanned
     *
     * @param string $content
     * @param int    $post_id
     * @return string
     */
    private function safe_do_shortcode( $content, $post_id ) : string {
        $previous_handler = set_error_handler( function ( $errno, $errstr ) { // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler
            throw new \ErrorException( esc_html( $errstr ), 0, absint( $errno ) );
        } );

        // Buffer locally around the shortcode expansion itself, since some
        // shortcodes echo directly (e.g. admin-facing validation notices)
        // rather than returning their output — this catches that regardless
        // of what's happening with any outer buffer elsewhere in the request.
        ob_start();

        try {
            $expanded    = do_shortcode( $content );
            $stray_echo  = ob_get_clean();

            restore_error_handler();

            if ( '' !== trim( $stray_echo ) ) {
                $shortcode_tags = $this->extract_shortcode_tags( $content );
                $tags_list      = ! empty( $shortcode_tags ) ? implode( ', ', $shortcode_tags ) : 'none detected';

                ErrorLog::instance()->log(
                    'shortcode_echo',
                    'A shortcode printed output directly instead of returning it (shortcode tag(s): ' . $tags_list . '): ' . substr( $stray_echo, 0, 500 ),
                    [ 'post_id' => $post_id ]
                );
            }

            return $expanded;
        } catch ( \Throwable $e ) {
            ob_end_clean();
            restore_error_handler();

            $shortcode_tags = $this->extract_shortcode_tags( $content );
            $tags_list      = ! empty( $shortcode_tags ) ? implode( ', ', $shortcode_tags ) : 'none detected';

            ErrorLog::instance()->log(
                'shortcode',
                $e->getMessage() . ' (shortcode tag(s) on this post: ' . $tags_list . ')',
                [ 'post_id' => $post_id ]
            );

            return $content;
        }
    } // End safe_do_shortcode()


    /**
     * Extract the registered shortcode tags present in a block of content,
     * used to identify which shortcode likely caused an extraction error
     *
     * @param string $content
     * @return array
     */
    private function extract_shortcode_tags( $content ) : array {
        global $shortcode_tags;

        if ( empty( $shortcode_tags ) || '' === trim( (string) $content ) ) {
            return [];
        }

        $pattern = get_shortcode_regex( array_keys( $shortcode_tags ) );

        if ( ! preg_match_all( '/' . $pattern . '/', $content, $matches ) ) {
            return [];
        }

        return array_unique( array_filter( $matches[ 2 ] ) );
    } // End extract_shortcode_tags()


    /**
     * Scan post excerpts
     *
     * @param array $terms
     * @param int   $offset
     * @param int   $limit
     * @return array
     */
    public function scan_excerpts( $terms, $offset, $limit ) : array {
        $post_ids = $this->get_post_batch( $offset, $limit );
        $rows     = [];

        foreach ( $post_ids as $post_id ) {
            if ( Omits::instance()->is_omitted( 'post', $post_id ) ) {
                continue;
            }

            $excerpt = get_post_field( 'post_excerpt', $post_id );
            $matches = $this->match_terms( $excerpt, $terms );

            foreach ( $matches as $match ) {
                $rows[] = $this->build_row( $match, 'excerpt', 'post', $post_id, get_permalink( $post_id ) );
            }
        }

        return [ 'rows' => $rows, 'done' => count( $post_ids ) < $limit ];
    } // End scan_excerpts()


    /**
     * Scan comments
     *
     * @param array $terms
     * @param int   $offset
     * @param int   $limit
     * @return array
     */
    public function scan_comments( $terms, $offset, $limit ) : array {
        $comments = get_comments( [
            'status' => 'approve',
            'number' => $limit,
            'offset' => $offset,
            'orderby' => 'comment_ID',
            'order'   => 'ASC',
        ] );

        $rows = [];

        foreach ( $comments as $comment ) {
            if ( Omits::instance()->is_omitted( 'comment', $comment->comment_ID ) ) {
                continue;
            }

            if ( Omits::instance()->is_omitted( 'post', $comment->comment_post_ID ) ) {
                continue;
            }

            $matches = $this->match_terms( $comment->comment_content, $terms );

            foreach ( $matches as $match ) {
                $rows[] = $this->build_row( $match, 'comment', 'comment', $comment->comment_ID, get_comment_link( $comment ) );
            }
        }

        return [ 'rows' => $rows, 'done' => count( $comments ) < $limit ];
    } // End scan_comments()


    /**
     * Scan taxonomy term names
     *
     * @param array $terms
     * @param int   $offset
     * @param int   $limit
     * @return array
     */
    public function scan_tax_names( $terms, $offset, $limit ) : array {
        return $this->scan_tax_field( $terms, $offset, $limit, 'name' );
    } // End scan_tax_names()


    /**
     * Scan taxonomy term slugs
     *
     * @param array $terms
     * @param int   $offset
     * @param int   $limit
     * @return array
     */
    public function scan_tax_slugs( $terms, $offset, $limit ) : array {
        return $this->scan_tax_field( $terms, $offset, $limit, 'slug' );
    } // End scan_tax_slugs()


    /**
     * Shared taxonomy scan logic
     *
     * @param array  $terms
     * @param int    $offset
     * @param int    $limit
     * @param string $field
     * @return array
     */
    private function scan_tax_field( $terms, $offset, $limit, $field ) : array {
        $taxonomies = get_taxonomies( [ 'public' => true ] );

        $term_objects = get_terms( [
            'taxonomy'   => $taxonomies,
            'hide_empty' => false,
            'number'     => $limit,
            'offset'     => $offset,
            'orderby'    => 'term_id',
            'order'      => 'ASC',
        ] );

        if ( is_wp_error( $term_objects ) ) {
            return [ 'rows' => [], 'done' => true ];
        }

        $rows = [];

        foreach ( $term_objects as $term_object ) {
            if ( Omits::instance()->is_omitted( 'term', $term_object->term_id ) ) {
                continue;
            }

            $value   = $field === 'slug' ? $term_object->slug : $term_object->name;
            $matches = $this->match_terms( $value, $terms );

            foreach ( $matches as $match ) {
                $link = get_term_link( $term_object );
                $link = is_wp_error( $link ) ? '' : $link;
                $rows[] = $this->build_row( $match, 'tax_' . $field, 'term', $term_object->term_id, $link );
            }
        }

        return [ 'rows' => $rows, 'done' => count( $term_objects ) < $limit ];
    } // End scan_tax_field()


    /**
     * Scan media filenames
     *
     * @param array $terms
     * @param int   $offset
     * @param int   $limit
     * @return array
     */
    public function scan_filenames( $terms, $offset, $limit ) : array {
        $attachment_ids = $this->get_attachment_batch( $offset, $limit );
        $rows           = [];

        foreach ( $attachment_ids as $attachment_id ) {
            if ( Omits::instance()->is_omitted( 'attachment', $attachment_id ) ) {
                continue;
            }

            $file     = get_attached_file( $attachment_id );
            $filename = $file ? wp_basename( $file ) : '';
            $matches  = $this->match_terms( $filename, $terms );

            foreach ( $matches as $match ) {
                $rows[] = $this->build_row( $match, 'filename', 'attachment', $attachment_id, wp_get_attachment_url( $attachment_id ) );
            }
        }

        return [ 'rows' => $rows, 'done' => count( $attachment_ids ) < $limit ];
    } // End scan_filenames()


    /**
     * Scan media alt text
     *
     * @param array $terms
     * @param int   $offset
     * @param int   $limit
     * @return array
     */
    public function scan_alt_text( $terms, $offset, $limit ) : array {
        $attachment_ids = $this->get_attachment_batch( $offset, $limit );
        $rows           = [];

        foreach ( $attachment_ids as $attachment_id ) {
            if ( Omits::instance()->is_omitted( 'attachment', $attachment_id ) ) {
                continue;
            }

            $alt_text = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
            $matches  = $this->match_terms( $alt_text, $terms );

            foreach ( $matches as $match ) {
                $rows[] = $this->build_row( $match, 'alt_text', 'attachment', $attachment_id, wp_get_attachment_url( $attachment_id ) );
            }
        }

        return [ 'rows' => $rows, 'done' => count( $attachment_ids ) < $limit ];
    } // End scan_alt_text()


    /**
     * Scan file contents (opt-in, no third-party parser dependencies)
     *
     * Only plain-text-readable file types are supported out of the box.
     * Developers can extend supported mime types via 'ptscanner_file_content_mimes'.
     *
     * @param array $terms
     * @param int   $offset
     * @param int   $limit
     * @return array
     */
    public function scan_file_contents( $terms, $offset, $limit ) : array {
        $mimes = apply_filters( 'ptscanner_file_content_mimes', [
            'text/plain',
            'text/csv',
            'application/pdf',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ] );

        $attachment_ids = $this->get_attachment_batch( $offset, $limit, $mimes );
        $rows           = [];

        foreach ( $attachment_ids as $attachment_id ) {
            if ( Omits::instance()->is_omitted( 'attachment', $attachment_id ) ) {
                continue;
            }

            $file = get_attached_file( $attachment_id );

            if ( ! $file || ! file_exists( $file ) ) {
                continue;
            }

            $mime_type = get_post_mime_type( $attachment_id );

            if ( 'application/pdf' === $mime_type ) {
                if ( Settings::instance()->get_pdf_page_lookup() ) {
                    $url  = wp_get_attachment_url( $attachment_id );
                    $rows = array_merge( $rows, $this->scan_pdf_by_page( $file, $terms, 'file_content', 'attachment', $attachment_id, $url ) );
                    continue;
                }

                $content = $this->extract_pdf_text( $file );
            } elseif ( 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' === $mime_type ) {
                $content = $this->extract_docx_text( $file );
            } else {
                $content = file_get_contents( $file );
            }

            $matches = $this->match_terms( $content, $terms );

            foreach ( $matches as $match ) {
                $rows[] = $this->build_row( $match, 'file_content', 'attachment', $attachment_id, wp_get_attachment_url( $attachment_id ) );
            }
        }

        return [ 'rows' => $rows, 'done' => count( $attachment_ids ) < $limit ];
    } // End scan_file_contents()


    /**
     * Scan a PDF page-by-page so matches can record which page they were found on
     *
     * @param string $file_path
     * @param array  $terms
     * @param string $location_type
     * @param string $source_type
     * @param int    $source_id
     * @param string $url
     * @return array
     */
    public function scan_pdf_by_page( $file_path, $terms, $location_type, $source_type, $source_id, $url ) : array {
        $rows = [];

        if ( ! class_exists( '\PTScannerVendor\Smalot\PdfParser\Parser' ) ) {
            ErrorLog::instance()->log( 'file_content', 'PDF parser library not available.' );

            return $rows;
        }

        try {
            $parser = new \PTScannerVendor\Smalot\PdfParser\Parser();
            $pdf    = $parser->parseFile( $file_path );
            $pages  = $pdf->getPages();
        } catch ( \Throwable $e ) {
            ErrorLog::instance()->log( 'file_content', 'PDF parsing failed for ' . $file_path . ': ' . $e->getMessage() );

            return $rows;
        }

        foreach ( $pages as $index => $page ) {
            try {
                $text = $page->getText();
            } catch ( \Throwable $e ) {
                continue;
            }

            $matches = $this->match_terms( $text, $terms );

            foreach ( $matches as $match ) {
                $row = [
                    'term'            => $match[ 'term' ],
                    'location_type'   => $location_type,
                    'source_type'     => $source_type,
                    'source_id'       => $source_id,
                    'source_url'      => $url,
                    'context_snippet' => $match[ 'snippet' ],
                    'file_page'       => $index + 1,
                    'match_hash'      => $this->build_match_hash( $match[ 'term' ], $location_type, $source_id . '-p' . ( $index + 1 ), $match[ 'snippet' ] ),
                ];
                $rows[] = $row;
            }
        }

        return $rows;
    } // End scan_pdf_by_page()


    /**
     * Get a batch of public post IDs across all enabled post types
     *
     * Excludes ERI File Library's own post type(s), since those are covered
     * by dedicated eri_filename/eri_description/eri_file_content location
     * types instead — scanning them generically here as well would cause
     * omits to need checking under two different keys for the same file.
     *
     * @param int $offset
     * @param int $limit
     * @return array
     */
    private function get_post_batch( $offset, $limit ) : array {
        $post_types = Settings::instance()->get_enabled_post_types();
        $post_types = array_diff( $post_types, [ 'erifl-files', 'eri-files' ] );

        if ( empty( $post_types ) ) {
            return [];
        }

        $query = new \WP_Query( [
            'post_type'      => $post_types,
            'post_status'    => 'publish',
            'posts_per_page' => $limit,
            'offset'         => $offset,
            'orderby'        => 'ID',
            'order'          => 'ASC',
            'fields'         => 'ids',
            'no_found_rows'  => true,
        ] );

        return $query->posts;
    } // End get_post_batch()


    /**
     * Get a batch of attachment IDs, optionally filtered by mime type
     *
     * @param int   $offset
     * @param int   $limit
     * @param array $mimes
     * @return array
     */
    private function get_attachment_batch( $offset, $limit, $mimes = [] ) : array {
        $args = [
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'posts_per_page' => $limit,
            'offset'         => $offset,
            'orderby'        => 'ID',
            'order'          => 'ASC',
            'fields'         => 'ids',
            'no_found_rows'  => true,
        ];

        if ( ! empty( $mimes ) ) {
            $args[ 'post_mime_type' ] = $mimes;
        }

        $query = new \WP_Query( $args );

        return $query->posts;
    } // End get_attachment_batch()


    /**
     * Build a standardized result row from a match
     *
     * @param array  $match
     * @param string $location_type
     * @param string $source_type
     * @param mixed  $source_id
     * @param string $source_url
     * @return array
     */
    private function build_row( $match, $location_type, $source_type, $source_id, $source_url ) : array {
        return [
            'term'            => $match[ 'term' ],
            'location_type'   => $location_type,
            'source_type'     => $source_type,
            'source_id'       => $source_id,
            'source_url'      => $source_url,
            'context_snippet' => $match[ 'snippet' ],
            'file_page'       => null,
            'match_hash'      => $this->build_match_hash( $match[ 'term' ], $location_type, $source_id, $match[ 'snippet' ] ),
        ];
    } // End build_row()


    /**
     * Link callback: post
     *
     * @param int $source_id
     * @return string
     */
    public function link_post( $source_id ) : string {
        return (string) get_permalink( $source_id );
    } // End link_post()


    /**
     * Link callback: comment
     *
     * @param int $source_id
     * @return string
     */
    public function link_comment( $source_id ) : string {
        $comment = get_comment( $source_id );

        return $comment ? (string) get_comment_link( $comment ) : '';
    } // End link_comment()


    /**
     * Link callback: taxonomy term
     *
     * @param int $source_id
     * @return string
     */
    public function link_term( $source_id ) : string {
        $term_object = get_term( $source_id );

        if ( ! $term_object || is_wp_error( $term_object ) ) {
            return '';
        }

        $link = get_term_link( $term_object );

        return is_wp_error( $link ) ? '' : (string) $link;
    } // End link_term()


    /**
     * Link callback: attachment
     *
     * @param int $source_id
     * @return string
     */
    public function link_attachment( $source_id ) : string {
        return (string) wp_get_attachment_url( $source_id );
    } // End link_attachment()


    /**
     * Extract text from a .docx file using PHP's built-in ZipArchive
     *
     * @param string $file_path
     * @return string
     */
    public function extract_docx_text( $file_path ) : string {
        if ( ! class_exists( '\ZipArchive' ) ) {
            ErrorLog::instance()->log( 'file_content', 'ZipArchive extension not available; cannot extract .docx text.' );

            return '';
        }

        $zip = new \ZipArchive();
        $open_result = $zip->open( $file_path );

        if ( true !== $open_result ) {
            ErrorLog::instance()->log( 'file_content', 'Could not open .docx as zip: ' . $file_path . ' (code ' . $open_result . ')' );

            return '';
        }

        $xml = $zip->getFromName( 'word/document.xml' );
        $zip->close();

        if ( false === $xml ) {
            ErrorLog::instance()->log( 'file_content', 'word/document.xml not found inside: ' . $file_path );

            return '';
        }

        $xml  = str_replace( '</w:p>', ' </w:p>', $xml );
        $text = wp_strip_all_tags( $xml );

        if ( '' === trim( $text ) ) {
            ErrorLog::instance()->log( 'file_content', 'Extracted empty text from .docx: ' . $file_path );
        }

        return $text;
    } // End extract_docx_text()


    /**
     * Extract text from a .pdf file using the bundled, namespace-scoped
     * smalot/pdfparser library
     *
     * @param string $file_path
     * @return string
     */
    public function extract_pdf_text( $file_path ) : string {
        if ( ! class_exists( '\PTScannerVendor\Smalot\PdfParser\Parser' ) ) {
            ErrorLog::instance()->log( 'file_content', 'PDF parser library not available (vendor/autoload.php missing or not loaded).' );

            return '';
        }

        try {
            $parser   = new \PTScannerVendor\Smalot\PdfParser\Parser();
            $pdf      = $parser->parseFile( $file_path );
            $text     = $pdf->getText();
        } catch ( \Throwable $e ) {
            ErrorLog::instance()->log( 'file_content', 'PDF parsing failed for ' . $file_path . ': ' . $e->getMessage() );

            return '';
        }

        if ( '' === trim( (string) $text ) ) {
            ErrorLog::instance()->log( 'file_content', 'Extracted empty text from PDF: ' . $file_path );
        }

        return (string) $text;
    } // End extract_pdf_text()


    /**
     * Extract text content from a file path based on its extension, reusing
     * the same extraction logic used for Media Library file content scanning
     *
     * @param string $file_path
     * @return string
     */
    public function extract_file_content_for_path( $file_path ) : string {
        $ext = strtolower( pathinfo( $file_path, PATHINFO_EXTENSION ) );

        if ( 'pdf' === $ext ) {
            return $this->extract_pdf_text( $file_path );
        }

        if ( 'docx' === $ext ) {
            return $this->extract_docx_text( $file_path );
        }

        if ( in_array( $ext, [ 'txt', 'csv' ], true ) ) {
            return (string) file_get_contents( $file_path );
        }

        return '';
    } // End extract_file_content_for_path()


    /**
     * Detect a fatal error/timeout during a scan batch and log which post
     * was being processed when it happened — this is the only way to catch
     * a hard PHP timeout, since it can't be caught with try/catch.
     *
     * @return void
     */
    public function log_fatal_during_scan() {
        $error = error_get_last();

        if ( null === $error || ! in_array( $error[ 'type' ], [ E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ], true ) ) {
            return;
        }

        $last_processed = get_option( 'ptscanner_last_processed', null );

        if ( null === $last_processed ) {
            return;
        }

        ErrorLog::instance()->log(
            'fatal_during_scan',
            'A fatal error/timeout occurred while processing post ID ' . $last_processed[ 'post_id' ] . ' (type: ' . $last_processed[ 'type' ] . '). PHP error: ' . $error[ 'message' ]
        );
    } // End log_fatal_during_scan()

}


Scanner::instance();