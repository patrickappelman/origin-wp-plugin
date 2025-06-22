<?php
/**
 * Main plugin class for Origin Recruitment Utilities.
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
    private $admin_settings;

    private function __construct() {
        // Initialize classes
        $this->post_types = new ORU_Post_Types();
        $this->taxonomies = new ORU_Taxonomies();
        $this->sanitization = new ORU_Sanitization();
        $this->zoho_auth = new ORU_Zoho_Auth();
        $this->zoho_api = new ORU_Zoho_API( $this->zoho_auth );
        $this->zoho_sync = new ORU_Zoho_Sync( $this->zoho_api, $this->sanitization );
        $this->admin_settings = new ORU_Admin_Settings( $this->zoho_auth, $this->zoho_api, $this->zoho_sync );

        // Register cron schedules
        add_filter( 'cron_schedules', [ $this, 'add_cron_schedule' ] );

        // Schedule cron jobs
        add_action( 'oru_zoho_refresh_token_cron', [ $this->zoho_auth, 'refresh_token' ] );
        add_action( 'oru_zoho_sync_jobs_cron', [ $this->zoho_sync, 'sync_jobs' ] );

        // Plugin activation and deactivation hooks
        register_activation_hook( __FILE__, [ $this, 'activate' ] );
        register_deactivation_hook( __FILE__, [ $this, 'deactivate' ] );
    }

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function add_cron_schedule( $schedules ) {
        $schedules['thirty_minutes'] = [
            'interval' => 1800, // 30 minutes
            'display' => __( 'Every Thirty Minutes', 'textdomain' ),
        ];
        return $schedules;
    }

    public function activate() {
        if ( ! wp_next_scheduled( 'oru_zoho_refresh_token_cron' ) ) {
            wp_schedule_event( time(), 'thirty_minutes', 'oru_zoho_refresh_token_cron' );
        }
        if ( ! wp_next_scheduled( 'oru_zoho_sync_jobs_cron' ) ) {
            wp_schedule_event( time(), 'hourly', 'oru_zoho_sync_jobs_cron' );
        }
    }

    public function deactivate() {
        wp_clear_scheduled_hook( 'oru_zoho_refresh_token_cron' );
        wp_clear_scheduled_hook( 'oru_zoho_sync_jobs_cron' );
    }
}