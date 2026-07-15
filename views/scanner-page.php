<?php
/**
 * Scanner page view
 */

namespace PluginRx\ProhibitedTermsScanner;

if ( ! defined( 'ABSPATH' ) ) exit;
?>
<div class="wrap ptscanner-wrap ptscanner-scanner-page">
    <header class="ptscanner-header">
        <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
        <p class="description"><?php esc_html_e( 'Paste a list of terms or phrases to search for across your site.', 'prohibited-terms-scanner' ); ?></p>
    </header>

    <?php if ( ! class_exists( '\ZipArchive' ) ) : ?>
        <div class="notice notice-warning inline ptscanner-ziparchive-notice">
            <p>
                <?php esc_html_e( 'Your server does not have the PHP ZipArchive extension enabled. This means Word (.docx) files cannot be scanned for content — filenames are unaffected. Contact your hosting provider to enable this extension if you need .docx content scanning.', 'prohibited-terms-scanner' ); ?>
            </p>
        </div>
    <?php endif; ?>

    <div class="ptscanner-card">
        <details class="ptscanner-terms-accordion" open>
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

            <div class="ptscanner-terms-cards" id="ptscanner-terms-cards"></div>
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
            <p class="ptscanner-progress-label" id="ptscanner-progress-label"></p>
            <ul class="ptscanner-scan-errors" id="ptscanner-scan-errors"></ul>
        </div>
    </div>

    <div class="ptscanner-summary" id="ptscanner-summary" style="display:none;">
        <h2><?php esc_html_e( 'Summary', 'prohibited-terms-scanner' ); ?></h2>
        <table class="wp-list-table widefat striped">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Term/Phrase', 'prohibited-terms-scanner' ); ?></th>
                    <th><?php esc_html_e( 'Occurrences Found', 'prohibited-terms-scanner' ); ?></th>
                </tr>
            </thead>
            <tbody id="ptscanner-summary-body"></tbody>
        </table>
    </div>

    <p>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . Bootstrap::textdomain() . '_results' ) ); ?>" class="button">
            <?php esc_html_e( 'View Full Results', 'prohibited-terms-scanner' ); ?>
        </a>
    </p>
</div>