<?php
/**
 * Results tab view. Row actions are handled via AJAX by results.js.
 *
 * @var string $status          'flagged' or 'ignored'
 * @var int    $page            Current page number
 * @var string $term_filter     Selected term filter
 * @var string $location_filter Selected location filter
 * @var array  $data            Rows, totals and filter options
 */

namespace PluginRx\ProhibitedTermsScanner;

if ( ! defined( 'ABSPATH' ) ) exit;

$ptscanner_can_scan    = Access::can_scan();
$ptscanner_base_url    = admin_url( 'admin.php?page=' . Menu::MENU_SLUG . '_results' );
$ptscanner_statuses    = [
    /* translators: %d: number of flagged results */
    'flagged' => sprintf( __( 'Flagged (%d)', 'prohibited-terms-scanner' ), absint( $data[ 'total_flagged' ] ) ),
    /* translators: %d: number of results marked as OK */
    'ignored' => sprintf( __( 'Marked as OK (%d)', 'prohibited-terms-scanner' ), absint( $data[ 'total_ignored' ] ) ),
];
$ptscanner_filter_args = array_filter( [
    'status'          => $status,
    'term_filter'     => $term_filter,
    'location_filter' => $location_filter,
] );
?>
<div class="wrap ptscanner-wrap ptscanner-content-wrap ptscanner-results-page" data-status="<?php echo esc_attr( $status ); ?>">

    <div class="ptscanner-box ptscanner-summary-box">
        <details class="ptscanner-box-details" id="ptscanner-summary-accordion">
            <summary class="ptscanner-box-header"><h2><?php esc_html_e( 'Summary by Term', 'prohibited-terms-scanner' ); ?></h2></summary>
            <div class="ptscanner-box-body">
                <table class="ptscanner-table">
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
            </div>
        </details>
    </div>

    <div class="ptscanner-box ptscanner-results-box">
        <div class="ptscanner-box-header ptscanner-toolbar">
            <nav class="ptscanner-segmented" aria-label="<?php esc_attr_e( 'Result status', 'prohibited-terms-scanner' ); ?>">
                <?php foreach ( $ptscanner_statuses as $ptscanner_key => $ptscanner_label ) : ?>
                    <a href="<?php echo esc_url( add_query_arg( 'status', $ptscanner_key, $ptscanner_base_url ) ); ?>" class="<?php echo esc_attr( $ptscanner_key === $status ? 'current' : '' ); ?>" data-count-status="<?php echo esc_attr( $ptscanner_key ); ?>" data-count="<?php echo absint( $data[ 'total_' . $ptscanner_key ] ); ?>" <?php echo $ptscanner_key === $status ? 'aria-current="page"' : ''; ?>><?php echo esc_html( $ptscanner_label ); ?></a>
                <?php endforeach; ?>
            </nav>

            <form method="get" class="ptscanner-results-filters">
                <input type="hidden" name="page" value="<?php echo esc_attr( Menu::MENU_SLUG . '_results' ); ?>">
                <input type="hidden" name="status" value="<?php echo esc_attr( $status ); ?>">
                <label class="screen-reader-text" for="ptscanner-term-filter"><?php esc_html_e( 'Filter by term', 'prohibited-terms-scanner' ); ?></label>
                <select name="term_filter" id="ptscanner-term-filter">
                    <option value=""><?php esc_html_e( 'All Terms', 'prohibited-terms-scanner' ); ?></option>
                    <?php foreach ( $data[ 'distinct_terms' ] as $ptscanner_term_option ) : ?>
                        <option value="<?php echo esc_attr( $ptscanner_term_option ); ?>" <?php selected( $term_filter, $ptscanner_term_option ); ?>><?php echo esc_html( $ptscanner_term_option ); ?></option>
                    <?php endforeach; ?>
                </select>
                <label class="screen-reader-text" for="ptscanner-location-filter"><?php esc_html_e( 'Filter by location', 'prohibited-terms-scanner' ); ?></label>
                <select name="location_filter" id="ptscanner-location-filter">
                    <option value=""><?php esc_html_e( 'All Locations', 'prohibited-terms-scanner' ); ?></option>
                    <?php foreach ( $data[ 'distinct_locations' ] as $ptscanner_location_key => $ptscanner_location_label ) : ?>
                        <option value="<?php echo esc_attr( $ptscanner_location_key ); ?>" <?php selected( $location_filter, $ptscanner_location_key ); ?>><?php echo esc_html( $ptscanner_location_label ); ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="ptscanner-button ptscanner-button-secondary ptscanner-filter-submit"><?php esc_html_e( 'Filter', 'prohibited-terms-scanner' ); ?></button>
            </form>

            <?php if ( $ptscanner_can_scan && ! empty( $data[ 'rows' ] ) ) : ?>
                <button type="button" class="ptscanner-button ptscanner-button-danger" id="ptscanner-clear-all" data-status="<?php echo esc_attr( $status ); ?>"><?php esc_html_e( 'Clear All', 'prohibited-terms-scanner' ); ?></button>
            <?php endif; ?>
        </div>

        <div class="ptscanner-table-wrap">
            <table class="ptscanner-table ptscanner-results-table">
                <thead>
                    <tr>
                        <th class="column-term"><?php esc_html_e( 'Term/Phrase', 'prohibited-terms-scanner' ); ?></th>
                        <th class="column-context"><?php esc_html_e( 'Context', 'prohibited-terms-scanner' ); ?></th>
                        <th class="column-location"><?php esc_html_e( 'Location', 'prohibited-terms-scanner' ); ?></th>
                        <th class="column-source"><?php esc_html_e( 'Source', 'prohibited-terms-scanner' ); ?></th>
                        <th class="column-note"><?php esc_html_e( 'Note', 'prohibited-terms-scanner' ); ?></th>
                        <th class="column-date"><?php esc_html_e( 'Date', 'prohibited-terms-scanner' ); ?></th>
                        <th class="column-actions"><?php esc_html_e( 'Actions', 'prohibited-terms-scanner' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $data[ 'rows' ] ) ) : ?>
                        <tr><td colspan="7"><?php esc_html_e( 'No results found.', 'prohibited-terms-scanner' ); ?></td></tr>
                    <?php else : ?>
                        <?php foreach ( $data[ 'rows' ] as $ptscanner_row ) : ?>
                            <?php $ptscanner_id = absint( $ptscanner_row[ 'id' ] ); ?>
                            <tr id="ptscanner-row-<?php echo esc_attr( $ptscanner_id ); ?>" data-id="<?php echo esc_attr( $ptscanner_id ); ?>">
                                <td class="column-term"><strong><?php echo esc_html( $ptscanner_row[ 'term' ] ); ?></strong></td>
                                <td class="column-context">
                                    <?php echo wp_kses( $ptscanner_row[ 'context_highlighted' ], [ 'strong' => [ 'class' => [] ] ] ); ?>
                                    <?php if ( ! empty( $ptscanner_row[ 'file_page' ] ) ) : ?>
                                        <br><em>
                                            <?php
                                            /* translators: %d: page number within a file */
                                            echo esc_html( sprintf( __( 'Page %d', 'prohibited-terms-scanner' ), absint( $ptscanner_row[ 'file_page' ] ) ) );
                                            ?>
                                        </em>
                                    <?php endif; ?>
                                </td>
                                <td class="column-location"><?php echo esc_html( $ptscanner_row[ 'location_label' ] ); ?></td>
                                <td class="column-source">
                                    <?php if ( ! empty( $ptscanner_row[ 'source_title' ] ) ) : ?>
                                        <strong><?php echo esc_html( $ptscanner_row[ 'source_title' ] ); ?></strong><br>
                                    <?php endif; ?>
                                    <span class="ptscanner-source-links">
                                        <?php if ( ! empty( $ptscanner_row[ 'highlight_link' ] ) ) : ?>
                                            <a href="<?php echo esc_url( $ptscanner_row[ 'highlight_link' ] ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View Source', 'prohibited-terms-scanner' ); ?></a><?php echo $ptscanner_can_scan ? ' |' : ''; ?>
                                        <?php endif; ?>
                                        <?php if ( $ptscanner_can_scan ) : ?>
                                        <a href="#" class="ptscanner-toggle-omit" data-id="<?php echo esc_attr( $ptscanner_row[ 'source_id' ] ); ?>" data-type="<?php echo esc_attr( $ptscanner_row[ 'source_type' ] ); ?>" data-omitted="0"><?php esc_html_e( 'Omit Source', 'prohibited-terms-scanner' ); ?></a>
                                        <?php endif; ?>
                                    </span>
                                    <div class="ptscanner-media-usage-result" id="ptscanner-media-usage-<?php echo esc_attr( $ptscanner_id ); ?>"></div>
                                </td>
                                <td class="column-note">
                                    <?php if ( $ptscanner_can_scan ) : ?>
                                        <textarea class="ptscanner-note-field" data-id="<?php echo esc_attr( $ptscanner_id ); ?>" rows="2" placeholder="<?php esc_attr_e( 'Add a note…', 'prohibited-terms-scanner' ); ?>" aria-label="<?php esc_attr_e( 'Note', 'prohibited-terms-scanner' ); ?>"><?php echo esc_textarea( $ptscanner_row[ 'note' ] ?? '' ); ?></textarea>
                                        <button type="button" class="ptscanner-link-button ptscanner-save-note" data-id="<?php echo esc_attr( $ptscanner_id ); ?>"><?php esc_html_e( 'Save Note', 'prohibited-terms-scanner' ); ?></button>
                                    <?php else : ?>
                                        <span class="ptscanner-note-display"><?php echo esc_html( $ptscanner_row[ 'note' ] ?? '' ); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="column-date"><?php echo esc_html( $ptscanner_row[ 'date_display' ] ); ?></td>
                                <td class="column-actions">
                                    <div class="ptscanner-row-actions">
                                        <?php if ( $ptscanner_can_scan ) : ?>
                                            <?php if ( 'flagged' === $ptscanner_row[ 'status' ] ) : ?>
                                                <button type="button" class="ptscanner-button ptscanner-button-small ptscanner-mark-ok" data-id="<?php echo esc_attr( $ptscanner_id ); ?>"><?php esc_html_e( 'Mark as OK', 'prohibited-terms-scanner' ); ?></button>
                                            <?php else : ?>
                                                <button type="button" class="ptscanner-button ptscanner-button-small ptscanner-mark-flagged" data-id="<?php echo esc_attr( $ptscanner_id ); ?>"><?php esc_html_e( 'Unignore', 'prohibited-terms-scanner' ); ?></button>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                        <?php if ( in_array( $ptscanner_row[ 'location_type' ], [ 'filename', 'alt_text' ], true ) ) : ?>
                                            <button type="button" class="ptscanner-button ptscanner-button-small ptscanner-button-secondary ptscanner-check-media-usage" data-id="<?php echo esc_attr( $ptscanner_row[ 'source_id' ] ); ?>"><?php esc_html_e( 'Check Usage', 'prohibited-terms-scanner' ); ?></button>
                                        <?php endif; ?>
                                        <?php if ( $ptscanner_can_scan ) : ?>
                                            <button type="button" class="ptscanner-button ptscanner-button-small ptscanner-button-danger ptscanner-clear-result" data-id="<?php echo esc_attr( $ptscanner_id ); ?>"><?php esc_html_e( 'Clear', 'prohibited-terms-scanner' ); ?></button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ( $data[ 'total_pages' ] > 1 ) : ?>
            <div class="ptscanner-pagination">
                <?php
                echo wp_kses_post( paginate_links( [
                    'base'    => add_query_arg( array_merge( $ptscanner_filter_args, [ 'paged' => '%#%' ] ), $ptscanner_base_url ),
                    'format'  => '',
                    'current' => $page,
                    'total'   => $data[ 'total_pages' ],
                ] ) );
                ?>
            </div>
        <?php endif; ?>
    </div>
</div>
