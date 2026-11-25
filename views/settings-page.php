<?php
/**
 * Settings tab view. Saved via AJAX by inc/js/settings.js.
 */

namespace PluginRx\ProhibitedTermsScanner;

if ( ! defined( 'ABSPATH' ) ) exit;

$ptscanner_settings      = Settings::instance();
$ptscanner_enabled_types = $ptscanner_settings->get_enabled_location_types();
$ptscanner_post_types    = $ptscanner_settings->get_enabled_post_types();
$ptscanner_taxonomies    = $ptscanner_settings->get_enabled_taxonomies();
$ptscanner_roles         = Access::get_assignable_roles();
$ptscanner_frequencies   = [
    'daily'   => __( 'Daily', 'prohibited-terms-scanner' ),
    'weekly'  => __( 'Weekly', 'prohibited-terms-scanner' ),
    'monthly' => __( 'Monthly', 'prohibited-terms-scanner' ),
];
$ptscanner_access_fields = [
    'view_roles' => [ __( 'Roles That Can View Results', 'prohibited-terms-scanner' ), __( 'Can open the Results and External Links tabs but cannot run scans or change anything. Administrators always have full access.', 'prohibited-terms-scanner' ), $ptscanner_settings->get_view_roles() ],
    'scan_roles' => [ __( 'Roles That Can Scan', 'prohibited-terms-scanner' ), __( 'Can run scans, manage omitted sources and view results, including the front-end shortcode. The Errors and Settings tabs are always administrators only.', 'prohibited-terms-scanner' ), $ptscanner_settings->get_scan_roles() ],
];
?>
<div class="wrap ptscanner-wrap ptscanner-content-wrap ptscanner-settings">
    <div class="ptscanner-settings-grid" id="ptscanner-settings-form">

        <div class="ptscanner-box">
            <div class="ptscanner-box-header"><h2><?php esc_html_e( 'Search Locations', 'prohibited-terms-scanner' ); ?></h2></div>
            <div class="ptscanner-box-body">
                <div class="ptscanner-field">
                    <label>
                        <?php esc_html_e( 'Locations Included by Default', 'prohibited-terms-scanner' ); ?>
                        <?php Helpers::tooltip( __( 'Locations checked here are preselected on the Scanner tab and used by scheduled scans.', 'prohibited-terms-scanner' ) ); ?>
                    </label>
                    <?php foreach ( TypeRegistry::instance()->get_grouped_types() as $ptscanner_group => $ptscanner_types ) : ?>
                        <div class="ptscanner-checkbox-group">
                            <span class="ptscanner-checkbox-group-title"><?php echo esc_html( ucwords( $ptscanner_group ) ); ?></span>
                            <div class="ptscanner-checkboxes">
                                <?php foreach ( $ptscanner_types as $ptscanner_slug => $ptscanner_type ) : ?>
                                    <label>
                                        <input type="checkbox" name="location_types[]" value="<?php echo esc_attr( $ptscanner_slug ); ?>" <?php checked( in_array( $ptscanner_slug, $ptscanner_enabled_types, true ) ); ?>>
                                        <?php echo esc_html( $ptscanner_type[ 'label' ] ); ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php if ( ! class_exists( '\ZipArchive' ) ) : ?>
                    <div class="ptscanner-note ptscanner-note-warning">
                        <p><?php esc_html_e( 'Your server does not have the PHP ZipArchive extension enabled, so .docx files cannot be scanned for content even if File Content is enabled. Contact your hosting provider to enable it.', 'prohibited-terms-scanner' ); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="ptscanner-box">
            <div class="ptscanner-box-header"><h2><?php esc_html_e( 'Post Types & Taxonomies', 'prohibited-terms-scanner' ); ?></h2></div>
            <div class="ptscanner-box-body">
                <div class="ptscanner-field">
                    <label>
                        <?php esc_html_e( 'Post Types', 'prohibited-terms-scanner' ); ?>
                        <?php Helpers::tooltip( __( 'Public post types included when scanning titles, content and excerpts.', 'prohibited-terms-scanner' ) ); ?>
                    </label>
                    <div class="ptscanner-checkboxes">
                        <?php foreach ( $ptscanner_settings->get_selectable_post_types() as $ptscanner_slug => $ptscanner_object ) : ?>
                            <label>
                                <input type="checkbox" name="post_types[]" value="<?php echo esc_attr( $ptscanner_slug ); ?>" <?php checked( in_array( $ptscanner_slug, $ptscanner_post_types, true ) ); ?>>
                                <?php echo esc_html( $ptscanner_object->label ); ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="ptscanner-field">
                    <label>
                        <?php esc_html_e( 'Taxonomies', 'prohibited-terms-scanner' ); ?>
                        <?php Helpers::tooltip( __( 'Public taxonomies whose term names and slugs are scanned.', 'prohibited-terms-scanner' ) ); ?>
                    </label>
                    <div class="ptscanner-checkboxes">
                        <?php foreach ( get_taxonomies( [ 'public' => true ], 'objects' ) as $ptscanner_taxonomy ) : ?>
                            <label>
                                <input type="checkbox" name="taxonomies[]" value="<?php echo esc_attr( $ptscanner_taxonomy->name ); ?>" <?php checked( in_array( $ptscanner_taxonomy->name, $ptscanner_taxonomies, true ) ); ?>>
                                <?php echo esc_html( $ptscanner_taxonomy->label ); ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="ptscanner-field">
                    <label class="ptscanner-inline-label">
                        <input type="checkbox" name="public_only" value="1" <?php checked( $ptscanner_settings->is_public_only() ); ?>>
                        <?php esc_html_e( 'Only Scan Public Pages', 'prohibited-terms-scanner' ); ?>
                        <?php Helpers::tooltip( __( 'Skips posts that logged-out visitors cannot open, such as pages restricted with Deadbolt. Developers can extend this with the ptscanner_is_public_post filter.', 'prohibited-terms-scanner' ) ); ?>
                    </label>
                </div>
            </div>
        </div>

        <div class="ptscanner-box">
            <div class="ptscanner-box-header"><h2><?php esc_html_e( 'Matching', 'prohibited-terms-scanner' ); ?></h2></div>
            <div class="ptscanner-box-body">
                <div class="ptscanner-field">
                    <label class="ptscanner-inline-label">
                        <input type="checkbox" name="default_case_sensitive" value="1" <?php checked( $ptscanner_settings->get_default_case_sensitive() ); ?>>
                        <?php esc_html_e( 'Case Sensitive by Default', 'prohibited-terms-scanner' ); ?>
                        <?php Helpers::tooltip( __( 'Applied to newly added terms. Each term can still be changed individually.', 'prohibited-terms-scanner' ) ); ?>
                    </label>
                </div>
                <div class="ptscanner-field">
                    <label class="ptscanner-inline-label">
                        <input type="checkbox" name="default_strict" value="1" <?php checked( $ptscanner_settings->get_default_strict() ); ?>>
                        <?php esc_html_e( 'Strict (Whole Word) Matching by Default', 'prohibited-terms-scanner' ); ?>
                        <?php Helpers::tooltip( __( 'Applied to newly added terms. Each term can still be changed individually.', 'prohibited-terms-scanner' ) ); ?>
                    </label>
                </div>
                <div class="ptscanner-field">
                    <label for="ptscanner_snippet_padding">
                        <?php esc_html_e( 'Snippet Padding', 'prohibited-terms-scanner' ); ?>
                        <?php Helpers::tooltip( __( 'Number of characters shown on each side of a match in the results context column. Default: 60.', 'prohibited-terms-scanner' ) ); ?>
                    </label>
                    <input type="number" id="ptscanner_snippet_padding" name="snippet_padding" min="0" max="500" value="<?php echo esc_attr( $ptscanner_settings->get_snippet_padding() ); ?>">
                </div>
            </div>
        </div>

        <div class="ptscanner-box">
            <div class="ptscanner-box-header"><h2><?php esc_html_e( 'Performance', 'prohibited-terms-scanner' ); ?></h2></div>
            <div class="ptscanner-box-body">
                <div class="ptscanner-field">
                    <label for="ptscanner_batch_size">
                        <?php esc_html_e( 'Batch Size', 'prohibited-terms-scanner' ); ?>
                        <?php Helpers::tooltip( __( 'Number of items processed per request. Lower this if scans are timing out. Default: 20.', 'prohibited-terms-scanner' ) ); ?>
                    </label>
                    <input type="number" id="ptscanner_batch_size" name="batch_size" min="1" max="500" value="<?php echo esc_attr( $ptscanner_settings->get_batch_size() ); ?>">
                </div>
                <div class="ptscanner-field">
                    <label class="ptscanner-inline-label">
                        <input type="checkbox" name="pdf_page_lookup" value="1" <?php checked( $ptscanner_settings->get_pdf_page_lookup() ); ?>>
                        <?php esc_html_e( 'Record PDF Page Numbers', 'prohibited-terms-scanner' ); ?>
                        <?php Helpers::tooltip( __( 'Scans PDFs page by page so results show the exact page. Slower, especially for large PDFs. When off, PDFs are still scanned, without page numbers.', 'prohibited-terms-scanner' ) ); ?>
                    </label>
                </div>
            </div>
        </div>

        <div class="ptscanner-box">
            <div class="ptscanner-box-header"><h2><?php esc_html_e( 'Save/Upload Warning', 'prohibited-terms-scanner' ); ?></h2></div>
            <div class="ptscanner-box-body">
                <div class="ptscanner-field">
                    <label class="ptscanner-inline-label">
                        <input type="checkbox" name="warning_enabled" value="1" <?php checked( $ptscanner_settings->is_warning_enabled() ); ?>>
                        <?php esc_html_e( 'Warn Before Saving or Uploading', 'prohibited-terms-scanner' ); ?>
                        <?php Helpers::tooltip( __( 'Warns editors before they save a post or upload a file that contains a monitored term.', 'prohibited-terms-scanner' ) ); ?>
                    </label>
                </div>
                <div class="ptscanner-field">
                    <label for="ptscanner-warning-terms-textarea"><?php esc_html_e( 'Monitored Terms (One Per Line)', 'prohibited-terms-scanner' ); ?></label>
                    <textarea id="ptscanner-warning-terms-textarea" rows="4"></textarea>
                    <div class="ptscanner-inline-actions">
                        <button type="button" class="ptscanner-button ptscanner-button-secondary" id="ptscanner-add-warning-terms"><?php esc_html_e( 'Add Terms', 'prohibited-terms-scanner' ); ?></button>
                        <button type="button" class="ptscanner-button ptscanner-button-danger" id="ptscanner-clear-all-warning-terms"><?php esc_html_e( 'Clear All Terms', 'prohibited-terms-scanner' ); ?></button>
                    </div>
                </div>
                <div class="ptscanner-terms-cards" id="ptscanner-warning-terms-cards"></div>
                <input type="hidden" name="warning_terms_json" id="ptscanner-warning-terms-json">
            </div>
        </div>

        <div class="ptscanner-box">
            <div class="ptscanner-box-header"><h2><?php esc_html_e( 'Scheduled Scanning', 'prohibited-terms-scanner' ); ?></h2></div>
            <div class="ptscanner-box-body">
                <div class="ptscanner-field">
                    <label class="ptscanner-inline-label">
                        <input type="checkbox" name="cron_enabled" value="1" <?php checked( $ptscanner_settings->is_cron_enabled() ); ?>>
                        <?php esc_html_e( 'Enable Scheduled Scanning', 'prohibited-terms-scanner' ); ?>
                        <?php Helpers::tooltip( __( 'Runs a full scan in the background using your saved terms and the locations above. Very large sites may need a longer PHP execution time for cron requests.', 'prohibited-terms-scanner' ) ); ?>
                    </label>
                </div>
                <div class="ptscanner-field">
                    <label for="ptscanner_cron_frequency"><?php esc_html_e( 'Frequency', 'prohibited-terms-scanner' ); ?></label>
                    <select id="ptscanner_cron_frequency" name="cron_frequency">
                        <?php foreach ( $ptscanner_frequencies as $ptscanner_value => $ptscanner_label ) : ?>
                            <option value="<?php echo esc_attr( $ptscanner_value ); ?>" <?php selected( $ptscanner_settings->get_cron_frequency(), $ptscanner_value ); ?>><?php echo esc_html( $ptscanner_label ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="ptscanner-field">
                    <p class="ptscanner-cron-status" id="ptscanner-cron-status"><?php $ptscanner_settings->render_cron_status(); ?></p>
                </div>
            </div>
        </div>

        <div class="ptscanner-box">
            <div class="ptscanner-box-header"><h2><?php esc_html_e( 'Access', 'prohibited-terms-scanner' ); ?></h2></div>
            <div class="ptscanner-box-body">
                <?php foreach ( $ptscanner_access_fields as $ptscanner_name => [ $ptscanner_label, $ptscanner_tip, $ptscanner_selected ] ) : ?>
                    <div class="ptscanner-field">
                        <label>
                            <?php echo esc_html( $ptscanner_label ); ?>
                            <?php Helpers::tooltip( $ptscanner_tip ); ?>
                        </label>
                        <div class="ptscanner-checkboxes">
                            <?php foreach ( $ptscanner_roles as $ptscanner_role => $ptscanner_role_name ) : ?>
                                <label>
                                    <input type="checkbox" name="<?php echo esc_attr( $ptscanner_name ); ?>[]" value="<?php echo esc_attr( $ptscanner_role ); ?>" <?php checked( in_array( $ptscanner_role, $ptscanner_selected, true ) ); ?>>
                                    <?php echo esc_html( $ptscanner_role_name ); ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                <div class="ptscanner-note">
                    <p>
                        <?php
                        printf(
                            /* translators: %s: shortcode tag */
                            esc_html__( 'Add %s to any page to give scan roles a front-end scanner.', 'prohibited-terms-scanner' ),
                            '<code>[prohibited_terms_scanner]</code>'
                        );
                        ?>
                    </p>
                </div>
            </div>
        </div>

        <div class="ptscanner-box">
            <div class="ptscanner-box-header"><h2><?php esc_html_e( 'Advanced', 'prohibited-terms-scanner' ); ?></h2></div>
            <div class="ptscanner-box-body">
                <div class="ptscanner-field">
                    <label class="ptscanner-inline-label">
                        <input type="checkbox" name="clear_data_on_uninstall" value="1" <?php checked( $ptscanner_settings->clear_data_on_uninstall() ); ?>>
                        <?php esc_html_e( 'Remove All Plugin Data on Uninstall', 'prohibited-terms-scanner' ); ?>
                        <?php Helpers::tooltip( __( 'Permanently deletes all settings, terms, scan results, notes, omitted sources and the error log when the plugin is deleted.', 'prohibited-terms-scanner' ) ); ?>
                    </label>
                </div>
                <div class="ptscanner-field" id="ptscanner_field_upload_download_settings">
                    <label>
                        <?php esc_html_e( 'Upload/Download Settings', 'prohibited-terms-scanner' ); ?>
                        <?php Helpers::tooltip( __( 'Export your terms and settings as a JSON file for backup or to copy to another site. Scan results are not included. Importing overwrites your current terms and settings.', 'prohibited-terms-scanner' ) ); ?>
                    </label>
                    <div class="ptscanner-inline-actions">
                        <button type="button" class="ptscanner-button" id="ptscanner-download-settings"><?php esc_html_e( 'Download Settings', 'prohibited-terms-scanner' ); ?></button>
                        <label for="ptscanner-upload-settings" class="ptscanner-button" tabindex="0"><?php esc_html_e( 'Upload Settings', 'prohibited-terms-scanner' ); ?></label>
                        <input type="file" id="ptscanner-upload-settings" accept="application/json,.json" hidden>
                        <span id="ptscanner-upload-filename"></span>
                    </div>
                </div>
                <div class="ptscanner-field" id="ptscanner_field_reset_settings">
                    <label>
                        <?php esc_html_e( 'Reset Settings', 'prohibited-terms-scanner' ); ?>
                        <?php Helpers::tooltip( __( 'Resets every setting to its default. Your terms, scan results, notes and omitted sources are kept.', 'prohibited-terms-scanner' ) ); ?>
                    </label>
                    <div><button type="button" class="ptscanner-button ptscanner-button-danger" id="ptscanner-reset-settings"><?php esc_html_e( 'Reset Settings', 'prohibited-terms-scanner' ); ?></button></div>
                </div>
            </div>
        </div>

    </div>
</div>
