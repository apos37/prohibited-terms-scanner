<?php
/**
 * Settings page view
 */

namespace PluginRx\ProhibitedTermsScanner;

if ( ! defined( 'ABSPATH' ) ) exit;

$settings          = Settings::instance();
$grouped_types     = TypeRegistry::instance()->get_grouped_types();
$enabled_types     = $settings->get_enabled_location_types();
$post_types        = get_post_types( [ 'public' => true ], 'objects' );
$enabled_post_types = $settings->get_enabled_post_types();
$all_roles         = wp_roles()->roles;
$shortcode_roles   = $settings->get_shortcode_roles();
$warning_terms     = $settings->get_warning_terms();
?>
<div class="wrap ptscanner-wrap ptscanner-settings-page">
    <header class="ptscanner-header">
        <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
    </header>

    <?php settings_errors( 'ptscanner-notices' ); ?>

    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <?php wp_nonce_field( Settings::instance()->nonce_action(), Settings::instance()->nonce_action() . '_field' ); ?>
        <input type="hidden" name="action" value="ptscanner_save_settings">

        <div class="ptscanner-card">
            <h2><?php esc_html_e( 'Search Locations', 'prohibited-terms-scanner' ); ?></h2>
            <p class="description"><?php esc_html_e( 'Choose which locations are included by default when scanning.', 'prohibited-terms-scanner' ); ?></p>

            <?php foreach ( $grouped_types as $group_name => $types ) : ?>
                <fieldset class="ptscanner-type-group">
                    <legend><?php echo esc_html( ucwords( $group_name ) ); ?></legend>
                    <?php foreach ( $types as $slug => $type ) : ?>
                        <label class="ptscanner-type-checkbox">
                            <input type="checkbox" name="location_types[]" value="<?php echo esc_attr( $slug ); ?>"
                                <?php checked( in_array( $slug, $enabled_types, true ) ); ?>>
                            <?php echo esc_html( $type[ 'label' ] ); ?>
                        </label>
                    <?php endforeach; ?>
                </fieldset>
            <?php endforeach; ?>
        </div>

        <div class="ptscanner-card">
            <h2><?php esc_html_e( 'Post Types', 'prohibited-terms-scanner' ); ?></h2>
            <p class="description"><?php esc_html_e( 'Choose which public post types are included when scanning content.', 'prohibited-terms-scanner' ); ?></p>

            <?php foreach ( $post_types as $post_type ) : ?>
                <label class="ptscanner-type-checkbox">
                    <input type="checkbox" name="post_types[]" value="<?php echo esc_attr( $post_type->name ); ?>"
                        <?php checked( in_array( $post_type->name, $enabled_post_types, true ) ); ?>>
                    <?php echo esc_html( $post_type->label ); ?>
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
                            value="<?php echo esc_attr( $settings->get_batch_size() ); ?>" class="small-text">
                        <p class="description"><?php esc_html_e( 'Number of items processed per AJAX request. Lower this if scans are timing out.', 'prohibited-terms-scanner' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="ptscanner-snippet-padding"><?php esc_html_e( 'Snippet Padding', 'prohibited-terms-scanner' ); ?></label></th>
                    <td>
                        <input type="number" id="ptscanner-snippet-padding" name="snippet_padding" min="0" max="500"
                            value="<?php echo esc_attr( $settings->get_snippet_padding() ); ?>" class="small-text">
                        <p class="description"><?php esc_html_e( 'Number of characters shown on each side of a matched term in the results context column. Default: 60.', 'prohibited-terms-scanner' ); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <div class="ptscanner-card">
            <h2><?php esc_html_e( 'Default Matching Behavior', 'prohibited-terms-scanner' ); ?></h2>
            <p class="description"><?php esc_html_e( 'Applied to newly added terms unless overridden per term.', 'prohibited-terms-scanner' ); ?></p>

            <label>
                <input type="checkbox" name="default_case_sensitive" <?php checked( $settings->get_default_case_sensitive() ); ?>>
                <?php esc_html_e( 'Case Sensitive by default', 'prohibited-terms-scanner' ); ?>
            </label>
            <br>
            <label>
                <input type="checkbox" name="default_strict" <?php checked( $settings->get_default_strict() ); ?>>
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

            <?php foreach ( $all_roles as $role_slug => $role_data ) : ?>
                <?php if ( 'administrator' === $role_slug ) : continue; endif; ?>
                <label class="ptscanner-type-checkbox">
                    <input type="checkbox" name="shortcode_roles[]" value="<?php echo esc_attr( $role_slug ); ?>"
                        <?php checked( in_array( $role_slug, $shortcode_roles, true ) ); ?>>
                    <?php echo esc_html( translate_user_role( $role_data[ 'name' ] ) ); ?>
                </label>
            <?php endforeach; ?>
        </div>

        <div class="ptscanner-card">
            <h2><?php esc_html_e( 'Save/Upload Warning', 'prohibited-terms-scanner' ); ?></h2>
            <label>
                <input type="checkbox" name="warning_enabled" id="ptscanner-warning-enabled" <?php checked( $settings->is_warning_enabled() ); ?>>
                <?php esc_html_e( 'Warn before saving a post/page or uploading a file that contains a monitored term', 'prohibited-terms-scanner' ); ?>
            </label>

            <div class="ptscanner-warning-terms-input">
                <label for="ptscanner-warning-terms-textarea"><?php esc_html_e( 'Monitored terms, one per line', 'prohibited-terms-scanner' ); ?></label>
                <textarea id="ptscanner-warning-terms-textarea" rows="4" class="large-text"></textarea>
                <button type="button" class="button" id="ptscanner-add-warning-terms">
                    <?php esc_html_e( 'Add Terms', 'prohibited-terms-scanner' ); ?>
                </button>
            </div>

            <div class="ptscanner-terms-cards" id="ptscanner-warning-terms-cards"></div>

            <input type="hidden" name="warning_terms_json" id="ptscanner-warning-terms-json">
        </div>

        <?php submit_button( __( 'Save Settings', 'prohibited-terms-scanner' ) ); ?>
    </form>
</div>