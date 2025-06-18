<?php
/*
* Plugin Name: Origin Recruitment - Utilities
* Description: A custom plugin developed for Origin Recruitment by Appelman Designs to augment Wordpress to include a Jobs post type, as well as custom tag taxonomy such as Languages, Countries, and Industries.
* Version: 1.0.0
* Author: Appelman Designs
* Author URI: https://appelmandesigns.com/
*/



/* DEFINE CUSTOM POST TYPES */

function or_create_posttypes() {

  // Register custom post type: Jobs

  register_post_type( 'job',
    array(
      'labels' => array(
        'name' => __( 'Jobs' ),
        'singular_name'         => __( 'Job' ),
        'menu_name'             => __( 'Jobs', 'Admin Menu text', 'textdomain' ),
        'name_admin_bar'        => __( 'Job', 'Add New on Toolbar', 'textdomain' ),
        'add_new'               => __( 'Add New', 'textdomain' ),
        'add_new_item'          => __( 'Add Job', 'textdomain' ),
        'new_item'              => __( 'New Job', 'textdomain' ),
        'edit_item'             => __( 'Edit Job', 'textdomain' ),
        'view_item'             => __( 'View Job', 'textdomain' ),
        'all_items'             => __( 'All Jobs', 'textdomain' ),
        'search_items'          => __( 'Search Jobs', 'textdomain' ),
        'parent_item_colon'     => __( 'Parent Jobs:', 'textdomain' ),
        'not_found'             => __( 'No jobs found.', 'textdomain' ),
        'not_found_in_trash'    => __( 'No jobs found in Trash.', 'textdomain' ),
        'featured_image'        => _x( 'Job Cover Image', 'Overrides the “Featured Image” phrase for this post type. Added in 4.3', 'textdomain' ),
        'set_featured_image'    => _x( 'Set cover image', 'Overrides the “Set featured image” phrase for this post type. Added in 4.3', 'textdomain' ),
        'remove_featured_image' => _x( 'Remove cover image', 'Overrides the “Remove featured image” phrase for this post type. Added in 4.3', 'textdomain' ),
        'use_featured_image'    => _x( 'Use as cover image', 'Overrides the “Use as featured image” phrase for this post type. Added in 4.3', 'textdomain' ),
        'archives'              => _x( 'Job archives', 'The post type archive label used in nav menus. Default “Post Archives”. Added in 4.4', 'textdomain' ),
        'insert_into_item'      => _x( 'Insert into job', 'Overrides the “Insert into post”/”Insert into page” phrase (used when inserting media into a post). Added in 4.4', 'textdomain' ),
        'uploaded_to_this_item' => _x( 'Uploaded to this job', 'Overrides the “Uploaded to this post”/”Uploaded to this page” phrase (used when viewing media attached to a post). Added in 4.4', 'textdomain' ),
        'filter_items_list'     => _x( 'Filter jobs list', 'Screen reader text for the filter links heading on the post type listing screen. Default “Filter posts list”/”Filter pages list”. Added in 4.4', 'textdomain' ),
        'items_list_navigation' => _x( 'Jobs list navigation', 'Screen reader text for the pagination heading on the post type listing screen. Default “Posts list navigation”/”Pages list navigation”. Added in 4.4', 'textdomain' ),
        'items_list'            => _x( 'Jobs list', 'Screen reader text for the items list heading on the post type listing screen. Default “Posts list”/”Pages list”. Added in 4.4', 'textdomain' ),
      ),
      'description'        => 'A Wordpress custom post type for jobs.',
      'public'             => true,
      'publicly_queryable' => true,
      'show_ui'            => true,
      'supports'           => array( 'title', 'editor', 'excerpt', 'custom-fields' ),
      'show_in_rest'       => true,
      'hierarchical'       => false,
      'rewrite'            => array('slug' => 'jobs', 'with_front' => false),

      'capability_type'    => 'post',
      'taxonomies'         => array('country'),
      'has_archive'        => false,
      'menu_icon'          => 'dashicons-businessman',
      
    )
  );

}

add_action( 'init', 'or_create_posttypes' );



/* DEFINE CUSTOM TAXONOMY */

