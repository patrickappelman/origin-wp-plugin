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
		if ( isset( $body['data'] ) && ! empty( $body['data'] ) ) {
			error_log( 'Zoho API Test Sample Job: ' . print_r( $body['data'][0], true ) );
			$result = [
				'count' => count( $body['data'] ),
				'sample_job' => [],
			];
			if ( ! empty( $body['data'][0] ) ) {
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

	public function test_candidate_api() {
		$access_token = $this->get_access_token();
		if ( is_wp_error( $access_token ) ) {
			return $access_token;
		}
		$api_base_url = 'https://recruit.zoho.eu/recruit/v2/';
		$candidate_id = '70860000000902006';
		$endpoint = $api_base_url . 'Candidates/' . $candidate_id;
		error_log( 'Zoho Candidate API Test Request: ' . $endpoint );
		$response = wp_remote_get( $endpoint, [
			'headers' => [
				'Authorization' => 'Zoho-oauthtoken ' . $access_token,
			],
			'timeout' => 30,
		]);
		if ( is_wp_error( $response ) ) {
			error_log( 'Zoho Candidate API Test Error: ' . $response->get_error_message() );
			return $response;
		}
		$response_code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		error_log( 'Zoho Candidate API Test Response: ' . print_r( $body, true ) );
		if ( $response_code !== 200 ) {
			$error_message = $body['message'] ?? 'HTTP ' . $response_code;
			error_log( 'Zoho Candidate API Test Error: ' . $error_message );
			return new WP_Error( 'api_error', $error_message );
		}
		if ( isset( $body['data'][0] ) ) {
			error_log( 'Zoho Candidate API Test Sample Candidate: ' . print_r( $body['data'][0], true ) );
			$result = [
				'sample_candidate' => [],
			];
			foreach ( $body['data'][0] as $key => $value ) {
				if ( is_scalar( $value ) || ( is_array( $value ) && array_walk_recursive( $value, function( $v ) { return is_scalar( $v ); } ) ) ) {
					$result['sample_candidate'][$key] = $value;
				}
			}
			$result['sample_candidate']['Email'] = $body['data'][0]['Email'] ?? 'Unknown';
			$result['sample_candidate']['Candidate_ID'] = $body['data'][0]['Candidate_ID'] ?? 'Unknown';
			return $result;
		} elseif ( isset( $body['error'] ) ) {
			$error_message = $body['error']['message'] ?? 'Unknown API error';
			error_log( 'Zoho Candidate API Test Error: ' . $error_message );
			return new WP_Error( 'api_error', $error_message );
		} else {
			error_log( 'Zoho Candidate API Test Error: No candidate found for ID ' . $candidate_id );
			return new WP_Error( 'no_data', 'No candidate found for ID ' . $candidate_id );
		}
	}

	public function test_application_api() {
		$access_token = $this->get_access_token();
		if ( is_wp_error( $access_token ) ) {
			return $access_token;
		}
		$api_base_url = 'https://recruit.zoho.eu/recruit/v2/';
		$endpoint = $api_base_url . 'Applications?per_page=10&sort_by=Applied_Date&sort_order=desc';
		error_log( 'Zoho Application API Test Request: ' . $endpoint );
		$response = wp_remote_get( $endpoint, [
			'headers' => [
				'Authorization' => 'Zoho-oauthtoken ' . $access_token,
			],
			'timeout' => 30,
		]);
		if ( is_wp_error( $response ) ) {
			error_log( 'Zoho Application API Test Error: ' . $response->get_error_message() );
			return $response;
		}
		$response_code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		error_log( 'Zoho Application API Test Response: ' . print_r( $body, true ) );
		if ( $response_code !== 200 ) {
			$error_message = $body['message'] ?? 'HTTP ' . $response_code;
			error_log( 'Zoho Application API Test Error: ' . $error_message );
			return new WP_Error( 'api_error', $error_message );
		}
		if ( isset( $body['data'] ) && ! empty( $body['data'] ) ) {
			error_log( 'Zoho Application API Test Sample Application: ' . print_r( $body['data'][0], true ) );
			$result = [
				'count' => count( $body['data'] ),
				'sample_application' => [],
			];
			if ( ! empty( $body['data'][0] ) ) {
				foreach ( $body['data'][0] as $key => $value ) {
					if ( is_scalar( $value ) || ( is_array( $value ) && array_walk_recursive( $value, function( $v ) { return is_scalar( $v ); } ) ) ) {
						$result['sample_application'][$key] = $value;
					}
				}
				$result['sample_application']['id'] = $body['data'][0]['id'] ?? 'Unknown';
				$result['sample_application']['Candidate'] = $body['data'][0]['Candidate']['name'] ?? 'Unknown';
				$result['sample_application']['Job_Opening'] = $body['data'][0]['Job_Opening']['name'] ?? 'Unknown';
			}
			return $result;
		} else {
			error_log( 'Zoho Application API Test Error: No applications found or unexpected response' );
			return new WP_Error( 'no_data', 'No applications found or unexpected API response.' );
		}
	}

	public function search_candidate_by_email( $email ) {
		$access_token = $this->get_access_token();
		if ( is_wp_error( $access_token ) ) {
			error_log( 'Zoho Search Candidate Error: ' . $access_token->get_error_message() );
			return $access_token;
		}
		$api_base_url = 'https://recruit.zoho.eu/recruit/v2/';
		$endpoint = $api_base_url . 'Candidates/search?criteria=(Email:equals:' . urlencode( $email ) . ')';
		error_log( 'Zoho Search Candidate Request: ' . $endpoint );
		$response = wp_remote_get( $endpoint, [
			'headers' => [
				'Authorization' => 'Zoho-oauthtoken ' . $access_token,
			],
			'timeout' => 30,
		]);
		if ( is_wp_error( $response ) ) {
			error_log( 'Zoho Search Candidate Error: ' . $response->get_error_message() );
			return $response;
		}
		$response_code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		error_log( 'Zoho Search Candidate Response: Code=' . $response_code . ', Body=' . print_r( $body, true ) );
		if ( $response_code !== 200 ) {
			$error_message = $body['message'] ?? 'HTTP ' . $response_code;
			error_log( 'Zoho Search Candidate Error: ' . $error_message );
			return new WP_Error( 'api_error', $error_message );
		}
		if ( isset( $body['data'] ) && ! empty( $body['data'] ) ) {
			error_log( 'Zoho Search Candidate Found: ' . print_r( $body['data'][0], true ) );
			return [
				'id' => $body['data'][0]['id'] ?? '',
				'candidate_id' => $body['data'][0]['Candidate_ID'] ?? '',
			];
		}
		error_log( 'Zoho Search Candidate: No candidate found for email ' . $email );
		return null;
	}

	public function create_candidate( $candidate_data ) {
		$access_token = $this->get_access_token();
		if ( is_wp_error( $access_token ) ) {
			error_log( 'Zoho Create Candidate Error: ' . $access_token->get_error_message() );
			return $access_token;
		}
		$api_base_url = 'https://recruit.zoho.eu/recruit/v2/';
		$endpoint = $api_base_url . 'Candidates';
		error_log( 'Zoho Create Candidate Request: ' . $endpoint . ' with data: ' . print_r( $candidate_data, true ) );
		$response = wp_remote_post( $endpoint, [
			'headers' => [
				'Authorization' => 'Zoho-oauthtoken ' . $access_token,
				'Content-Type' => 'application/json',
			],
			'body' => json_encode( [ 'data' => [ $candidate_data ] ] ),
			'timeout' => 30,
		]);
		if ( is_wp_error( $response ) ) {
			error_log( 'Zoho Create Candidate Error: ' . $response->get_error_message() );
			return $response;
		}
		$response_code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		error_log( 'Zoho Create Candidate Response: Code=' . $response_code . ', Body=' . print_r( $body, true ) );
		if ( $response_code !== 201 ) {
			$error_message = $body['message'] ?? 'HTTP ' . $response_code;
			error_log( 'Zoho Create Candidate Error: ' . $error_message );
			return new WP_Error( 'api_error', $error_message );
		}
		if ( isset( $body['data'][0]['details']['id'] ) ) {
			return [
				'id' => $body['data'][0]['details']['id'],
				'candidate_id' => $body['data'][0]['details']['Candidate_ID'] ?? '',
			];
		}
		error_log( 'Zoho Create Candidate Error: No candidate ID returned' );
		return new WP_Error( 'no_data', 'No candidate ID returned from Zoho.' );
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
?>
