<?php
/**
 * External links table handling
 */

namespace PluginRx\ProhibitedTermsScanner;

if ( ! defined( 'ABSPATH' ) ) exit;

class ExternalLinksDB {


    /**
     * Table name (without prefix)
     *
     * @var string
     */
    private string $table_name = 'ptscanner_external_links';


    /**
     * Current schema version, bump to trigger dbDelta on upgrade
     *
     * @var string
     */
    private string $schema_version = '1.0.0';


    /**
     * The single instance of the class
     *
     * @var self|null
     */
    private static ?ExternalLinksDB $instance = null;


    /**
     * Get the singleton instance
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
        add_action( 'plugins_loaded', [ $this, 'maybe_upgrade' ] );
    } // End __construct()


    /**
     * Full table name with prefix
     *
     * @return string
     */
    public function table() : string {
        global $wpdb;

        return $wpdb->prefix . $this->table_name;
    } // End table()


    /**
     * Check schema version and upgrade if needed
     *
     * @return void
     */
    public function maybe_upgrade() {
        $installed_version = get_option( 'ptscanner_ext_links_schema_version', '' );

        if ( $installed_version !== $this->schema_version ) {
            $this->create_table();
            update_option( 'ptscanner_ext_links_schema_version', $this->schema_version, false );
        }
    } // End maybe_upgrade()


    /**
     * Create or update the external links table
     *
     * @return void
     */
    public function create_table() {
        global $wpdb;

        $table_name      = $this->table();
        $charset_collate = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $sql = "CREATE TABLE {$table_name} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            link TEXT NOT NULL,
            link_hash CHAR(32) NOT NULL,
            anchor_text TEXT NULL,
            source_type VARCHAR(50) NOT NULL,
            source_id BIGINT UNSIGNED NOT NULL,
            is_flagged TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
            matched_term TEXT NULL,
            note TEXT NULL,
            last_seen_at DATETIME NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY link_source (link_hash, source_id),
            KEY is_flagged (is_flagged),
            KEY source_id (source_id)
        ) {$charset_collate};";

        dbDelta( $sql );
    } // End create_table()


    /**
     * Upsert a link found during a scan — insert new, or refresh an existing
     * row's flagged status/text and last_seen_at timestamp, preserving note
     *
     * @param array $args
     * @return bool True if a new row was inserted, false if an existing row was updated
     */
    public function upsert( array $args ) : bool {
        global $wpdb;

        $table_name = $this->table();
        $now        = current_time( 'mysql' );
        $link_hash  = md5( $args[ 'link' ] );

        $existing_id = $wpdb->get_var( // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare(
                "SELECT id FROM {$table_name} WHERE link_hash = %s AND source_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $link_hash,
                $args[ 'source_id' ]
            )
        );

        if ( $existing_id ) {
            $result = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $table_name,
                [
                    'anchor_text'  => $args[ 'anchor_text' ],
                    'is_flagged'   => $args[ 'is_flagged' ] ? 1 : 0,
                    'matched_term' => $args[ 'matched_term' ] ?? null,
                    'last_seen_at' => $now,
                    'updated_at'   => $now,
                ],
                [ 'id' => $existing_id ],
                [ '%s', '%d', '%s', '%s', '%s' ],
                [ '%d' ]
            );

            return false !== $result;
        }

        $result = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            $table_name,
            [
                'link'         => $args[ 'link' ],
                'link_hash'    => $link_hash,
                'anchor_text'  => $args[ 'anchor_text' ],
                'source_type'  => $args[ 'source_type' ],
                'source_id'    => $args[ 'source_id' ],
                'is_flagged'   => $args[ 'is_flagged' ] ? 1 : 0,
                'matched_term' => $args[ 'matched_term' ] ?? null,
                'last_seen_at' => $now,
                'created_at'   => $now,
                'updated_at'   => $now,
            ],
            [ '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s' ]
        );

        return false !== $result;
    } // End upsert()


    /**
     * Remove links not seen in the most recent scan pass (i.e. the link was
     * removed from the source content since the last scan)
     *
     * @param string $cutoff MySQL datetime — rows with last_seen_at before this are stale
     * @return int Number of rows deleted
     */
    public function delete_stale( string $cutoff ) : int {
        global $wpdb;

        $table_name = esc_sql( $this->table() );

        $deleted = $wpdb->query( // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare(
                "DELETE FROM {$table_name} WHERE last_seen_at < %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $cutoff
            )
        );

        return false !== $deleted ? (int) $deleted : 0;
    } // End delete_stale()


    /**
     * Get a page of links
     *
     * @param int    $page
     * @param int    $per_page
     * @param string $filter all|flagged
     * @return array [ 'rows' => array, 'total_pages' => int ]
     */
    public function get_page( int $page, int $per_page = 50, string $filter = 'all' ) : array {
        global $wpdb;

        $table_name = esc_sql( $this->table() );
        $offset     = ( max( 1, $page ) - 1 ) * $per_page;
        $where      = 'flagged' === $filter ? 'WHERE is_flagged = 1' : '';

        $total_all     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $total_flagged = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name} WHERE is_flagged = 1" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $total         = 'flagged' === $filter ? $total_flagged : $total_all;

        $rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare(
                "SELECT * FROM {$table_name} {$where} ORDER BY is_flagged DESC, link ASC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $per_page,
                $offset
            ),
            ARRAY_A
        );

        return [
            'rows'          => $rows ?: [],
            'total_pages'   => (int) ceil( $total / $per_page ),
            'total_all'     => $total_all,
            'total_flagged' => $total_flagged,
        ];
    } // End get_page()


    /**
     * Save or clear a note on a link row
     *
     * @param int    $id
     * @param string $note
     * @return bool
     */
    public function update_note( int $id, string $note ) : bool {
        global $wpdb;

        $updated = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $this->table(),
            [
                'note'       => '' === $note ? null : $note,
                'updated_at' => current_time( 'mysql' ),
            ],
            [ 'id' => $id ],
            [ '%s', '%s' ],
            [ '%d' ]
        );

        return false !== $updated;
    } // End update_note()

}


ExternalLinksDB::instance();