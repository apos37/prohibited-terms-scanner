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

$ptscanner_textdomain      = Bootstrap::textdomain();
$ptscanner_base_url        = admin_url( 'admin.php?page=' . $ptscanner_textdomain . '_results' );
$ptscanner_flagged_url     = add_query_arg( 'status', 'flagged', $ptscanner_base_url );
$ptscanner_ignored_url     = add_query_arg( 'status', 'ignored', $ptscanner_base_url );
$ptscanner_term_filter     = isset( $_GET[ 'term_filter' ] ) ? sanitize_text_field( wp_unslash( $_GET[ 'term_filter' ] ) ) : '';
$ptscanner_location_filter = isset( $_GET[ 'location_filter' ] ) ? sanitize_key( wp_unslash( $_GET[ 'location_filter' ] ) ) : '';
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

    <details class="ptscanner-terms-accordion" id="ptscanner-summary-accordion">
        <summary><?php esc_html_e( 'Summary by Term', 'prohibited-terms-scanner' ); ?></summary>
        <table class="ptscanner-front-table">
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
    </details>

    <ul class="subsubsub">
        <li>
            <a href="<?php echo esc_url( $ptscanner_flagged_url ); ?>" class="<?php echo 'flagged' === $status ? 'current' : ''; ?>">
                <?php
                printf(
                    /* translators: %d: number of flagged results */
                    esc_html__( 'Flagged (%d)', 'prohibited-terms-scanner' ),
                    absint( $data[ 'total_flagged' ] )
                );
                ?>
            </a> |
        </li>
        <li>
            <a href="<?php echo esc_url( $ptscanner_ignored_url ); ?>" class="<?php echo 'ignored' === $status ? 'current' : ''; ?>">
                <?php
                printf(
                    /* translators: %d: number of ignored results */
                    esc_html__( 'Marked as OK (%d)', 'prohibited-terms-scanner' ),
                    absint( $data[ 'total_ignored' ] )
                );
                ?>
            </a>
        </li>
    </ul>

    <div class="ptscanner-clear-all-wrap ptscanner-front-filters-row">
        <form method="get" class="ptscanner-front-filters">
            <input type="hidden" name="page" value="<?php echo esc_attr( $ptscanner_textdomain . '_results' ); ?>">
            <input type="hidden" name="status" value="<?php echo esc_attr( $status ); ?>">

            <select name="term_filter" onchange="this.form.submit()">
                <option value=""><?php esc_html_e( 'All Terms', 'prohibited-terms-scanner' ); ?></option>
                <?php foreach ( $data[ 'distinct_terms' ] as $ptscanner_term_option ) : ?>
                    <option value="<?php echo esc_attr( $ptscanner_term_option ); ?>" <?php selected( $ptscanner_term_filter, $ptscanner_term_option ); ?>>
                        <?php echo esc_html( $ptscanner_term_option ); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select name="location_filter" onchange="this.form.submit()">
                <option value=""><?php esc_html_e( 'All Locations', 'prohibited-terms-scanner' ); ?></option>
                <?php foreach ( $data[ 'distinct_locations' ] as $ptscanner_location_slug => $ptscanner_location_label ) : ?>
                    <option value="<?php echo esc_attr( $ptscanner_location_slug ); ?>" <?php selected( $ptscanner_location_filter, $ptscanner_location_slug ); ?>>
                        <?php echo esc_html( $ptscanner_location_label ); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>

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
                <th><?php esc_html_e( 'Note', 'prohibited-terms-scanner' ); ?></th>
                <th><?php esc_html_e( 'Date', 'prohibited-terms-scanner' ); ?></th>
                <th><?php esc_html_e( 'Actions', 'prohibited-terms-scanner' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if ( empty( $data[ 'rows' ] ) ) : ?>
                <tr>
                    <td colspan="7"><?php esc_html_e( 'No results found.', 'prohibited-terms-scanner' ); ?></td>
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
                                    <?php esc_html_e( 'View Source', 'prohibited-terms-scanner' ); ?>
                                </a> |
                            <?php else : ?>
                                &mdash;
                            <?php endif; ?>
                            <a href="#" class="ptscanner-toggle-omit" data-id="<?php echo esc_attr( $ptscanner_row[ 'source_id' ] ); ?>" data-type="<?php echo esc_attr( $ptscanner_row[ 'source_type' ] ); ?>" data-omitted="0">
                                <?php esc_html_e( 'Omit Source', 'prohibited-terms-scanner' ); ?>
                            </a>
                            <div class="ptscanner-media-usage-result" id="ptscanner-media-usage-<?php echo absint( $ptscanner_row[ 'id' ] ); ?>" style="margin-top:8px;"></div>
                        </td>
                        <td>
                            <textarea class="ptscanner-note-field" data-id="<?php echo absint( $ptscanner_row[ 'id' ] ); ?>" rows="2" placeholder="<?php esc_attr_e( 'Add a note…', 'prohibited-terms-scanner' ); ?>"><?php echo esc_textarea( $ptscanner_row[ 'note' ] ?? '' ); ?></textarea>
                            <br>
                            <button type="button" class="button-link ptscanner-save-note" data-id="<?php echo absint( $ptscanner_row[ 'id' ] ); ?>">
                                <?php esc_html_e( 'Save Note', 'prohibited-terms-scanner' ); ?>
                            </button>
                        </td>
                        <td><?php echo esc_html( mysql2date( 'Y-m-d g:i A', $ptscanner_row[ 'created_at' ] ) ); ?></td>
                        <td class="ptscanner-row-actions">
                            <?php if ( in_array( $ptscanner_row[ 'location_type' ], [ 'filename', 'alt_text' ], true ) ) : ?>
                                <button type="button" class="button-link ptscanner-check-media-usage" data-id="<?php echo absint( $ptscanner_row[ 'source_id' ] ); ?>">
                                    <?php esc_html_e( 'Check Usage', 'prohibited-terms-scanner' ); ?>
                                </button>
                                <br>
                            <?php endif; ?>
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
        <?php
        $ptscanner_pagination_base = add_query_arg( 'status', $status, $ptscanner_base_url );

        if ( '' !== $ptscanner_term_filter ) {
            $ptscanner_pagination_base = add_query_arg( 'term_filter', $ptscanner_term_filter, $ptscanner_pagination_base );
        }

        if ( '' !== $ptscanner_location_filter ) {
            $ptscanner_pagination_base = add_query_arg( 'location_filter', $ptscanner_location_filter, $ptscanner_pagination_base );
        }
        ?>
        <div class="tablenav">
            <div class="tablenav-pages">
                <?php
                echo wp_kses_post(
                    paginate_links( [
                        'base'      => add_query_arg( 'paged', '%#%', $ptscanner_pagination_base ),
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