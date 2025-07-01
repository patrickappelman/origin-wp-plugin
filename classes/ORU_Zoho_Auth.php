<?php
/**
 * Class for Zoho OAuth authentication.
 * Path: wp-content/plugins/origin-recruitment-utilities/classes/ORU_Zoho_Auth.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ORU_Zoho_Auth {
	public function get_credentials() {
		$settings = get_option( 'zoho_recruit_settings', [] );
		$redirect_uri = isset( $settings['redirect_uri'] ) && !empty( $settings['redirect_uri'] ) 
			? $settings['redirect_uri'] 
			: ZOHO_RECRUIT_REDIRECT_URI;
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
			'redirect_uri' => $credentials['redirect_uri'], // Avoid double-encoding
			'response_type' => 'code',
			'scope' => implode( ',', $scopes ),
			'access_type' => 'offline',
			'prompt' => 'consent',
		];
		$query_string = http_build_query( $query_params, '', '&', PHP_QUERY_RFC3986 );
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
				'redirect_uri' => $credentials['redirect_uri'], // Avoid encoding here too
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
