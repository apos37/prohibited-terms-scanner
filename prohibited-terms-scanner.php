<?php
/**
 * Plugin Name:         Prohibited Terms Scanner
 * Plugin URI:          https://pluginrx.com/plugin/prohibited-terms-scanner
 * Description:         Scan pages, posts, media, and files for prohibited terms and phrases.
 * Version:             1.0.0
 * Requires at least:   6.0
 * Tested up to:        7.0
 * Requires PHP:        8.0
 * Author:              PluginRx
 * Author URI:          https://pluginrx.com/
 * Text Domain:         prohibited-terms-scanner
 * License:             GPLv2 or later
 * License URI:         http://www.gnu.org/licenses/gpl-2.0.txt
 */

namespace PluginRx\ProhibitedTermsScanner;

if ( ! defined( 'ABSPATH' ) ) exit;


/**
 * BOOTSTRAP
 *
 * Loads plugin metadata, performs environment checks, and initializes the plugin.
 */
final class Bootstrap {

    /**
     * Plugin files to load (all requests)
     */
    public const FILES = [
        'inc/omits.php',
        'inc/db.php',
        'inc/type-registry.php',
        'inc/settings.php',
        'inc/scanner.php',
        'inc/ajax.php',
        'inc/hooks-warning.php',
        'inc/results-page-data.php',
        'inc/front-end.php'
    ];


    /**
     * Admin-only files
     */
    public const ADMIN_FILES = [
        'inc/admin-menu.php',
        'inc/import-export.php',
    ];


    /**
     * Plugin header keys
     */
    public const HEADER_KEYS = [
        'name'         => 'Plugin Name',
        'description'  => 'Description',
        'version'      => 'Version',
        'plugin_uri'   => 'Plugin URI',
        'requires_php' => 'Requires PHP',
        'textdomain'   => 'Text Domain',
        'author'       => 'Author',
        'author_uri'   => 'Author URI',
    ];


    /**
     * Plugin metadata
     */
    private array $meta;


    /**
     * Singleton instance
     */
    private static ?Bootstrap $instance = null;


    /**
     * Get instance
     */
    public static function instance() : self {
        return self::$instance ??= new self();
    } // End instance()


    /**
     * Constructor
     */
    private function __construct() {
        $this->meta = get_file_data( __FILE__, self::HEADER_KEYS );

        register_activation_hook( __FILE__, [ $this, 'activate' ] );
        register_deactivation_hook( __FILE__, [ $this, 'deactivate' ] );

        add_action( 'plugins_loaded', [ $this, 'load_files' ] );
    } // End __construct()


    /**
     * Load plugin files
     *
     * @return void
     */
    public function load_files() {
        foreach ( self::FILES as $file ) {
            require_once self::path( $file );
        }

        if ( is_admin() ) {
            foreach ( self::ADMIN_FILES as $file ) {
                require_once self::path( $file );
            }
        }
    } // End load_files()


    /**
     * Activation
     *
     * @return void
     */
    public function activate() {
        DB::instance()->create_table();
    } // End activate()


    /**
     * Deactivation
     *
     * @return void
     */
    public function deactivate() {
        // Intentionally left blank; table persists across deactivation.
    } // End deactivate()


    /**
     * Get a meta value
     *
     * @param string $key
     * @return string
     */
    private static function meta( $key ) : string {
        return self::instance()->meta[ $key ] ?? '';
    } // End meta()


    /**
     * Plugin name
     */
    public static function name() : string {
        return self::meta( 'name' );
    } // End name()


    /**
     * Plugin version
     */
    public static function version() : string {
        return self::meta( 'version' );
    } // End version()


    /**
     * Text domain
     */
    public static function textdomain() : string {
        return self::meta( 'textdomain' );
    } // End textdomain()


    /**
     * Plugin file (basename)
     */
    public static function plugin_file() : string {
        return plugin_basename( __FILE__ );
    } // End plugin_file()


    /**
     * Plugin path
     *
     * @param string $path
     * @return string
     */
    public static function path( $path = '' ) : string {
        return plugin_dir_path( __FILE__ ) . ltrim( $path, '/' );
    } // End path()


    /**
     * Plugin URL
     *
     * @param string $path
     * @return string
     */
    public static function url( $path = '' ) : string {
        return plugin_dir_url( __FILE__ ) . ltrim( $path, '/' );
    } // End url()


    /**
     * Settings page URL
     */
    public static function settings_url() : string {
        return admin_url( 'admin.php?page=' . self::textdomain() );
    } // End settings_url()


    /**
     * Prevent cloning/unserializing
     */
    public function __clone() {}
    public function __wakeup() {}

}


Bootstrap::instance();