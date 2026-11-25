<?php
/**
 * HELPERS
 *
 * Shared utility functions used across the plugin.
 */

namespace PluginRx\ProhibitedTermsScanner;

if ( ! defined( 'ABSPATH' ) ) exit;


class Helpers {

    /**
     * Check whether the current admin request is one of this plugin's tabs.
     * Uses the page slug instead of the screen ID, since the screen ID prefix
     * changes with the menu title's count bubble.
     *
     * @return bool
     */
    public static function is_plugin_screen() : bool {
        if ( ! is_admin() || ! class_exists( __NAMESPACE__ . '\\Menu' ) ) {
            return false;
        }

        return array_key_exists( self::current_page_slug(), Menu::get_tabs() );
    } // End is_plugin_screen()


    /**
     * Get the current admin page slug from $_GET['page'].
     *
     * @return string
     */
    public static function current_page_slug() : string {
        return isset( $_GET[ 'page' ] ) ? sanitize_key( wp_unslash( $_GET[ 'page' ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only, used only to detect the current screen.
    } // End current_page_slug()


    /**
     * Sanitize a hex color, returning a fallback when invalid.
     *
     * @param mixed  $color
     * @param string $fallback
     * @return string
     */
    public static function hex( $color, string $fallback = '' ) : string {
        $clean = sanitize_hex_color( is_string( $color ) ? $color : '' );

        return $clean ? $clean : $fallback;
    } // End hex()


    /**
     * Get the relative luminance of a hex color.
     *
     * @param string $hex
     * @return float
     */
    private static function luminance( string $hex ) : float {
        $hex = ltrim( self::hex( $hex, '#000000' ), '#' );
        $hex = 3 === strlen( $hex ) ? $hex[ 0 ] . $hex[ 0 ] . $hex[ 1 ] . $hex[ 1 ] . $hex[ 2 ] . $hex[ 2 ] : $hex;
        $channels = [];

        foreach ( [ 0, 2, 4 ] as $offset ) {
            $value = hexdec( substr( $hex, $offset, 2 ) ) / 255;
            $channels[] = $value <= 0.03928 ? $value / 12.92 : pow( ( $value + 0.055 ) / 1.055, 2.4 );
        }

        return 0.2126 * $channels[ 0 ] + 0.7152 * $channels[ 1 ] + 0.0722 * $channels[ 2 ];
    } // End luminance()


    /**
     * Get white for dark and mid-tone backgrounds, black otherwise.
     *
     * @param string $background Hex color.
     * @return string '#000000' or '#ffffff'.
     */
    public static function text_color( string $background ) : string {
        return self::luminance( $background ) <= 0.22 ? '#ffffff' : '#000000';
    } // End text_color()


    /**
     * Output a help tooltip icon.
     *
     * @param string $text
     * @return void
     */
    public static function tooltip( string $text ) : void {
        if ( '' === $text ) {
            return;
        }
        ?>
        <span class="ptscanner-tooltip" tabindex="0">
            <span class="dashicons dashicons-editor-help" aria-hidden="true"></span>
            <span class="ptscanner-tooltip-text" role="tooltip"><?php echo wp_kses_post( $text ); ?></span>
        </span>
        <?php
    } // End tooltip()



    /**
     * Format a number of seconds as "2m 5s" or "45s".
     *
     * @param int $seconds
     * @return string
     */
    public static function format_duration( int $seconds ) : string {
        $minutes = intdiv( max( 0, $seconds ), 60 );
        $seconds = max( 0, $seconds ) % 60;

        if ( $minutes > 0 ) {
            /* translators: 1: minutes, 2: seconds */
            return sprintf( __( '%1$dm %2$ds', 'prohibited-terms-scanner' ), $minutes, $seconds );
        }

        /* translators: %d: seconds */
        return sprintf( __( '%ds', 'prohibited-terms-scanner' ), $seconds );
    } // End format_duration()


    /**
     * Enqueue the shared stylesheets: theme, dynamic theme colors and component styles.
     *
     * @param string $type admin|frontend
     * @return void
     */
    public static function enqueue_styles( string $type ) : void {
        $ver = Bootstrap::script_version();

        wp_enqueue_style( 'ptscanner-theme', Bootstrap::url( 'inc/css/theme.css' ), [ 'dashicons' ], $ver );
        DynamicCss::enqueue( 'ptscanner-dynamic', $type, [ 'ptscanner-theme' ] );
        wp_enqueue_style( 'ptscanner-admin', Bootstrap::url( 'inc/css/admin.css' ), [ 'ptscanner-theme' ], $ver );
    } // End enqueue_styles()


    /**
     * Enqueue plugin scripts from inc/js/ in order, each depending on ptscanner-core and the ones before it.
     *
     * @param array $scripts Script file names without the .js extension.
     * @return void
     */
    public static function enqueue_scripts( array $scripts ) : void {
        $deps = [ 'ptscanner-core' ];

        foreach ( $scripts as $script ) {
            wp_enqueue_script( 'ptscanner-' . $script, Bootstrap::url( 'inc/js/' . $script . '.js' ), $deps, Bootstrap::script_version(), true );
            $deps[] = 'ptscanner-' . $script;
        }
    } // End enqueue_scripts()


    /**
     * Register the core script handle that carries ptscanner_data, shared by the admin tabs and the front-end shortcode.
     *
     * @param array $extra Data merged over the shared data.
     * @return void
     */
    public static function enqueue_core_script( array $extra = [] ) : void {
        wp_register_script( 'ptscanner-core', false, [ 'jquery', 'wp-i18n' ], Bootstrap::script_version(), true );
        wp_enqueue_script( 'ptscanner-core' );
        wp_localize_script( 'ptscanner-core', 'ptscanner_data', array_replace_recursive( self::script_data(), $extra ) );
    } // End enqueue_core_script()


    /**
     * Data shared by every ptscanner script, passed as ptscanner_data.
     *
     * @return array
     */
    public static function script_data() : array {
        $settings = Settings::instance();

        $data = [
            'ajaxUrl'              => admin_url( 'admin-ajax.php' ),
            'nonce'                => wp_create_nonce( 'ptscanner_nonce' ),
            'savedTerms'           => $settings->get_terms(),
            'locationTypes'        => TypeRegistry::instance()->get_grouped_types(),
            'enabledTypes'         => $settings->get_enabled_location_types(),
            'defaultCaseSensitive' => $settings->get_default_case_sensitive(),
            'defaultStrict'        => $settings->get_default_strict(),
            'batchSize'            => $settings->get_batch_size(),
            'canScan'              => Access::can_scan(),
            'strings'              => [
                'scanning'               => __( 'Scanning', 'prohibited-terms-scanner' ),
                'done'                   => __( 'Scan complete.', 'prohibited-terms-scanner' ),
                'noTerms'                => __( 'Add at least one term or phrase first.', 'prohibited-terms-scanner' ),
                'noLocations'            => __( 'Select at least one location to search in.', 'prohibited-terms-scanner' ),
                'confirmClear'           => __( 'Clear this result? This cannot be undone.', 'prohibited-terms-scanner' ),
                'duplicateTerm'          => __( 'Already in the list, skipped.', 'prohibited-terms-scanner' ),
                'requestFailed'          => __( 'Request failed (network or server error).', 'prohibited-terms-scanner' ),
                'unknownError'           => __( 'Unknown error.', 'prohibited-terms-scanner' ),
                'cancelling'             => __( 'Cancelling…', 'prohibited-terms-scanner' ),
                'cancelled'              => __( 'Scan cancelled. Results so far are saved.', 'prohibited-terms-scanner' ),
                'caseSensitiveLabel'     => __( 'Case Sensitive', 'prohibited-terms-scanner' ),
                'strictLabel'            => __( 'Strict', 'prohibited-terms-scanner' ),
                'caseBadge'              => _x( 'CS', 'short for Case Sensitive', 'prohibited-terms-scanner' ),
                'removeTerm'             => __( 'Remove term', 'prohibited-terms-scanner' ),
                'edit'                   => __( 'Edit', 'prohibited-terms-scanner' ),
                'editList'               => __( 'Edit list', 'prohibited-terms-scanner' ),
                /* translators: %d: number of terms */
                'termsLoaded'            => __( '%d terms loaded.', 'prohibited-terms-scanner' ),
                'termsSaved'             => __( 'Terms saved.', 'prohibited-terms-scanner' ),
                'termsSaveError'         => __( 'Error saving terms. Please try again.', 'prohibited-terms-scanner' ),
                'unsavedTerms'           => __( 'You have unsaved term changes.', 'prohibited-terms-scanner' ),
                /* translators: 1: location label, 2: number of items checked, 3: number of matches found */
                'progressDetail'         => __( '%1$s (~%2$d checked, %3$d found so far)', 'prohibited-terms-scanner' ),
                /* translators: 1: location label, 2: batch offset, 3: technical error message */
                'scanError'              => __( 'There was an error scanning %1$s at batch %2$d. Technical detail: %3$s', 'prohibited-terms-scanner' ),
                'loading'                => __( 'Loading…', 'prohibited-terms-scanner' ),
                'noResults'              => __( 'No results found.', 'prohibited-terms-scanner' ),
                'confirmClearAllTerms'   => __( 'Clear all terms from the list? This cannot be undone.', 'prohibited-terms-scanner' ),
                'actionFailed'           => __( 'Something went wrong. Please try again.', 'prohibited-terms-scanner' ),
                'confirmClearErrors'     => __( 'Clear all logged errors?', 'prohibited-terms-scanner' ),
                'confirmClearAllFlagged' => __( 'Clear all flagged results? This cannot be undone.', 'prohibited-terms-scanner' ),
                'confirmClearAllIgnored' => __( 'Clear all results marked as OK? This cannot be undone.', 'prohibited-terms-scanner' ),
                'confirmClearWithNote'   => __( 'This result has a note on it. Clear it anyway? This cannot be undone.', 'prohibited-terms-scanner' ),
                'confirmOmit'            => __( 'Omit this source from all future scans? Existing flagged results for this source will also be cleared.', 'prohibited-terms-scanner' ),
                'omitSource'             => __( 'Omit Source', 'prohibited-terms-scanner' ),
                'omitPage'               => __( 'Omit Page', 'prohibited-terms-scanner' ),
                'unomit'                 => __( 'Un-Omit', 'prohibited-terms-scanner' ),
                'mediaNotUsed'           => __( 'Not used anywhere. Safe to omit.', 'prohibited-terms-scanner' ),
                'mediaUsedOn'            => __( 'Used on:', 'prohibited-terms-scanner' ),
                'noFlaggedTerms'         => __( 'No flagged terms.', 'prohibited-terms-scanner' ),
                /* translators: %d: number of flagged results */
                'flaggedCount'           => __( 'Flagged (%d)', 'prohibited-terms-scanner' ),
                /* translators: %d: number of results marked as OK */
                'ignoredCount'           => __( 'Marked as OK (%d)', 'prohibited-terms-scanner' ),
                /* translators: %d: number of external links */
                'allLinksCount'          => __( 'All Links (%d)', 'prohibited-terms-scanner' ),
                /* translators: %d: number of flagged external links */
                'flaggedLinksCount'      => __( 'Flagged Only (%d)', 'prohibited-terms-scanner' ),
                'allTerms'               => __( 'All Terms', 'prohibited-terms-scanner' ),
                'allLocations'           => __( 'All Locations', 'prohibited-terms-scanner' ),
                /* translators: %d: page number within a file */
                'pageNumber'             => __( 'Page %d', 'prohibited-terms-scanner' ),
                'viewSource'             => __( 'View Source', 'prohibited-terms-scanner' ),
                'addNote'                => __( 'Add a note…', 'prohibited-terms-scanner' ),
                'note'                   => __( 'Note', 'prohibited-terms-scanner' ),
                'saveNote'               => __( 'Save Note', 'prohibited-terms-scanner' ),
                'saving'                 => __( 'Saving…', 'prohibited-terms-scanner' ),
                'saved'                  => __( 'Saved', 'prohibited-terms-scanner' ),
                'markOk'                 => __( 'Mark as OK', 'prohibited-terms-scanner' ),
                'unignore'               => __( 'Unignore', 'prohibited-terms-scanner' ),
                'clear'                  => __( 'Clear', 'prohibited-terms-scanner' ),
                'checkUsage'             => __( 'Check Usage', 'prohibited-terms-scanner' ),
                'noLinks'                => __( 'No links found.', 'prohibited-terms-scanner' ),
                /* translators: 1: number of links found, 2: number of pages scanned */
                'extLinksDone'           => __( 'Scan complete. %1$d links found across %2$d pages.', 'prohibited-terms-scanner' ),
            ],
        ];

        /**
         * Filter the data passed to every ptscanner script as ptscanner_data (admin tabs and front-end shortcode).
         *
         * @param array $data
         */
        return (array) apply_filters( 'ptscanner_script_data', $data );
    } // End script_data()

} // End class Helpers
