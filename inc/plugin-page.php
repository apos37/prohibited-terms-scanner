<?php
/**
 * PLUGIN PAGE
 *
 * Adds row meta links (Guide, Docs, Support) and a Settings action link
 * on the Plugins screen.
 */

namespace PluginRx\ProhibitedTermsScanner;

if ( ! defined( 'ABSPATH' ) ) exit;


class PluginPage {

    /**
     * @var PluginPage|null Singleton instance
     */
    private static ?PluginPage $instance = null;


    /**
     * Get instance
     *
     * @return self
     */
    public static function instance() : self {
        return self::$instance ??= new self();
    } // End instance()


    /**
     * Constructor
     */
    private function __construct() {
        add_filter( 'plugin_row_meta', [ $this, 'add_row_meta' ], 10, 2 );
        add_filter( 'plugin_action_links_' . Bootstrap::plugin_file(), [ $this, 'add_action_links' ] );
    } // End __construct()


    /**
     * Get the external help links.
     *
     * @return array Key => [ label, url ].
     */
    public static function links() : array {
        $links = [
            'guide'   => [
                'label' => __( 'How-To Guide', 'prohibited-terms-scanner' ),
                'url'   => 'https://pluginrx.com/guide/plugin/prohibited-terms-scanner/',
            ],
            'docs'    => [
                'label' => __( 'Developer Docs', 'prohibited-terms-scanner' ),
                'url'   => 'https://pluginrx.com/docs/plugin/prohibited-terms-scanner/',
            ],
            'support' => [
                'label' => __( 'Support', 'prohibited-terms-scanner' ),
                'url'   => 'https://pluginrx.com/support/plugin/prohibited-terms-scanner/',
            ],
        ];

        return (array) apply_filters( 'ptscanner_help_links', $links );
    } // End links()


    /**
     * Add Guide, Docs, and Support links to the plugin's row meta.
     *
     * @param array  $links
     * @param string $plugin_file
     * @return array
     */
    public function add_row_meta( array $links, string $plugin_file ) : array {
        if ( Bootstrap::plugin_file() !== $plugin_file ) {
            return $links;
        }

        foreach ( self::links() as $link ) {
            $links[] = '<a href="' . esc_url( $link[ 'url' ] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $link[ 'label' ] ) . '</a>';
        }

        return $links;
    } // End add_row_meta()


    /**
     * Add a Settings link to the plugin's action links.
     *
     * @param array $links
     * @return array
     */
    public function add_action_links( array $links ) : array {
        if ( ! Access::can_manage() ) {
            return $links;
        }

        array_unshift( $links, '<a href="' . esc_url( Bootstrap::settings_url() ) . '">' . esc_html__( 'Settings', 'prohibited-terms-scanner' ) . '</a>' );

        return $links;
    } // End add_action_links()

} // End class PluginPage

PluginPage::instance();
