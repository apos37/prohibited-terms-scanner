<?php
/**
 * Front-end shortcode view. Scans, results, page omits and external links are handled
 * via AJAX by terms-ui.js, scanner.js and results.js.
 */

namespace PluginRx\ProhibitedTermsScanner;

if ( ! defined( 'ABSPATH' ) ) exit;
?>
<div class="ptscanner-shortcode-scanner">

    <?php
    $ptscanner_is_admin = false;
    require Bootstrap::path( 'views/partials/scanner-boxes.php' );
    ?>

    <div class="ptscanner-box ptscanner-summary-box">
        <details class="ptscanner-box-details" id="ptscanner-summary-accordion">
            <summary class="ptscanner-box-header"><h2><?php esc_html_e( 'Summary by Term', 'prohibited-terms-scanner' ); ?></h2></summary>
            <div class="ptscanner-box-body">
                <table class="ptscanner-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Term/Phrase', 'prohibited-terms-scanner' ); ?></th>
                            <th><?php esc_html_e( 'Occurrences Found', 'prohibited-terms-scanner' ); ?></th>
                        </tr>
                    </thead>
                    <tbody id="ptscanner-persistent-summary-body">
                        <tr><td colspan="2"><?php esc_html_e( 'Loading…', 'prohibited-terms-scanner' ); ?></td></tr>
                    </tbody>
                </table>
            </div>
        </details>
    </div>

    <div class="ptscanner-box ptscanner-front-panels">
        <div class="ptscanner-box-header ptscanner-toolbar">
            <div class="ptscanner-segmented" role="group" aria-label="<?php esc_attr_e( 'Section', 'prohibited-terms-scanner' ); ?>">
                <button type="button" class="ptscanner-front-main-tab current" data-panel="results" aria-pressed="true"><?php esc_html_e( 'Results', 'prohibited-terms-scanner' ); ?></button>
                <button type="button" class="ptscanner-front-main-tab" data-panel="pages" aria-pressed="false"><?php esc_html_e( 'Omit Public Pages', 'prohibited-terms-scanner' ); ?></button>
                <button type="button" class="ptscanner-front-main-tab" data-panel="external-links" aria-pressed="false"><?php esc_html_e( 'External Links', 'prohibited-terms-scanner' ); ?></button>
            </div>
        </div>

        <div class="ptscanner-front-panel" id="ptscanner-front-results" data-status="flagged">
            <div class="ptscanner-toolbar ptscanner-panel-toolbar">
                <div class="ptscanner-segmented" role="group" aria-label="<?php esc_attr_e( 'Result status', 'prohibited-terms-scanner' ); ?>">
                    <button type="button" class="ptscanner-front-tab current" data-status="flagged" data-count-status="flagged" aria-pressed="true"><?php esc_html_e( 'Flagged', 'prohibited-terms-scanner' ); ?></button>
                    <button type="button" class="ptscanner-front-tab" data-status="ignored" data-count-status="ignored" aria-pressed="false"><?php esc_html_e( 'Marked as OK', 'prohibited-terms-scanner' ); ?></button>
                </div>
                <div class="ptscanner-results-filters">
                    <label class="screen-reader-text" for="ptscanner-front-term-filter"><?php esc_html_e( 'Filter by term', 'prohibited-terms-scanner' ); ?></label>
                    <select id="ptscanner-front-term-filter"><option value=""><?php esc_html_e( 'All Terms', 'prohibited-terms-scanner' ); ?></option></select>
                    <label class="screen-reader-text" for="ptscanner-front-location-filter"><?php esc_html_e( 'Filter by location', 'prohibited-terms-scanner' ); ?></label>
                    <select id="ptscanner-front-location-filter"><option value=""><?php esc_html_e( 'All Locations', 'prohibited-terms-scanner' ); ?></option></select>
                </div>
                <button type="button" class="ptscanner-button ptscanner-button-danger ptscanner-toolbar-end" id="ptscanner-front-clear-all"><?php esc_html_e( 'Clear All', 'prohibited-terms-scanner' ); ?></button>
            </div>
            <div class="ptscanner-table-wrap">
                <table class="ptscanner-table ptscanner-results-table">
                    <thead>
                        <tr>
                            <th class="column-term"><?php esc_html_e( 'Term/Phrase', 'prohibited-terms-scanner' ); ?></th>
                            <th class="column-context"><?php esc_html_e( 'Context', 'prohibited-terms-scanner' ); ?></th>
                            <th class="column-location"><?php esc_html_e( 'Location', 'prohibited-terms-scanner' ); ?></th>
                            <th class="column-source"><?php esc_html_e( 'Source', 'prohibited-terms-scanner' ); ?></th>
                            <th class="column-note"><?php esc_html_e( 'Note', 'prohibited-terms-scanner' ); ?></th>
                            <th class="column-date"><?php esc_html_e( 'Date', 'prohibited-terms-scanner' ); ?></th>
                            <th class="column-actions"><?php esc_html_e( 'Actions', 'prohibited-terms-scanner' ); ?></th>
                        </tr>
                    </thead>
                    <tbody id="ptscanner-front-results-body"></tbody>
                </table>
            </div>
            <div class="ptscanner-pagination" id="ptscanner-front-pagination" hidden></div>
        </div>

        <div class="ptscanner-front-panel" id="ptscanner-front-pages" hidden>
            <div class="ptscanner-table-wrap">
                <table class="ptscanner-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Page', 'prohibited-terms-scanner' ); ?></th>
                            <th><?php esc_html_e( 'Type', 'prohibited-terms-scanner' ); ?></th>
                            <th class="column-actions"><?php esc_html_e( 'Status', 'prohibited-terms-scanner' ); ?></th>
                        </tr>
                    </thead>
                    <tbody id="ptscanner-front-pages-body"></tbody>
                </table>
            </div>
            <div class="ptscanner-pagination" id="ptscanner-front-pages-pagination" hidden></div>
        </div>

        <div class="ptscanner-front-panel" id="ptscanner-front-external-links" hidden>
            <div class="ptscanner-toolbar ptscanner-panel-toolbar">
                <div class="ptscanner-segmented" role="group" aria-label="<?php esc_attr_e( 'Link filter', 'prohibited-terms-scanner' ); ?>">
                    <button type="button" class="ptscanner-ext-links-front-filter-tab current" data-filter="all" aria-pressed="true"><?php esc_html_e( 'All Links', 'prohibited-terms-scanner' ); ?></button>
                    <button type="button" class="ptscanner-ext-links-front-filter-tab" data-filter="flagged" aria-pressed="false"><?php esc_html_e( 'Flagged Only', 'prohibited-terms-scanner' ); ?></button>
                </div>
            </div>
            <div class="ptscanner-table-wrap">
                <table class="ptscanner-table ptscanner-ext-links-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Link', 'prohibited-terms-scanner' ); ?></th>
                            <th><?php esc_html_e( 'Anchor Text', 'prohibited-terms-scanner' ); ?></th>
                            <th><?php esc_html_e( 'Found On', 'prohibited-terms-scanner' ); ?></th>
                            <th><?php esc_html_e( 'Flagged Term', 'prohibited-terms-scanner' ); ?></th>
                            <th><?php esc_html_e( 'Note', 'prohibited-terms-scanner' ); ?></th>
                        </tr>
                    </thead>
                    <tbody id="ptscanner-front-ext-links-body"></tbody>
                </table>
            </div>
            <div class="ptscanner-pagination" id="ptscanner-front-ext-links-pagination" hidden></div>
        </div>
    </div>
</div>
