<?php
/**
 * Plugin Name:         Prohibited Terms Scanner
 * Plugin URI:          https://pluginrx.com/plugin/prohibited-terms-scanner/
 * Description:         Scan pages, posts, media, and files for prohibited terms and phrases.
 * Version:             1.3.0
 * Requires at least:   6.0
 * Tested up to:        7.0
 * Requires PHP:        8.0
 * Author:              PluginRx
 * Author URI:          https://pluginrx.com/
 * Discord URI:         https://discord.gg/3HnzNEJVnR
 * Text Domain:         prohibited-terms-scanner
 * License:             GPLv2 or later
 * License URI:         https://www.gnu.org/licenses/gpl-2.0.html
 * Created on:          July 15, 2026
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
     * Plugin files to load on every request, relative to /inc/.
     */
    public const FILES = [
        'helpers.php',
        'access.php',
        'omits.php',
        'error-log.php',
        'db.php',
        'type-registry.php',
        'settings.php',
        'external-links-db.php',
        'external-links-scanner.php',
        'scanner.php',
        'ajax.php',
        'hooks-warning.php',
        'results-page-data.php',
        'front-end.php',
        'pages-list.php',
        'cron.php',
        'dynamic-css.php',
        'integrations.php',
    ];


    /**
     * Admin-only files, relative to /inc/. AJAX requests count as admin.
     */
    public const ADMIN_FILES = [
        'plugin-page.php',
        'menu.php',
        'import-export.php',
        'omitted-sources-page.php',
    ];


    /**
     * Plugin header keys for get_file_data()
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
        'discord_uri'  => 'Discord URI',
    ];


    /**
     * @var array Plugin metadata from file header
     */
    private array $meta;


    /**
     * @var Bootstrap|null Singleton instance
     */
    private static ?Bootstrap $instance = null;


    /**
     * Get instance
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
        $this->meta = get_file_data( __FILE__, self::HEADER_KEYS );
        $this->check_environment();
        add_action( 'plugins_loaded', [ $this, 'load_files' ] );
        add_action( 'plugins_loaded', [ $this, 'load_integrations' ], 20 );
        register_activation_hook( __FILE__, [ $this, 'activate' ] );
    } // End __construct()


    /**
     * Get a meta value
     *
     * @param string $key
     * @return string
     */
    public function meta( string $key ) : string {
        return $this->meta[ $key ] ?? '';
    } // End meta()


    /**
     * Get plugin name
     *
     * @return string
     */
    public static function name() : string {
        return self::instance()->meta( 'name' );
    } // End name()


    /**
     * Get plugin version
     *
     * @return string
     */
    public static function version() : string {
        return self::instance()->meta( 'version' );
    } // End version()


    /**
     * Get the text domain, which doubles as the top-level menu slug.
     *
     * @return string
     */
    public static function textdomain() : string {
        return self::instance()->meta( 'textdomain' );
    } // End textdomain()


    /**
     * Check if the site is in test mode via Developer Debug Tools.
     *
     * @return bool
     */
    public static function is_test_mode() : bool {
        return filter_var( apply_filters( 'ptscanner_test_mode', get_option( 'ddtt_test_mode' ) ), FILTER_VALIDATE_BOOLEAN );
    } // End is_test_mode()


    /**
     * Get the version string used for enqueued scripts/styles.
     *
     * @return string
     */
    public static function script_version() : string {
        return self::is_test_mode() ? (string) time() : self::version();
    } // End script_version()


    /**
     * Get the plugin's main file path.
     *
     * @return string
     */
    public static function file() : string {
        return __FILE__;
    } // End file()


    /**
     * Get the plugin basename.
     *
     * @return string
     */
    public static function plugin_file() : string {
        return plugin_basename( __FILE__ );
    } // End plugin_file()


    /**
     * Get the plugin's base directory path, with trailing slash.
     *
     * @return string
     */
    public static function dir() : string {
        return plugin_dir_path( __FILE__ );
    } // End dir()


    /**
     * Get an absolute path inside the plugin.
     *
     * @param string $path
     * @return string
     */
    public static function path( string $path = '' ) : string {
        return self::dir() . ltrim( $path, '/' );
    } // End path()


    /**
     * Get a URL inside the plugin.
     *
     * @param string $path
     * @return string
     */
    public static function url( string $path = '' ) : string {
        return plugin_dir_url( __FILE__ ) . ltrim( $path, '/' );
    } // End url()


    /**
     * Get the Settings tab URL.
     *
     * @return string
     */
    public static function settings_url() : string {
        return admin_url( 'admin.php?page=' . self::textdomain() . '_settings' );
    } // End settings_url()


    /**
     * Check environment requirements
     *
     * @return void
     */
    private function check_environment() : void {
        $requires_php = $this->meta( 'requires_php' );

        if ( $requires_php && version_compare( PHP_VERSION, $requires_php, '<' ) ) {
            add_action( 'admin_notices', [ $this, 'notice_php_version' ] );
        }
    } // End check_environment()


    /**
     * Admin notice for unsupported PHP version
     *
     * @return void
     */
    public function notice_php_version() : void {
        echo '<div class="notice notice-error"><p>' . esc_html__( 'Prohibited Terms Scanner requires a newer version of PHP. Please contact your host to upgrade.', 'prohibited-terms-scanner' ) . '</p></div>';
    } // End notice_php_version()


    /**
     * Load plugin files
     *
     * @return void
     */
    public function load_files() : void {
        if ( version_compare( PHP_VERSION, $this->meta( 'requires_php' ), '<' ) ) {
            return;
        }

        $autoloader = self::path( 'vendor/pdfparser-autoload.php' );

        if ( file_exists( $autoloader ) ) {
            require_once $autoloader;
        }

        $files = is_admin() ? array_merge( self::FILES, self::ADMIN_FILES ) : self::FILES;

        foreach ( $files as $file ) {
            $path = self::path( 'inc/' . $file );

            if ( file_exists( $path ) ) {
                require_once $path;
            }
        }

        do_action( 'ptscanner_loaded' );
    } // End load_files()


    /**
     * Load each integration's integration.php file from inc/integrations/{slug}/.
     * Each integration.php gates itself on its companion plugin being active.
     *
     * @return void
     */
    public function load_integrations() : void {
        if ( ! class_exists( __NAMESPACE__ . '\\Integrations' ) ) {
            return;
        }

        $integrations_dir = self::path( 'inc/integrations/' );

        if ( ! is_dir( $integrations_dir ) ) {
            return;
        }

        foreach ( glob( $integrations_dir . '*', GLOB_ONLYDIR ) as $folder ) {
            $file = $folder . '/integration.php';

            if ( file_exists( $file ) ) {
                require_once $file;
            }
        }
    } // End load_integrations()


    /**
     * Activation hook
     *
     * @return void
     */
    public function activate() : void {
        require_once self::path( 'inc/db.php' );

        DB::instance()->create_table();

        if ( ! get_option( 'ptscanner_activated_time' ) ) {
            update_option( 'ptscanner_activated_time', time(), false );
        }
    } // End activate()

} // End class Bootstrap

Bootstrap::instance();