function or_register_custom_taxonomies() {

	// Register Taxonomy - Languages

	register_taxonomy( 'language', [ 'post', 'page', 'job' ], array(
		'labels'            => array(
			'name'              => _x( 'Languages', 'taxonomy general name' ),
			'singular_name'     => _x( 'Language', 'taxonomy singular name' ),
			'search_items'      => __( 'Search Languages' ),
			'all_items'         => __( 'All Languages' ),
			'parent_item'       => __( 'Parent Language' ),
			'parent_item_colon' => __( 'Parent Language:' ),
			'edit_item'         => __( 'Edit Language' ),
			'update_item'       => __( 'Update Language' ),
			'add_new_item'      => __( 'Add New Language' ),
			'new_item_name'     => __( 'New Language Name' ),
			'menu_name'         => __( 'Languages' ),
		),
		'hierarchical'      => false, // make it hierarchical (like categories)
		'show_ui'           => true,
		'show_in_menu'      => true,
		'show_in_rest'      => true,
		'show_in_nav_menus' => true,
		'meta_box_cb'       => 'post_tags_meta_box',
		'show_admin_column' => true,
		'query_var'         => true,
		'rewrite'           => [ 'slug' => 'languages', 'with_front' => false, 'hierarchical' => false ],
	));



	// Register Taxonomy - Countries

	register_taxonomy( 'country', [ 'post', 'page', 'job' ], array(
		'labels'            => array(
			'name'              => _x( 'Countries', 'taxonomy general name' ),
			'singular_name'     => _x( 'Country', 'taxonomy singular name' ),
			'search_items'      => __( 'Search Countries' ),
			'all_items'         => __( 'All Countries' ),
			'parent_item'       => __( 'Parent Country' ),
			'parent_item_colon' => __( 'Parent Country:' ),
			'edit_item'         => __( 'Edit Country' ),
			'update_item'       => __( 'Update Country' ),
			'add_new_item'      => __( 'Add New Country' ),
			'new_item_name'     => __( 'New Country Name' ),
			'menu_name'         => __( 'Countries' ),
		),
		'hierarchical'      => false, // make it hierarchical (like categories)
		'show_ui'           => true,
		'show_in_menu'      => true,
		'show_in_rest'      => true,
		'show_in_nav_menus' => true,
		'meta_box_cb'       => 'post_tags_meta_box',
		'show_admin_column' => true,
		'query_var'         => true,
		'rewrite'           => [ 'slug' => 'locations', 'with_front' => false, 'hierarchical' => false ],
	));



	// Register Taxonomy - Industries

	register_taxonomy( 'industry', [ 'post', 'page', 'job' ], array(
		'labels'            => array(
			'name'              => _x( 'Industries', 'taxonomy general name' ),
			'singular_name'     => _x( 'Industry', 'taxonomy singular name' ),
			'search_items'      => __( 'Search Industries' ),
			'all_items'         => __( 'All Industries' ),
			'parent_item'       => __( 'Parent Industry' ),
			'parent_item_colon' => __( 'Parent Industry:' ),
			'edit_item'         => __( 'Edit Industry' ),
			'update_item'       => __( 'Update Industry' ),
			'add_new_item'      => __( 'Add New Industry' ),
			'new_item_name'     => __( 'New Industry Name' ),
			'menu_name'         => __( 'Industries' ),
		),
		'hierarchical'      => false, // make it hierarchical (like categories)
		'show_ui'           => true,
		'show_in_menu'      => true,
		'show_in_rest'      => true,
		'show_in_nav_menus' => true,
		'meta_box_cb'       => 'post_categories_meta_box',
		'show_admin_column' => true,
		'query_var'         => true,
		'rewrite'           => [ 'slug' => 'industries', 'with_front' => false, 'hierarchical' => false ],
	));



	// Register Taxonomy - Sectors

	register_taxonomy( 'sector', [ 'post', 'page', 'job' ], array(
		'labels'            => array(
			'name'              => _x( 'Sectors', 'taxonomy general name' ),
			'singular_name'     => _x( 'Sector', 'taxonomy singular name' ),
			'search_items'      => __( 'Search Sectors' ),
			'all_items'         => __( 'All Sectors' ),
			'parent_item'       => __( 'Parent Sector' ),
			'parent_item_colon' => __( 'Parent Sector:' ),
			'edit_item'         => __( 'Edit Sector' ),
			'update_item'       => __( 'Update Sector' ),
			'add_new_item'      => __( 'Add New Sector' ),
			'new_item_name'     => __( 'New Sector Name' ),
			'menu_name'         => __( 'Sectors' ),
		),
		'hierarchical'      => false, // make it hierarchical (like categories)
		'show_ui'           => true,
		'show_in_menu'      => true,
		'show_in_rest'      => true,
		'show_in_nav_menus' => true,
		'meta_box_cb'       => 'post_categories_meta_box',
		'show_admin_column' => true,
		'query_var'         => true,
		'rewrite'           => [ 'slug' => 'sectors', 'with_front' => false, 'hierarchical' => false ],
	));



}

