<?php
/**
 * Scheduled cron scanning
 *
 * Optional, off by default. Runs a full scan using the saved term list and
 * enabled location types on a schedule, without requiring anyone to visit
 * the Scanner page. Intended primarily for developers/site owners who want
 * unattended, recurring scans.
 */

namespace PluginRx\ProhibitedTermsScanner;

if ( ! defined( 'ABSPATH' ) ) exit;

class Cron {


    /**
     * Cron hook name
     *
     * @var string
     */
    private string $hook = 'ptscanner_scheduled_scan';


    /**
     * The single instance of the class
     *
     * @var self|null
     */
    private static ?Cron $instance = null;


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
        add_filter( 'cron_schedules', [ $this, 'add_custom_schedules' ] ); // phpcs:ignore WordPress.WP.CronInterval.CronSchedulesInterval
        add_action( $this->hook, [ $this, 'run_scheduled_scan' ] );
        add_action( 'ptscanner_reschedule_cron', [ $this, 'reschedule' ] );
        add_action( 'init', [ $this, 'maybe_schedule' ] );
    } // End __construct()


    /**
     * Register 'weekly' and 'monthly' cron intervals, since WordPress only
     * includes hourly/twicedaily/daily by default
     *
     * @param array $schedules
     * @return array
     */
    public function add_custom_schedules( array $schedules ) : array {
        if ( ! isset( $schedules[ 'weekly' ] ) ) {
            $schedules[ 'weekly' ] = [
                'interval' => WEEK_IN_SECONDS,
                'display'  => __( 'Once Weekly', 'prohibited-terms-scanner' ),
            ];
        }

        if ( ! isset( $schedules[ 'monthly' ] ) ) {
            $schedules[ 'monthly' ] = [
                'interval' => 30 * DAY_IN_SECONDS,
                'display'  => __( 'Once Monthly', 'prohibited-terms-scanner' ),
            ];
        }

        return $schedules;
    } // End add_custom_schedules()


    /**
     * Ensure the cron event is scheduled (or not) to match current settings
     *
     * @return void
     */
    public function maybe_schedule() {
        $is_enabled = Settings::instance()->is_cron_enabled();
        $is_scheduled = false !== wp_next_scheduled( $this->hook );

        if ( $is_enabled && ! $is_scheduled ) {
            wp_schedule_event( time(), Settings::instance()->get_cron_frequency(), $this->hook );
        } elseif ( ! $is_enabled && $is_scheduled ) {
            wp_clear_scheduled_hook( $this->hook );
        }
    } // End maybe_schedule()


    /**
     * Clear and reschedule, called when Settings are saved
     *
     * @return void
     */
    public function reschedule() {
        wp_clear_scheduled_hook( $this->hook );
        $this->maybe_schedule();
    } // End reschedule()


    /**
     * Run a full scan synchronously across all enabled location types
     *
     * WARNING: this runs in a single PHP execution with no browser-driven
     * batching, unlike the manual Scanner page. On very large sites this
     * can approach PHP's max_execution_time. The $max_batches_per_type cap
     * is a safety valve — filterable — that stops a single type from
     * looping indefinitely if something goes wrong, rather than a true fix
     * for large-site performance. Site owners with very large content
     * volumes should test this in a staging environment before relying on
     * it, or increase max_execution_time for cron requests specifically.
     *
     * @return void
     */
    public function run_scheduled_scan() {
        if ( get_transient( 'ptscanner_cron_running' ) ) {
            ErrorLog::instance()->log( 'cron', 'Scheduled scan skipped: a previous run appears to still be in progress.' );

            return;
        }

        set_transient( 'ptscanner_cron_running', true, HOUR_IN_SECONDS );

        $terms = Settings::instance()->get_terms();

        if ( empty( $terms ) ) {
            delete_transient( 'ptscanner_cron_running' );

            return;
        }

        $enabled_types = Settings::instance()->get_enabled_location_types();
        $batch_size    = Settings::instance()->get_batch_size();
        $max_batches    = (int) apply_filters( 'ptscanner_cron_max_batches_per_type', 500 );

        DB::instance()->wipe_flagged();

        $ignored_hashes = DB::instance()->get_match_hashes( 'ignored' );
        $total_inserted = 0;

        foreach ( $enabled_types as $type_slug ) {
            $offset      = 0;
            $batch_count = 0;

            do {
                try {
                    $result = Scanner::instance()->run_batch( $type_slug, $terms, $offset, $batch_size );
                } catch ( \Throwable $e ) {
                    ErrorLog::instance()->log( 'cron', 'Scan error for type ' . $type_slug . ': ' . $e->getMessage() );

                    break;
                }

                foreach ( $result[ 'rows' ] as $row ) {
                    if ( isset( $ignored_hashes[ $row[ 'match_hash' ] ] ) ) {
                        continue;
                    }

                    DB::instance()->insert( $row );
                    $total_inserted++;
                }

                $offset += $batch_size;
                $batch_count++;
            } while ( ! $result[ 'done' ] && $batch_count < $max_batches );
        }

        delete_transient( 'ptscanner_cron_running' );

        update_option( 'ptscanner_cron_last_run', current_time( 'mysql' ), false );
        update_option( 'ptscanner_cron_last_result', $total_inserted, false );

        /**
         * Fires after a scheduled cron scan completes.
         *
         * @param int $total_inserted Number of new flagged results found.
         */
        do_action( 'ptscanner_cron_scan_complete', $total_inserted );

        ErrorLog::instance()->log( 'cron', 'Scheduled scan completed: ' . $total_inserted . ' result(s) found.' );
    } // End run_scheduled_scan()


    /**
     * Get a small status summary for display in Settings
     *
     * @return array
     */
    public function get_status() : array {
        return [
            'enabled'      => Settings::instance()->is_cron_enabled(),
            'next_run'     => wp_next_scheduled( $this->hook ),
            'last_run'     => get_option( 'ptscanner_cron_last_run', '' ),
            'last_result'  => get_option( 'ptscanner_cron_last_result', null ),
        ];
    } // End get_status()

}


Cron::instance();