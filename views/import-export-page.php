<?php
/**
 * Import/Export page view
 */

namespace PluginRx\ProhibitedTermsScanner;

if ( ! defined( 'ABSPATH' ) ) exit;
?>
<div class="wrap ptscanner-wrap ptscanner-import-export-page">
    <header class="ptscanner-header">
        <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
    </header>

    <div class="ptscanner-card">
        <h2><?php esc_html_e( 'Export', 'prohibited-terms-scanner' ); ?></h2>
        <p class="description"><?php esc_html_e( 'Download your current terms and settings as a JSON file to move to another site.', 'prohibited-terms-scanner' ); ?></p>
        <button type="button" class="button button-primary" id="ptscanner-export-btn">
            <?php esc_html_e( 'Export Settings (JSON)', 'prohibited-terms-scanner' ); ?>
        </button>
    </div>

    <div class="ptscanner-card">
        <h2><?php esc_html_e( 'Import', 'prohibited-terms-scanner' ); ?></h2>
        <p class="description"><?php esc_html_e( 'Importing will overwrite your current terms and settings.', 'prohibited-terms-scanner' ); ?></p>
        <input type="file" id="ptscanner-import-file" accept=".json">
        <button type="button" class="button" id="ptscanner-import-btn">
            <?php esc_html_e( 'Import JSON File', 'prohibited-terms-scanner' ); ?>
        </button>
    </div>
</div>