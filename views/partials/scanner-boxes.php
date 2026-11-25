<?php
/**
 * Scanner boxes (Terms & Phrases, Search In, Run Scan), shared by the Scanner tab and
 * the front-end shortcode.
 *
 * @var bool $ptscanner_is_admin Whether this renders in wp-admin
 */

namespace PluginRx\ProhibitedTermsScanner;

if ( ! defined( 'ABSPATH' ) ) exit;

$ptscanner_terms         = Settings::instance()->get_terms();
$ptscanner_last_duration = (int) get_option( 'ptscanner_last_scan_duration', 0 );
$ptscanner_last_time     = (string) get_option( 'ptscanner_last_scan_time', '' );
?>
<div class="ptscanner-scanner-grid">

    <div class="ptscanner-box ptscanner-terms-box">
        <div class="ptscanner-box-header"><h2><?php esc_html_e( 'Terms & Phrases', 'prohibited-terms-scanner' ); ?></h2></div>
        <div class="ptscanner-box-body">
            <details class="ptscanner-terms-accordion" <?php echo empty( $ptscanner_terms ) ? 'open' : ''; ?>>
                <summary><?php esc_html_e( 'Edit Terms', 'prohibited-terms-scanner' ); ?> <span class="ptscanner-count-badge" id="ptscanner-terms-count"><?php echo esc_html( number_format_i18n( count( $ptscanner_terms ) ) ); ?></span></summary>
                <div class="ptscanner-field ptscanner-terms-input">
                    <label for="ptscanner-terms-textarea">
                        <?php esc_html_e( 'Paste Terms (One Per Line)', 'prohibited-terms-scanner' ); ?>
                        <?php Helpers::tooltip( __( '"Case Sensitive" matches exact letter casing. "Strict" matches whole words only, so "class" will not match "classic".', 'prohibited-terms-scanner' ) ); ?>
                    </label>
                    <textarea id="ptscanner-terms-textarea" rows="4"></textarea>
                    <div class="ptscanner-inline-actions">
                        <button type="button" class="ptscanner-button ptscanner-button-secondary" id="ptscanner-add-terms"><?php esc_html_e( 'Add Terms', 'prohibited-terms-scanner' ); ?></button>
                        <button type="button" class="ptscanner-button" id="ptscanner-save-terms"><?php esc_html_e( 'Save Terms', 'prohibited-terms-scanner' ); ?></button>
                        <button type="button" class="ptscanner-button ptscanner-button-danger" id="ptscanner-clear-all-terms"><?php esc_html_e( 'Clear All Terms', 'prohibited-terms-scanner' ); ?></button>
                        <span id="ptscanner-terms-status" aria-live="polite"></span>
                    </div>
                </div>
                <div class="ptscanner-terms-cards" id="ptscanner-terms-cards"></div>
            </details>
            <div class="ptscanner-terms-pills" id="ptscanner-terms-pills" hidden></div>
        </div>
    </div>

    <div class="ptscanner-box ptscanner-locations-box">
        <div class="ptscanner-box-header"><h2><?php esc_html_e( 'Search In', 'prohibited-terms-scanner' ); ?></h2></div>
        <div class="ptscanner-box-body">
            <div id="ptscanner-location-type-checkboxes"></div>
            <?php if ( $ptscanner_is_admin ) : ?>
            <p class="ptscanner-section-desc">
                <?php
                printf(
                    /* translators: %s: link to the Settings tab */
                    esc_html__( 'Change the default selection on the %s tab.', 'prohibited-terms-scanner' ),
                    '<a href="' . esc_url( Bootstrap::settings_url() ) . '">' . esc_html__( 'Settings', 'prohibited-terms-scanner' ) . '</a>'
                );
                ?>
            </p>
            <?php endif; ?>
        </div>
    </div>

    <div class="ptscanner-box ptscanner-run-box">
        <div class="ptscanner-box-header"><h2><?php esc_html_e( 'Run Scan', 'prohibited-terms-scanner' ); ?></h2></div>
        <div class="ptscanner-box-body">
            <div class="ptscanner-inline-actions ptscanner-actions">
                <button type="button" class="ptscanner-button ptscanner-button-large" id="ptscanner-start-scan"><?php esc_html_e( 'Start Full Scan', 'prohibited-terms-scanner' ); ?></button>
                <button type="button" class="ptscanner-button ptscanner-button-danger ptscanner-button-large" id="ptscanner-cancel-scan" hidden><?php esc_html_e( 'Cancel Scan', 'prohibited-terms-scanner' ); ?></button>
                <?php if ( $ptscanner_is_admin ) : ?>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . Menu::MENU_SLUG . '_results' ) ); ?>" class="ptscanner-button ptscanner-button-secondary ptscanner-button-large"><?php esc_html_e( 'View Results', 'prohibited-terms-scanner' ); ?></a>
                <?php endif; ?>
            </div>
            <p class="ptscanner-section-desc ptscanner-scan-note">
                <?php esc_html_e( 'A full scan clears previous unresolved results before running.', 'prohibited-terms-scanner' ); ?>
                <?php if ( $ptscanner_last_duration > 0 && '' !== $ptscanner_last_time ) : ?>
                    <br>
                    <?php
                    /* translators: 1: scan duration such as "2m 5s", 2: date and time the scan finished */
                    echo esc_html( sprintf( __( 'Last scan took %1$s, completed %2$s.', 'prohibited-terms-scanner' ), Helpers::format_duration( $ptscanner_last_duration ), mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $ptscanner_last_time ) ) );
                    ?>
                <?php endif; ?>
            </p>
            <div class="ptscanner-progress" id="ptscanner-progress" hidden>
                <div class="ptscanner-progress-bar">
                    <div class="ptscanner-progress-fill" id="ptscanner-progress-fill"></div>
                </div>
                <p class="ptscanner-progress-label">
                    <span class="ptscanner-progress-spinner"></span>
                    <span id="ptscanner-progress-label-text"></span>
                </p>
                <p class="ptscanner-elapsed-time" id="ptscanner-elapsed-time" hidden>
                    <?php esc_html_e( 'Elapsed:', 'prohibited-terms-scanner' ); ?> <span id="ptscanner-elapsed-time-text">0s</span>
                </p>
                <ul class="ptscanner-scan-errors" id="ptscanner-scan-errors"></ul>
            </div>
        </div>
    </div>

</div>
