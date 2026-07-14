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

$textdomain  = Bootstrap::textdomain();
$base_url    = admin_url( 'admin.php?page=' . $textdomain . '_results' );
$flagged_url = add_query_arg( 'status', 'flagged', $base_url );
$ignored_url = add_query_arg( 'status', 'ignored', $base_url );
?>
<div class="wrap ptscanner-wrap ptscanner-results-page">
    <header class="ptscanner-header">
        <h1><?php esc_html_e( 'Results', 'prohibited-terms-scanner' ); ?></h1>
    </header>

    <ul class="subsubsub">
        <li>
            <a href="<?php echo esc_url( $flagged_url ); ?>" class="<?php echo 'flagged' === $status ? 'current' : ''; ?>">
                <?php esc_html_e( 'Flagged', 'prohibited-terms-scanner' ); ?>
            </a> |
        </li>
        <li>
            <a href="<?php echo esc_url( $ignored_url ); ?>" class="<?php echo 'ignored' === $status ? 'current' : ''; ?>">
                <?php esc_html_e( 'Marked as OK', 'prohibited-terms-scanner' ); ?>
            </a>
        </li>
    </ul>

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
                <?php foreach ( $data[ 'rows' ] as $row ) : ?>
                    <tr id="ptscanner-row-<?php echo absint( $row[ 'id' ] ); ?>" data-id="<?php echo absint( $row[ 'id' ] ); ?>">
                        <td><strong><?php echo esc_html( $row[ 'term' ] ); ?></strong></td>
                        <td><?php echo esc_html( $row[ 'context_snippet' ] ); ?><?php if ( ! empty( $row[ 'file_page' ] ) ) : ?>
                                <br><em><?php
                                    printf(
                                        /* translators: %d: file page number */
                                        esc_html__( 'Page %d', 'prohibited-terms-scanner' ),
                                        absint( $row[ 'file_page' ] )
                                    );
                                ?></em>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html( $row[ 'location_label' ] ); ?></td>
                        <td>
                            <?php if ( ! empty( $row[ 'highlight_link' ] ) ) : ?>
                                <a href="<?php echo esc_url( $row[ 'highlight_link' ] ); ?>" target="_blank">
                                    <?php esc_html_e( 'View', 'prohibited-terms-scanner' ); ?>
                                </a>
                            <?php else : ?>
                                &mdash;
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html( mysql2date( 'Y-m-d H:i', $row[ 'created_at' ] ) ); ?></td>
                        <td class="ptscanner-row-actions">
                            <?php if ( 'flagged' === $row[ 'status' ] ) : ?>
                                <button type="button" class="button-link ptscanner-mark-ok" data-id="<?php echo absint( $row[ 'id' ] ); ?>">
                                    <?php esc_html_e( 'Mark as OK', 'prohibited-terms-scanner' ); ?>
                                </button>
                            <?php else : ?>
                                <button type="button" class="button-link ptscanner-mark-flagged" data-id="<?php echo absint( $row[ 'id' ] ); ?>">
                                    <?php esc_html_e( 'Unignore', 'prohibited-terms-scanner' ); ?>
                                </button>
                            <?php endif; ?>
                            <button type="button" class="button-link ptscanner-clear-result" data-id="<?php echo absint( $row[ 'id' ] ); ?>">
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
                        'base'      => add_query_arg( 'paged', '%#%', add_query_arg( 'status', $status, $base_url ) ),
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