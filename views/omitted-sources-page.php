<?php
/**
 * Omitted sources page view
 */

namespace PluginRx\ProhibitedTermsScanner;

if ( ! defined( 'ABSPATH' ) ) exit;

$ptscanner_omits = Omits::instance()->get_list();
?>
<div class="wrap ptscanner-wrap ptscanner-omitted-page">
    <header class="ptscanner-header">
        <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
        <p class="description"><?php esc_html_e( 'Sources omitted here are skipped during all scans. Use this for content that still exists on your site but is unpublished/private and never meant to be public.', 'prohibited-terms-scanner' ); ?></p>
    </header>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th><?php esc_html_e( 'Name', 'prohibited-terms-scanner' ); ?></th>
                <th><?php esc_html_e( 'Type', 'prohibited-terms-scanner' ); ?></th>
                <th><?php esc_html_e( 'Actions', 'prohibited-terms-scanner' ); ?></th>
            </tr>
        </thead>
        <tbody id="ptscanner-omitted-body">
            <?php if ( empty( $ptscanner_omits ) ) : ?>
                <tr><td colspan="3"><?php esc_html_e( 'No omitted sources.', 'prohibited-terms-scanner' ); ?></td></tr>
            <?php else : ?>
                <?php foreach ( $ptscanner_omits as $ptscanner_entry ) : ?>
                    <tr data-id="<?php echo esc_attr( $ptscanner_entry[ 'id' ] ); ?>" data-type="<?php echo esc_attr( $ptscanner_entry[ 'type' ] ); ?>">
                        <td><?php echo esc_html( $ptscanner_entry[ 'label' ] ?: '#' . $ptscanner_entry[ 'id' ] ); ?></td>
                        <td><?php echo esc_html( ucwords( str_replace( '_', ' ', $ptscanner_entry[ 'type' ] ) ) ); ?></td>
                        <td>
                            <button type="button" class="button-link ptscanner-remove-omit" data-id="<?php echo esc_attr( $ptscanner_entry[ 'id' ] ); ?>" data-type="<?php echo esc_attr( $ptscanner_entry[ 'type' ] ); ?>">
                                <?php esc_html_e( 'Remove', 'prohibited-terms-scanner' ); ?>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>