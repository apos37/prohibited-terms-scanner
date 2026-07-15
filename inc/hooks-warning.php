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
        add_action( 'edit_form_top', [ $this, 'render_attachment_content_warning' ] );
        add_action( 'add_attachment', [ $this, 'check_uploaded_content' ] );
        add_filter( 'wp_prepare_attachment_for_js', [ $this, 'expose_content_warning' ], 10, 2 );
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
        $script_version = Bootstrap::script_version();

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
        $script_version = Bootstrap::script_version();

        wp_enqueue_media();

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
            [ $textdomain . '-core', 'wp-plupload', 'media-views' ],
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
        $script_version = Bootstrap::script_version();

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


    /**
     * Scan an attachment's file content and display a warning notice on its
     * edit screen, every time it's loaded. Mirrors the ERI File Library
     * integration's approach: check on view rather than chase upload events.
     *
     * @param \WP_Post $post
     * @return void
     */
    public function render_attachment_content_warning( $post ) {
        if ( 'attachment' !== $post->post_type ) {
            return;
        }

        if ( ! Settings::instance()->is_warning_enabled() ) {
            return;
        }

        $terms = Settings::instance()->get_warning_terms();

        if ( empty( $terms ) ) {
            return;
        }

        $file = get_attached_file( $post->ID );

        if ( ! $file || ! file_exists( $file ) ) {
            return;
        }

        try {
            $content = Scanner::instance()->extract_file_content_for_path( $file );
        } catch ( \Throwable $e ) {
            ErrorLog::instance()->log( 'attachment_content_warning', 'Failed to extract content: ' . $e->getMessage() );

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
    } // End render_attachment_content_warning()


    /**
     * Scan a newly uploaded attachment's content against warning terms
     *
     * @param int $attachment_id
     * @return void
     */
    public function check_uploaded_content( $attachment_id ) {
        if ( ! Settings::instance()->is_warning_enabled() ) {
            return;
        }

        $terms = Settings::instance()->get_warning_terms();

        if ( empty( $terms ) ) {
            return;
        }

        $file = get_attached_file( $attachment_id );

        if ( ! $file || ! file_exists( $file ) ) {
            return;
        }

        try {
            $content = Scanner::instance()->extract_file_content_for_path( $file );
        } catch ( \Throwable $e ) {
            ErrorLog::instance()->log( 'upload_content_warning', 'Failed to extract content for warning check: ' . $e->getMessage() );

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

        update_post_meta( $attachment_id, '_ptscanner_content_warning', $matched_terms );
    } // End check_uploaded_content()


    /**
     * Expose the content warning in the attachment's JS/JSON representation,
     * which WordPress includes in async-upload.php's response after a
     * successful upload — this is how the front-end script detects it.
     *
     * @param array    $response
     * @param \WP_Post $attachment
     * @return array
     */
    public function expose_content_warning( $response, $attachment ) {
        $matched_terms = get_post_meta( $attachment->ID, '_ptscanner_content_warning', true );

        if ( ! empty( $matched_terms ) && is_array( $matched_terms ) ) {
            $response[ 'ptscannerFlaggedTerms' ] = $matched_terms;
        }

        return $response;
    } // End expose_content_warning()

}


HooksWarning::instance();