add_action( 'init', 'or_register_custom_taxonomies' );



// BEGIN ZOHO INTEGRATION

// Define redirect URI
if ( defined( 'WP_LOCAL_DEV' ) && WP_LOCAL_DEV ) {
    $current_host = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? 'origin-recruitment:8890';
    define( 'ZOHO_RECRUIT_REDIRECT_URI', $current_host !== 'origin-recruitment:8890'
        ? rtrim( ( isset( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http' ) . '://' . $current_host, '/' ) . '/wp-admin/admin.php?page=zoho-recruit-auth-callback'
        : 'https://origin-recruitment.com/wp-admin/admin.php?page=zoho-recruit-auth-callback' );
} else {
    define( 'ZOHO_RECRUIT_REDIRECT_URI', 'https://origin-recruitment.com/wp-admin/admin.php?page=zoho-recruit-auth-callback' );
}

// Schedule cron job for token refresh
add_action( 'zoho_recruit_refresh_token_cron', 'zoho_recruit_refresh_token' );
register_activation_hook( __FILE__, function() {
    if ( !wp_next_scheduled( 'zoho_recruit_refresh_token_cron' ) ) {
        wp_schedule_event( time(), 'thirty_minutes', 'zoho_recruit_refresh_token_cron' );
    }
});
register_deactivation_hook( __FILE__, function() {
    wp_clear_scheduled_hook( 'zoho_recruit_refresh_token_cron' );
});

// Add custom cron schedule
add_filter( 'cron_schedules', function( $schedules ) {
    $schedules['thirty_minutes'] = [
        'interval' => 1800, // 30 minutes in seconds
        'display' => __( 'Every Thirty Minutes', 'textdomain' ),
    ];
    return $schedules;
});

// Settings page
add_action( 'admin_menu', 'zoho_recruit_add_admin_menu' );
add_action( 'admin_init', 'zoho_recruit_settings_init' );

function zoho_recruit_add_admin_menu() {
    add_menu_page(
        'Zoho Recruit Settings',
        'Zoho Recruit',
        'manage_options',
        'zoho_recruit_settings',
        'zoho_recruit_settings_page',
        'dashicons-admin-generic',
        80
    );
    add_submenu_page(
        null,
        'Zoho Recruit Auth',
        'Zoho Recruit Auth',
        'manage_options',
        'zoho-recruit-auth-callback',
        'zoho_recruit_handle_callback'
    );
}

function zoho_recruit_settings_page() {
    $auth_url = zoho_recruit_generate_auth_url();
    $access_token = get_option( 'zoho_recruit_access_token', '' );
    $token_expires = get_option( 'zoho_recruit_token_expires', 0 );
    $auth_status = $access_token && $token_expires > time() ? 'Authenticated' : 'Not Authenticated';

    // Handle test API call
    $test_result = '';
    if ( isset( $_POST['zoho_recruit_test_api'] ) && check_admin_referer( 'zoho_recruit_test_api_nonce' ) ) {
        $test_result = zoho_recruit_test_api();
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
                    <?php if ( !empty( $test_result['sample_job'] ) ) : ?>
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
        <form method="post" action="options.php">
            <?php
            settings_fields( 'zoho_recruit_settings_group' );
            do_settings_sections( 'zoho_recruit_settings' );
            submit_button();
            ?>
        </form>
        <?php if ( !is_wp_error( $auth_url ) ) : ?>
            <p><a href="<?php echo esc_url( $auth_url ); ?>" class="button button-primary">Authorize with Zoho</a></p>
        <?php endif; ?>
        <?php if ( $access_token ) : ?>
            <form method="post" action="">
                <?php wp_nonce_field( 'zoho_recruit_test_api_nonce' ); ?>
                <input type="hidden" name="zoho_recruit_test_api" value="1">
                <p><button type="submit" class="button button-secondary">Test API Connection</button></p>
            </form>
        <?php endif; ?>
    </div>
    <?php
}

function zoho_recruit_settings_init() {
    register_setting(
        'zoho_recruit_settings_group',
        'zoho_recruit_settings',
        'zoho_recruit_sanitize_settings'
    );

    add_settings_section(
        'zoho_recruit_main_section',
        'Zoho Recruit API Credentials',
        'zoho_recruit_section_callback',
        'zoho_recruit_settings'
    );

    add_settings_field(
        'zoho_recruit_client_id',
        'Client ID',
        'zoho_recruit_client_id_callback',
        'zoho_recruit_settings',
        'zoho_recruit_main_section'
    );

    add_settings_field(
        'zoho_recruit_client_secret',
        'Client Secret',
        'zoho_recruit_client_secret_callback',
        'zoho_recruit_settings',
        'zoho_recruit_main_section'
    );

    add_settings_field(
        'zoho_recruit_redirect_uri',
        'OAuth Redirect URI',
        'zoho_recruit_redirect_uri_callback',
        'zoho_recruit_settings',
        'zoho_recruit_main_section'
    );
}

function zoho_recruit_section_callback() {
    echo '<p>Enter your Zoho Recruit API credentials from the <a href="https://api-console.zoho.eu/" target="_blank">Zoho Developer Console</a>.</p>';
}

function zoho_recruit_client_id_callback() {
    $settings = get_option( 'zoho_recruit_settings', [] );
    $client_id = isset( $settings['client_id'] ) ? esc_attr( $settings['client_id'] ) : '';
    echo '<input type="text" name="zoho_recruit_settings[client_id]" value="' . $client_id . '" class="regular-text" />';
}

function zoho_recruit_client_secret_callback() {
    $settings = get_option( 'zoho_recruit_settings', [] );
    $client_secret = isset( $settings['client_secret'] ) ? esc_attr( $settings['client_secret'] ) : '';
    echo '<input type="password" name="zoho_recruit_settings[client_secret]" value="' . $client_secret . '" class="regular-text" />';
}

function zoho_recruit_redirect_uri_callback() {
    $settings = get_option( 'zoho_recruit_settings', [] );
    $redirect_uri = isset( $settings['redirect_uri'] ) ? esc_attr( $settings['redirect_uri'] ) : ZOHO_RECRUIT_REDIRECT_URI;
    echo '<input type="text" name="zoho_recruit_settings[redirect_uri]" value="' . $redirect_uri . '" class="regular-text" />';
    echo '<p class="description">Enter the redirect URI registered in the Zoho Developer Console. Defaults to ' . esc_html( ZOHO_RECRUIT_REDIRECT_URI ) . '.</p>';
}

function zoho_recruit_sanitize_settings( $input ) {
    $sanitized_input = [];
    $sanitized_input['client_id'] = sanitize_text_field( $input['client_id'] ?? '' );
    $sanitized_input['client_secret'] = sanitize_text_field( $input['client_secret'] ?? '' );
    $sanitized_input['redirect_uri'] = esc_url_raw( $input['redirect_uri'] ?? ZOHO_RECRUIT_REDIRECT_URI );
    return $sanitized_input;
}

function zoho_recruit_get_credentials() {
    $settings = get_option( 'zoho_recruit_settings', [] );
    $current_host = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? 'origin-recruitment:8890';
    $redirect_uri = ( defined( 'WP_LOCAL_DEV' ) && WP_LOCAL_DEV && $current_host !== 'origin-recruitment:8890' )
        ? rtrim( ( isset( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http' ) . '://' . $current_host, '/' ) . '/wp-admin/admin.php?page=zoho-recruit-auth-callback'
        : ( isset( $settings['redirect_uri'] ) && !empty( $settings['redirect_uri'] ) ? $settings['redirect_uri'] : ZOHO_RECRUIT_REDIRECT_URI );
    error_log( 'Zoho Redirect URI: ' . $redirect_uri );
    return [
        'client_id' => isset( $settings['client_id'] ) ? $settings['client_id'] : '',
        'client_secret' => isset( $settings['client_secret'] ) ? $settings['client_secret'] : '',
        'redirect_uri' => $redirect_uri,
    ];
}

function zoho_recruit_generate_auth_url() {
    $credentials = zoho_recruit_get_credentials();
    if ( empty( $credentials['client_id'] ) || empty( $credentials['client_secret'] ) ) {
        error_log( 'Zoho Auth URL Error: Missing Client ID or Client Secret' );
        return new WP_Error( 'missing_credentials', 'Please enter Client ID and Client Secret in the settings.' );
    }

    // Define scopes
    $scopes = [
        'ZohoRecruit.modules.ALL',
    ];

    // Manually build query string to avoid encoding commas
    $query_params = [
        'client_id' => rawurlencode($credentials['client_id']),
        'redirect_uri' => rawurlencode($credentials['redirect_uri']),
        'response_type' => 'code',
        'scope' => implode(',', $scopes), // Unencoded commas
        'access_type' => 'offline',
        'prompt' => 'consent',
    ];

    $query_string = '';
    foreach ( $query_params as $key => $value ) {
        $query_string .= $query_string ? '&' : '';
        $query_string .= $key . '=' . $value;
    }

    $auth_url = 'https://accounts.zoho.eu/oauth/v2/auth?' . $query_string;
    error_log( 'Zoho Auth URL: ' . $auth_url );
    return $auth_url;
}

function zoho_recruit_handle_callback() {
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

                $token_response = zoho_recruit_exchange_code_for_tokens( $auth_code );
                if ( !is_wp_error( $token_response ) ) {
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

function zoho_recruit_exchange_code_for_tokens( $auth_code ) {
    $credentials = zoho_recruit_get_credentials();
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

    $error_message = isset( $body['error'] ) ? $body['error'] : 'Failed to obtain tokens.';
    error_log( 'Zoho Token Error: ' . $error_message );
    return new WP_Error( 'token_error', $error_message );
}

function zoho_recruit_refresh_token() {
    $refresh_token = get_option( 'zoho_recruit_refresh_token', '' );
    $credentials = zoho_recruit_get_credentials();

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

    $error_message = isset( $body['error'] ) ? $body['error'] : 'Failed to refresh token.';
    error_log( 'Zoho Token Refresh Error: ' . $error_message );
    return new WP_Error( 'refresh_error', $error_message );
}

function zoho_recruit_test_api() {
    $access_token = get_option( 'zoho_recruit_access_token', '' );
    $token_expires = get_option( 'zoho_recruit_token_expires', 0 );
    $api_location = get_option( 'zoho_recruit_api_location', 'eu' );

    // Refresh token if expired or near expiration (within 5 minutes)
    if ( empty( $access_token ) || $token_expires <= time() + 300 ) {
        $refresh_result = zoho_recruit_refresh_token();
        if ( is_wp_error( $refresh_result ) ) {
            return $refresh_result;
        }
        $access_token = get_option( 'zoho_recruit_access_token', '' );
    }

    if ( empty( $access_token ) ) {
        error_log( 'Zoho API Test Error: Invalid or expired access token' );
        return new WP_Error( 'invalid_token', 'Access token is invalid or expired. Please reauthorize.' );
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
        // Log the first job opening for debugging
        error_log( 'Zoho API Test Sample Job: ' . print_r( $body['data'][0], true ) );

        // Prepare result
        $result = [
            'count' => count( $body['data'] ),
            'sample_job' => [],
        ];

        // Extract fields from the first job opening
        if ( !empty( $body['data'][0] ) ) {
            foreach ( $body['data'][0] as $key => $value ) {
                // Sanitize and limit to scalar or simple array values
                if ( is_scalar( $value ) || ( is_array( $value ) && array_walk_recursive( $value, function( $v ) { return is_scalar( $v ); } ) ) ) {
                    $result['sample_job'][$key] = $value;
                }
            }
            // Ensure Job_Opening_Name is included
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
