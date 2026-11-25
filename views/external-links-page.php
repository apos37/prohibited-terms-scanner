<?php
/**
 * External Links tab view. Loaded and scanned via AJAX by external-links.js.
 */

namespace PluginRx\ProhibitedTermsScanner;

if ( ! defined( 'ABSPATH' ) ) exit;

$ptscanner_can_scan = Access::can_scan();
?>
<div class="wrap ptscanner-wrap ptscanner-content-wrap ptscanner-table-page ptscanner-external-links-page">

    <div class="ptscanner-note ptscanner-page-note">
        <p><?php esc_html_e( 'All external links found across your content. Links containing a prohibited term are flagged; use notes to track which need fixing.', 'prohibited-terms-scanner' ); ?></p>
    </div>

    <?php if ( $ptscanner_can_scan ) : ?>
        <div class="ptscanner-box">
            <div class="ptscanner-box-header"><h2><?php esc_html_e( 'Scan External Links', 'prohibited-terms-scanner' ); ?></h2></div>
            <div class="ptscanner-box-body">
                <div class="ptscanner-inline-actions">
                    <button type="button" class="ptscanner-button ptscanner-button-large" id="ptscanner-ext-links-start-scan"><?php esc_html_e( 'Scan External Links', 'prohibited-terms-scanner' ); ?></button>
                </div>
                <div class="ptscanner-progress" id="ptscanner-ext-links-progress" hidden>
                    <div class="ptscanner-progress-bar">
                        <div class="ptscanner-progress-fill" id="ptscanner-ext-links-progress-fill"></div>
                    </div>
                    <p class="ptscanner-progress-label">
                        <span class="ptscanner-progress-spinner"></span>
                        <span id="ptscanner-ext-links-progress-label" aria-live="polite"></span>
                    </p>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="ptscanner-box">
        <div class="ptscanner-box-header ptscanner-toolbar">
            <div class="ptscanner-segmented" role="group" aria-label="<?php esc_attr_e( 'Link filter', 'prohibited-terms-scanner' ); ?>">
                <button type="button" class="ptscanner-ext-links-filter-tab current" data-filter="all" aria-pressed="true"><?php esc_html_e( 'All Links', 'prohibited-terms-scanner' ); ?></button>
                <button type="button" class="ptscanner-ext-links-filter-tab" data-filter="flagged" aria-pressed="false"><?php esc_html_e( 'Flagged Only', 'prohibited-terms-scanner' ); ?></button>
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
                <tbody id="ptscanner-ext-links-body">
                    <tr><td colspan="5"><?php esc_html_e( 'Loading…', 'prohibited-terms-scanner' ); ?></td></tr>
                </tbody>
            </table>
        </div>
        <div class="ptscanner-pagination" id="ptscanner-ext-links-pagination" hidden></div>
    </div>
</div>
