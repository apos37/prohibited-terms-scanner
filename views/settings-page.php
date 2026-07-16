<?php
/**
 * Settings page view
 */

namespace PluginRx\ProhibitedTermsScanner;

if ( ! defined( 'ABSPATH' ) ) exit;

$ptscanner_settings          = Settings::instance();
$ptscanner_grouped_types     = TypeRegistry::instance()->get_grouped_types();
$ptscanner_enabled_types     = $ptscanner_settings->get_enabled_location_types();
$ptscanner_post_types        = get_post_types( [ 'public' => true ], 'objects' );
$ptscanner_enabled_post_types = $ptscanner_settings->get_enabled_post_types();
$ptscanner_all_roles         = wp_roles()->roles;
$ptscanner_shortcode_roles   = $ptscanner_settings->get_shortcode_roles();
$ptscanner_warning_terms     = $ptscanner_settings->get_warning_terms();
?>
<div class="wrap ptscanner-wrap ptscanner-settings-page">
    <header class="ptscanner-header">
        <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
    </header>

    <?php settings_errors( 'ptscanner-notices' ); ?>

    <form method="post" id="ptscanner-settings-form">
        <?php wp_nonce_field( Settings::instance()->nonce_action(), Settings::instance()->nonce_action() . '_field' ); ?>
        <input type="hidden" name="action" value="ptscanner_save_settings">

        <div class="ptscanner-card">
            <h2><?php esc_html_e( 'Search Locations', 'prohibited-terms-scanner' ); ?></h2>
            <p class="description"><?php esc_html_e( 'Choose which locations are included by default when scanning.', 'prohibited-terms-scanner' ); ?></p>

            <?php foreach ( $ptscanner_grouped_types as $ptscanner_group_name => $ptscanner_types ) : ?>
                <fieldset class="ptscanner-type-group">
                    <legend><?php echo esc_html( ucwords( $ptscanner_group_name ) ); ?></legend>
                    <?php foreach ( $ptscanner_types as $ptscanner_slug => $ptscanner_type ) : ?>
                        <label class="ptscanner-type-checkbox">
                            <input type="checkbox" name="location_types[]" value="<?php echo esc_attr( $ptscanner_slug ); ?>"
                                <?php checked( in_array( $ptscanner_slug, $ptscanner_enabled_types, true ) ); ?>>
                            <?php echo esc_html( $ptscanner_type[ 'label' ] ); ?>
                        </label>
                    <?php endforeach; ?>
                </fieldset>
            <?php endforeach; ?>

            <?php if ( ! class_exists( '\ZipArchive' ) ) : ?>
                <div class="notice notice-warning inline ptscanner-ziparchive-notice">
                    <p>
                        <?php esc_html_e( 'Your server does not have the PHP ZipArchive extension enabled, so .docx files cannot be scanned for content even if File Content is enabled below. Contact your hosting provider to enable this extension.', 'prohibited-terms-scanner' ); ?>
                    </p>
                </div>
            <?php endif; ?>
        </div>

        <div class="ptscanner-card">
            <h2><?php esc_html_e( 'Post Types', 'prohibited-terms-scanner' ); ?></h2>
            <p class="description"><?php esc_html_e( 'Choose which public post types are included when scanning content.', 'prohibited-terms-scanner' ); ?></p>

            <?php foreach ( $ptscanner_post_types as $ptscanner_post_type ) : ?>
                <?php if ( in_array( $ptscanner_post_type->name, [ 'erifl-files', 'eri-files' ], true ) ) : continue; endif; ?>
                <label class="ptscanner-type-checkbox">
                    <input type="checkbox" name="post_types[]" value="<?php echo esc_attr( $ptscanner_post_type->name ); ?>"
                        <?php checked( in_array( $ptscanner_post_type->name, $ptscanner_enabled_post_types, true ) ); ?>>
                    <?php echo esc_html( $ptscanner_post_type->label ); ?>
                </label>
            <?php endforeach; ?>
        </div>

        <div class="ptscanner-card">
            <h2><?php esc_html_e( 'Performance', 'prohibited-terms-scanner' ); ?></h2>

            <table class="form-table">
                <tr>
                    <th><label for="ptscanner-batch-size"><?php esc_html_e( 'Batch Size', 'prohibited-terms-scanner' ); ?></label></th>
                    <td>
                        <input type="number" id="ptscanner-batch-size" name="batch_size" min="1" max="500"
                            value="<?php echo esc_attr( $ptscanner_settings->get_batch_size() ); ?>" class="small-text">
                        <p class="description"><?php esc_html_e( 'Number of items processed per AJAX request. Lower this if scans are timing out.', 'prohibited-terms-scanner' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'PDF Page Lookup', 'prohibited-terms-scanner' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="pdf_page_lookup" <?php checked( $ptscanner_settings->get_pdf_page_lookup() ); ?>>
                            <?php esc_html_e( 'Record which page of a PDF each match is found on', 'prohibited-terms-scanner' ); ?>
                        </label>
                        <p class="description"><?php esc_html_e( 'When enabled, PDFs are scanned page-by-page so results show the exact page number. This is slower, especially for large PDFs. When disabled, PDF content is still scanned, but without page numbers.', 'prohibited-terms-scanner' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="ptscanner-snippet-padding"><?php esc_html_e( 'Snippet Padding', 'prohibited-terms-scanner' ); ?></label></th>
                    <td>
                        <input type="number" id="ptscanner-snippet-padding" name="snippet_padding" min="0" max="500"
                            value="<?php echo esc_attr( $ptscanner_settings->get_snippet_padding() ); ?>" class="small-text">
                        <p class="description"><?php esc_html_e( 'Number of characters shown on each side of a matched term in the results context column. Default: 60.', 'prohibited-terms-scanner' ); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <div class="ptscanner-card">
            <h2><?php esc_html_e( 'Default Matching Behavior', 'prohibited-terms-scanner' ); ?></h2>
            <p class="description"><?php esc_html_e( 'Applied to newly added terms unless overridden per term.', 'prohibited-terms-scanner' ); ?></p>

            <label>
                <input type="checkbox" name="default_case_sensitive" <?php checked( $ptscanner_settings->get_default_case_sensitive() ); ?>>
                <?php esc_html_e( 'Case Sensitive by default', 'prohibited-terms-scanner' ); ?>
            </label>
            <br>
            <label>
                <input type="checkbox" name="default_strict" <?php checked( $ptscanner_settings->get_default_strict() ); ?>>
                <?php esc_html_e( 'Strict (whole word) matching by default', 'prohibited-terms-scanner' ); ?>
            </label>
        </div>

        <div class="ptscanner-card">
            <h2><?php esc_html_e( 'Front-End Shortcode Access', 'prohibited-terms-scanner' ); ?></h2>
            <p class="description">
                <?php
                printf(
                    /* translators: %s: shortcode tag */
                    esc_html__( 'Use %s on any page. Only roles selected below (in addition to Administrators) can use it.', 'prohibited-terms-scanner' ),
                    '<code>[prohibited_terms_scanner]</code>'
                );
                ?>
            </p>

            <?php foreach ( $ptscanner_all_roles as $ptscanner_role_slug => $ptscanner_role_data ) : ?>
                <?php if ( 'administrator' === $ptscanner_role_slug ) : continue; endif; ?>
                <label class="ptscanner-type-checkbox">
                    <input type="checkbox" name="shortcode_roles[]" value="<?php echo esc_attr( $ptscanner_role_slug ); ?>"
                        <?php checked( in_array( $ptscanner_role_slug, $ptscanner_shortcode_roles, true ) ); ?>>
                    <?php echo esc_html( translate_user_role( $ptscanner_role_data[ 'name' ] ) ); ?>
                </label>
            <?php endforeach; ?>
        </div>

        <div class="ptscanner-card">
            <h2><?php esc_html_e( 'Save/Upload Warning', 'prohibited-terms-scanner' ); ?></h2>
            <label>
                <input type="checkbox" name="warning_enabled" id="ptscanner-warning-enabled" <?php checked( $ptscanner_settings->is_warning_enabled() ); ?>>
                <?php esc_html_e( 'Warn before saving a post/page or uploading a file that contains a monitored term', 'prohibited-terms-scanner' ); ?>
            </label>

            <details class="ptscanner-terms-accordion" <?php echo empty( $ptscanner_settings->get_warning_terms() ) ? 'open' : ''; ?>>
                <summary><?php esc_html_e( 'Monitored Terms', 'prohibited-terms-scanner' ); ?></summary>

                <div class="ptscanner-warning-terms-input">
                    <label for="ptscanner-warning-terms-textarea"><?php esc_html_e( 'Monitored terms, one per line', 'prohibited-terms-scanner' ); ?></label>
                    <textarea id="ptscanner-warning-terms-textarea" rows="4" class="large-text"></textarea>
                    <button type="button" class="button" id="ptscanner-add-warning-terms">
                        <?php esc_html_e( 'Add Terms', 'prohibited-terms-scanner' ); ?>
                    </button>
                    <button type="button" class="button" id="ptscanner-clear-all-warning-terms">
                        <?php esc_html_e( 'Clear All Terms', 'prohibited-terms-scanner' ); ?>
                    </button>
                </div>

                <div class="ptscanner-terms-cards" id="ptscanner-warning-terms-cards"></div>
            </details>

            <input type="hidden" name="warning_terms_json" id="ptscanner-warning-terms-json">
        </div>

        <div class="ptscanner-card">
            <h2><?php esc_html_e( 'Scheduled Scanning', 'prohibited-terms-scanner' ); ?></h2>
            <p class="description"><?php esc_html_e( 'Automatically run a full scan on a schedule using the term list and search locations configured above, without needing to visit the Scanner page. This runs as a single background process — very large sites may need a longer PHP execution time limit for cron requests.', 'prohibited-terms-scanner' ); ?></p>

            <label>
                <input type="checkbox" name="cron_enabled" <?php checked( $ptscanner_settings->is_cron_enabled() ); ?>>
                <?php esc_html_e( 'Enable scheduled scanning', 'prohibited-terms-scanner' ); ?>
            </label>

            <p>
                <label for="ptscanner-cron-frequency"><?php esc_html_e( 'Frequency', 'prohibited-terms-scanner' ); ?></label>
                <select name="cron_frequency" id="ptscanner-cron-frequency">
                    <option value="daily" <?php selected( $ptscanner_settings->get_cron_frequency(), 'daily' ); ?>><?php esc_html_e( 'Daily', 'prohibited-terms-scanner' ); ?></option>
                    <option value="weekly" <?php selected( $ptscanner_settings->get_cron_frequency(), 'weekly' ); ?>><?php esc_html_e( 'Weekly', 'prohibited-terms-scanner' ); ?></option>
                    <option value="monthly" <?php selected( $ptscanner_settings->get_cron_frequency(), 'monthly' ); ?>><?php esc_html_e( 'Monthly', 'prohibited-terms-scanner' ); ?></option>
                </select>
            </p>

            <?php $ptscanner_cron_status = \PluginRx\ProhibitedTermsScanner\Cron::instance()->get_status(); ?>
            <p class="ptscanner-cron-status">
                <?php if ( $ptscanner_cron_status[ 'enabled' ] ) : ?>
                    <strong><?php esc_html_e( 'Status:', 'prohibited-terms-scanner' ); ?></strong>
                    <?php if ( $ptscanner_cron_status[ 'next_run' ] ) : ?>
                        <?php
                        printf(
                            /* translators: %s: date/time of next scheduled scan */
                            esc_html__( 'Next scan: %s', 'prohibited-terms-scanner' ),
                            esc_html( wp_date( 'F j, Y g:i a', $ptscanner_cron_status[ 'next_run' ] ) )
                        );
                        ?>
                    <?php else : ?>
                        <?php esc_html_e( 'Not currently scheduled — save this page to schedule it.', 'prohibited-terms-scanner' ); ?>
                    <?php endif; ?>
                    <br>
                    <?php if ( '' !== $ptscanner_cron_status[ 'last_run' ] ) : ?>
                        <?php
                        printf(
                            /* translators: 1: date/time of last scan, 2: number of results found */
                            esc_html__( 'Last scan: %1$s (%2$d result(s) found)', 'prohibited-terms-scanner' ),
                            esc_html( mysql2date( 'F j, Y g:i a', $ptscanner_cron_status[ 'last_run' ] ) ),
                            (int) $ptscanner_cron_status[ 'last_result' ]
                        );
                        ?>
                    <?php else : ?>
                        <?php esc_html_e( 'No scheduled scan has run yet.', 'prohibited-terms-scanner' ); ?>
                    <?php endif; ?>
                <?php else : ?>
                    <em><?php esc_html_e( 'Scheduled scanning is currently disabled.', 'prohibited-terms-scanner' ); ?></em>
                <?php endif; ?>
            </p>
        </div>

        <?php submit_button( __( 'Save Settings', 'prohibited-terms-scanner' ) ); ?>
    </form>
</div>