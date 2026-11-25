<?php
/**
 * Errors tab view. Clearing is handled via AJAX by results.js.
 *
 * @var array $errors
 */

namespace PluginRx\ProhibitedTermsScanner;

if ( ! defined( 'ABSPATH' ) ) exit;
?>
<div class="wrap ptscanner-wrap ptscanner-content-wrap ptscanner-table-page ptscanner-errors-page">

    <div class="ptscanner-note ptscanner-page-note">
        <p><?php esc_html_e( 'Errors caught during scans, imports and other operations are logged here, so a single failure never silently breaks the rest of a run.', 'prohibited-terms-scanner' ); ?></p>
    </div>

    <div class="ptscanner-box">
        <div class="ptscanner-box-header ptscanner-toolbar">
            <h2><?php esc_html_e( 'Error Log', 'prohibited-terms-scanner' ); ?></h2>
            <button type="button" class="ptscanner-button ptscanner-button-danger ptscanner-toolbar-end" id="ptscanner-clear-errors" <?php disabled( empty( $errors ) ); ?>><?php esc_html_e( 'Clear Error Log', 'prohibited-terms-scanner' ); ?></button>
        </div>
        <div class="ptscanner-table-wrap">
            <table class="ptscanner-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Context', 'prohibited-terms-scanner' ); ?></th>
                        <th><?php esc_html_e( 'Message', 'prohibited-terms-scanner' ); ?></th>
                        <th><?php esc_html_e( 'Details', 'prohibited-terms-scanner' ); ?></th>
                        <th><?php esc_html_e( 'Time', 'prohibited-terms-scanner' ); ?></th>
                    </tr>
                </thead>
                <tbody id="ptscanner-errors-body">
                    <?php if ( empty( $errors ) ) : ?>
                        <tr><td colspan="4"><?php esc_html_e( 'No errors logged.', 'prohibited-terms-scanner' ); ?></td></tr>
                    <?php else : ?>
                        <?php foreach ( $errors as $ptscanner_error ) : ?>
                            <tr>
                                <td><code><?php echo esc_html( $ptscanner_error[ 'context' ] ); ?></code></td>
                                <td><?php echo esc_html( $ptscanner_error[ 'message' ] ); ?></td>
                                <td>
                                    <?php foreach ( (array) $ptscanner_error[ 'extra' ] as $ptscanner_key => $ptscanner_value ) : ?>
                                        <?php echo esc_html( $ptscanner_key . ': ' . ( is_scalar( $ptscanner_value ) ? $ptscanner_value : wp_json_encode( $ptscanner_value ) ) ); ?><br>
                                    <?php endforeach; ?>
                                </td>
                                <td><?php echo esc_html( $ptscanner_error[ 'time' ] ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
