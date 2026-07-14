<?php
/**
 * Save/upload warning integration
 */

namespace PluginRx\ProhibitedTermsScanner;

if ( ! defined( 'ABSPATH' ) ) exit;

class HooksWarning {


    /**
     * The single instance of the class
     *
     * @var self|null
     */
    private static ?HooksWarning $instance = null;


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
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_editor_warning' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_upload_warning' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_alt_text_warning' ] );
    } // End __construct()


    /**
     * Enqueue the editor pre-publish warning script, post/page edit screens only
     *
     * @param string $hook
     * @return void
     */
    public function enqueue_editor_warning( $hook ) {
        if ( ! Settings::instance()->is_warning_enabled() ) {
            return;
        }

        if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) {
            return;
        }

        $textdomain     = Bootstrap::textdomain();
        $script_version = Bootstrap::version();

        wp_register_script( $textdomain . '-core', false, [ 'jquery' ], $script_version, true );
        wp_enqueue_script( $textdomain . '-core' );

        wp_localize_script( $textdomain . '-core', 'ptscanner_warning_data', [
            'terms'   => Settings::instance()->get_warning_terms(),
            'message' => __( 'The following term(s) were found and flagged as not allowed: ', 'prohibited-terms-scanner' ),
            'confirm' => __( 'Are you sure you want to save/publish this?', 'prohibited-terms-scanner' ),
        ] );

        wp_enqueue_script(
            $textdomain . '-editor-warning',
            Bootstrap::url( 'inc/js/editor-warning.js' ),
            [ $textdomain . '-core' ],
            $script_version,
            true
        );
    } // End enqueue_editor_warning()


    /**
     * Enqueue the upload warning script wherever the media library can be used
     *
     * @param string $hook
     * @return void
     */
    public function enqueue_upload_warning( $hook ) {
        if ( ! Settings::instance()->is_warning_enabled() ) {
            return;
        }

        $textdomain     = Bootstrap::textdomain();
        $script_version = Bootstrap::version();

        wp_register_script( $textdomain . '-core', false, [ 'jquery' ], $script_version, true );
        wp_enqueue_script( $textdomain . '-core' );

        wp_localize_script( $textdomain . '-core', 'ptscanner_upload_warning_data', [
            'terms'   => Settings::instance()->get_warning_terms(),
            'message' => __( 'This filename contains a flagged term: ', 'prohibited-terms-scanner' ),
            'confirm' => __( 'Are you sure you want to upload this file?', 'prohibited-terms-scanner' ),
        ] );

        wp_enqueue_script(
            $textdomain . '-upload-warning',
            Bootstrap::url( 'inc/js/upload-warning.js' ),
            [ $textdomain . '-core' ],
            $script_version,
            true
        );
    } // End enqueue_upload_warning()


    /**
     * Enqueue the alt text warning script wherever attachment details can be edited
     *
     * @param string $hook
     * @return void
     */
    public function enqueue_alt_text_warning( $hook ) {
        if ( ! Settings::instance()->is_warning_enabled() ) {
            return;
        }

        $textdomain     = Bootstrap::textdomain();
        $script_version = Bootstrap::version();

        wp_register_script( $textdomain . '-core', false, [ 'jquery' ], $script_version, true );
        wp_enqueue_script( $textdomain . '-core' );

        wp_localize_script( $textdomain . '-core', 'ptscanner_alt_text_warning_data', [
            'terms'   => Settings::instance()->get_warning_terms(),
            'message' => __( 'This alt text contains a flagged term: ', 'prohibited-terms-scanner' ),
        ] );

        wp_enqueue_script(
            $textdomain . '-alt-text-warning',
            Bootstrap::url( 'inc/js/alt-text-warning.js' ),
            [ $textdomain . '-core' ],
            $script_version,
            true
        );
    } // End enqueue_alt_text_warning()

}


HooksWarning::instance();