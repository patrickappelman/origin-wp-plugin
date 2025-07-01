<?php
/**
 * Main plugin class for Origin Recruitment Utilities.
 * Path: wp-content/plugins/origin-recruitment-utilities/classes/OriginRecruitmentUtilities.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class OriginRecruitmentUtilities {
	private static $instance = null;

	private $post_types;
	private $taxonomies;
	private $sanitization;
	private $zoho_auth;
	private $zoho_api;
	private $zoho_sync;
	private $zoho_cron;
	private $admin_settings;
	private $candidate_registration;
	private $candidate_application;

	private function __construct() {
		// Initialize classes
		$this->post_types = new ORU_Post_Types();
		$this->taxonomies = new ORU_Taxonomies();
		$this->sanitization = new ORU_Sanitization();
		$this->zoho_auth = new ORU_Zoho_Auth();
		$this->zoho_api = new ORU_Zoho_API( $this->zoho_auth );
		$this->zoho_sync = new ORU_Zoho_Sync( $this->zoho_api, $this->sanitization );
		$this->candidate_application = new ORU_Candidate_Application( $this->zoho_api, $this->sanitization );
		$this->zoho_cron = new ORU_Zoho_Cron( $this->zoho_api, $this->zoho_sync, $this->sanitization, $this->candidate_application );
		$this->admin_settings = new ORU_Admin_Settings( $this->zoho_auth, $this->zoho_api, $this->zoho_sync );
		$this->candidate_registration = new ORU_Candidate_Registration( $this->zoho_api, $this->sanitization );

		error_log( 'OriginRecruitmentUtilities: Constructor called, all classes initialized including ORU_Candidate_Application and ORU_Zoho_Cron, backtrace: ' . wp_debug_backtrace_summary() );

		// Register cron schedules
		add_filter( 'cron_schedules', [ $this, 'add_cron_schedule' ] );

		// Schedule cron jobs
		add_action( 'oru_zoho_refresh_token_cron', [ $this->zoho_auth, 'refresh_token' ] );
		add_action( 'oru_zoho_sync_updated_jobs', [ $this->zoho_cron, 'sync_updated_jobs' ] );
		add_action( 'oru_zoho_sync_updated_applications', [ $this->zoho_cron, 'sync_updated_applications' ] );
		add_action( 'oru_zoho_sync_updated_candidates', [ $this->zoho_cron, 'sync_updated_candidates' ] );

		// Plugin activation and deactivation hooks
		register_activation_hook( dirname( __DIR__ ) . '/origin-recruitment-utilities.php', [ $this, 'activate' ] );
		register_deactivation_hook( dirname( __DIR__ ) . '/origin-recruitment-utilities.php', [ $this, 'deactivate' ] );
	}

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
			error_log( 'OriginRecruitmentUtilities: Singleton instance created, backtrace: ' . wp_debug_backtrace_summary() );
		} else {
			error_log( 'OriginRecruitmentUtilities: Singleton instance already exists, returning existing instance, backtrace: ' . wp_debug_backtrace_summary() );
		}
		return self::$instance;
	}

	public function add_cron_schedule( $schedules ) {
		$schedules['fifteen_minutes'] = [
			'interval' => 900, // 15 minutes
			'display' => __( 'Every Fifteen Minutes', 'textdomain' ),
		];
		$schedules['thirty_minutes'] = [
			'interval' => 1800, // 30 minutes
			'display' => __( 'Every Thirty Minutes', 'textdomain' ),
		];
		return $schedules;
	}

	public function activate() {
		error_log( 'OriginRecruitmentUtilities: Activation hook triggered' );
		if ( ! wp_next_scheduled( 'oru_zoho_refresh_token_cron' ) ) {
			wp_schedule_event( time(), 'thirty_minutes', 'oru_zoho_refresh_token_cron' );
			error_log( 'OriginRecruitmentUtilities: Scheduled oru_zoho_refresh_token_cron' );
		}
		if ( wp_next_scheduled( 'oru_zoho_sync_jobs_cron' ) ) {
			wp_clear_scheduled_hook( 'oru_zoho_sync_jobs_cron' );
			error_log( 'OriginRecruitmentUtilities: Cleared oru_zoho_sync_jobs_cron' );
		}
		$now = time();
		if ( ! wp_next_scheduled( 'oru_zoho_sync_updated_jobs' ) ) {
			wp_schedule_event( $now, 'fifteen_minutes', 'oru_zoho_sync_updated_jobs' );
			error_log( 'OriginRecruitmentUtilities: Scheduled oru_zoho_sync_updated_jobs at ' . gmdate( 'Y-m-d H:i:s', $now ) );
		}
		if ( ! wp_next_scheduled( 'oru_zoho_sync_updated_applications' ) ) {
			wp_schedule_event( $now + 300, 'fifteen_minutes', 'oru_zoho_sync_updated_applications' ); // 5-minute offset
			error_log( 'OriginRecruitmentUtilities: Scheduled oru_zoho_sync_updated_applications at ' . gmdate( 'Y-m-d H:i:s', $now + 300 ) );
		}
		if ( ! wp_next_scheduled( 'oru_zoho_sync_updated_candidates' ) ) {
			wp_schedule_event( $now + 600, 'fifteen_minutes', 'oru_zoho_sync_updated_candidates' ); // 10-minute offset
			error_log( 'OriginRecruitmentUtilities: Scheduled oru_zoho_sync_updated_candidates at ' . gmdate( 'Y-m-d H:i:s', $now + 600 ) );
		}
		$this->create_job_applications_table();
	}

	public function deactivate() {
		wp_clear_scheduled_hook( 'oru_zoho_refresh_token_cron' );
		wp_clear_scheduled_hook( 'oru_zoho_sync_updated_jobs' );
		wp_clear_scheduled_hook( 'oru_zoho_sync_updated_applications' );
		wp_clear_scheduled_hook( 'oru_zoho_sync_updated_candidates' );
		error_log( 'OriginRecruitmentUtilities: Deactivation hook triggered, cleared cron schedules' );
	}

	private function create_job_applications_table() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'job_applications';
		$charset_collate = $wpdb->get_charset_collate();

		// Check if table exists
		$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) === $table_name;
		error_log( 'OriginRecruitmentUtilities: Checking if table exists - ' . ( $table_exists ? 'Table already exists' : 'Table does not exist' ) );

		if ( ! $table_exists ) {
			$sql = "CREATE TABLE $table_name (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				zoho_candidate_id bigint(20) unsigned NOT NULL,
				zoho_job_id bigint(20) unsigned NOT NULL,
				zoho_application_id bigint(20) unsigned NOT NULL,
				zoho_application_status varchar(50) NOT NULL,
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				PRIMARY KEY (id),
				KEY zoho_candidate_id (zoho_candidate_id),
				KEY zoho_job_id (zoho_job_id),
				KEY zoho_application_status (zoho_application_status),
				UNIQUE KEY zoho_application_id (zoho_application_id)
			) $charset_collate;";

			error_log( 'OriginRecruitmentUtilities: Attempting to create table with SQL: ' . $sql );

			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			dbDelta( $sql );

			if ( $wpdb->last_error ) {
				error_log( 'OriginRecruitmentUtilities: Table creation failed: ' . $wpdb->last_error );
			} else {
				// Verify table creation
				$new_table_exists = $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) === $table_name;
				if ( $new_table_exists ) {
					error_log( 'OriginRecruitmentUtilities: Zoho Job Applications Table Created Successfully' );
				} else {
					error_log( 'OriginRecruitmentUtilities: Table creation appeared successful but table does not exist' );
				}
			}
		} else {
			// Check and update column types if necessary
			$columns = $wpdb->get_results( "DESCRIBE $table_name" );
			$column_types = [];
			foreach ( $columns as $column ) {
				$column_types[ $column->Field ] = $column->Type;
			}

			$expected_types = [
				'zoho_candidate_id' => 'bigint(20) unsigned',
				'zoho_job_id' => 'bigint(20) unsigned',
				'zoho_application_id' => 'bigint(20) unsigned',
			];

			$alter_sql = [];
			foreach ( $expected_types as $column => $expected_type ) {
				if ( isset( $column_types[ $column ] ) && strtolower( $column_types[ $column ] ) !== $expected_type ) {
					$alter_sql[] = "MODIFY COLUMN $column $expected_type NOT NULL";
				}
			}

			if ( ! empty( $alter_sql ) ) {
				$alter_query = "ALTER TABLE $table_name " . implode( ', ', $alter_sql );
				error_log( 'OriginRecruitmentUtilities: Attempting to alter table with SQL: ' . $alter_query );
				$wpdb->query( $alter_query );
				if ( $wpdb->last_error ) {
					error_log( 'OriginRecruitmentUtilities: Table alteration failed: ' . $wpdb->last_error );
				} else {
					error_log( 'OriginRecruitmentUtilities: Table columns updated successfully' );
				}
			} else {
				error_log( 'OriginRecruitmentUtilities: Table exists with correct column types, no alteration needed' );
			}
		}
	}
}
?>
