<?php
/**
 * ERI File Library integration
 *
 * Registers ERI File Library's custom post type as scannable locations,
 * supporting both the current standalone plugin (erifl-files, meta key
 * 'url') and the legacy version bundled inside eri-webtools-plugin
 * (eri-files, meta key '_post_url'), only if one of them is active.
 */

namespace PluginRx\ProhibitedTermsScanner\Integrations;

use PluginRx\ProhibitedTermsScanner\Scanner;
use PluginRx\ProhibitedTermsScanner\Omits;
use PluginRx\ProhibitedTermsScanner\ErrorLog;

if ( ! defined( 'ABSPATH' ) ) exit;

class EriFileLibrary {


    /**
     * Which ERI variant is active: 'new', 'legacy', or 'none'
     *
     * @var string|null
     */
    private ?string $variant = null;


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
        add_action( 'edit_form_top', [ $this, 'render_content_warning' ] );
    } // End __construct()


    /**
     * Detect which ERI File Library variant is active, if any
     *
     * @return string|null 'new', 'legacy', or null
     */
    private function detect_variant() : ?string {
        if ( null !== $this->variant ) {
            return 'none' === $this->variant ? null : $this->variant;
        }

        if ( class_exists( '\Apos37\EriFileLibrary\PostType' ) ) {
            $this->variant = 'new';

            return $this->variant;
        }

        if ( ! function_exists( 'is_plugin_active' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        if ( is_plugin_active( 'eri-webtools-plugin/eri-webtools-plugin.php' ) && 1 === (int) get_option( 'eri_file_manager' ) ) {
            $this->variant = 'legacy';

            return $this->variant;
        }

        $this->variant = 'none';

        return null;
    } // End detect_variant()


    /**
     * Whether any ERI File Library variant is active and usable
     *
     * @return bool
     */
    private function is_available() : bool {
        return null !== $this->detect_variant();
    } // End is_available()


    /**
     * The post type slug for the active variant
     *
     * @return string
     */
    private function post_type() : string {
        return 'legacy' === $this->detect_variant() ? 'eri-files' : 'erifl-files';
    } // End post_type()


    /**
     * The meta key storing the filename for the active variant
     *
     * @return string
     */
    private function url_meta_key() : string {
        return 'legacy' === $this->detect_variant() ? '_post_url' : 'url';
    } // End url_meta_key()


    /**
     * The meta key storing the description for the active variant
     *
     * Assumes the legacy plugin follows the same '_post_' prefix pattern
     * seen in its own migration mapping ('_post_desc' => 'description').
     * Confirm this matches the actual legacy meta key if results seem off.
     *
     * @return string
     */
    private function description_meta_key() : string {
        return 'legacy' === $this->detect_variant() ? '_post_desc' : 'description';
    } // End description_meta_key()


    /**
     * Get the full file path or URL for a given file post, for either variant
     *
     * @param int     $post_id
     * @param boolean $abspath
     * @return string|false
     */
    private function file_url( $post_id, $abspath = false ) {
        if ( 'new' === $this->detect_variant() ) {
            $eri = new \Apos37\EriFileLibrary\PostType();

            return $eri->file_url( $post_id, $abspath );
        }

        if ( 'legacy' === $this->detect_variant() ) {
            $filename = get_post_meta( $post_id, $this->url_meta_key(), true );

            if ( ! $filename ) {
                return false;
            }

            $uploads_dir = wp_get_upload_dir();
            $base        = $abspath ? $uploads_dir[ 'basedir' ] : $uploads_dir[ 'baseurl' ];

            return $base . '/eri-files/' . $filename;
        }

        return false;
    } // End file_url()


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

        $types[ 'eri_title' ] = [
            'label'           => __( 'ERI File Library Title', 'prohibited-terms-scanner' ),
            'group'           => 'files',
            'source_callback' => [ $this, 'scan_eri_titles' ],
            'link_callback'   => [ $this, 'link_eri_file' ],
            'default_enabled' => true,
        ];

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
     * Get a batch of ERI file post IDs, for whichever variant is active
     *
     * @param int $offset
     * @param int $limit
     * @return array
     */
    private function get_eri_batch( $offset, $limit ) : array {
        $query = new \WP_Query( [
            'post_type'      => $this->post_type(),
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
     * Scan ERI file post titles
     *
     * @param array $terms
     * @param int   $offset
     * @param int   $limit
     * @return array
     */
    public function scan_eri_titles( $terms, $offset, $limit ) : array {
        $post_ids = $this->get_eri_batch( $offset, $limit );
        $rows     = [];
        $scanner  = Scanner::instance();

        foreach ( $post_ids as $post_id ) {
            if ( Omits::instance()->is_omitted( 'eri_file', $post_id ) ) {
                continue;
            }

            $title   = get_the_title( $post_id );
            $matches = $scanner->match_terms( $title, $terms );

            foreach ( $matches as $match ) {
                $rows[] = $this->build_row( $scanner, $match, 'eri_title', $post_id );
            }
        }

        return [ 'rows' => $rows, 'done' => count( $post_ids ) < $limit ];
    } // End scan_eri_titles()


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

        foreach ( $post_ids as $post_id ) {
            if ( Omits::instance()->is_omitted( 'eri_file', $post_id ) ) {
                continue;
            }

            $filename = get_post_meta( $post_id, $this->url_meta_key(), true );
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

        foreach ( $post_ids as $post_id ) {
            if ( Omits::instance()->is_omitted( 'eri_file', $post_id ) ) {
                continue;
            }

            $description = get_post_meta( $post_id, $this->description_meta_key(), true );
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
        $post_ids  = $this->get_eri_batch( $offset, $limit );
        $rows      = [];
        $scanner   = Scanner::instance();
        $use_pages = \PluginRx\ProhibitedTermsScanner\Settings::instance()->get_pdf_page_lookup();

        foreach ( $post_ids as $post_id ) {
            if ( Omits::instance()->is_omitted( 'eri_file', $post_id ) ) {
                continue;
            }

            $file_path = $this->file_url( $post_id, true );

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


    /**
     * Scan the file's content and display a warning notice directly on the
     * edit screen, every time it's loaded — as long as a filename is saved.
     * Runs on page load rather than on save, so it always reflects current
     * state and doesn't depend on save-hook timing. Works for either variant.
     *
     * @param \WP_Post $post
     * @return void
     */
    public function render_content_warning( $post ) {
        if ( ! $this->is_available() || $this->post_type() !== $post->post_type ) {
            return;
        }

        $settings = \PluginRx\ProhibitedTermsScanner\Settings::instance();

        if ( ! $settings->is_warning_enabled() ) {
            return;
        }

        $terms = $settings->get_warning_terms();

        if ( empty( $terms ) ) {
            return;
        }

        $filename = get_post_meta( $post->ID, $this->url_meta_key(), true );

        if ( empty( $filename ) ) {
            return;
        }

        $file_path = $this->file_url( $post->ID, true );

        if ( ! $file_path || ! file_exists( $file_path ) ) {
            return;
        }

        try {
            $content = Scanner::instance()->extract_file_content_for_path( $file_path );
        } catch ( \Throwable $e ) {
            ErrorLog::instance()->log( 'eri_upload_content_warning', 'Failed to extract content: ' . $e->getMessage() );

            return;
        }

        if ( '' === trim( $content ) ) {
            return;
        }

        $matches = Scanner::instance()->match_terms( $content, $terms );

        if ( empty( $matches ) ) {
            return;
        }

        $matched_terms = array_unique( wp_list_pluck( $matches, 'term' ) );

        printf(
            '<div class="notice notice-warning inline"><p>%s</p></div>',
            esc_html(
                sprintf(
                    /* translators: %s: comma-separated list of flagged terms */
                    __( 'This file\'s content contains a flagged term: %s', 'prohibited-terms-scanner' ),
                    implode( ', ', $matched_terms )
                )
            )
        );
    } // End render_content_warning()

}


EriFileLibrary::instance();