<?php
/**
 * Scanner page view
 */

namespace PluginRx\ProhibitedTermsScanner;

if ( ! defined( 'ABSPATH' ) ) exit;

$ptscanner_settings = Settings::instance();
?>
<div class="wrap ptscanner-wrap ptscanner-scanner-page">
    <header class="ptscanner-header">
        <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
        <p class="description"><?php esc_html_e( 'Paste a list of terms or phrases to search for across your site.', 'prohibited-terms-scanner' ); ?></p>
    </header>

    <?php
        $ptscanner_last_scan_seconds = get_option( 'ptscanner_last_scan_duration', 0 );
        $ptscanner_last_scan_time    = get_option( 'ptscanner_last_scan_time', '' );
        ?>
        <?php if ( $ptscanner_last_scan_seconds > 0 ) : ?>
            <p class="description">
                <?php
                $ptscanner_minutes = floor( $ptscanner_last_scan_seconds / 60 );
                $ptscanner_seconds = $ptscanner_last_scan_seconds % 60;
                $ptscanner_duration_text = $ptscanner_minutes > 0
                    ? sprintf( '%dm %ds', $ptscanner_minutes, $ptscanner_seconds )
                    : sprintf( '%ds', $ptscanner_seconds );

                printf(
                    /* translators: 1: duration, 2: date/time */
                    esc_html__( 'Last scan took %1$s, completed %2$s.', 'prohibited-terms-scanner' ),
                    esc_html( $ptscanner_duration_text ),
                    esc_html( mysql2date( 'Y-m-d g:i A', $ptscanner_last_scan_time ) )
                );
                ?>
            </p>
        <?php endif; ?>

    <?php if ( ! class_exists( '\ZipArchive' ) ) : ?>
        <div class="notice notice-warning inline ptscanner-ziparchive-notice">
            <p>
                <?php esc_html_e( 'Your server does not have the PHP ZipArchive extension enabled. This means Word (.docx) files cannot be scanned for content — filenames are unaffected. Contact your hosting provider to enable this extension if you need .docx content scanning.', 'prohibited-terms-scanner' ); ?>
            </p>
        </div>
    <?php endif; ?>

    <div class="ptscanner-card">
        <details class="ptscanner-terms-accordion" <?php echo empty( $ptscanner_settings->get_terms() ) ? 'open' : ''; ?>>
            <summary><?php esc_html_e( 'Terms & Options', 'prohibited-terms-scanner' ); ?></summary>

            <div class="ptscanner-terms-input">
                <label for="ptscanner-terms-textarea"><?php esc_html_e( 'Paste terms, one per line', 'prohibited-terms-scanner' ); ?></label>
                <textarea id="ptscanner-terms-textarea" rows="4" class="large-text"></textarea>
                <button type="button" class="button" id="ptscanner-add-terms">
                    <?php esc_html_e( 'Add Terms', 'prohibited-terms-scanner' ); ?>
                </button>
                <button type="button" class="button" id="ptscanner-clear-all-terms">
                    <?php esc_html_e( 'Clear All Terms', 'prohibited-terms-scanner' ); ?>
                </button>
                <p class="description ptscanner-strict-note">
                    <?php esc_html_e( '"Case Sensitive" matches exact letter casing. "Strict" matches whole words only (e.g. "class" will not match "classic").', 'prohibited-terms-scanner' ); ?>
                </p>
            </div>

            <div class="ptscanner-terms-cards" id="ptscanner-terms-cards">
                <p class="ptscanner-terms-loading"><?php esc_html_e( 'Fetching terms…', 'prohibited-terms-scanner' ); ?></p>
            </div>
        </details>

        <div class="ptscanner-terms-pills" id="ptscanner-terms-pills" style="display:none;"></div>

        <div class="ptscanner-location-types">
            <h2><?php esc_html_e( 'Search In', 'prohibited-terms-scanner' ); ?></h2>
            <div id="ptscanner-location-type-checkboxes"></div>
        </div>

        <div class="ptscanner-actions">
            <button type="button" class="button button-primary button-hero" id="ptscanner-start-scan">
                <?php esc_html_e( 'Start Full Scan', 'prohibited-terms-scanner' ); ?>
            </button>
            <button type="button" class="button" id="ptscanner-cancel-scan" style="display:none;">
                <?php esc_html_e( 'Cancel Scan', 'prohibited-terms-scanner' ); ?>
            </button>
            <span class="ptscanner-scan-note">
                <?php esc_html_e( 'A full scan clears previous unresolved results before running.', 'prohibited-terms-scanner' ); ?>
            </span>
        </div>

        <div class="ptscanner-progress" id="ptscanner-progress" style="display:none;">
            <div class="ptscanner-progress-bar">
                <div class="ptscanner-progress-fill" id="ptscanner-progress-fill"></div>
            </div>
            <p class="ptscanner-progress-label">
                <span class="ptscanner-progress-spinner"></span>
                <span id="ptscanner-progress-label-text"></span>
            </p>
            <p class="ptscanner-elapsed-time" id="ptscanner-elapsed-time" style="display:none;">
                <?php esc_html_e( 'Elapsed: ', 'prohibited-terms-scanner' ); ?><span id="ptscanner-elapsed-time-text">0s</span>
            </p>
            <ul class="ptscanner-scan-errors" id="ptscanner-scan-errors"></ul>
        </div>
    </div>

    <p>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . Bootstrap::textdomain() . '_results' ) ); ?>" class="button">
            <?php esc_html_e( 'View Full Results', 'prohibited-terms-scanner' ); ?>
        </a>
    </p>
</div>