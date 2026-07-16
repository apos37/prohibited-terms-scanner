<?php
/**
 * Omitted sources page data + AJAX handling
 */

namespace PluginRx\ProhibitedTermsScanner;

if ( ! defined( 'ABSPATH' ) ) exit;

class OmittedSourcesPage {


    /**
     * The single instance of the class
     *
     * @var self|null
     */
    private static ?OmittedSourcesPage $instance = null;


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
        add_action( 'wp_ajax_ptscanner_toggle_omit', [ $this, 'ajax_toggle_omit' ] );
        add_action( 'wp_ajax_ptscanner_bulk_omit', [ $this, 'ajax_bulk_omit' ] );

        // Row action links — post_row_actions covers non-hierarchical post
        // types (Posts, most custom post types), page_row_actions covers
        // hierarchical ones (Pages, and any hierarchical custom post type).
        add_filter( 'post_row_actions', [ $this, 'add_row_action' ], 10, 2 );
        add_filter( 'page_row_actions', [ $this, 'add_row_action' ], 10, 2 );
        add_filter( 'media_row_actions', [ $this, 'add_row_action' ], 10, 2 );

        add_action( 'admin_enqueue_scripts', [ $this, 'maybe_enqueue_row_action_script' ] );

        // Register bulk actions dynamically for every public post type, not
        // just Posts/Pages, matching the same post types configurable in Settings.
        add_action( 'admin_init', [ $this, 'register_bulk_actions_for_public_post_types' ] );
        add_action( 'admin_notices', [ $this, 'bulk_action_notice' ] );
        add_filter( 'bulk_actions-upload', [ $this, 'add_bulk_actions' ] );
        add_filter( 'handle_bulk_actions-upload', [ $this, 'handle_bulk_actions' ], 10, 3 );
    } // End __construct()


    /**
     * Register the bulk action dropdown option + handler for every
     * registered public post type, so custom post types get the same
     * Omit/Unignore bulk option as Posts and Pages
     *
     * @return void
     */
    public function register_bulk_actions_for_public_post_types() {
        $post_types = get_post_types( [ 'public' => true ], 'names' );

        foreach ( $post_types as $post_type ) {
            add_filter( 'bulk_actions-edit-' . $post_type, [ $this, 'add_bulk_actions' ] );
            add_filter( 'handle_bulk_actions-edit-' . $post_type, [ $this, 'handle_bulk_actions' ], 10, 3 );
        }
    } // End register_bulk_actions_for_public_post_types()


    /**
     * Add an "Omit from Terms Scanner" / "Unignore" row action link
     *
     * @param array    $actions
     * @param \WP_Post $post
     * @return array
     */
    public function add_row_action( array $actions, \WP_Post $post ) : array {
        if ( ! current_user_can( 'manage_options' ) ) {
            return $actions;
        }

        $type = $this->resolve_omit_type( $post->post_type );

        $is_omitted = Omits::instance()->is_omitted( $type, $post->ID );
        $label      = $is_omitted
            ? __( 'Unignore from Terms Scanner', 'prohibited-terms-scanner' )
            : __( 'Omit from Terms Scanner', 'prohibited-terms-scanner' );

        $actions[ 'ptscanner_omit' ] = sprintf(
            '<a href="#" class="ptscanner-toggle-omit" data-id="%1$d" data-type="%2$s" data-omitted="%3$s">%4$s</a>',
            absint( $post->ID ),
            esc_attr( $type ),
            $is_omitted ? '1' : '0',
            esc_html( $label )
        );

        return $actions;
    } // End add_row_action()


    /**
     * Resolve the correct Omits type key for a given post type, matching
     * whatever the scanner itself checks against for that source
     *
     * @param string $post_type
     * @return string
     */
    private function resolve_omit_type( string $post_type ) : string {
        if ( 'attachment' === $post_type ) {
            return 'attachment';
        }

        if ( in_array( $post_type, [ 'erifl-files', 'eri-files' ], true ) ) {
            return 'eri_file';
        }

        return 'post';
    } // End resolve_omit_type()


    /**
     * Enqueue the row action click handler, only on the list table screens
     * where it's needed (Posts/Pages/CPT list tables, and Media Library)
     *
     * @param string $hook
     * @return void
     */
    public function maybe_enqueue_row_action_script( $hook ) {
        if ( ! in_array( $hook, [ 'edit.php', 'upload.php' ], true ) ) {
            return;
        }

        $textdomain     = Bootstrap::textdomain();
        $script_version = Bootstrap::script_version();

        wp_enqueue_script(
            $textdomain . '-row-actions',
            Bootstrap::url( 'inc/js/row-actions.js' ),
            [ 'jquery' ],
            $script_version,
            true
        );

        wp_localize_script( $textdomain . '-row-actions', 'ptscanner_row_actions_data', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'ptscanner_nonce' ),
            'labels'  => [
                'omit'   => __( 'Omit from Terms Scanner', 'prohibited-terms-scanner' ),
                'unomit' => __( 'Unignore from Terms Scanner', 'prohibited-terms-scanner' ),
            ],
        ] );
    } // End maybe_enqueue_row_action_script()


    /**
     * AJAX: toggle a single source's omit status
     *
     * @return void
     */
    public function ajax_toggle_omit() {
        check_ajax_referer( 'ptscanner_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'prohibited-terms-scanner' ) ] );
        }

        $id   = isset( $_POST[ 'id' ] ) ? absint( wp_unslash( $_POST[ 'id' ] ) ) : 0;
        $type = isset( $_POST[ 'type' ] ) ? sanitize_key( wp_unslash( $_POST[ 'type' ] ) ) : '';
        $omit = isset( $_POST[ 'omit' ] ) && '1' === sanitize_text_field( wp_unslash( $_POST[ 'omit' ] ) );

        if ( ! $id || '' === $type ) {
            wp_send_json_error( [ 'message' => __( 'Invalid request.', 'prohibited-terms-scanner' ) ] );
        }

        $cleared = 0;

        if ( $omit ) {
            Omits::instance()->add( $type, $id, get_the_title( $id ) );
            $cleared = DB::instance()->delete_by_source( $type, $id );
        } else {
            Omits::instance()->remove( $type, $id );
        }

        wp_send_json_success( [
            'message' => __( 'Updated.', 'prohibited-terms-scanner' ),
            'cleared' => $cleared,
        ] );
    } // End ajax_toggle_omit()


    /**
     * AJAX: bulk omit/unomit multiple sources at once
     *
     * @return void
     */
    public function ajax_bulk_omit() {
        check_ajax_referer( 'ptscanner_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'prohibited-terms-scanner' ) ] );
        }

        $ids  = isset( $_POST[ 'ids' ] ) ? array_map( 'absint', (array) wp_unslash( $_POST[ 'ids' ] ) ) : [];
        $type = isset( $_POST[ 'type' ] ) ? sanitize_key( wp_unslash( $_POST[ 'type' ] ) ) : '';
        $omit = isset( $_POST[ 'omit' ] ) && '1' === sanitize_text_field( wp_unslash( $_POST[ 'omit' ] ) );

        if ( empty( $ids ) || '' === $type ) {
            wp_send_json_error( [ 'message' => __( 'Invalid request.', 'prohibited-terms-scanner' ) ] );
        }

        foreach ( $ids as $id ) {
            if ( $omit ) {
                Omits::instance()->add( $type, $id, get_the_title( $id ) );
            } else {
                Omits::instance()->remove( $type, $id );
            }
        }

        wp_send_json_success( [
            /* translators: %d: number of items updated */
            'message' => sprintf( __( '%d item(s) updated.', 'prohibited-terms-scanner' ), count( $ids ) ),
        ] );
    } // End ajax_bulk_omit()


    /**
     * Add bulk action options to the list table dropdown
     *
     * @param array $bulk_actions
     * @return array
     */
    public function add_bulk_actions( array $bulk_actions ) : array {
        if ( ! current_user_can( 'manage_options' ) ) {
            return $bulk_actions;
        }

        $bulk_actions[ 'ptscanner_omit' ]   = __( 'Omit from Terms Scanner', 'prohibited-terms-scanner' );
        $bulk_actions[ 'ptscanner_unomit' ] = __( 'Unignore from Terms Scanner', 'prohibited-terms-scanner' );

        return $bulk_actions;
    } // End add_bulk_actions()


    /**
     * Handle the bulk action (native, full-page — bulk row actions on list
     * tables use a page redirect, not AJAX, unlike the single-row action)
     *
     * @param string $redirect_url
     * @param string $action
     * @param array  $post_ids
     * @return string
     */
    public function handle_bulk_actions( $redirect_url, $action, $post_ids ) {
        if ( ! in_array( $action, [ 'ptscanner_omit', 'ptscanner_unomit' ], true ) ) {
            return $redirect_url;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            return $redirect_url;
        }

        $count = 0;

        foreach ( $post_ids as $post_id ) {
            $type = $this->resolve_omit_type( get_post_type( $post_id ) );

            if ( 'ptscanner_omit' === $action ) {
                Omits::instance()->add( $type, $post_id, get_the_title( $post_id ) );
            } else {
                Omits::instance()->remove( $type, $post_id );
            }

            $count++;
        }

        return add_query_arg( 'ptscanner_bulk_omitted', $count, $redirect_url );
    } // End handle_bulk_actions()


    /**
     * Show a confirmation notice after a bulk omit/unomit action
     *
     * @return void
     */
    public function bulk_action_notice() {
        if ( empty( $_REQUEST[ 'ptscanner_bulk_omitted' ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            return;
        }

        $count = absint( $_REQUEST[ 'ptscanner_bulk_omitted' ] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

        printf(
            '<div class="notice notice-success is-dismissible"><p>%s</p></div>',
            esc_html(
                sprintf(
                    /* translators: %d: number of items updated */
                    _n( '%d item updated for Prohibited Terms Scanner.', '%d items updated for Prohibited Terms Scanner.', $count, 'prohibited-terms-scanner' ),
                    $count
                )
            )
        );
    } // End bulk_action_notice()

}


OmittedSourcesPage::instance();