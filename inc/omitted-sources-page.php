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

        // Row action links on standard admin list tables.
        add_filter( 'post_row_actions', [ $this, 'add_row_action' ], 10, 2 );
        add_filter( 'media_row_actions', [ $this, 'add_row_action' ], 10, 2 );
        add_action( 'admin_footer-edit.php', [ $this, 'enqueue_row_action_script' ] );
        add_action( 'admin_footer-upload.php', [ $this, 'enqueue_row_action_script' ] );

        add_filter( 'bulk_actions-edit-post', [ $this, 'add_bulk_actions' ] );
        add_filter( 'bulk_actions-edit-page', [ $this, 'add_bulk_actions' ] );
        add_filter( 'bulk_actions-upload', [ $this, 'add_bulk_actions' ] );
        add_filter( 'handle_bulk_actions-edit-post', [ $this, 'handle_bulk_actions' ], 10, 3 );
        add_filter( 'handle_bulk_actions-edit-page', [ $this, 'handle_bulk_actions' ], 10, 3 );
        add_filter( 'handle_bulk_actions-upload', [ $this, 'handle_bulk_actions' ], 10, 3 );
        add_action( 'admin_notices', [ $this, 'bulk_action_notice' ] );
    } // End __construct()


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

        $type       = 'attachment' === $post->post_type ? 'attachment' : 'post';
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
     * Enqueue the small inline script that handles the row action click,
     * only on the list table screens where it's needed
     *
     * @return void
     */
    public function enqueue_row_action_script() {
        $nonce = wp_create_nonce( 'ptscanner_nonce' );
        $ajax_url = admin_url( 'admin-ajax.php' );

        $labels = wp_json_encode( [
            'omit'   => __( 'Omit from Terms Scanner', 'prohibited-terms-scanner' ),
            'unomit' => __( 'Unignore from Terms Scanner', 'prohibited-terms-scanner' ),
        ] );

        echo '<script>
        jQuery( function ( $ ) {
            var ptscannerLabels = ' . $labels . ';

            $( document ).on( "click", ".ptscanner-toggle-omit", function ( event ) {
                event.preventDefault();

                var link = $( this );
                var id = link.data( "id" );
                var type = link.data( "type" );
                var isOmitted = link.data( "omitted" ) == 1;

                $.post( "' . esc_url( $ajax_url ) . '", {
                    action: "ptscanner_toggle_omit",
                    nonce: "' . esc_js( $nonce ) . '",
                    id: id,
                    type: type,
                    omit: isOmitted ? "0" : "1"
                } ).done( function ( response ) {
                    if ( ! response.success ) {
                        alert( response.data.message || "Could not update." );
                        return;
                    }

                    var nowOmitted = ! isOmitted;
                    link.data( "omitted", nowOmitted ? "1" : "0" );
                    link.text( nowOmitted ? ptscannerLabels.unomit : ptscannerLabels.omit );
                } ).fail( function () {
                    alert( "Request failed." );
                } );
            } );
        } );
        </script>';
    } // End enqueue_row_action_script()


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
        $omit = isset( $_POST[ 'omit' ] ) && '1' === wp_unslash( $_POST[ 'omit' ] );

        if ( ! $id || '' === $type ) {
            wp_send_json_error( [ 'message' => __( 'Invalid request.', 'prohibited-terms-scanner' ) ] );
        }

        if ( $omit ) {
            $label = $type === 'attachment' ? get_the_title( $id ) : get_the_title( $id );
            Omits::instance()->add( $type, $id, $label );
        } else {
            Omits::instance()->remove( $type, $id );
        }

        wp_send_json_success( [ 'message' => __( 'Updated.', 'prohibited-terms-scanner' ) ] );
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
        $omit = isset( $_POST[ 'omit' ] ) && '1' === wp_unslash( $_POST[ 'omit' ] );

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
            $type = 'attachment' === get_post_type( $post_id ) ? 'attachment' : 'post';

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