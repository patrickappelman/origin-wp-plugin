<?php
/**
 * Class for admin settings and UI.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
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
