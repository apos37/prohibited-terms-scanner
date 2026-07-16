<?php
/**
 * Results page view
 *
 * @var string $status
 * @var int    $page
 * @var array  $data
 */

namespace PluginRx\ProhibitedTermsScanner;

if ( ! defined( 'ABSPATH' ) ) exit;

$ptscanner_textdomain  = Bootstrap::textdomain();
$ptscanner_base_url    = admin_url( 'admin.php?page=' . $ptscanner_textdomain . '_results' );
$ptscanner_flagged_url = add_query_arg( 'status', 'flagged', $ptscanner_base_url );
$ptscanner_ignored_url = add_query_arg( 'status', 'ignored', $ptscanner_base_url );
?>
<div class="wrap ptscanner-wrap ptscanner-results-page">
    <header class="ptscanner-header">
        <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
        <p>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $ptscanner_textdomain ) ); ?>">
                &larr; <?php esc_html_e( 'Back to Scanner', 'prohibited-terms-scanner' ); ?>
            </a>
        </p>
    </header>

    <div class="ptscanner-clear-all-wrap">
        <ul class="subsubsub">
            <li>
                <a href="<?php echo esc_url( $ptscanner_flagged_url ); ?>" class="<?php echo 'flagged' === $status ? 'current' : ''; ?>">
                    <?php esc_html_e( 'Flagged', 'prohibited-terms-scanner' ); ?>
                </a> |
            </li>
            <li>
                <a href="<?php echo esc_url( $ptscanner_ignored_url ); ?>" class="<?php echo 'ignored' === $status ? 'current' : ''; ?>">
                    <?php esc_html_e( 'Marked as OK', 'prohibited-terms-scanner' ); ?>
                </a>
            </li>
        </ul>

        <?php if ( ! empty( $data[ 'rows' ] ) ) : ?>
            <button type="button" class="button" id="ptscanner-clear-all" data-status="<?php echo esc_attr( $status ); ?>">
                <?php esc_html_e( 'Clear All', 'prohibited-terms-scanner' ); ?>
            </button>
        <?php endif; ?>
    </div>

    <table class="wp-list-table widefat fixed striped ptscanner-results-table">
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
        <tbody>
            <?php if ( empty( $data[ 'rows' ] ) ) : ?>
                <tr>
                    <td colspan="6"><?php esc_html_e( 'No results found.', 'prohibited-terms-scanner' ); ?></td>
                </tr>
            <?php else : ?>
                <?php foreach ( $data[ 'rows' ] as $ptscanner_row ) : ?>
                    <tr id="ptscanner-row-<?php echo absint( $ptscanner_row[ 'id' ] ); ?>" data-id="<?php echo absint( $ptscanner_row[ 'id' ] ); ?>">
                        <td><strong><?php echo esc_html( $ptscanner_row[ 'term' ] ); ?></strong></td>
                        <td><?php echo wp_kses( $ptscanner_row[ 'context_highlighted' ], [ 'strong' => [ 'class' => [] ] ] ); ?><?php if ( ! empty( $ptscanner_row[ 'file_page' ] ) ) : ?>
                                <br><em><?php
                                    printf(
                                        /* translators: %d: file page number */
                                        esc_html__( 'Page %d', 'prohibited-terms-scanner' ),
                                        absint( $ptscanner_row[ 'file_page' ] )
                                    );
                                ?></em>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html( $ptscanner_row[ 'location_label' ] ); ?></td>
                        <td>
                            <?php if ( ! empty( $ptscanner_row[ 'source_title' ] ) ) : ?>
                                <strong><?php echo esc_html( $ptscanner_row[ 'source_title' ] ); ?></strong><br>
                            <?php endif; ?>
                            <?php if ( ! empty( $ptscanner_row[ 'highlight_link' ] ) ) : ?>
                                <a href="<?php echo esc_url( $ptscanner_row[ 'highlight_link' ] ); ?>" target="_blank">
                                    <?php esc_html_e( 'View', 'prohibited-terms-scanner' ); ?>
                                </a> |
                            <?php else : ?>
                                &mdash;
                            <?php endif; ?>
                            <a href="#" class="ptscanner-toggle-omit" data-id="<?php echo esc_attr( $ptscanner_row[ 'source_id' ] ); ?>" data-type="<?php echo esc_attr( $ptscanner_row[ 'source_type' ] ); ?>" data-omitted="0">
                                <?php esc_html_e( 'Omit', 'prohibited-terms-scanner' ); ?>
                            </a>
                        </td>
                        <td><?php echo esc_html( mysql2date( 'Y-m-d H:i', $ptscanner_row[ 'created_at' ] ) ); ?></td>
                        <td class="ptscanner-row-actions">
                            <?php if ( 'flagged' === $ptscanner_row[ 'status' ] ) : ?>
                                <button type="button" class="button-link ptscanner-mark-ok" data-id="<?php echo absint( $ptscanner_row[ 'id' ] ); ?>">
                                    <?php esc_html_e( 'Mark as OK', 'prohibited-terms-scanner' ); ?>
                                </button>
                            <?php else : ?>
                                <button type="button" class="button-link ptscanner-mark-flagged" data-id="<?php echo absint( $ptscanner_row[ 'id' ] ); ?>">
                                    <?php esc_html_e( 'Unignore', 'prohibited-terms-scanner' ); ?>
                                </button>
                            <?php endif; ?>
                            <button type="button" class="button-link ptscanner-clear-result" data-id="<?php echo absint( $ptscanner_row[ 'id' ] ); ?>">
                                <?php esc_html_e( 'Clear', 'prohibited-terms-scanner' ); ?>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <?php if ( $data[ 'total_pages' ] > 1 ) : ?>
        <div class="tablenav">
            <div class="tablenav-pages">
                <?php
                echo wp_kses_post(
                    paginate_links( [
                        'base'      => add_query_arg( 'paged', '%#%', add_query_arg( 'status', $status, $ptscanner_base_url ) ),
                        'format'    => '',
                        'current'   => $page,
                        'total'     => $data[ 'total_pages' ],
                    ] )
                );
                ?>
            </div>
        </div>
    <?php endif; ?>
</div>