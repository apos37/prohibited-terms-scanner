<?php
/**
 * Omitted sources
 *
 * Stores a global list of source_type/source_id pairs excluded from all scans.
 */

namespace PluginRx\ProhibitedTermsScanner;

if ( ! defined( 'ABSPATH' ) ) exit;

class Omits {


    /**
     * Option key
     *
     * @var string
     */
    private string $option_key = 'ptscanner_omits';


    /**
     * Cached list for the current request, keyed as "type|id" for O(1) lookups
     *
     * @var array|null
     */
    private static ?array $cache = null;


    /**
     * The single instance of the class
     *
     * @var self|null
     */
    private static ?Omits $instance = null;


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
     * Get the raw omit list
     *
     * @return array Array of [ 'type', 'id', 'label' ]
     */
    public function get_list() : array {
        $list = get_option( $this->option_key, [] );

        return is_array( $list ) ? $list : [];
    } // End get_list()


    /**
     * Build (and cache) a lookup map for fast is_omitted() checks
     *
     * @return array
     */
    private function get_lookup_map() : array {
        if ( null !== self::$cache ) {
            return self::$cache;
        }

        $map = [];

        foreach ( $this->get_list() as $entry ) {
            $key = $entry[ 'type' ] . '|' . $entry[ 'id' ];
            $map[ $key ] = true;
        }

        self::$cache = $map;

        return self::$cache;
    } // End get_lookup_map()


    /**
     * Check whether a given source is omitted
     *
     * @param string $type post|comment|term|attachment|file, or a custom slug
     * @param mixed  $id
     * @return boolean
     */
    public function is_omitted( $type, $id ) : bool {
        $map = $this->get_lookup_map();
        $key = $type . '|' . $id;

        return isset( $map[ $key ] );
    } // End is_omitted()


    /**
     * Add an entry to the omit list
     *
     * @param string $type
     * @param mixed  $id
     * @param string $label
     * @return bool
     */
    public function add( $type, $id, $label = '' ) : bool {
        $list = $this->get_list();
        $key  = $type . '|' . $id;

        foreach ( $list as $entry ) {
            if ( $entry[ 'type' ] . '|' . $entry[ 'id' ] === $key ) {
                return true; // already omitted
            }
        }

        $list[] = [
            'type'  => sanitize_key( $type ),
            'id'    => is_numeric( $id ) ? absint( $id ) : sanitize_text_field( $id ),
            'label' => sanitize_text_field( $label ),
        ];

        self::$cache = null;

        return update_option( $this->option_key, $list, false );
    } // End add()


    /**
     * Remove an entry from the omit list
     *
     * @param string $type
     * @param mixed  $id
     * @return bool
     */
    public function remove( $type, $id ) : bool {
        $list = $this->get_list();
        $key  = $type . '|' . $id;

        $list = array_values( array_filter( $list, function( $entry ) use ( $key ) {
            return $entry[ 'type' ] . '|' . $entry[ 'id' ] !== $key;
        } ) );

        self::$cache = null;

        return update_option( $this->option_key, $list, false );
    } // End remove()

}