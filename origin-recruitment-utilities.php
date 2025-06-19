<?php
/*
 * Plugin Name: Origin Recruitment - Utilities
 * Description: A custom plugin developed for Origin Recruitment by Appelman Designs to augment WordPress to include a Jobs post type, as well as custom tag taxonomy such as Languages, Countries, and Industries.
 * Version: 1.0.12
 * Author: Appelman Designs
 * Author URI: https://appelmandesigns.com/
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// Define redirect URI
if ( defined( 'WP_LOCAL_DEV' ) && WP_LOCAL_DEV ) {
    $current_host = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? 'origin-recruitment:8890';
    define( 'ZOHO_RECRUIT_REDIRECT_URI', $current_host !== 'origin-recruitment:8890'
        ? rtrim( ( isset( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http' ) . '://' . $current_host, '/' ) . '/wp-admin/admin.php?page=zoho-recruit-auth-callback'
        : 'https://origin-recruitment.com/wp-admin/admin.php?page=zoho-recruit-auth-callback' );
} else {
    define( 'ZOHO_RECRUIT_REDIRECT_URI', 'https://origin-recruitment.com/wp-admin/admin.php?page=zoho-recruit-auth-callback' );
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

class ORU_Post_Types {
    public function __construct() {
        add_action( 'init', [ $this, 'register_post_types' ] );
    }

    public function register_post_types() {
        register_post_type( 'job', [
            'labels' => [
                'name' => __( 'Jobs' ),
                'singular_name' => __( 'Job' ),
                'menu_name' => __( 'Jobs', 'Admin Menu text', 'textdomain' ),
                'name_admin_bar' => __( 'Job', 'Add New on Toolbar', 'textdomain' ),
                'add_new' => __( 'Add New', 'textdomain' ),
                'add_new_item' => __( 'Add Job', 'textdomain' ),
                'new_item' => __( 'New Job', 'textdomain' ),
                'edit_item' => __( 'Edit Job', 'textdomain' ),
                'view_item' => __( 'View Job', 'textdomain' ),
                'all_items' => __( 'All Jobs', 'textdomain' ),
                'search_items' => __( 'Search Jobs', 'textdomain' ),
                'parent_item_colon' => __( 'Parent Jobs:', 'textdomain' ),
                'not_found' => __( 'No jobs found.', 'textdomain' ),
                'not_found_in_trash' => __( 'No jobs found in Trash.', 'textdomain' ),
                'featured_image' => _x( 'Job Cover Image', 'Overrides the “Featured Image” phrase for this post type. Added in 4.3', 'textdomain' ),
                'set_featured_image' => _x( 'Set cover image', 'Overrides the “Set featured image” phrase for this post type. Added in 4.3', 'textdomain' ),
                'remove_featured_image' => _x( 'Remove cover image', 'Overrides the “Remove featured image” phrase for this post type. Added in 4.3', 'textdomain' ),
                'use_featured_image' => _x( 'Use as cover image', 'Overrides the “Use as featured image” phrase for this post type. Added in 4.3', 'textdomain' ),
                'archives' => _x( 'Job archives', 'The post type archive label used in nav menus. Default “Post Archives”. Added in 4.4', 'textdomain' ),
                'insert_into_item' => _x( 'Insert into job', 'Overrides the “Insert into post”/”Insert into page” phrase (used when inserting media into a post). Added in 4.4', 'textdomain' ),
                'uploaded_to_this_item' => _x( 'Uploaded to this job', 'Overrides the “Uploaded to this post”/”Uploaded to this page” phrase (used when viewing media attached to a post). Added in 4.4', 'textdomain' ),
                'filter_items_list' => _x( 'Filter jobs list', 'Screen reader text for the filter links heading on the post type listing screen. Default “Filter posts list”/”Filter pages list”. Added in 4.4', 'textdomain' ),
                'items_list_navigation' => _x( 'Jobs list navigation', 'Screen reader text for the pagination heading on the post type listing screen. Default “Posts list navigation”/”Pages list navigation”. Added in 4.4', 'textdomain' ),
                'items_list' => _x( 'Jobs list', 'Screen reader text for the items list heading on the post type listing screen. Default “Posts list”/”Pages list”. Added in 4.4', 'textdomain' ),
            ],
            'description' => 'A WordPress custom post type for jobs.',
            'public' => true,
            'publicly_queryable' => true,
            'show_ui' => true,
            'supports' => [ 'title', 'editor', 'excerpt', 'custom-fields' ],
            'show_in_rest' => true,
            'hierarchical' => false,
            'rewrite' => [ 'slug' => 'jobs', 'with_front' => false ],
            'capability_type' => 'post',
            'taxonomies' => [ 'country' ],
            'has_archive' => false,
            'menu_icon' => 'dashicons-businessman',
        ] );
    }
}

class ORU_Taxonomies {
    public function __construct() {
        add_action( 'init', [ $this, 'register_taxonomies' ] );
    }

    public function register_taxonomies() {
        $taxonomies = [
            'language' => [
                'labels' => [
                    'name' => _x( 'Languages', 'taxonomy general name' ),
                    'singular_name' => _x( 'Language', 'taxonomy singular name' ),
                    'search_items' => __( 'Search Languages' ),
                    'all_items' => __( 'All Languages' ),
                    'parent_item' => __( 'Parent Language' ),
                    'parent_item_colon' => __( 'Parent Language:' ),
                    'edit_item' => __( 'Edit Language' ),
                    'update_item' => __( 'Update Language' ),
                    'add_new_item' => __( 'Add New Language' ),
                    'new_item_name' => __( 'New Language Name' ),
                    'menu_name' => __( 'Languages' ),
                ],
                'slug' => 'languages',
                'meta_box_cb' => 'post_tags_meta_box',
            ],
            'country' => [
                'labels' => [
                    'name' => _x( 'Countries', 'taxonomy general name' ),
                    'singular_name' => _x( 'Country', 'taxonomy singular name' ),
                    'search_items' => __( 'Search Countries' ),
                    'all_items' => __( 'All Countries' ),
                    'parent_item' => __( 'Parent Country' ),
                    'parent_item_colon' => __( 'Parent Country:' ),
                    'edit_item' => __( 'Edit Country' ),
                    'update_item' => __( 'Update Country' ),
                    'add_new_item' => __( 'Add New Country' ),
                    'new_item_name' => __( 'New Country Name' ),
                    'menu_name' => __( 'Countries' ),
                ],
                'slug' => 'locations',
                'meta_box_cb' => 'post_tags_meta_box',
            ],
            'industry' => [
                'labels' => [
                    'name' => _x( 'Industries', 'taxonomy general name' ),
                    'singular_name' => _x( 'Industry', 'taxonomy singular name' ),
                    'search_items' => __( 'Search Industries' ),
                    'all_items' => __( 'All Industries' ),
                    'parent_item' => __( 'Parent Industry' ),
                    'parent_item_colon' => __( 'Parent Industry:' ),
                    'edit_item' => __( 'Edit Industry' ),
                    'update_item' => __( 'Update Industry' ),
                    'add_new_item' => __( 'Add New Industry' ),
                    'new_item_name' => __( 'New Industry Name' ),
                    'menu_name' => __( 'Industries' ),
                ],
                'slug' => 'industries',
                'meta_box_cb' => 'post_categories_meta_box',
            ],
            'sector' => [
                'labels' => [
                    'name' => _x( 'Sectors', 'taxonomy general name' ),
                    'singular_name' => _x( 'Sector', 'taxonomy singular name' ),
                    'search_items' => __( 'Search Sectors' ),
                    'all_items' => __( 'All Sectors' ),
                    'parent_item' => __( 'Parent Sector' ),
                    'parent_item_colon' => __( 'Parent Sector:' ),
                    'edit_item' => __( 'Edit Sector' ),
                    'update_item' => __( 'Update Sector' ),
                    'add_new_item' => __( 'Add New Sector' ),
                    'new_item_name' => __( 'New Sector Name' ),
                    'menu_name' => __( 'Sectors' ),
                ],
                'slug' => 'sectors',
                'meta_box_cb' => 'post_categories_meta_box',
            ],
        ];

        foreach ( $taxonomies as $taxonomy => $args ) {
            register_taxonomy( $taxonomy, [ 'post', 'page', 'job' ], [
                'labels' => $args['labels'],
                'hierarchical' => false,
                'show_ui' => true,
                'show_in_menu' => true,
                'show_in_rest' => true,
                'show_in_nav_menus' => true,
                'meta_box_cb' => $args['meta_box_cb'],
                'show_admin_column' => true,
                'query_var' => true,
                'rewrite' => [ 'slug' => $args['slug'], 'with_front' => false, 'hierarchical' => false ],
            ] );
        }
    }
}

class ORU_Sanitization {
    public function __construct() {
        add_action( 'admin_init', [ $this, 'test_sanitization' ] );
    }

    public function sanitize_job_description( $content ) {
        $content = html_entity_decode( $content, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
        $content = str_replace( [ "\xc2\xa0", " " ], ' ', $content );
        error_log( 'Zoho Sanitization Input: ' . substr( $content, 0, 1000 ) );
        $allowed_tags = [
            'p' => [ 'class' => [], 'id' => [] ],
            'h1' => [ 'class' => [], 'id' => [] ],
            'h2' => [ 'class' => [], 'id' => [] ],
            'h3' => [ 'class' => [], 'id' => [] ],
            'h4' => [ 'class' => [], 'id' => [] ],
            'h5' => [ 'class' => [], 'id' => [] ],
            'h6' => [ 'class' => [], 'id' => [] ],
            'ul' => [ 'class' => [], 'id' => [] ],
            'ol' => [ 'class' => [], 'id' => [] ],
            'li' => [ 'class' => [], 'id' => [] ],
        ];
        $sanitized = wp_kses( $content, $allowed_tags );
        error_log( 'Zoho Sanitization Output: ' . substr( $sanitized, 0, 1000 ) );
        return $sanitized;
    }

    public function test_sanitization() {
        if ( current_user_can( 'manage_options' ) && isset( $_GET['zoho_test_sanitization'] ) ) {
            $test_html = '<h2 style="color: red;">Test Heading</h2><p class="intro" style="font-size: 16px;">Test paragraph with <strong>bold</strong> and <em>italic</em>.</p><ul><li>Item 1</li><li>Item 2</li></ul><table><tr><td>Cell</td></tr></table><div>Invalid div</div><p>Test' . "\xc2\xa0" . 'paragraph</p>';
            $sanitized = $this->sanitize_job_description( $test_html );
            add_action( 'admin_notices', function() use ( $test_html, $sanitized ) {
                echo '<div class="notice notice-info"><p><strong>Sanitization Test Input:</strong> ' . esc_html( substr( $test_html, 0, 500 ) ) . '</p><p><strong>Output:</strong> ' . esc_html( substr( $sanitized, 0, 500 ) ) . '</p></div>';
            });
            error_log( 'Zoho Sanitization Test Input: ' . $test_html );
            error_log( 'Zoho Sanitization Test Output: ' . $sanitized );
        }
    }
}

class ORU_Zoho_Auth {
    public function get_credentials() {
        $settings = get_option( 'zoho_recruit_settings', [] );
        $current_host = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? 'origin-recruitment:8890';
        $redirect_uri = ( defined( 'WP_LOCAL_DEV' ) && WP_LOCAL_DEV && $current_host !== 'origin-recruitment:8890' )
            ? rtrim( ( isset( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http' ) . '://' . $current_host, '/' ) . '/wp-admin/admin.php?page=zoho-recruit-auth-callback'
            : ( isset( $settings['redirect_uri'] ) && !empty( $settings['redirect_uri'] ) ? $settings['redirect_uri'] : ZOHO_RECRUIT_REDIRECT_URI );
        error_log( 'Zoho Redirect URI: ' . $redirect_uri );
        return [
            'client_id' => $settings['client_id'] ?? '',
            'client_secret' => $settings['client_secret'] ?? '',
            'redirect_uri' => $redirect_uri,
        ];
    }

    public function generate_auth_url() {
        $credentials = $this->get_credentials();
        if ( empty( $credentials['client_id'] ) || empty( $credentials['client_secret'] ) ) {
            error_log( 'Zoho Auth URL Error: Missing Client ID or Client Secret' );
            return new WP_Error( 'missing_credentials', 'Please enter Client ID and Client Secret in the settings.' );
        }
        $scopes = [ 'ZohoRecruit.modules.ALL' ];
        $query_params = [
            'client_id' => rawurlencode( $credentials['client_id'] ),
            'redirect_uri' => rawurlencode( $credentials['redirect_uri'] ),
            'response_type' => 'code',
            'scope' => implode( ',', $scopes ),
            'access_type' => 'offline',
            'prompt' => 'consent',
        ];
        $query_string = http_build_query( $query_params );
        $auth_url = 'https://accounts.zoho.eu/oauth/v2/auth?' . $query_string;
        error_log( 'Zoho Auth URL: ' . $auth_url );
        return $auth_url;
    }

    public function exchange_code_for_tokens( $auth_code ) {
        $credentials = $this->get_credentials();
        if ( empty( $credentials['client_id'] ) || empty( $credentials['client_secret'] ) || empty( $credentials['redirect_uri'] ) ) {
            error_log( 'Zoho Token Request Error: Missing credentials or redirect URI' );
            return new WP_Error( 'missing_credentials', 'Missing Client ID, Client Secret, or Redirect URI.' );
        }
        error_log( 'Zoho Token Request: Code=' . $auth_code . ', Redirect URI=' . $credentials['redirect_uri'] );
        $response = wp_remote_post( 'https://accounts.zoho.eu/oauth/v2/token', [
            'body' => [
                'client_id' => $credentials['client_id'],
                'client_secret' => $credentials['client_secret'],
                'redirect_uri' => $credentials['redirect_uri'],
                'code' => $auth_code,
                'grant_type' => 'authorization_code',
            ],
            'timeout' => 30,
        ]);
        if ( is_wp_error( $response ) ) {
            error_log( 'Zoho Token Request Error: ' . $response->get_error_message() );
            return $response;
        }
        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        error_log( 'Zoho Token Response: ' . print_r( $body, true ) );
        if ( isset( $body['access_token'] ) ) {
            update_option( 'zoho_recruit_access_token', $body['access_token'] );
            update_option( 'zoho_recruit_refresh_token', $body['refresh_token'] ?? get_option( 'zoho_recruit_refresh_token', '' ) );
            update_option( 'zoho_recruit_token_expires', time() + ( $body['expires_in'] ?? 3600 ) );
            return $body;
        }
        $error_message = $body['error'] ?? 'Failed to obtain tokens.';
        error_log( 'Zoho Token Error: ' . $error_message );
        return new WP_Error( 'token_error', $error_message );
    }

    public function refresh_token() {
        $refresh_token = get_option( 'zoho_recruit_refresh_token', '' );
        $credentials = $this->get_credentials();
        if ( empty( $refresh_token ) || empty( $credentials['client_id'] ) || empty( $credentials['client_secret'] ) ) {
            error_log( 'Zoho Token Refresh Error: Missing refresh token or credentials' );
            return new WP_Error( 'missing_credentials', 'Missing refresh token or credentials.' );
        }
        error_log( 'Zoho Token Refresh Attempt' );
        $response = wp_remote_post( 'https://accounts.zoho.eu/oauth/v2/token', [
            'body' => [
                'refresh_token' => $refresh_token,
                'client_id' => $credentials['client_id'],
                'client_secret' => $credentials['client_secret'],
                'grant_type' => 'refresh_token',
            ],
            'timeout' => 30,
        ]);
        if ( is_wp_error( $response ) ) {
            error_log( 'Zoho Token Refresh Error: ' . $response->get_error_message() );
            return $response;
        }
        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        error_log( 'Zoho Token Refresh Response: ' . print_r( $body, true ) );
        if ( isset( $body['access_token'] ) ) {
            update_option( 'zoho_recruit_access_token', $body['access_token'] );
            update_option( 'zoho_recruit_refresh_token', $body['refresh_token'] ?? $refresh_token );
            update_option( 'zoho_recruit_token_expires', time() + ( $body['expires_in'] ?? 3600 ) );
            error_log( 'Zoho Token Refresh Success' );
            return $body;
        }
        $error_message = $body['error'] ?? 'Failed to refresh token.';
        error_log( 'Zoho Token Refresh Error: ' . $error_message );
        return new WP_Error( 'refresh_error', $error_message );
    }

    public function get_access_token() {
        $access_token = get_option( 'zoho_recruit_access_token', '' );
        $token_expires = get_option( 'zoho_recruit_token_expires', 0 );
        if ( empty( $access_token ) || $token_expires <= time() + 300 ) {
            $refresh_result = $this->refresh_token();
            if ( is_wp_error( $refresh_result ) ) {
                return $refresh_result;
            }
            $access_token = get_option( 'zoho_recruit_access_token', '' );
        }
        if ( empty( $access_token ) ) {
            error_log( 'Zoho API Error: Invalid or expired access token' );
            return new WP_Error( 'invalid_token', 'Access token is invalid or expired. Please reauthorize.' );
        }
        return $access_token;
    }
}

class ORU_Zoho_API {
    private $zoho_auth;

    public function __construct( ORU_Zoho_Auth $zoho_auth ) {
        $this->zoho_auth = $zoho_auth;
    }

    public function get_access_token() {
        return $this->zoho_auth->get_access_token();
    }

    public function test_api() {
        $access_token = $this->get_access_token();
        if ( is_wp_error( $access_token ) ) {
            return $access_token;
        }
        $api_base_url = 'https://recruit.zoho.eu/recruit/v2/';
        $endpoint = $api_base_url . 'Job_Openings';
        error_log( 'Zoho API Test Request: ' . $endpoint );
        $response = wp_remote_get( $endpoint, [
            'headers' => [
                'Authorization' => 'Zoho-oauthtoken ' . $access_token,
            ],
            'timeout' => 30,
        ]);
        if ( is_wp_error( $response ) ) {
            error_log( 'Zoho API Test Error: ' . $response->get_error_message() );
            return $response;
        }
        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        error_log( 'Zoho API Test Response: ' . print_r( $body, true ) );
        if ( isset( $body['data'] ) && !empty( $body['data'] ) ) {
            error_log( 'Zoho API Test Sample Job: ' . print_r( $body['data'][0], true ) );
            $result = [
                'count' => count( $body['data'] ),
                'sample_job' => [],
            ];
            if ( !empty( $body['data'][0] ) ) {
                foreach ( $body['data'][0] as $key => $value ) {
                    if ( is_scalar( $value ) || ( is_array( $value ) && array_walk_recursive( $value, function( $v ) { return is_scalar( $v ); } ) ) ) {
                        $result['sample_job'][$key] = $value;
                    }
                }
                $result['sample_job']['Job_Opening_Name'] = $body['data'][0]['Job_Opening_Name'] ?? 'Unknown';
            }
            return $result;
        } elseif ( isset( $body['error'] ) ) {
            $error_message = $body['error']['message'] ?? 'Unknown API error';
            error_log( 'Zoho API Test Error: ' . $error_message );
            return new WP_Error( 'api_error', $error_message );
        } else {
            error_log( 'Zoho API Test Error: No job openings found or unexpected response' );
            return new WP_Error( 'no_data', 'No job openings found or unexpected API response.' );
        }
    }

    public function get_job_by_id( $zoho_id, $access_token ) {
        $api_base_url = 'https://recruit.zoho.eu/recruit/v2/';
        $endpoint = $api_base_url . 'Job_Openings/' . $zoho_id;
        error_log( 'Zoho GetRecordsByID Request: ' . $endpoint );
        $response = wp_remote_get( $endpoint, [
            'headers' => [
                'Authorization' => 'Zoho-oauthtoken ' . $access_token,
            ],
            'timeout' => 30,
        ]);
        if ( is_wp_error( $response ) ) {
            error_log( 'Zoho GetRecordsByID Error for Zoho ID ' . $zoho_id . ': ' . $response->get_error_message() );
            return $response;
        }
        $response_code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        error_log( 'Zoho GetRecordsByID Response for Zoho ID ' . $zoho_id . ': Code=' . $response_code . ', Body=' . print_r( $body, true ) );
        error_log( 'Zoho GetRecordsByID Job_Description for Zoho ID ' . $zoho_id . ': ' . ( isset( $body['data'][0]['Job_Description'] ) ? substr( $body['data'][0]['Job_Description'], 0, 1000 ) : 'Not found' ) );
        if ( $response_code !== 200 ) {
            $error_message = $body['message'] ?? 'HTTP ' . $response_code;
            error_log( 'Zoho GetRecordsByID Error for Zoho ID ' . $zoho_id . ': ' . $error_message );
            return new WP_Error( 'api_error', $error_message );
        }
        if ( isset( $body['data'][0] ) ) {
            return $body['data'][0];
        } elseif ( isset( $body['error'] ) ) {
            $error_message = $body['error']['message'] ?? 'Unknown API error';
            error_log( 'Zoho GetRecordsByID Error for Zoho ID ' . $zoho_id . ': ' . $error_message );
            return new WP_Error( 'api_error', $error_message );
        } else {
            error_log( 'Zoho GetRecordsByID Error for Zoho ID ' . $zoho_id . ': No data found' );
            return new WP_Error( 'no_data', 'No job data found for ID ' . $zoho_id );
        }
    }
}

class ORU_Zoho_Sync {
    private $zoho_api;
    private $sanitization;

    public function __construct( ORU_Zoho_API $zoho_api, ORU_Sanitization $sanitization ) {
        $this->zoho_api = $zoho_api;
        $this->sanitization = $sanitization;
    }

    public function sync_jobs() {
        if ( ! function_exists( 'acf_update_field' ) ) {
            error_log( 'Zoho Sync Error: ACF plugin not active' );
            add_action( 'admin_notices', function() {
                echo '<div class="notice notice-error"><p>Zoho Sync Error: Advanced Custom Fields plugin is required.</p></div>';
            });
            return new WP_Error( 'acf_missing', 'Advanced Custom Fields plugin is required.' );
        }
        $access_token = $this->zoho_api->get_access_token();
        if ( is_wp_error( $access_token ) ) {
            add_action( 'admin_notices', function() use ( $access_token ) {
                echo '<div class="notice notice-error"><p>Zoho Sync Error: ' . esc_html( $access_token->get_error_message() ) . '</p></div>';
            });
            return $access_token;
        }
        $api_base_url = 'https://recruit.zoho.eu/recruit/v2/';
        $endpoint = $api_base_url . 'Job_Openings';
        error_log( 'Zoho Sync Request: ' . $endpoint );
        $response = wp_remote_get( $endpoint, [
            'headers' => [
                'Authorization' => 'Zoho-oauthtoken ' . $access_token,
            ],
            'timeout' => 30,
        ]);
        if ( is_wp_error( $response ) ) {
            error_log( 'Zoho Sync Error: ' . $response->get_error_message() );
            add_action( 'admin_notices', function() use ( $response ) {
                echo '<div class="notice notice-error"><p>Zoho Sync Error: ' . esc_html( $response->get_error_message() ) . '</p></div>';
            });
            return $response;
        }
        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        error_log( 'Zoho Sync Response: ' . print_r( $body, true ) );
        error_log( 'Zoho Sync Response Keys: ' . print_r( array_keys( $body['data'][0] ?? [] ), true ) );
        if ( ! isset( $body['data'] ) || empty( $body['data'] ) ) {
            error_log( 'Zoho Sync Error: No job openings found' );
            add_action( 'admin_notices', function() {
                echo '<div class="notice notice-error"><p>Zoho Sync Error: No job openings found in Zoho Recruit.</p></div>';
            });
            return new WP_Error( 'no_data', 'No job openings found in Zoho Recruit.' );
        }
        $synced_jobs = 0;
        $zoho_job_opening_ids = [];
        $log_post_content = function( $data, $postarr ) {
            if ( $data['post_type'] === 'job' ) {
                global $wp_filter;
                $filter_names = [];
                if ( isset( $wp_filter['wp_insert_post_data'] ) && is_object( $wp_filter['wp_insert_post_data'] ) && $wp_filter['wp_insert_post_data'] instanceof WP_Hook ) {
                    foreach ( $wp_filter['wp_insert_post_data']->callbacks as $priority => $hooks ) {
                        $filter_names = array_merge( $filter_names, array_keys( $hooks ) );
                    }
                }
                error_log( 'Zoho Sync Before Save Job ID ' . ( $postarr['ID'] ?? 'New' ) . ': Post Content: ' . substr( $data['post_content'], 0, 1000 ) );
                error_log( 'Zoho Sync Active wp_insert_post_data Filters: ' . implode( ', ', $filter_names ) );
                add_action( 'admin_notices', function() use ( $postarr, $data, $filter_names ) {
                    echo '<div class="notice notice-info"><p>Zoho Sync Before Save Job ID ' . esc_html( $postarr['ID'] ?? 'New' ) . ': Post Content: ' . esc_html( substr( $data['post_content'], 0, 500 ) ) . '</p><p>Active wp_insert_post_data Filters: ' . esc_html( implode( ', ', $filter_names ) ) . '</p></div>';
                });
            }
            return $data;
        };
        add_filter( 'wp_insert_post_data', $log_post_content, 9, 2 );
        foreach ( $body['data'] as $job ) {
            $zoho_job_opening_id = $job['Job_Opening_ID'] ?? '';
            $zoho_id = $job['id'] ?? '';
            error_log( 'Zoho Sync Job Data: ' . print_r( $job, true ) );
            if ( empty( $zoho_id ) ) {
                error_log( 'Zoho Sync Error: Missing id for job ' . print_r( $job, true ) );
                add_action( 'admin_notices', function() use ( $zoho_job_opening_id, $zoho_id ) {
                    echo '<div class="notice notice-error"><p>Zoho Sync Error: Missing id (Job_Opening_ID: ' . esc_html( $zoho_job_opening_id ) . ').</p></div>';
                });
                continue;
            }
            if ( empty( $zoho_job_opening_id ) ) {
                $zoho_job_opening_id = $zoho_id;
                error_log( 'Zoho Sync Warning: Missing Job_Opening_ID, using id ' . $zoho_id . ' for matching' );
            }
            error_log( 'Zoho Sync Processing Job: Job_Opening_ID=' . $zoho_job_opening_id . ', id=' . $zoho_id );
            $zoho_job_opening_ids[] = $zoho_job_opening_id;
            $existing_posts = get_posts( [
                'post_type' => 'job',
                'meta_key' => 'job_opening_id',
                'meta_value' => $zoho_job_opening_id,
                'posts_per_page' => 1,
                'post_status' => 'any',
            ]);
            $modified_time = $job['Modified_Time'] ?? '';
            $zoho_modified = '';
            if ( $modified_time ) {
                try {
                    $date = DateTime::createFromFormat( 'Y-m-d\TH:i:sP', $modified_time );
                    if ( $date ) {
                        $date->setTimezone( new DateTimeZone( 'UTC' ) );
                        $zoho_modified = $date->format( 'Y-m-d H:i:s' );
                        error_log( 'Zoho Sync Job ID ' . $zoho_job_opening_id . ': Modified_Time parsed as ' . $zoho_modified . ' UTC' );
                    } else {
                        throw new Exception( 'Invalid Modified_Time format' );
                    }
                } catch ( Exception $e ) {
                    error_log( 'Zoho Sync Error: Failed to parse Modified_Time for Job ID ' . $zoho_job_opening_id . ': ' . $e->getMessage() );
                }
            }
            $needs_update = true;
            if ( ! empty( $existing_posts ) && $zoho_modified ) {
                $post_modified_gmt = $existing_posts[0]->post_modified_gmt;
                if ( $post_modified_gmt && strtotime( $post_modified_gmt ) >= strtotime( $zoho_modified ) ) {
                    $needs_update = false;
                    error_log( 'Zoho Sync Job ID ' . $zoho_job_opening_id . ': No update needed (post_modified_gmt: ' . $post_modified_gmt . ' >= zoho_modified: ' . $zoho_modified . ')' );
                    $synced_jobs++;
                    continue;
                }
            }
            if ( $needs_update || empty( $existing_posts ) ) {
                $job_details = $this->zoho_api->get_job_by_id( $zoho_id, $access_token );
                if ( is_wp_error( $job_details ) || ! isset( $job_details['Job_Description'] ) ) {
                    $error_message = is_wp_error( $job_details ) ? $job_details->get_error_message() : 'Job_Description not found';
                    error_log( 'Zoho Sync Job ID ' . $zoho_job_opening_id . ': Failed to fetch HTML Job_Description: ' . $error_message );
                    add_action( 'admin_notices', function() use ( $zoho_job_opening_id, $zoho_id, $error_message ) {
                        echo '<div class="notice notice-error"><p>Zoho Sync Error for Job ID ' . esc_html( $zoho_job_opening_id ) . ' (Zoho id ' . esc_html( $zoho_id ) . '): Failed to fetch HTML Job_Description: ' . esc_html( $error_message ) . '</p></div>';
                    });
                    continue;
                }
                $job_description = $job_details['Job_Description'];
                error_log( 'Zoho Sync Job ID ' . $zoho_job_opening_id . ': Fetched HTML Job_Description from GetRecordsByID using Zoho id ' . $zoho_id );
                error_log( 'Zoho Sync Job ID ' . $zoho_job_opening_id . ': Raw Job Description: ' . substr( $job_description, 0, 1000 ) );
                $sanitized_description = $this->sanitization->sanitize_job_description( $job_description );
                error_log( 'Zoho Sync Job ID ' . $zoho_job_opening_id . ': Sanitized Description Length: ' . strlen( $sanitized_description ) . ', Content: ' . substr( $sanitized_description, 0, 1000 ) );
                $excerpt = wp_strip_all_tags( $sanitized_description );
                $excerpt = strlen( $excerpt ) > 160 ? substr( $excerpt, 0, 157 ) . '...' : $excerpt;
                add_action( 'admin_notices', function() use ( $zoho_job_opening_id, $zoho_id, $job_description, $sanitized_description ) {
                    echo '<div class="notice notice-info">';
                    echo '<p><strong>Zoho Job ID ' . esc_html( $zoho_job_opening_id ) . ' (Zoho id ' . esc_html( $zoho_id ) . ') Debug:</strong></p>';
                    echo '<p>Update Needed: Yes</p>';
                    echo '<p>Raw Description: ' . esc_html( substr( $job_description, 0, 500 ) ) . '</p>';
                    echo '<p>Sanitized Description: ' . esc_html( substr( $sanitized_description, 0, 500 ) ) . '</p>';
                    echo '</div>';
                });
                $post_date = '';
                $post_date_gmt = '';
                if ( isset( $job['Date_Opened'] ) && ! empty( $job['Date_Opened'] ) ) {
                    try {
                        $date = DateTime::createFromFormat( 'Y-m-d', $job['Date_Opened'] );
                        if ( $date ) {
                            $date->setTime( 0, 0, 0 );
                            $date->setTimezone( new DateTimeZone( 'UTC' ) );
                            $post_date_gmt = $date->format( 'Y-m-d H:i:s' );
                            $post_date = get_date_from_gmt( $post_date_gmt, 'Y-m-d H:i:s' );
                            error_log( 'Zoho Sync Job ID ' . $zoho_job_opening_id . ': Date_Opened parsed as ' . $post_date_gmt . ' UTC' );
                        } else {
                            throw new Exception( 'Invalid Date_Opened format' );
                        }
                    } catch ( Exception $e ) {
                        error_log( 'Zoho Sync Error: Failed to parse Date_Opened for Job ID ' . $zoho_job_opening_id . ': ' . $e->getMessage() );
                    }
                } elseif ( isset( $job['Created_Time'] ) && ! empty( $job['Created_Time'] ) ) {
                    try {
                        $date = DateTime::createFromFormat( 'Y-m-d\TH:i:sP', $job['Created_Time'] );
                        if ( $date ) {
                            $date->setTimezone( new DateTimeZone( 'UTC' ) );
                            $post_date_gmt = $date->format( 'Y-m-d H:i:s' );
                            $post_date = get_date_from_gmt( $post_date_gmt, 'Y-m-d H:i:s' );
                            error_log( 'Zoho Sync Job ID ' . $zoho_job_opening_id . ': Created_Time parsed as ' . $post_date_gmt . ' UTC' );
                        } else {
                            throw new Exception( 'Invalid Created_Time format' );
                        }
                    } catch ( Exception $e ) {
                        error_log( 'Zoho Sync Error: Failed to parse Created_Time for Job ID ' . $zoho_job_opening_id . ': ' . $e->getMessage() );
                    }
                }
                $post_args = [
                    'post_title' => sanitize_text_field( $job['Job_Opening_Name'] ?? 'Untitled Job' ),
                    'post_content' => wp_slash( $sanitized_description ),
                    'post_excerpt' => sanitize_text_field( $excerpt ),
                    'post_type' => 'job',
                    'post_status' => 'publish',
                ];
                if ( $post_date && $post_date_gmt ) {
                    $post_args['post_date'] = $post_date;
                    $post_args['post_date_gmt'] = $post_date_gmt;
                }
                if ( $zoho_modified ) {
                    $post_args['post_modified_gmt'] = $zoho_modified;
                    $post_args['post_modified'] = get_date_from_gmt( $zoho_modified, 'Y-m-d H:i:s' );
                }
                $filters = [];
                $filters['wp_filter_post_kses'] = has_filter( 'wp_insert_post_data', 'wp_filter_post_kses' );
                $filters['content_save_pre'] = has_filter( 'content_save_pre', 'wp_filter_post_kses' );
                $filters['content_filtered_save_pre'] = has_filter( 'content_filtered_save_pre', 'wp_filter_kses_deep' );
                if ( $filters['wp_filter_post_kses'] !== false ) {
                    remove_filter( 'wp_insert_post_data', 'wp_filter_post_kses', $filters['wp_filter_post_kses'] );
                }
                if ( $filters['content_save_pre'] !== false ) {
                    remove_filter( 'content_save_pre', 'wp_filter_post_kses', $filters['content_save_pre'] );
                }
                if ( $filters['content_filtered_save_pre'] !== false ) {
                    remove_filter( 'content_filtered_save_pre', 'wp_filter_kses_deep', $filters['content_filtered_save_pre'] );
                }
                $is_update = ! empty( $existing_posts );
                if ( $is_update ) {
                    $post_args['ID'] = $existing_posts[0]->ID;
                    $post_id = wp_update_post( $post_args, true );
                    error_log( 'Zoho Sync Updated Post ID: ' . $post_args['ID'] . ' for Zoho Job ID: ' . $zoho_job_opening_id );
                } else {
                    $post_id = wp_insert_post( $post_args, true );
                    error_log( 'Zoho Sync Created Post ID: ' . $post_id . ' for Zoho Job ID: ' . $zoho_job_opening_id );
                }
                if ( $filters['wp_filter_post_kses'] !== false ) {
                    add_filter( 'wp_insert_post_data', 'wp_filter_post_kses', $filters['wp_filter_post_kses'] );
                }
                if ( $filters['content_save_pre'] !== false ) {
                    add_filter( 'content_save_pre', 'wp_filter_post_kses', $filters['content_save_pre'] );
                }
                if ( $filters['content_filtered_save_pre'] !== false ) {
                    add_filter( 'content_filtered_save_pre', 'wp_filter_kses_deep', $filters['content_filtered_save_pre'] );
                }
                if ( is_wp_error( $post_id ) ) {
                    error_log( 'Zoho Sync Error: Failed to save post for Zoho ID ' . $zoho_job_opening_id . ': ' . $post_id->get_error_message() );
                    add_action( 'admin_notices', function() use ( $zoho_job_opening_id, $zoho_id, $post_id ) {
                        echo '<div class="notice notice-error"><p>Zoho Sync Error for Job ID ' . esc_html( $zoho_job_opening_id ) . ' (Zoho id ' . esc_html( $zoho_id ) . '): ' . esc_html( $post_id->get_error_message() ) . '</p></div>';
                    });
                    continue;
                }
                update_post_meta( $post_id, '_zoho_raw_description', $job_description );
                update_post_meta( $post_id, '_zoho_sanitized_description', $sanitized_description );
                $stored_post = get_post( $post_id );
                error_log( 'Zoho Sync Job ID ' . $zoho_job_opening_id . ': Stored Post Content: ' . substr( $stored_post->post_content, 0, 1000 ) );
                add_action( 'admin_notices', function() use ( $zoho_job_opening_id, $zoho_id, $stored_post ) {
                    echo '<div class="notice notice-info"><p>Zoho Job ID ' . esc_html( $zoho_job_opening_id ) . ' (Zoho id ' . esc_html( $zoho_id ) . ') Stored Post Content: ' . esc_html( substr( $stored_post->post_content, 0, 500 ) ) . '</p></div>';
                });
                $acf_mappings = [
                    'ID' => [ 'field' => 'id', 'type' => 'text' ],
                    'job_opening_id' => [ 'field' => 'Job_Opening_ID', 'type' => 'text' ],
                    'job_opening_status' => [ 'field' => 'Job_Opening_Status', 'type' => 'text' ],
                    'state' => [ 'field' => 'State', 'type' => 'text' ],
                    'city' => [ 'field' => 'City', 'type' => 'text' ],
                    'job_type' => [ 'field' => 'Job_Type', 'type' => 'text' ],
                    'salary' => [ 'field' => 'Salary', 'type' => 'text' ],
                    'date_opened' => [ 'field' => 'Date_Opened', 'type' => 'date' ],
                    'target_date' => [ 'field' => 'Target_Date', 'type' => 'date' ],
                    'number_of_positions' => [ 'field' => 'Number_of_Positions', 'type' => 'number' ],
                    'no_of_candidates_associated' => [ 'field' => 'No_of_Candidates_Associated', 'type' => 'number' ],
                    'no_of_candidates_hired' => [ 'field' => 'No_of_Candidates_Hired', 'type' => 'number' ],
                    'work_experience' => [ 'field' => 'Work_Experience', 'type' => 'text' ],
                ];
                foreach ( $acf_mappings as $acf_name => $mapping ) {
                    $value = $job[$mapping['field']] ?? '';
                    if ( $value !== '' ) {
                        if ( $mapping['type'] === 'date' ) {
                            try {
                                $date_format = ( $mapping['field'] === 'Date_Opened' ) ? 'Y-m-d' : 'Y-m-d\TH:i:sP';
                                $date = DateTime::createFromFormat( $date_format, $value );
                                if ( $date ) {
                                    $date->setTimezone( new DateTimeZone( 'UTC' ) );
                                    $value = $date->format( 'Ymd' );
                                } else {
                                    throw new Exception( 'Invalid date format' );
                                }
                            } catch ( Exception $e ) {
                                $value = '';
                                error_log( 'Zoho Sync Error: Failed to parse date field ' . $mapping['field'] . ' for Job ID ' . $zoho_job_opening_id . ': ' . $e->getMessage() );
                            }
                        } elseif ( $mapping['type'] === 'number' ) {
                            $value = (int)( $value ?? 0 );
                        } else {
                            $value = sanitize_text_field( $value );
                        }
                        update_field( $acf_name, $value, $post_id );
                        error_log( 'Zoho Sync Updated ACF Field ' . $acf_name . ' for Post ID ' . $post_id );
                    } else {
                        update_field( $acf_name, '', $post_id );
                    }
                }
                $remote_value = false;
                if ( isset( $job['Remote_Job'] ) ) {
                    $remote = $job['Remote_Job'];
                    $remote_value = in_array( $remote, [ true, 'true', '1', 1 ], true ) ? true : false;
                }
                update_field( 'remote_job', $remote_value, $post_id );
                error_log( 'Zoho Sync Updated ACF Field remote_job for Post ID ' . $post_id );
                $taxonomy_mappings = [
                    'languages' => [ 'field' => 'Languages', 'taxonomy' => 'language' ],
                    'industry' => [ 'field' => 'Industry', 'taxonomy' => 'industry' ],
                    'sectors' => [ 'field' => 'Sectors', 'taxonomy' => 'sector' ],
                    'country' => [ 'field' => 'Country', 'taxonomy' => 'country' ],
                ];
                foreach ( $taxonomy_mappings as $acf_name => $mapping ) {
                    $taxonomy = $mapping['taxonomy'];
                    $zoho_field = $mapping['field'];
                    if ( ! isset( $job[$zoho_field] ) || empty( $job[$zoho_field] ) ) {
                        wp_set_post_terms( $post_id, [], $taxonomy, false );
                        error_log( 'Zoho Sync Cleared terms for taxonomy ' . $taxonomy . ' for Post ID ' . $post_id );
                        continue;
                    }
                    $terms = is_array( $job[$zoho_field] ) ? $job[$zoho_field] : [ $job[$zoho_field] ];
                    $term_ids = [];
                    foreach ( $terms as $term_name ) {
                        if ( empty( $term_name ) ) {
                            continue;
                        }
                        $term_name = sanitize_text_field( trim( $term_name ) );
                        $existing_term = term_exists( $term_name, $taxonomy );
                        if ( $existing_term !== 0 && $existing_term !== null ) {
                            $term_ids[] = (int)$existing_term['term_id'];
                        } else {
                            $new_term = wp_insert_term( $term_name, $taxonomy );
                            if ( ! is_wp_error( $new_term ) ) {
                                $term_ids[] = (int)$new_term['term_id'];
                                error_log( 'Zoho Sync Created term ' . $term_name . ' for taxonomy ' . $taxonomy );
                            } else {
                                error_log( 'Zoho Sync Error: Failed to create term ' . $term_name . ' for taxonomy ' . $taxonomy . ': ' . $new_term->get_error_message() );
                            }
                        }
                    }
                    if ( ! empty( $term_ids ) ) {
                        $result = wp_set_post_terms( $post_id, $term_ids, $taxonomy, false );
                        if ( is_wp_error( $result ) ) {
                            error_log( 'Zoho Sync Error: Failed to set terms for taxonomy ' . $taxonomy . ' for Post ID ' . $post_id . ': ' . $result->get_error_message() );
                        } else {
                            error_log( 'Zoho Sync Set terms for taxonomy ' . $taxonomy . ' for Post ID ' . $post_id );
                        }
                    } else {
                        wp_set_post_terms( $post_id, [], $taxonomy, false );
                        error_log( 'Zoho Sync Cleared terms for taxonomy ' . $taxonomy . ' for Post ID ' . $post_id );
                    }
                }
                if ( function_exists( 'acf_reset_cache' ) ) {
                    acf_reset_cache( 'post-' . $post_id );
                    error_log( 'Zoho Sync Cleared ACF cache for Post ID ' . $post_id );
                }
                $synced_jobs++;
            }
        }
        remove_filter( 'wp_insert_post_data', $log_post_content, 9 );
        $args = [
            'post_type' => 'job',
            'posts_per_page' => -1,
            'post_status' => 'any',
            'meta_query' => [
                [
                    'key' => 'job_opening_id',
                    'value' => $zoho_job_opening_ids,
                    'compare' => 'NOT IN',
                ],
            ],
        ];
        $posts_to_delete = get_posts( $args );
        foreach ( $posts_to_delete as $post ) {
            wp_delete_post( $post->ID, true );
            error_log( 'Zoho Sync Deleted Post ID ' . $post->ID . ' (non-existent Zoho Job)' );
        }
        $message = sprintf( 'Successfully synchronized %d job openings.', $synced_jobs );
        error_log( $message );
        add_action( 'admin_notices', function() use ( $message ) {
            echo '<div class="notice notice-success"><p>' . esc_html( $message ) . '</p></div>';
        });
        return $message;
    }
}

class ORU_Admin_Settings {
    private $zoho_auth;
    private $zoho_api;
    private $zoho_sync;

    public function __construct( ORU_Zoho_Auth $zoho_auth, ORU_Zoho_API $zoho_api, ORU_Zoho_Sync $zoho_sync ) {
        $this->zoho_auth = $zoho_auth;
        $this->zoho_api = $zoho_api;
        $this->zoho_sync = $zoho_sync;
        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
        add_action( 'admin_init', [ $this, 'settings_init' ] );
    }

    public function add_admin_menu() {
        add_menu_page(
            'Zoho Recruit Settings',
            'Zoho Recruit',
            'manage_options',
            'zoho_recruit_settings',
            [ $this, 'settings_page' ],
            'dashicons-admin-generic',
            80
        );
        add_submenu_page(
            null,
            'Zoho Recruit Auth',
            'Zoho Recruit Auth',
            'manage_options',
            'zoho-recruit-auth-callback',
            [ $this, 'handle_callback' ]
        );
    }

    public function settings_init() {
        register_setting(
            'zoho_recruit_settings_group',
            'zoho_recruit_settings',
            [ $this, 'sanitize_settings' ]
        );
        add_settings_section(
            'zoho_recruit_main_section',
            'Zoho Recruit API Credentials',
            [ $this, 'section_callback' ],
            'zoho_recruit_settings'
        );
        add_settings_field(
            'zoho_recruit_client_id',
            'Client ID',
            [ $this, 'client_id_callback' ],
            'zoho_recruit_settings',
            'zoho_recruit_main_section'
        );
        add_settings_field(
            'zoho_recruit_client_secret',
            'Client Secret',
            [ $this, 'client_secret_callback' ],
            'zoho_recruit_settings',
            'zoho_recruit_main_section'
        );
        add_settings_field(
            'zoho_recruit_redirect_uri',
            'OAuth Redirect URI',
            [ $this, 'redirect_uri_callback' ],
            'zoho_recruit_settings',
            'zoho_recruit_main_section'
        );
    }

    public function sanitize_settings( $input ) {
        $sanitized_input = [];
        $sanitized_input['client_id'] = sanitize_text_field( $input['client_id'] ?? '' );
        $sanitized_input['client_secret'] = sanitize_text_field( $input['client_secret'] ?? '' );
        $sanitized_input['redirect_uri'] = esc_url_raw( $input['redirect_uri'] ?? ZOHO_RECRUIT_REDIRECT_URI );
        return $sanitized_input;
    }

    public function section_callback() {
        echo '<p>Enter your Zoho Recruit API credentials from the <a href="https://api-console.zoho.eu/" target="_blank">Zoho Developer Console</a>.</p>';
    }

    public function client_id_callback() {
        $settings = get_option( 'zoho_recruit_settings', [] );
        $client_id = $settings['client_id'] ?? '';
        echo '<input type="text" name="zoho_recruit_settings[client_id]" value="' . esc_attr( $client_id ) . '" class="regular-text" />';
    }

    public function client_secret_callback() {
        $settings = get_option( 'zoho_recruit_settings', [] );
        $client_secret = $settings['client_secret'] ?? '';
        echo '<input type="password" name="zoho_recruit_settings[client_secret]" value="' . esc_attr( $client_secret ) . '" class="regular-text" />';
    }

    public function redirect_uri_callback() {
        $settings = get_option( 'zoho_recruit_settings', [] );
        $redirect_uri = $settings['redirect_uri'] ?? ZOHO_RECRUIT_REDIRECT_URI;
        echo '<input type="text" name="zoho_recruit_settings[redirect_uri]" value="' . esc_attr( $redirect_uri ) . '" class="regular-text" />';
        echo '<p class="description">Enter the redirect URI registered in the Zoho Developer Console. Defaults to ' . esc_html( ZOHO_RECRUIT_REDIRECT_URI ) . '.</p>';
    }

    public function settings_page() {
        $auth_url = $this->zoho_auth->generate_auth_url();
        $access_token = get_option( 'zoho_recruit_access_token', '' );
        $token_expires = get_option( 'zoho_recruit_token_expires', 0 );
        $auth_status = $access_token && $token_expires > time() ? 'Authenticated' : 'Not Authenticated';
        $test_result = '';
        if ( isset( $_POST['zoho_recruit_test_api'] ) && check_admin_referer( 'zoho_recruit_test_api_nonce' ) ) {
            $test_result = $this->zoho_api->test_api();
        }
        $sync_result = '';
        if ( isset( $_POST['zoho_recruit_sync_jobs'] ) && check_admin_referer( 'zoho_recruit_sync_jobs_nonce' ) ) {
            $sync_result = $this->zoho_sync->sync_jobs();
        }
        ?>
        <div class="wrap">
            <h1>Zoho Recruit Integration Settings</h1>
            <p><strong>Authentication Status: </strong><?php echo esc_html( $auth_status ); ?></p>
            <?php if ( $access_token && $token_expires > time() ) : ?>
                <p>Access token valid until: <?php echo esc_html( gmdate( 'Y-m-d H:i:s', $token_expires ) ); ?> UTC</p>
            <?php endif; ?>
            <?php if ( $test_result ) : ?>
                <div class="notice <?php echo is_wp_error( $test_result ) ? 'notice-error' : 'notice-success'; ?>">
                    <p><strong>Test API Result:</strong></p>
                    <?php if ( is_wp_error( $test_result ) ) : ?>
                        <p><?php echo esc_html( $test_result->get_error_message() ); ?></p>
                    <?php elseif ( is_array( $test_result ) ) : ?>
                        <p>Retrieved <?php echo esc_html( $test_result['count'] ); ?> job openings.</p>
                        <?php if ( ! empty( $test_result['sample_job'] ) ) : ?>
                            <p><strong>Sample Job Details:</strong></p>
                            <ul>
                                <?php foreach ( $test_result['sample_job'] as $key => $value ) : ?>
                                    <li><?php echo esc_html( $key ); ?>: <?php echo esc_html( is_scalar( $value ) ? $value : json_encode( $value ) ); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else : ?>
                            <p>No job details available.</p>
                        <?php endif; ?>
                    <?php else : ?>
                        <p><?php echo esc_html( $test_result ); ?></p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <?php if ( $sync_result ) : ?>
                <div class="notice <?php echo is_wp_error( $sync_result ) ? 'notice-error' : 'notice-success'; ?>">
                    <p><strong>Job Sync Result:</strong> <?php echo esc_html( is_wp_error( $sync_result ) ? $sync_result->get_error_message() : $sync_result ); ?></p>
                </div>
            <?php endif; ?>
            <form method="post" action="options.php">
                <?php
                settings_fields( 'zoho_recruit_settings_group' );
                do_settings_sections( 'zoho_recruit_settings' );
                submit_button();
                ?>
            </form>
            <?php if ( ! is_wp_error( $auth_url ) ) : ?>
                <p><a href="<?php echo esc_url( $auth_url ); ?>" class="button button-primary">Authorize with Zoho</a></p>
            <?php endif; ?>
            <?php if ( $access_token ) : ?>
                <form method="post" action="">
                    <?php wp_nonce_field( 'zoho_recruit_test_api_nonce' ); ?>
                    <input type="hidden" name="zoho_recruit_test_api" value="1">
                    <p><button type="submit" class="button button-secondary">Test API Connection</button></p>
                </form>
                <form method="post" action="">
                    <?php wp_nonce_field( 'zoho_recruit_sync_jobs_nonce' ); ?>
                    <input type="hidden" name="zoho_recruit_sync_jobs" value="1">
                    <p><button type="submit" class="button button-secondary">Sync Jobs Now</button></p>
                </form>
                <p><a href="<?php echo esc_url( admin_url( 'admin.php?page=zoho_recruit_settings&zoho_test_sanitization=1' ) ); ?>" class="button button-secondary">Test Sanitization</a></p>
            <?php endif; ?>
        </div>
        <?php
    }

    public function handle_callback() {
        error_log( 'Zoho Callback Accessed: ' . print_r( $_GET, true ) );
        ?>
        <div class="wrap">
            <h1>Zoho Recruit Authentication</h1>
            <?php
            try {
                if ( isset( $_GET['code'] ) && isset( $_GET['location'] ) ) {
                    $auth_code = sanitize_text_field( $_GET['code'] );
                    $location = sanitize_text_field( $_GET['location'] );
                    error_log( 'Zoho Callback: Code=' . $auth_code . ', Location=' . $location );
                    update_option( 'zoho_recruit_api_location', $location );
                    $token_response = $this->zoho_auth->exchange_code_for_tokens( $auth_code );
                    if ( ! is_wp_error( $token_response ) ) {
                        echo '<div class="notice notice-success"><p>Authentication successful! Tokens stored.</p></div>';
                        echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=zoho_recruit_settings' ) ) . '" class="button button-primary">Return to Settings</a></p>';
                    } else {
                        echo '<div class="notice notice-error"><p>Error: ' . esc_html( $token_response->get_error_message() ) . '</p></div>';
                    }
                } elseif ( isset( $_GET['error'] ) ) {
                    $error_description = isset( $_GET['error_description'] ) ? sanitize_text_field( $_GET['error_description'] ) : 'No description provided';
                    error_log( 'Zoho OAuth Error: ' . sanitize_text_field( $_GET['error'] ) . ' - ' . $error_description );
                    echo '<div class="notice notice-error"><p>OAuth Error: ' . esc_html( sanitize_text_field( $_GET['error'] ) ) . '<br>Description: ' . esc_html( $error_description ) . '</p></div>';
                } else {
                    error_log( 'Zoho Callback Error: Missing code or location' );
                    echo '<div class="notice notice-error"><p>Error: No authorization code or location received.</p></div>';
                }
            } catch ( Exception $e ) {
                error_log( 'Zoho Callback Exception: ' . $e->getMessage() );
                echo '<div class="notice notice-error"><p>Unexpected error: ' . esc_html( $e->getMessage() ) . '</p></div>';
            }
            ?>
        </div>
        <?php
    }
}

// Initialize the plugin
OriginRecruitmentUtilities::get_instance();