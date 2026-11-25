<?php
/**
 * External links page view
 */

namespace PluginRx\ProhibitedTermsScanner;

if ( ! defined( 'ABSPATH' ) ) exit;
?>
<div class="wrap ptscanner-wrap ptscanner-external-links-page">
    <header class="ptscanner-header">
        <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
        <p class="description"><?php esc_html_e( 'All external links found across your content. Links containing a prohibited term are flagged; use notes to track which need fixing.', 'prohibited-terms-scanner' ); ?></p>
    </header>

    <div class="ptscanner-card">
        <button type="button" class="button button-primary" id="ptscanner-ext-links-start-scan">
            <?php esc_html_e( 'Scan External Links', 'prohibited-terms-scanner' ); ?>
        </button>

        <div class="ptscanner-progress" id="ptscanner-ext-links-progress" style="display:none;">
            <div class="ptscanner-progress-bar">
                <div class="ptscanner-progress-fill" id="ptscanner-ext-links-progress-fill"></div>
            </div>
            <p class="ptscanner-progress-label">
                <span class="ptscanner-progress-spinner"></span>
                <span id="ptscanner-ext-links-progress-label"></span>
            </p>
        </div>
    </div>

    <ul class="subsubsub">
        <li><button type="button" class="button-link ptscanner-ext-links-filter-tab active" data-filter="all"><?php esc_html_e( 'All Links', 'prohibited-terms-scanner' ); ?></button> |</li>
        <li><button type="button" class="button-link ptscanner-ext-links-filter-tab" data-filter="flagged"><?php esc_html_e( 'Flagged Only', 'prohibited-terms-scanner' ); ?></button></li>
    </ul>

    <table class="wp-list-table widefat fixed striped">
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
            <tr><td colspan="5"><?php esc_html_e( 'Click "Scan External Links" to begin.', 'prohibited-terms-scanner' ); ?></td></tr>
        </tbody>
    </table>

    <div class="ptscanner-ext-links-pagination" id="ptscanner-ext-links-pagination"></div>
</div>