<?php
/**
 * Scanner tab view. Terms and scans are handled via AJAX by terms-ui.js and scanner.js.
 */

namespace PluginRx\ProhibitedTermsScanner;

if ( ! defined( 'ABSPATH' ) ) exit;
?>
<div class="wrap ptscanner-wrap ptscanner-content-wrap ptscanner-scanner-page">

    <?php if ( ! class_exists( '\ZipArchive' ) ) : ?>
        <div class="ptscanner-note ptscanner-note-warning ptscanner-page-note">
            <p><?php esc_html_e( 'Your server does not have the PHP ZipArchive extension enabled, so Word (.docx) file content cannot be scanned. Filenames are unaffected. Contact your hosting provider to enable it.', 'prohibited-terms-scanner' ); ?></p>
        </div>
    <?php endif; ?>

    <?php
    $ptscanner_is_admin = true;
    require Bootstrap::path( 'views/partials/scanner-boxes.php' );
    ?>
</div>
