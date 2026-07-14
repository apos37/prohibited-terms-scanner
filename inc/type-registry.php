<?php
/**
 * Location type registry
 *
 * Third-party integrations hook 'ptscanner_location_types' to register new scan
 * targets. Each entry needs a label, group, source_callback, and link_callback.
 */

namespace PluginRx\ProhibitedTermsScanner;

if ( ! defined( 'ABSPATH' ) ) exit;

class TypeRegistry {


    /**
     * Cached registry for the current request
     *
     * @var array|null
     */
    private static ?array $cache = null;


    /**
     * The single instance of the class
     *
     * @var self|null
     */
    private static ?TypeRegistry $instance = null;


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
     * Default built-in location types
     *
     * @return array
     */
    private function defaults() : array {
        $scanner = Scanner::instance();

        return [
            'title' => [
                'label'           => __( 'Post Title', 'prohibited-terms-scanner' ),
                'group'           => 'content',
                'source_callback' => [ $scanner, 'scan_titles' ],
                'link_callback'   => [ $scanner, 'link_post' ],
                'default_enabled' => true,
            ],
            'content' => [
                'label'           => __( 'Main Content', 'prohibited-terms-scanner' ),
                'group'           => 'content',
                'source_callback' => [ $scanner, 'scan_content' ],
                'link_callback'   => [ $scanner, 'link_post' ],
                'default_enabled' => true,
            ],
            'excerpt' => [
                'label'           => __( 'Excerpt', 'prohibited-terms-scanner' ),
                'group'           => 'content',
                'source_callback' => [ $scanner, 'scan_excerpts' ],
                'link_callback'   => [ $scanner, 'link_post' ],
                'default_enabled' => true,
            ],
            'comment' => [
                'label'           => __( 'Comments', 'prohibited-terms-scanner' ),
                'group'           => 'content',
                'source_callback' => [ $scanner, 'scan_comments' ],
                'link_callback'   => [ $scanner, 'link_comment' ],
                'default_enabled' => true,
            ],
            'tax_name' => [
                'label'           => __( 'Taxonomy Term Name', 'prohibited-terms-scanner' ),
                'group'           => 'content',
                'source_callback' => [ $scanner, 'scan_tax_names' ],
                'link_callback'   => [ $scanner, 'link_term' ],
                'default_enabled' => true,
            ],
            'tax_slug' => [
                'label'           => __( 'Taxonomy Term Slug', 'prohibited-terms-scanner' ),
                'group'           => 'content',
                'source_callback' => [ $scanner, 'scan_tax_slugs' ],
                'link_callback'   => [ $scanner, 'link_term' ],
                'default_enabled' => true,
            ],
            'filename' => [
                'label'           => __( 'Media Filename', 'prohibited-terms-scanner' ),
                'group'           => 'media',
                'source_callback' => [ $scanner, 'scan_filenames' ],
                'link_callback'   => [ $scanner, 'link_attachment' ],
                'default_enabled' => true,
            ],
            'alt_text' => [
                'label'           => __( 'Media Alt Text', 'prohibited-terms-scanner' ),
                'group'           => 'media',
                'source_callback' => [ $scanner, 'scan_alt_text' ],
                'link_callback'   => [ $scanner, 'link_attachment' ],
                'default_enabled' => true,
            ],
            'file_content' => [
                'label'           => __( 'File Content', 'prohibited-terms-scanner' ),
                'group'           => 'files',
                'source_callback' => [ $scanner, 'scan_file_contents' ],
                'link_callback'   => [ $scanner, 'link_attachment' ],
                'default_enabled' => false,
            ],
        ];
    } // End defaults()


    /**
     * Get the full registry, filtered by third parties, cached per request
     *
     * @return array
     */
    public function get_types() : array {
        if ( null !== self::$cache ) {
            return self::$cache;
        }

        $types = apply_filters( 'ptscanner_location_types', $this->defaults() );

        foreach ( $types as $slug => $type ) {
            if ( ! isset( $type[ 'source_callback' ] ) || ! is_callable( $type[ 'source_callback' ] ) ) {
                unset( $types[ $slug ] );
            }
        }

        self::$cache = $types;

        return self::$cache;
    } // End get_types()


    /**
     * Get a single type definition by slug
     *
     * @param string $slug
     * @return array|null
     */
    public function get_type( $slug ) : ?array {
        $types = $this->get_types();

        return $types[ $slug ] ?? null;
    } // End get_type()


    /**
     * Get types grouped for settings UI rendering
     *
     * @return array
     */
    public function get_grouped_types() : array {
        $grouped = [];

        foreach ( $this->get_types() as $slug => $type ) {
            $group = $type[ 'group' ] ?? 'custom';
            $grouped[ $group ][ $slug ] = $type;
        }

        return $grouped;
    } // End get_grouped_types()

}