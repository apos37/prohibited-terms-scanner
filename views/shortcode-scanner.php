<?php
/**
 * Shortcode scanner view (front-end)
 */

namespace PluginRx\ProhibitedTermsScanner;

if ( ! defined( 'ABSPATH' ) ) exit;

$ptscanner_settings = Settings::instance();
?>
<div class="ptscanner-wrap ptscanner-shortcode-scanner">
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
        </div>

        <div class="ptscanner-progress" id="ptscanner-progress" style="display:none;">
            <div class="ptscanner-progress-bar">
                <div class="ptscanner-progress-fill" id="ptscanner-progress-fill"></div>
            </div>
            <p class="ptscanner-progress-label">
                <span class="ptscanner-progress-spinner"></span>
                <span id="ptscanner-progress-label-text"></span>
            </p>
            <ul class="ptscanner-scan-errors" id="ptscanner-scan-errors"></ul>
        </div>
    </div>

    <div class="ptscanner-summary" id="ptscanner-summary" style="display:none;">
        <h2><?php esc_html_e( 'Summary', 'prohibited-terms-scanner' ); ?></h2>
        <table class="ptscanner-front-table">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Term/Phrase', 'prohibited-terms-scanner' ); ?></th>
                    <th><?php esc_html_e( 'Occurrences Found', 'prohibited-terms-scanner' ); ?></th>
                </tr>
            </thead>
            <tbody id="ptscanner-summary-body"></tbody>
        </table>
    </div>

    <div class="ptscanner-front-results" id="ptscanner-front-results" style="display:none;">
        <h2><?php esc_html_e( 'Results', 'prohibited-terms-scanner' ); ?></h2>

        <ul class="ptscanner-front-tabs">
            <li><button type="button" class="button-link ptscanner-front-tab" data-status="flagged"><?php esc_html_e( 'Flagged', 'prohibited-terms-scanner' ); ?></button></li>
            <li><button type="button" class="button-link ptscanner-front-tab" data-status="ignored"><?php esc_html_e( 'Marked as OK', 'prohibited-terms-scanner' ); ?></button></li>
        </ul>

        <div class="ptscanner-front-clear-all-wrap">
            <button type="button" class="button" id="ptscanner-front-clear-all" data-status="flagged">
                <?php esc_html_e( 'Clear All', 'prohibited-terms-scanner' ); ?>
            </button>
        </div>

        <table class="ptscanner-front-table ptscanner-results-table">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Term/Phrase', 'prohibited-terms-scanner' ); ?></th>
                    <th><?php esc_html_e( 'Context', 'prohibited-terms-scanner' ); ?></th>
                    <th><?php esc_html_e( 'Location', 'prohibited-terms-scanner' ); ?></th>
                    <th><?php esc_html_e( 'Source', 'prohibited-terms-scanner' ); ?></th>
                    <th><?php esc_html_e( 'Date', 'prohibited-terms-scanner' ); ?></th>
                    <th><?php esc_html_e( 'Actions', 'prohibited-terms-scanner' ); ?></th>
                </tr>
            </thead>
            <tbody id="ptscanner-front-results-body"></tbody>
        </table>

        <div class="ptscanner-front-pagination" id="ptscanner-front-pagination"></div>
    </div>
</div>