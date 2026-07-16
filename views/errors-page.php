<?php
/**
 * Errors page view
 *
 * @var array $errors
 */

namespace PluginRx\ProhibitedTermsScanner;

if ( ! defined( 'ABSPATH' ) ) exit;
?>
<div class="wrap ptscanner-wrap ptscanner-errors-page">
    <header class="ptscanner-header">
        <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
        <p class="description"><?php esc_html_e( 'Errors caught during scans, imports, or other operations are logged here so a single failure never silently breaks the rest of a run.', 'prohibited-terms-scanner' ); ?></p>
    </header>

    <div class="ptscanner-card">
        <button type="button" class="button" id="ptscanner-clear-errors" <?php echo empty( $errors ) ? 'disabled' : ''; ?>>
            <?php esc_html_e( 'Clear Error Log', 'prohibited-terms-scanner' ); ?>
        </button>

        <table class="wp-list-table widefat fixed striped" style="margin-top:15px;">
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
                    <tr>
                        <td colspan="4"><?php esc_html_e( 'No errors logged.', 'prohibited-terms-scanner' ); ?></td>
                    </tr>
                <?php else : ?>
                    <?php foreach ( $errors as $ptscanner_error ) : ?>
                        <tr>
                            <td><?php echo esc_html( $ptscanner_error[ 'context' ] ); ?></td>
                            <td><?php echo esc_html( $ptscanner_error[ 'message' ] ); ?></td>
                            <td>
                                <?php foreach ( $ptscanner_error[ 'extra' ] as $ptscanner_key => $ptscanner_value ) : ?>
                                    <?php echo esc_html( $ptscanner_key . ': ' . $ptscanner_value ); ?><br>
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