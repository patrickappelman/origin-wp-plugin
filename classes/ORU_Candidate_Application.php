<?php
/**
 * Class for handling job application submission and Zoho syncing.
 * Path: wp-content/plugins/origin-recruitment-utilities/classes/ORU_Candidate_Application.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ORU_Candidate_Application {
	private $zoho_api;
	private $sanitization;

	public function __construct( ORU_Zoho_API $zoho_api, ORU_Sanitization $sanitization ) {
		$this->zoho_api = $zoho_api;
		$this->sanitization = $sanitization;
		add_action( 'wp_ajax_oru_submit_application', [ $this, 'handle_application_submission' ] );
		add_action( 'wp_ajax_nopriv_oru_submit_application', [ $this, 'handle_application_submission' ] );
		error_log( 'ORU_Candidate_Application: Constructor called, AJAX hooks registered' );
	}

	public function handle_application_submission() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'oru_submit_application_nonce' ) ) {
			error_log( 'Zoho Application Submission Error: Invalid or missing nonce' );
			wp_send_json_error( [ 'message' => 'Invalid request.' ], 403 );
		}

		if ( ! is_user_logged_in() ) {
			error_log( 'Zoho Application Submission Error: User not logged in' );
			wp_send_json_error( [ 'message' => 'Please log in to apply.' ], 401 );
		}

		$user_id = get_current_user_id();
		$job_id = isset( $_POST['job_id'] ) ? absint( $_POST['job_id'] ) : 0;
		$redirect_to = isset( $_POST['redirect_to'] ) ? esc_url_raw( $_POST['redirect_to'] ) : '';

		if ( ! $job_id || ! get_post( $job_id ) || get_post_type( $job_id ) !== 'job' ) {
			error_log( 'Zoho Application Submission Error: Invalid job ID ' . $job_id );
			wp_send_json_error( [ 'message' => 'Invalid job ID.' ], 400 );
		}

		$zoho_job_id = get_post_meta( $job_id, 'id', true );
		if ( empty( $zoho_job_id ) ) {
			error_log( 'Zoho Application Submission Error: Missing Zoho job ID for WP job ID ' . $job_id );
			wp_send_json_error( [ 'message' => 'Job not found in Zoho.' ], 400 );
		}

		$zoho_candidate_id = get_user_meta( $user_id, 'id', true );
		if ( empty( $zoho_candidate_id ) ) {
			error_log( 'Zoho Application Submission Error: No Zoho candidate ID for user ID ' . $user_id );
			wp_send_json_error( [ 'message' => 'Please complete your profile before applying.' ], 400 );
		}

		// Associate candidate with job in Zoho
		$application_data = [
			'jobids' => [ $zoho_job_id ],
			'ids' => [ $zoho_candidate_id ],
			'comments' => 'Application submitted via website',
		];
		$result = $this->associate_candidate_to_job( $zoho_candidate_id, $application_data );
		if ( is_wp_error( $result ) && $result->get_error_code() !== 'ALREADY_ASSOCIATED' ) {
			error_log( 'Zoho Application Submission Error: Failed to associate candidate for user ID ' . $user_id . ': ' . $result->get_error_message() );
			wp_send_json_error( [ 'message' => 'Failed to submit application: ' . $result->get_error_message() ], 500 );
		}

		// Fetch application details via Get Related Records API
		$application = $this->get_application_by_candidate_and_job( $zoho_candidate_id, $zoho_job_id );
		if ( is_wp_error( $application ) ) {
			error_log( 'Zoho Application Submission Error: Failed to retrieve application for candidate ID ' . $zoho_candidate_id . ', job ID ' . $zoho_job_id . ': ' . $application->get_error_message() );
			wp_send_json_error( [ 'message' => 'Failed to retrieve application: ' . $application->get_error_message() ], 500 );
		}

		$zoho_application_id = $application['id'] ?? '';
		$zoho_application_status = $application['Application_Status'] ?? 'Associated';
		$created_at = $application['Created_Time'] ? gmdate( 'Y-m-d H:i:s', strtotime( $application['Created_Time'] ) ) : current_time( 'mysql', true );
		$updated_at = $application['Last_Activity_Time'] ? gmdate( 'Y-m-d H:i:s', strtotime( $application['Last_Activity_Time'] ) ) : current_time( 'mysql', true );

		if ( empty( $zoho_application_id ) ) {
			error_log( 'Zoho Application Submission Error: No application ID retrieved for candidate ID ' . $zoho_candidate_id . ', job ID ' . $zoho_job_id );
			wp_send_json_error( [ 'message' => 'Failed to retrieve application ID.' ], 500 );
		}

		// Update application status to "Applied"
		$status_result = $this->update_application_status( $zoho_application_id, 'Applied' );
		if ( is_wp_error( $status_result ) ) {
			error_log( 'Zoho Application Submission Error: Failed to update application status for application ID ' . $zoho_application_id . ': ' . $status_result->get_error_message() );
			wp_send_json_error( [ 'message' => 'Failed to update application status: ' . $status_result->get_error_message() ], 500 );
		}
		$zoho_application_status = 'Applied';

		// Store application in wp_job_applications
		global $wpdb;
		$table_name = $wpdb->prefix . 'job_applications';
		$result = $wpdb->insert(
			$table_name,
			[
				'zoho_candidate_id' => $zoho_candidate_id,
				'zoho_job_id' => $zoho_job_id,
				'zoho_application_id' => $zoho_application_id,
				'zoho_application_status' => $zoho_application_status,
				'created_at' => $created_at,
				'updated_at' => $updated_at,
			],
			[ '%d', '%d', '%d', '%s', '%s', '%s' ]
		);
		if ( $result === false ) {
			error_log( 'Zoho Application Submission Error: Failed to save application to wp_job_applications for candidate ID ' . $zoho_candidate_id . ': ' . $wpdb->last_error );
			wp_send_json_error( [ 'message' => 'Failed to save application data.' ], 500 );
		}

		$redirect_url = add_query_arg( 'application', 'success', get_permalink( $job_id ) ) . '#Apply';
		if ( $redirect_to ) {
			$redirect_url = add_query_arg( 'application', 'success', $redirect_to ) . '#Apply';
		}
		error_log( 'Zoho Application Submission Success: Application stored for candidate ID ' . $zoho_candidate_id . ', job ID ' . $zoho_job_id . ', application ID ' . $zoho_application_id );
		wp_send_json_success( [ 'redirect_url' => $redirect_url ] );
	}

	public function associate_candidate_to_job( $candidate_id, $application_data ) {
		$access_token = $this->zoho_api->get_access_token();
		if ( is_wp_error( $access_token ) ) {
			error_log( 'Zoho Associate Candidate Error: ' . $access_token->get_error_message() );
			return $access_token;
		}
		$api_base_url = 'https://recruit.zoho.eu/recruit/v2/';
		$endpoint = $api_base_url . 'Candidates/actions/associate';
		error_log( 'Zoho Associate Candidate Request: ' . $endpoint . ' with data: ' . print_r( $application_data, true ) );
		$response = wp_remote_request( $endpoint, [
			'method' => 'PUT',
			'headers' => [
				'Authorization' => 'Zoho-oauthtoken ' . $access_token,
				'Content-Type' => 'application/json',
			],
			'body' => json_encode( [ 'data' => [ $application_data ] ] ),
			'timeout' => 30,
		]);
		if ( is_wp_error( $response ) ) {
			error_log( 'Zoho Associate Candidate Error: ' . $response->get_error_message() );
			return $response;
		}
		$response_code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		error_log( 'Zoho Associate Candidate Response: Code=' . $response_code . ', Body=' . print_r( $body, true ) );
		if ( $response_code !== 200 ) {
			$error_code = $body['data'][0]['code'] ?? 'UNKNOWN_ERROR';
			$error_message = $body['data'][0]['message'] ?? 'HTTP ' . $response_code;
			if ( $error_code === 'ALREADY_ASSOCIATED' ) {
				return new WP_Error( 'ALREADY_ASSOCIATED', $error_message );
			}
			error_log( 'Zoho Associate Candidate Error: ' . $error_message );
			return new WP_Error( 'api_error', $error_message );
		}
		if ( isset( $body['data'][0]['details']['jobid'] ) ) {
			return [
				'jobid' => $body['data'][0]['details']['jobid'],
			];
		}
		error_log( 'Zoho Associate Candidate Error: No job ID returned' );
		return new WP_Error( 'no_data', 'No job ID returned from Zoho.' );
	}

	public function get_application_by_candidate_and_job( $candidate_id, $job_id ) {
		$access_token = $this->zoho_api->get_access_token();
		if ( is_wp_error( $access_token ) ) {
			error_log( 'Zoho Get Related Applications Error: ' . $access_token->get_error_message() );
			return $access_token;
		}
		$api_base_url = 'https://recruit.zoho.eu/recruit/v2/';
		$endpoint = $api_base_url . 'Candidates/' . $candidate_id . '/Applications';
		error_log( 'Zoho Get Related Applications Request: ' . $endpoint );
		$response = wp_remote_get( $endpoint, [
			'headers' => [
				'Authorization' => 'Zoho-oauthtoken ' . $access_token,
			],
			'timeout' => 30,
		]);
		if ( is_wp_error( $response ) ) {
			error_log( 'Zoho Get Related Applications Error: ' . $response->get_error_message() );
			return $response;
		}
		$response_code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		error_log( 'Zoho Get Related Applications Response: Code=' . $response_code . ', Body=' . print_r( $body, true ) );
		if ( $response_code !== 200 ) {
			$error_message = $body['data'][0]['message'] ?? 'HTTP ' . $response_code;
			error_log( 'Zoho Get Related Applications Error: ' . $error_message );
			return new WP_Error( 'api_error', $error_message );
		}
		if ( isset( $body['data'] ) ) {
			foreach ( $body['data'] as $application ) {
				if ( isset( $application['$Job_Opening_Id'] ) && $application['$Job_Opening_Id'] == $job_id ) {
					return [
						'id' => $application['id'],
						'Application_Status' => $application['Application_Status'] ?? 'Associated',
						'Created_Time' => $application['Created_Time'] ?? '',
						'Last_Activity_Time' => $application['Last_Activity_Time'] ?? '',
					];
				}
			}
		}
		error_log( 'Zoho Get Related Applications Error: No application found for candidate ID ' . $candidate_id . ', job ID ' . $job_id );
		return new WP_Error( 'no_data', 'No application found.' );
	}

	public function update_application_status( $application_id, $status ) {
		$access_token = $this->zoho_api->get_access_token();
		if ( is_wp_error( $access_token ) ) {
			error_log( 'Zoho Update Application Status Error: ' . $access_token->get_error_message() );
			return $access_token;
		}
		$api_base_url = 'https://recruit.zoho.eu/recruit/v2/';
		$endpoint = $api_base_url . 'Applications/' . $application_id;
		error_log( 'Zoho Update Application Status Request: ' . $endpoint . ' with status: ' . $status );
		$response = wp_remote_request( $endpoint, [
			'method' => 'PUT',
			'headers' => [
				'Authorization' => 'Zoho-oauthtoken ' . $access_token,
				'Content-Type' => 'application/json',
			],
			'body' => json_encode( [ 'data' => [ [ 'Application_Status' => $status ] ] ] ),
			'timeout' => 30,
		]);
		if ( is_wp_error( $response ) ) {
			error_log( 'Zoho Update Application Status Error: ' . $response->get_error_message() );
			return $response;
		}
		$response_code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		error_log( 'Zoho Update Application Status Response: Code=' . $response_code . ', Body=' . print_r( $body, true ) );
		if ( $response_code !== 200 ) {
			$error_message = $body['data'][0]['message'] ?? 'HTTP ' . $response_code;
			error_log( 'Zoho Update Application Status Error: ' . $error_message );
			return new WP_Error( 'api_error', $error_message );
		}
		return true;
	}
}
?>
