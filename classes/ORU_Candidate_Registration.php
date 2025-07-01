<?php
/**
 * Class for handling candidate registration and Zoho syncing.
 * Path: wp-content/plugins/origin-recruitment-utilities/classes/ORU_Candidate_Registration.php
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ORU_Candidate_Registration {
	private $zoho_api;
	private $sanitization;

	public function __construct( ORU_Zoho_API $zoho_api, ORU_Sanitization $sanitization ) {
		$this->zoho_api = $zoho_api;
		$this->sanitization = $sanitization;
		add_action( 'oru_profile_updated', [ $this, 'sync_candidate_to_zoho' ], 10, 1 );
		add_action( 'init', [ $this, 'handle_resume_download' ] );
		add_action( 'init', [ $this, 'handle_cover_letter_download' ] );
		error_log( 'ORU_Candidate_Registration: Constructor called, hooks registered' );
	}

	public function handle_resume_download() {
		if ( ! isset( $_GET['action'] ) || $_GET['action'] !== 'download_resume' ) {
			return;
		}

		if ( ! is_user_logged_in() ) {
			error_log( 'Zoho Resume Download Error: User not logged in' );
			wp_die( 'Please log in to download your resume.', 'Unauthorized', [ 'response' => 401 ] );
		}

		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'download_resume' ) ) {
			error_log( 'Zoho Resume Download Error: Invalid or missing nonce' );
			wp_die( 'Invalid request.', 'Unauthorized', [ 'response' => 403 ] );
		}

		if ( ! empty( $_GET['candidate_id'] ) || ! empty( $_GET['attachment_id'] ) ) {
			error_log( 'Zoho Resume Download Error: Query parameters candidate_id or attachment_id not allowed' );
			wp_die( 'Invalid request.', 'Unauthorized', [ 'response' => 403 ] );
		}

		$user_id = get_current_user_id();
		$resume_url = get_user_meta( $user_id, 'resume_url', true );
		$stored_candidate_id = get_user_meta( $user_id, 'candidate_id', true );

		if ( empty( $resume_url ) ) {
			error_log( 'Zoho Resume Download Error: No resume found for user ID ' . $user_id );
			wp_die( 'No resume available for download.', 'Not Found', [ 'response' => 404 ] );
		}

		$parts = explode( '/', rtrim( $resume_url, '/' ) );
		$attachment_id = end( $parts );
		$candidate_id = count( $parts ) >= 3 ? $parts[count( $parts ) - 3] : '';

		if ( ! ctype_digit( $candidate_id ) || ! ctype_digit( $attachment_id ) ) {
			error_log( 'Zoho Resume Download Error: Invalid resume_url format for user ID ' . $user_id . ': ' . $resume_url );
			wp_die( 'Invalid resume data.', 'Bad Request', [ 'response' => 400 ] );
		}

		if ( ! current_user_can( 'manage_options' ) && $candidate_id !== $stored_candidate_id ) {
			error_log( 'Zoho Resume Download Error: Unauthorized access for user ID ' . $user_id );
			wp_die( 'Unauthorized access.', 'Forbidden', [ 'response' => 403 ] );
		}

		error_log( 'Zoho Resume Download: Triggered for user ID ' . $user_id . ', candidate ID ' . $candidate_id . ', attachment ID ' . $attachment_id );
		$result = $this->download_attachment( $candidate_id, $attachment_id, 'resume' );
		if ( is_wp_error( $result ) ) {
			error_log( 'Zoho Resume Download Error: ' . $result->get_error_message() );
			wp_die( 'Download failed: ' . esc_html( $result->get_error_message() ), 'Error', [ 'response' => 500 ] );
		}
		// download_attachment exits on success
	}

	public function handle_cover_letter_download() {
		if ( ! isset( $_GET['action'] ) || $_GET['action'] !== 'download_cover_letter' ) {
			return;
		}

		if ( ! is_user_logged_in() ) {
			error_log( 'Zoho Cover Letter Download Error: User not logged in' );
			wp_die( 'Please log in to download your cover letter.', 'Unauthorized', [ 'response' => 401 ] );
		}

		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'download_cover_letter' ) ) {
			error_log( 'Zoho Cover Letter Download Error: Invalid or missing nonce' );
			wp_die( 'Invalid request.', 'Unauthorized', [ 'response' => 403 ] );
		}

		if ( ! empty( $_GET['candidate_id'] ) || ! empty( $_GET['attachment_id'] ) ) {
			error_log( 'Zoho Cover Letter Download Error: Query parameters candidate_id or attachment_id not allowed' );
			wp_die( 'Invalid request.', 'Unauthorized', [ 'response' => 403 ] );
		}

		$user_id = get_current_user_id();
		$cover_letter_url = get_user_meta( $user_id, 'cover_letter_url', true );
		$stored_candidate_id = get_user_meta( $user_id, 'candidate_id', true );

		if ( empty( $cover_letter_url ) ) {
			error_log( 'Zoho Cover Letter Download Error: No cover letter found for user ID ' . $user_id );
			wp_die( 'No cover letter available for download.', 'Not Found', [ 'response' => 404 ] );
		}

		$parts = explode( '/', rtrim( $cover_letter_url, '/' ) );
		$attachment_id = end( $parts );
		$candidate_id = count( $parts ) >= 3 ? $parts[count( $parts ) - 3] : '';

		if ( ! ctype_digit( $candidate_id ) || ! ctype_digit( $attachment_id ) ) {
			error_log( 'Zoho Cover Letter Download Error: Invalid cover_letter_url format for user ID ' . $user_id . ': ' . $cover_letter_url );
			wp_die( 'Invalid cover letter data.', 'Bad Request', [ 'response' => 400 ] );
		}

		if ( ! current_user_can( 'manage_options' ) && $candidate_id !== $stored_candidate_id ) {
			error_log( 'Zoho Cover Letter Download Error: Unauthorized access for user ID ' . $user_id );
			wp_die( 'Unauthorized access.', 'Forbidden', [ 'response' => 403 ] );
		}

		error_log( 'Zoho Cover Letter Download: Triggered for user ID ' . $user_id . ', candidate ID ' . $candidate_id . ', attachment ID ' . $attachment_id );
		$result = $this->download_attachment( $candidate_id, $attachment_id, 'cover_letter' );
		if ( is_wp_error( $result ) ) {
			error_log( 'Zoho Cover Letter Download Error: ' . $result->get_error_message() );
			wp_die( 'Download failed: ' . esc_html( $result->get_error_message() ), 'Error', [ 'response' => 500 ] );
		}
		// download_attachment exits on success
	}

	public function upload_attachment( $candidate_id, $attachment_path, $attachment_category = 'Resume' ) {
		$access_token = $this->zoho_api->get_access_token();
		if ( is_wp_error( $access_token ) ) {
			error_log( "Zoho {$attachment_category} Upload Error: " . $access_token->get_error_message() );
			return $access_token;
		}
		$endpoint = 'https://recruit.zoho.eu/recruit/v2/Candidates/' . $candidate_id . '/Attachments';
		$boundary = '----WebKitFormBoundary' . uniqid();
		$file_name = basename( $attachment_path );
		$file_content = file_get_contents( $attachment_path );
		$body = "--$boundary\r\n";
		$body .= "Content-Disposition: form-data; name=\"attachments_category\"\r\n\r\n";
		$body .= "$attachment_category\r\n";
		$body .= "--$boundary\r\n";
		$body .= "Content-Disposition: form-data; name=\"file\"; filename=\"$file_name\"\r\n";
		$body .= "Content-Type: application/pdf\r\n\r\n";
		$body .= $file_content . "\r\n";
		$body .= "--$boundary--\r\n";
		error_log( "Zoho {$attachment_category} Upload Request: " . $endpoint . ' with body size: ' . strlen( $body ) . ' bytes' );
		$response = wp_remote_post( $endpoint, [
			'headers' => [
				'Authorization' => 'Zoho-oauthtoken ' . $access_token,
				'Content-Type' => "multipart/form-data; boundary=$boundary",
			],
			'body' => $body,
			'timeout' => 30,
		] );
		if ( is_wp_error( $response ) ) {
			error_log( "Zoho {$attachment_category} Upload Error: " . $response->get_error_message() );
			return $response;
		}
		$response_code = wp_remote_retrieve_response_code( $response );
		$body_response = json_decode( wp_remote_retrieve_body( $response ), true );
		error_log( "Zoho {$attachment_category} Upload Response: Code=" . $response_code . ', Body=' . print_r( $body_response, true ) );
		if ( $response_code === 201 || ($response_code === 200 && isset( $body_response['data'][0]['status'] ) && $body_response['data'][0]['status'] === 'success')) {
			return $body_response;
		}
		$error_message = isset($body_response['data'][0]['message']) ? $body_response['data'][0]['message'] : 'HTTP ' . $response_code;
		error_log( "Zoho {$attachment_category} Upload Error: " . $error_message );
		return new WP_Error( 'api_error', $error_message );
	}

	public function download_attachment( $candidate_id, $attachment_id, $attachment_type = 'resume' ) {
		$access_token = $this->zoho_api->get_access_token();
		if ( is_wp_error( $access_token ) ) {
			error_log( 'Zoho Attachment Download Error: ' . $access_token->get_error_message() );
			return new WP_Error( 'auth_error', $access_token->get_error_message() );
		}
		$endpoint = 'https://recruit.zoho.eu/recruit/v2/Candidates/' . $candidate_id . '/Attachments/' . $attachment_id;
		error_log( 'Zoho Attachment Download Request: ' . $endpoint );
		$response = wp_remote_get( $endpoint, [
			'headers' => [
				'Authorization' => 'Zoho-oauthtoken ' . $access_token,
			],
			'timeout' => 30,
		] );
		if ( is_wp_error( $response ) ) {
			error_log( 'Zoho Attachment Download Error: ' . $response->get_error_message() );
			return $response;
		}
		$response_code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$headers = wp_remote_retrieve_headers( $response );
		error_log( 'Zoho Attachment Download Response: Code=' . $response_code . ', Headers=' . print_r( $headers, true ) );
		if ( $response_code !== 200 ) {
			$body_response = json_decode( $body, true );
			$error_message = $body_response['message'] ?? 'HTTP ' . $response_code;
			error_log( 'Zoho Attachment Download Error: ' . $error_message );
			return new WP_Error( 'api_error', $error_message );
		}
		// Stream the file to the browser
		header( 'Content-Type: application/pdf' );
		header( 'Content-Disposition: attachment; filename="' . $attachment_type . '_' . $candidate_id . '.pdf"' );
		header( 'Content-Length: ' . strlen( $body ) );
		echo $body;
		exit;
	}

	private function delete_existing_resume( $candidate_id ) {
		$access_token = $this->zoho_api->get_access_token();
		if ( is_wp_error( $access_token ) ) {
			error_log( 'Zoho Attachment List Error: Failed to get access token: ' . $access_token->get_error_message() );
			return new WP_Error( 'auth_error', 'Failed to get access token: ' . $access_token->get_error_message() );
		}
		$endpoint = 'https://recruit.zoho.eu/recruit/v2/Candidates/' . $candidate_id . '/Attachments';
		error_log( 'Zoho Attachment List Request: ' . $endpoint );
		$response = wp_remote_get( $endpoint, [
			'headers' => [
				'Authorization' => 'Zoho-oauthtoken ' . $access_token,
			],
			'timeout' => 30,
		] );
		if ( is_wp_error( $response ) ) {
			error_log( 'Zoho Attachment List Error: Failed to fetch attachments: ' . $response->get_error_message() );
			return new WP_Error( 'api_error', 'Failed to fetch attachments: ' . $response->get_error_message() );
		}
		$response_code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		error_log( 'Zoho Attachment List Response: Code=' . $response_code . ', Body=' . print_r( $body, true ) );
		if ( $response_code !== 200 ) {
			$error_message = $body['message'] ?? 'HTTP ' . $response_code;
			error_log( "Zoho Attachment List Error: Failed to fetch attachments for candidate ID $candidate_id: $error_message" );
			return new WP_Error( 'api_error', "Failed to fetch attachments: $error_message" );
		}
		if ( empty( $body['data'] ) ) {
			error_log( "Zoho Attachment List: No attachments found for candidate ID $candidate_id" );
			return true; // No attachments, proceed with upload
		}
		$resume_attachment = null;
		foreach ( $body['data'] as $attachment ) {
			$category = isset( $attachment['Category']['name'] ) ? $attachment['Category']['name'] : '';
			$filename = isset( $attachment['File_Name'] ) ? $attachment['File_Name'] : '';
			error_log( "Zoho Attachment List: Checking attachment ID {$attachment['id']}, Category=$category, Filename=$filename" );
			if ( $category === 'Resume' || stripos( $filename, 'resume' ) !== false ) {
				$resume_attachment = $attachment;
				break;
			}
		}
		if ( ! $resume_attachment ) {
			error_log( "Zoho Attachment List: No resume attachment found for candidate ID $candidate_id" );
			return true; // No resume attachment, proceed with upload
		}
		$attachment_id = $resume_attachment['id'];
		$delete_endpoint = "https://recruit.zoho.eu/recruit/v2/Candidates/$candidate_id/Attachments/$attachment_id";
		$max_retries = 2;
		$retry_count = 0;
		while ( $retry_count < $max_retries ) {
			error_log( "Zoho Attachment Delete Request: $delete_endpoint (Attempt " . ( $retry_count + 1 ) . " of $max_retries)" );
			$delete_response = wp_remote_request( $delete_endpoint, [
				'method' => 'DELETE',
				'headers' => [
					'Authorization' => 'Zoho-oauthtoken ' . $access_token,
				],
				'timeout' => 30,
			] );
			$delete_response_code = wp_remote_retrieve_response_code( $delete_response );
			$delete_body = json_decode( wp_remote_retrieve_body( $delete_response ), true );
			error_log( "Zoho Attachment Delete Response: Code=$delete_response_code, Body=" . print_r( $delete_body, true ) );
			if ( $delete_response_code === 200 ) {
				error_log( "Zoho Attachment Delete Success: Deleted resume attachment ID $attachment_id for candidate ID $candidate_id" );
				return true;
			}
			$error_message = $delete_body['message'] ?? 'HTTP ' . $delete_response_code;
			error_log( "Zoho Attachment Delete Error: Failed to delete resume attachment ID $attachment_id for candidate ID $candidate_id: $error_message (Attempt " . ( $retry_count + 1 ) . ")" );
			if ( $delete_response_code >= 500 ) {
				$retry_count++;
				sleep( 1 ); // Wait before retrying
				continue;
			}
			return new WP_Error( 'api_error', "Failed to delete resume attachment: $error_message" );
		}
		error_log( "Zoho Attachment Delete Error: Exhausted retries for attachment ID $attachment_id, candidate ID $candidate_id" );
		return new WP_Error( 'api_error', 'Failed to delete resume attachment after retries' );
	}

	private function delete_existing_cover_letter( $candidate_id ) {
		$access_token = $this->zoho_api->get_access_token();
		if ( is_wp_error( $access_token ) ) {
			error_log( 'Zoho Attachment List Error: Failed to get access token: ' . $access_token->get_error_message() );
			return new WP_Error( 'auth_error', 'Failed to get access token: ' . $access_token->get_error_message() );
		}
		$endpoint = 'https://recruit.zoho.eu/recruit/v2/Candidates/' . $candidate_id . '/Attachments';
		error_log( 'Zoho Attachment List Request: ' . $endpoint );
		$response = wp_remote_get( $endpoint, [
			'headers' => [
				'Authorization' => 'Zoho-oauthtoken ' . $access_token,
			],
			'timeout' => 30,
		] );
		if ( is_wp_error( $response ) ) {
			error_log( 'Zoho Attachment List Error: Failed to fetch attachments: ' . $response->get_error_message() );
			return new WP_Error( 'api_error', 'Failed to fetch attachments: ' . $response->get_error_message() );
		}
		$response_code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		error_log( 'Zoho Attachment List Response: Code=' . $response_code . ', Body=' . print_r( $body, true ) );
		if ( $response_code !== 200 ) {
			$error_message = $body['message'] ?? 'HTTP ' . $response_code;
			error_log( "Zoho Attachment List Error: Failed to fetch attachments for candidate ID $candidate_id: $error_message" );
			return new WP_Error( 'api_error', "Failed to fetch attachments: $error_message" );
		}
		if ( empty( $body['data'] ) ) {
			error_log( "Zoho Attachment List: No attachments found for candidate ID $candidate_id" );
			return true; // No attachments, proceed with upload
		}
		$cover_letter_attachment = null;
		foreach ( $body['data'] as $attachment ) {
			$category = isset( $attachment['Category']['name'] ) ? $attachment['Category']['name'] : '';
			$filename = isset( $attachment['File_Name'] ) ? $attachment['File_Name'] : '';
			error_log( "Zoho Attachment List: Checking attachment ID {$attachment['id']}, Category=$category, Filename=$filename" );
			if ( $category === 'Cover Letter' || stripos( $filename, 'cover_letter' ) !== false ) {
				$cover_letter_attachment = $attachment;
				break;
			}
		}
		if ( ! $cover_letter_attachment ) {
			error_log( "Zoho Attachment List: No cover letter attachment found for candidate ID $candidate_id" );
			return true; // No cover letter attachment, proceed with upload
		}
		$attachment_id = $cover_letter_attachment['id'];
		$delete_endpoint = "https://recruit.zoho.eu/recruit/v2/Candidates/$candidate_id/Attachments/$attachment_id";
		$max_retries = 2;
		$retry_count = 0;
		while ( $retry_count < $max_retries ) {
			error_log( "Zoho Attachment Delete Request: $delete_endpoint (Attempt " . ( $retry_count + 1 ) . " of $max_retries)" );
			$delete_response = wp_remote_request( $delete_endpoint, [
				'method' => 'DELETE',
				'headers' => [
					'Authorization' => 'Zoho-oauthtoken ' . $access_token,
				],
				'timeout' => 30,
			] );
			$delete_response_code = wp_remote_retrieve_response_code( $delete_response );
			$delete_body = json_decode( wp_remote_retrieve_body( $delete_response ), true );
			error_log( "Zoho Attachment Delete Response: Code=$delete_response_code, Body=" . print_r( $delete_body, true ) );
			if ( $delete_response_code === 200 ) {
				error_log( "Zoho Attachment Delete Success: Deleted cover letter attachment ID $attachment_id for candidate ID $candidate_id" );
				return true;
			}
			$error_message = $delete_body['message'] ?? 'HTTP ' . $delete_response_code;
			error_log( "Zoho Attachment Delete Error: Failed to delete cover letter attachment ID $attachment_id for candidate ID $candidate_id: $error_message (Attempt " . ( $retry_count + 1 ) . ")" );
			if ( $delete_response_code >= 500 ) {
				$retry_count++;
				sleep( 1 ); // Wait before retrying
				continue;
			}
			return new WP_Error( 'api_error', "Failed to delete cover letter attachment: $error_message" );
		}
		error_log( "Zoho Attachment Delete Error: Exhausted retries for attachment ID $attachment_id, candidate ID $candidate_id" );
		return new WP_Error( 'api_error', 'Failed to delete cover letter attachment after retries' );
	}

	private function get_candidate_by_id( $candidate_id ) {
		$access_token = $this->zoho_api->get_access_token();
		if ( is_wp_error( $access_token ) ) {
			error_log( 'Zoho Get Candidate Error: Failed to get access token: ' . $access_token->get_error_message() );
			return new WP_Error( 'auth_error', 'Failed to get access token: ' . $access_token->get_error_message() );
		}
		$endpoint = 'https://recruit.zoho.eu/recruit/v2/Candidates/' . $candidate_id;
		error_log( 'Zoho Get Candidate Request: ' . $endpoint );
		$response = wp_remote_get( $endpoint, [
			'headers' => [
				'Authorization' => 'Zoho-oauthtoken ' . $access_token,
			],
			'timeout' => 30,
		] );
		if ( is_wp_error( $response ) ) {
			error_log( 'Zoho Get Candidate Error: ' . $response->get_error_message() );
			return $response;
		}
		$response_code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		error_log( 'Zoho Get Candidate Response: Code=' . $response_code . ', Body=' . print_r( $body, true ) );
		if ( $response_code !== 200 || empty( $body['data'] ) ) {
			$error_message = $body['message'] ?? 'HTTP ' . $response_code;
			error_log( 'Zoho Get Candidate Error: Invalid response or no data: ' . $error_message );
			return new WP_Error( 'api_error', 'Invalid response or no data: ' . $error_message );
		}
		return $body;
	}

	public function sync_candidate_to_zoho( $user_id ) {
		error_log( 'ORU_Candidate_Registration: sync_candidate_to_zoho triggered for user ID ' . $user_id );

		if ( ! function_exists( 'get_field' ) ) {
			error_log( 'Zoho Candidate Sync Error: ACF plugin not active for user ID ' . $user_id );
			return;
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			error_log( 'Zoho Candidate Sync Error: Invalid user ID ' . $user_id );
			return;
		}

		$email = $this->sanitization->sanitize_text( $user->user_email );
		if ( empty( $email ) ) {
			error_log( 'Zoho Candidate Sync Error: Email is required for user ID ' . $user_id );
			return;
		}

		$existing_candidate = $this->zoho_api->search_candidate_by_email( $email );
		error_log( 'Zoho Candidate Sync: Search result for email ' . $email . ': ' . print_r( $existing_candidate, true ) );

		$candidate_data = [];
		$field_mappings = [
			'first_name' => [ 'api_name' => 'First_Name', 'type' => 'text', 'source' => 'user' ],
			'last_name' => [ 'api_name' => 'Last_Name', 'type' => 'text', 'source' => 'user' ],
			'user_email' => [ 'api_name' => 'Email', 'type' => 'email', 'source' => 'user' ],
			'candidate_id' => [ 'api_name' => 'Candidate_ID', 'type' => 'text', 'source' => 'acf' ],
			'id' => [ 'api_name' => 'id', 'type' => 'number', 'source' => 'acf' ],
			'mother_tongue' => [ 'api_name' => 'Mother_tongue', 'type' => 'taxonomy_single', 'source' => 'acf', 'taxonomy' => 'language' ],
			'fluent_languages' => [ 'api_name' => 'Fluent_Languages', 'type' => 'multi_select', 'source' => 'acf', 'taxonomy' => 'language' ],
			'phone' => [ 'api_name' => 'Phone', 'type' => 'text', 'source' => 'acf' ],
			'mobile' => [ 'api_name' => 'Mobile', 'type' => 'text', 'source' => 'acf' ],
			'linkedin__s' => [ 'api_name' => 'LinkedIn__s', 'type' => 'url', 'source' => 'acf' ],
			'street' => [ 'api_name' => 'Street', 'type' => 'text', 'source' => 'acf' ],
			'city' => [ 'api_name' => 'City', 'type' => 'text', 'source' => 'acf' ],
			'state' => [ 'api_name' => 'State', 'type' => 'text', 'source' => 'acf' ],
			'zip_code' => [ 'api_name' => 'Zip_Code', 'type' => 'text', 'source' => 'acf' ],
			'country' => [ 'api_name' => 'Country', 'type' => 'taxonomy_single', 'source' => 'acf', 'taxonomy' => 'country' ],
			'experience_in_years' => [ 'api_name' => 'Experience_in_Years', 'type' => 'number', 'source' => 'acf' ],
			'current_job_title' => [ 'api_name' => 'Current_Job_Title', 'type' => 'text', 'source' => 'acf' ],
			'type_of_work_you_are_interested_in' => [ 'api_name' => 'Type_of_work_you_are_interested_in', 'type' => 'multi_select', 'source' => 'acf' ],
			'locations_you_are_willing_to_work' => [ 'api_name' => 'Locations_you_are_willing_to_work', 'type' => 'textarea', 'source' => 'acf' ],
			'sector_type_of_role_you_are_interested_in' => [ 'api_name' => 'Sector_Type_of_role_you_are_interested_in', 'type' => 'multi_select', 'source' => 'acf', 'taxonomy' => 'sector' ],
			'job_roles_you_are_interested_in' => [ 'api_name' => 'Job_Roles_you_are_interested_in', 'type' => 'multi_select', 'source' => 'acf' ],
			'highest_qualification_held' => [ 'api_name' => 'Highest_Qualification_Held', 'type' => 'text', 'source' => 'acf' ],
			'current_salary' => [ 'api_name' => 'Current_Salary', 'type' => 'number', 'source' => 'acf' ],
			'expected_salary' => [ 'api_name' => 'Expected_Salary', 'type' => 'number', 'source' => 'acf' ],
			'additional_info' => [ 'api_name' => 'Additional_Info', 'type' => 'textarea', 'source' => 'acf' ],
			'skill_set' => [ 'api_name' => 'Skill_Set', 'type' => 'taxonomy_multi', 'source' => 'acf', 'taxonomy' => 'skill' ],
		];

		foreach ( $field_mappings as $wp_field => $mapping ) {
			$value = null;
			if ( $mapping['source'] === 'user' ) {
				$value = $user->$wp_field ?? '';
			} elseif ( $mapping['source'] === 'acf' ) {
				$value = get_field( $wp_field, 'user_' . $user_id );
			}

			if ( $value !== null && $value !== '' ) {
				if ( $mapping['type'] === 'taxonomy_single' && isset( $mapping['taxonomy'] ) ) {
					$term = get_term( $value, $mapping['taxonomy'] );
					$value = $term && ! is_wp_error( $term ) ? $term->name : '';
					if ( $mapping['api_name'] === 'Mother_tongue' && empty( $value ) ) {
						error_log( 'Zoho Candidate Sync Warning: Invalid Mother_tongue value for term ID ' . $value . ' for user ID ' . $user_id );
					}
				} elseif ( $mapping['type'] === 'taxonomy_multi' && isset( $mapping['taxonomy'] ) ) {
					$terms = get_terms( [
						'taxonomy' => $mapping['taxonomy'],
						'include' => (array) $value,
						'hide_empty' => false,
					] );
					$value = ! is_wp_error( $terms ) ? wp_list_pluck( $terms, 'name' ) : [];
					$value = ! empty( $value ) ? implode( ', ', $value ) : '';
				} elseif ( $mapping['type'] === 'multi_select' ) {
					$value = is_array( $value ) ? $value : [ $value ];
					if ( isset( $mapping['taxonomy'] ) ) {
						$terms = get_terms( [
							'taxonomy' => $mapping['taxonomy'],
							'include' => $value,
							'hide_empty' => false,
						] );
						$value = ! is_wp_error( $terms ) ? wp_list_pluck( $terms, 'name' ) : [];
					}
				} elseif ( $mapping['type'] === 'text' ) {
					$value = is_array( $value ) ? reset( $value ) : $value;
					$value = $this->sanitization->sanitize_text( $value );
				} elseif ( $mapping['type'] === 'number' ) {
					$value = (int) $value;
				} elseif ( $mapping['type'] === 'email' || $mapping['type'] === 'url' || $mapping['type'] === 'textarea' ) {
					$value = $this->sanitization->sanitize_text( $value );
				}
				if ( ! empty( $value ) || ( is_array( $value ) && count( $value ) > 0 ) ) {
					$candidate_data[$mapping['api_name']] = $value;
				}
			}
		}

		error_log( 'Zoho Candidate Sync: Prepared candidate data for user ID ' . $user_id . ': ' . print_r( $candidate_data, true ) );

		if ( empty( $candidate_data['Email'] ) ) {
			error_log( 'Zoho Candidate Sync Error: Email is required for user ID ' . $user_id );
			return;
		}

		$zoho_id = null;
		$zoho_candidate_id = '';
		if ( ! is_wp_error( $existing_candidate ) && isset( $existing_candidate['id'] ) && isset( $existing_candidate['candidate_id'] ) ) {
			$zoho_id = $existing_candidate['id'];
			if ( isset( $candidate_data['Skill_Set'] ) ) {
				$clear_result = $this->update_candidate( $existing_candidate['id'], [ 'Skill_Set' => null ] );
				if ( is_wp_error( $clear_result ) ) {
					error_log( 'Zoho Candidate Sync Error: Failed to clear Skill_Set for user ID ' . $user_id . ': ' . $clear_result->get_error_message() );
				} else {
					error_log( 'Zoho Candidate Sync: Cleared Skill_Set for Zoho ID ' . $existing_candidate['id'] );
				}
			}

			if ( isset( $candidate_data['Mother_tongue'] ) ) {
				$mother_tongue_result = $this->update_candidate( $existing_candidate['id'], [ 'Mother_tongue' => $candidate_data['Mother_tongue'] ] );
				if ( is_wp_error( $mother_tongue_result ) ) {
					error_log( 'Zoho Candidate Sync Error: Failed to update Mother_tongue for user ID ' . $user_id . ': ' . $mother_tongue_result->get_error_message() );
				} else {
					error_log( 'Zoho Candidate Sync: Updated Mother_tongue for Zoho ID ' . $existing_candidate['id'] . ' to ' . $candidate_data['Mother_tongue'] );
				}
			}

			$result = $this->update_candidate( $existing_candidate['id'], $candidate_data );
			if ( is_wp_error( $result ) ) {
				error_log( 'Zoho Candidate Sync Error: Failed to update candidate for user ID ' . $user_id . ': ' . $result->get_error_message() );
				return;
			}
			update_user_meta( $user_id, 'id', $existing_candidate['id'] );
			update_user_meta( $user_id, 'candidate_id', $existing_candidate['candidate_id'] );
			$acf_result1 = update_field( 'field_candidate_id', $existing_candidate['candidate_id'], 'user_' . $user_id );
			$acf_result2 = update_field( 'field_id', (string) $existing_candidate['id'], 'user_' . $user_id );
			error_log( 'Zoho Candidate Sync: ACF update results for existing candidate, user ID ' . $user_id . ': candidate_id=' . var_export( $acf_result1, true ) . ', id=' . var_export( $acf_result2, true ) );
			$stored_candidate_id = get_field( 'field_candidate_id', 'user_' . $user_id );
			$stored_id = get_field( 'field_id', 'user_' . $user_id );
			error_log( 'Zoho Candidate Sync: Verified ACF fields for user ID ' . $user_id . ': candidate_id=' . var_export( $stored_candidate_id, true ) . ', id=' . var_export( $stored_id, true ) );
			$meta_candidate_id = get_user_meta( $user_id, 'candidate_id', true );
			$meta_id = get_user_meta( $user_id, 'id', true );
			error_log( 'Zoho Candidate Sync: Verified wp_usermeta for user ID ' . $user_id . ': candidate_id=' . var_export( $meta_candidate_id, true ) . ', id=' . var_export( $meta_id, true ) );
			error_log( 'Zoho Candidate Sync Success: Updated existing candidate for user ID ' . $user_id . ': Zoho ID ' . $existing_candidate['id'] . ', Candidate_ID ' . $existing_candidate['candidate_id'] );
			$zoho_id = $existing_candidate['id'];
			$zoho_candidate_id = $existing_candidate['candidate_id'];
		} else {
			error_log( 'Zoho Candidate Sync: No existing candidate found for email ' . $email . ', attempting to create new candidate' );
			$result = $this->zoho_api->create_candidate( $candidate_data );
			if ( is_wp_error( $result ) ) {
				error_log( 'Zoho Candidate Sync Error: Failed to create candidate for user ID ' . $user_id . ': ' . $result->get_error_message() );
				return;
			}
			if ( isset( $result['id'] ) ) {
				$zoho_id = $result['id'];
				$zoho_candidate_id = $result['candidate_id'] ?? '';
				if ( empty( $zoho_candidate_id ) ) {
					error_log( 'Zoho Candidate Sync: Candidate_ID missing for user ID ' . $user_id . ', attempting to fetch via GET' );
					$full_candidate = $this->get_candidate_by_id( $zoho_id );
					if ( ! is_wp_error( $full_candidate ) && isset( $full_candidate['data'][0]['Candidate_ID'] ) ) {
						$zoho_candidate_id = $full_candidate['data'][0]['Candidate_ID'];
						error_log( 'Zoho Candidate Sync: Successfully fetched Candidate_ID ' . $zoho_candidate_id . ' for user ID ' . $user_id . ' via GET request' );
					} else {
						$error_message = is_wp_error( $full_candidate ) ? $full_candidate->get_error_message() : 'No Candidate_ID in GET response';
						error_log( 'Zoho Candidate Sync Warning: Failed to fetch Candidate_ID for user ID ' . $user_id . ': ' . $error_message );
					}
				} else {
					error_log( 'Zoho Candidate Sync: Candidate_ID ' . $zoho_candidate_id . ' retrieved from POST response for user ID ' . $user_id );
				}
			} else {
				error_log( 'Zoho Candidate Sync Error: No candidate ID returned from Zoho for user ID ' . $user_id . ': Response=' . print_r( $result, true ) );
				return;
			}
		}

		// Save candidate data regardless of Candidate_ID retrieval
		if ( $zoho_id ) {
			update_user_meta( $user_id, 'id', $zoho_id );
			update_user_meta( $user_id, 'candidate_id', $zoho_candidate_id );
			$acf_result1 = update_field( 'field_candidate_id', $zoho_candidate_id, 'user_' . $user_id );
			$acf_result2 = update_field( 'field_id', (string) $zoho_id, 'user_' . $user_id );
			error_log( 'Zoho Candidate Sync: ACF update results for user ID ' . $user_id . ': candidate_id=' . var_export( $acf_result1, true ) . ', id=' . var_export( $acf_result2, true ) );
			$stored_candidate_id = get_field( 'field_candidate_id', 'user_' . $user_id );
			$stored_id = get_field( 'field_id', 'user_' . $user_id );
			error_log( 'Zoho Candidate Sync: Verified ACF fields for user ID ' . $user_id . ': candidate_id=' . var_export( $stored_candidate_id, true ) . ', id=' . var_export( $stored_id, true ) );
			$meta_candidate_id = get_user_meta( $user_id, 'candidate_id', true );
			$meta_id = get_user_meta( $user_id, 'id', true );
			error_log( 'Zoho Candidate Sync: Verified wp_usermeta for user ID ' . $user_id . ': candidate_id=' . var_export( $meta_candidate_id, true ) . ', id=' . var_export( $meta_id, true ) );
			error_log( 'Zoho Candidate Sync Success: Processed candidate for user ID ' . $user_id . ': Zoho ID ' . $zoho_id . ', Candidate_ID ' . ($zoho_candidate_id ?: 'not retrieved') );
		}

		// Handle resume upload
		$resume_path = get_user_meta( $user_id, '_temp_resume_path', true );
		if ( $resume_path && file_exists( $resume_path ) && $zoho_id ) {
			$delete_result = $this->delete_existing_resume( $zoho_id );
			if ( is_wp_error( $delete_result ) ) {
				error_log( 'Zoho Candidate Sync Error: Failed to delete existing resume for user ID ' . $user_id . ': ' . $delete_result->get_error_message() );
			}
			$upload_response = $this->upload_attachment( $zoho_id, $resume_path, 'Resume' );
			if ( ! is_wp_error( $upload_response ) && isset( $upload_response['data'][0]['details']['id'] ) ) {
				$file_id = $upload_response['data'][0]['details']['id'];
				$attachment_url = 'https://recruit.zoho.eu/recruit/v2/Candidates/' . $zoho_id . '/Attachments/' . $file_id;
				update_user_meta( $user_id, 'resume_url', $attachment_url );
				if ( file_exists( $resume_path ) ) {
					if ( unlink( $resume_path ) ) {
						error_log( 'Zoho Candidate Sync: Deleted temporary resume file for user ID ' . $user_id . ': ' . $resume_path );
					} else {
						error_log( 'Zoho Candidate Sync Warning: Failed to delete temporary resume file for user ID ' . $user_id . ': ' . $resume_path );
					}
				}
				delete_user_meta( $user_id, '_temp_resume_path' );
				error_log( 'Zoho Candidate Sync: Resume uploaded for user ID ' . $user_id . ' to Zoho ID ' . $zoho_id . ', URL: ' . $attachment_url );
			} else {
				$error_message = is_wp_error( $upload_response ) ? $upload_response->get_error_message() : print_r( $upload_response, true );
				error_log( 'Zoho Candidate Sync Error: Failed to upload resume for user ID ' . $user_id . ': ' . $error_message );
			}
		}

		// Handle cover letter upload (optional)
		$cover_letter_path = get_user_meta( $user_id, '_temp_cover_letter_path', true );
		if ( $cover_letter_path && file_exists( $cover_letter_path ) && $zoho_id ) {
			$delete_result = $this->delete_existing_cover_letter( $zoho_id );
			if ( is_wp_error( $delete_result ) ) {
				error_log( 'Zoho Candidate Sync Error: Failed to delete existing cover letter for user ID ' . $user_id . ': ' . $delete_result->get_error_message() );
			}
			$upload_response = $this->upload_attachment( $zoho_id, $cover_letter_path, 'Cover Letter' );
			if ( ! is_wp_error( $upload_response ) && isset( $upload_response['data'][0]['details']['id'] ) ) {
				$file_id = $upload_response['data'][0]['details']['id'];
				$attachment_url = 'https://recruit.zoho.eu/recruit/v2/Candidates/' . $zoho_id . '/Attachments/' . $file_id;
				update_user_meta( $user_id, 'cover_letter_url', $attachment_url );
				if ( file_exists( $cover_letter_path ) ) {
					if ( unlink( $cover_letter_path ) ) {
						error_log( 'Zoho Candidate Sync: Deleted temporary cover letter file for user ID ' . $user_id . ': ' . $cover_letter_path );
					} else {
						error_log( 'Zoho Candidate Sync Warning: Failed to delete temporary cover letter file for user ID ' . $user_id . ': ' . $cover_letter_path );
					}
				}
				delete_user_meta( $user_id, '_temp_cover_letter_path' );
				error_log( 'Zoho Candidate Sync: Cover letter uploaded for user ID ' . $user_id . ' to Zoho ID ' . $zoho_id . ', URL: ' . $attachment_url );
			} else {
				$error_message = is_wp_error( $upload_response ) ? $upload_response->get_error_message() : print_r( $upload_response, true );
				error_log( 'Zoho Candidate Sync Error: Failed to upload cover letter for user ID ' . $user_id . ': ' . $error_message );
			}
		}
	}

	public function update_candidate( $candidate_id, $candidate_data ) {
		$access_token = $this->zoho_api->get_access_token();
		if ( is_wp_error( $access_token ) ) {
			error_log( 'Zoho Update Candidate Error: ' . $access_token->get_error_message() );
			return $access_token;
		}
		$api_base_url = 'https://recruit.zoho.eu/recruit/v2/';
		$endpoint = $api_base_url . 'Candidates/' . $candidate_id;
		error_log( 'Zoho Update Candidate Request: ' . $endpoint . ' with data: ' . print_r( $candidate_data, true ) );
		$response = wp_remote_request( $endpoint, [
			'method' => 'PUT',
			'headers' => [
				'Authorization' => 'Zoho-oauthtoken ' . $access_token,
				'Content-Type' => 'application/json',
			],
			'body' => json_encode( [ 'data' => [ $candidate_data ] ] ),
			'timeout' => 30,
		] );
		if ( is_wp_error( $response ) ) {
			error_log( 'Zoho Update Candidate Error: ' . $response->get_error_message() );
			return $response;
		}
		$response_code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		error_log( 'Zoho Update Candidate Response: Code=' . $response_code . ', Body=' . print_r( $body, true ) );
		if ( $response_code !== 200 ) {
			$error_message = $body['data'][0]['message'] ?? 'HTTP ' . $response_code;
			error_log( 'Zoho Update Candidate Error: ' . $error_message );
			return new WP_Error( 'api_error', $error_message );
		}
		if ( isset( $body['data'][0]['details']['id'] ) ) {
			return [
				'id' => $body['data'][0]['details']['id'],
				'candidate_id' => $body['data'][0]['details']['Candidate_ID'] ?? '',
			];
		}
		error_log( 'Zoho Update Candidate Error: No candidate ID returned from Zoho' );
		return new WP_Error( 'no_data', 'No candidate ID returned from Zoho.' );
	}
}
?>
