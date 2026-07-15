<?php
/**
 * Database table handling
 */

namespace PluginRx\ProhibitedTermsScanner;

if ( ! defined( 'ABSPATH' ) ) exit;

class DB {


    /**
     * Table name (without prefix)
     *
     * @var string
     */
    private string $table_name = 'ptscanner_results';


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
    private static ?DB $instance = null;


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
        $installed_version = get_option( 'ptscanner_schema_version', '' );

        if ( $installed_version !== $this->schema_version ) {
            $this->create_table();
            update_option( 'ptscanner_schema_version', $this->schema_version, false );
        }
    } // End maybe_upgrade()


    /**
     * Create or update the results table
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
            term TEXT NOT NULL,
            term_hash CHAR(32) NOT NULL,
            location_type VARCHAR(50) NOT NULL,
            source_type VARCHAR(50) NOT NULL,
            source_id BIGINT UNSIGNED NULL,
            source_url TEXT NULL,
            context_snippet TEXT NULL,
            file_page INT UNSIGNED NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'flagged',
            match_hash CHAR(32) NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY match_hash (match_hash),
            KEY status (status),
            KEY location_type (location_type),
            KEY term_hash (term_hash)
        ) {$charset_collate};";

        dbDelta( $sql );
    } // End create_table()


    /**
     * Wipe all flagged (non-ignored) results, used before a fresh full scan
     *
     * @return void
     */
    public function wipe_flagged() {
        global $wpdb;

        $table_name = $this->table();

        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table_name} WHERE status = %s",
                'flagged'
            )
        );

        $this->clear_flagged_count_cache();
    } // End wipe_flagged()


    /**
     * Get existing match hashes for a status, used to skip re-flagging ignored items
     *
     * @param string $status
     * @return array
     */
    public function get_match_hashes( $status = 'ignored' ) : array {
        global $wpdb;

        $table_name = $this->table();

        $hashes = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT match_hash FROM {$table_name} WHERE status = %s",
                $status
            )
        );

        return array_flip( $hashes );
    } // End get_match_hashes()


    /**
     * Insert a result row
     *
     * @param array $args
     * @return int|false
     */
    public function insert( $args ) {
        global $wpdb;

        $table_name = $this->table();
        $now        = current_time( 'mysql' );

        $inserted = $wpdb->insert(
            $table_name,
            [
                'term'             => $args[ 'term' ],
                'term_hash'        => md5( mb_strtolower( $args[ 'term' ] ) ),
                'location_type'    => $args[ 'location_type' ],
                'source_type'      => $args[ 'source_type' ],
                'source_id'        => $args[ 'source_id' ] ?? null,
                'source_url'       => $args[ 'source_url' ] ?? null,
                'context_snippet'  => $args[ 'context_snippet' ] ?? null,
                'file_page'        => $args[ 'file_page' ] ?? null,
                'status'           => 'flagged',
                'match_hash'       => $args[ 'match_hash' ],
                'created_at'       => $now,
                'updated_at'       => $now,
            ],
            [ '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s' ]
        );

        // $this->clear_flagged_count_cache();

        return $inserted ? $wpdb->insert_id : false;
    } // End insert()


    /**
     * Update a row's status (e.g. mark as ignored)
     *
     * @param int    $id
     * @param string $status
     * @return bool
     */
    public function set_status( $id, $status ) : bool {
        global $wpdb;

        $table_name = $this->table();

        $updated = $wpdb->update(
            $table_name,
            [
                'status'     => sanitize_key( $status ),
                'updated_at' => current_time( 'mysql' ),
            ],
            [ 'id' => absint( $id ) ],
            [ '%s', '%s' ],
            [ '%d' ]
        );

        $this->clear_flagged_count_cache();

        return false !== $updated;
    } // End set_status()


    /**
     * Delete a single result row
     *
     * @param int $id
     * @return bool
     */
    public function delete( $id ) : bool {
        global $wpdb;

        $table_name = $this->table();

        $deleted = $wpdb->delete(
            $table_name,
            [ 'id' => absint( $id ) ],
            [ '%d' ]
        );

        $this->clear_flagged_count_cache();

        return false !== $deleted && $deleted > 0;
    } // End delete()


    /**
     * Delete all rows matching a given status
     *
     * @param string $status
     * @return int Number of rows deleted
     */
    public function delete_all_by_status( $status ) : int {
        global $wpdb;

        $table_name = $this->table();

        $deleted = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table_name} WHERE status = %s",
                $status
            )
        );

        $this->clear_flagged_count_cache();

        return false !== $deleted ? (int) $deleted : 0;
    } // End delete_all_by_status()


    /**
     * Get a count of flagged occurrences grouped by term
     *
     * @return array Array of [ 'term' => string, 'count' => int ]
     */
    public function get_flagged_summary() : array {
        global $wpdb;

        $table_name = $this->table();

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT term, COUNT(*) as count FROM {$table_name} WHERE status = %s GROUP BY term_hash, term ORDER BY count DESC",
                'flagged'
            ),
            ARRAY_A
        );

        return $rows ? $rows : [];
    } // End get_flagged_summary()


    /**
     * Get the total count of flagged (unresolved) results, cached until
     * explicitly invalidated by a scan, clear, or status change
     *
     * @return int
     */
    public function get_flagged_count() : int {
        $cached = get_option( 'ptscanner_flagged_count', false );

        if ( false !== $cached ) {
            return (int) $cached;
        }

        global $wpdb;

        $table_name = $this->table();

        $count = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table_name} WHERE status = %s",
                'flagged'
            )
        );

        update_option( 'ptscanner_flagged_count', $count, false );

        return $count;
    } // End get_flagged_count()


    /**
     * Clear the cached flagged count, called whenever results change, so
     * the next read recounts immediately rather than waiting on an expiry
     *
     * @return void
     */
    public function clear_flagged_count_cache() {
        delete_option( 'ptscanner_flagged_count' );
    } // End clear_flagged_count_cache()

}