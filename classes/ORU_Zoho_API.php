<?php
/**
 * Class for Zoho API interactions.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
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
