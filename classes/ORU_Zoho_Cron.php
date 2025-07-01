<?php
/**
 * Class for handling Zoho Recruit cron jobs.
 * Path: wp-content/plugins/origin-recruitment-utilities/classes/ORU_Zoho_Cron.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class ORU_Zoho_Cron {
	private $zoho_api;
	private $zoho_sync;
	private $sanitization;
	private $candidate_application;

	public function __construct( ORU_Zoho_API $zoho_api, ORU_Zoho_Sync $zoho_sync, ORU_Sanitization $sanitization, ORU_Candidate_Application $candidate_application ) {
		$this->zoho_api = $zoho_api;
		$this->zoho_sync = $zoho_sync;
		$this->sanitization = $sanitization;
		$this->candidate_application = $candidate_application;
		error_log( 'ORU_Zoho_Cron: Constructor called' );
	}

	public function sync_updated_jobs() {
		$access_token = $this->zoho_api->get_access_token();
		if ( is_wp_error( $access_token ) ) {
			error_log( 'Zoho Cron Job Sync Error: ' . $access_token->get_error_message() );
			return $access_token;
		}

		$last_sync = get_option( 'zoho_cron_last_jobs_sync', 0 );
		$last_sync_time = $last_sync ? gmdate( 'Y-m-d\TH:i:s+00:00', $last_sync ) : '';
		$criteria = $last_sync_time ? '((Modified_Time:greater_than:' . $last_sync_time . ')OR(Created_Time:greater_than:' . $last_sync_time . ')OR(Last_Activity_Time:greater_than:' . $last_sync_time . '))' : '';
		$api_base_url = 'https://recruit.zoho.eu/recruit/v2/';
		$endpoint = $api_base_url . 'Job_Openings/search' . ($criteria ? '?criteria=' . urlencode( $criteria ) : '');
		error_log( 'Zoho Cron Job Sync Request: ' . $endpoint );

		$response = wp_remote_get( $endpoint, [
			'headers' => [
				'Authorization' => 'Zoho-oauthtoken ' . $access_token,
			],
			'timeout' => 30,
		]);

		if ( is_wp_error( $response ) ) {
			error_log( 'Zoho Cron Job Sync Error: ' . $response->get_error_message() );
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		error_log( 'Zoho Cron Job Sync Response: ' . print_r( $body, true ) );

		if ( ! isset( $body['data'] ) || empty( $body['data'] ) ) {
			error_log( 'Zoho Cron Job Sync: No updated job openings found' );
			update_option( 'zoho_cron_last_jobs_sync', time() );
			return 'No updated job openings found.';
		}

		$result = $this->zoho_sync->sync_jobs( [ 'data' => $body['data'] ] );

		if ( is_wp_error( $result ) ) {
			error_log( 'Zoho Cron Job Sync Error: ' . $result->get_error_message() );
			return $result;
		}

		update_option( 'zoho_cron_last_jobs_sync', time() );
		error_log( 'Zoho Cron Job Sync Success: ' . $result );
		return $result;
	}

	public function sync_updated_applications() {
		global $wpdb;
		$access_token = $this->zoho_api->get_access_token();
		if ( is_wp_error( $access_token ) ) {
			error_log( 'Zoho Cron Application Sync Error: ' . $access_token->get_error_message() );
			return $access_token;
		}

		$last_sync = get_option( 'zoho_cron_last_applications_sync', 0 );
		$last_sync_time = $last_sync ? gmdate( 'Y-m-d\TH:i:s+00:00', $last_sync ) : '';
		$criteria = $last_sync_time ? '((Updated_On:greater_than:' . $last_sync_time . ')OR(Created_Time:greater_than:' . $last_sync_time . '))' : '';
		$api_base_url = 'https://recruit.zoho.eu/recruit/v2/';
		$endpoint = $api_base_url . 'Applications/search' . ($criteria ? '?criteria=' . urlencode( $criteria ) : '');
		error_log( 'Zoho Cron Application Sync Request: ' . $endpoint );

		$response = wp_remote_get( $endpoint, [
			'headers' => [
				'Authorization' => 'Zoho-oauthtoken ' . $access_token,
			],
			'timeout' => 30,
		]);

		if ( is_wp_error( $response ) ) {
			error_log( 'Zoho Cron Application Sync Error: ' . $response->get_error_message() );
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		error_log( 'Zoho Cron Application Sync Response: ' . print_r( $body, true ) );

		if ( ! isset( $body['data'] ) || empty( $body['data'] ) ) {
			error_log( 'Zoho Cron Application Sync: No updated applications found' );
			update_option( 'zoho_cron_last_applications_sync', time() );
			return 'No updated applications found.';
		}

		$table_name = $wpdb->prefix . 'job_applications';
		$synced_applications = 0;

		foreach ( $body['data'] as $application ) {
			if ( empty( $application['id'] ) || empty( $application['Candidate']['id'] ) || empty( $application['$Job_Opening_Id'] ) ) {
				error_log( 'Zoho Cron Application Sync: Missing required fields for application ID ' . ($application['id'] ?? 'unknown') );
				continue;
			}

			$zoho_application_id = $this->sanitization->sanitize_text( $application['id'] );
			$zoho_candidate_id = $this->sanitization->sanitize_text( $application['Candidate']['id'] );
			$zoho_job_id = $this->sanitization->sanitize_text( $application['$Job_Opening_Id'] );
			$zoho_application_status = $this->sanitization->sanitize_text( $application['Application_Status'] ?? 'Associated' );
			$created_at = isset( $application['Created_Time'] ) ? gmdate( 'Y-m-d H:i:s', strtotime( $application['Created_Time'] ) ) : current_time( 'mysql', true );
			$updated_at = isset( $application['Last_Activity_Time'] ) ? gmdate( 'Y-m-d H:i:s', strtotime( $application['Last_Activity_Time'] ) ) : current_time( 'mysql', true );

			$existing_application = $wpdb->get_var( $wpdb->prepare(
				"SELECT id FROM $table_name WHERE zoho_application_id = %s",
				$zoho_application_id
			));

			$application_data = [
				'zoho_candidate_id' => $zoho_candidate_id,
				'zoho_job_id' => $zoho_job_id,
				'zoho_application_id' => $zoho_application_id,
				'zoho_application_status' => $zoho_application_status,
				'created_at' => $created_at,
				'updated_at' => $updated_at,
			];

			if ( $existing_application ) {
				$result = $wpdb->update(
					$table_name,
					$application_data,
					[ 'zoho_application_id' => $zoho_application_id ],
					[ '%s', '%s', '%s', '%s', '%s', '%s' ],
					[ '%s' ]
				);
				$application_id = $existing_application;
			} else {
				$result = $wpdb->insert(
					$table_name,
					$application_data,
					[ '%s', '%s', '%s', '%s', '%s', '%s' ]
				);
				$application_id = $wpdb->insert_id;
			}

			if ( false === $result ) {
				error_log( 'Zoho Cron Application Sync: Failed to sync application ID ' . $zoho_application_id . ': ' . $wpdb->last_error );
				continue;
			}

			error_log( 'Zoho Cron Application Sync: Synced application ID ' . $zoho_application_id . ' to WP ID ' . $application_id );
			$synced_applications++;
		}

		update_option( 'zoho_cron_last_applications_sync', time() );
		$message = sprintf( 'Successfully synchronized %d applications.', $synced_applications );
		error_log( 'Zoho Cron Application Sync Success: ' . $message );
		return $message;
	}

	public function sync_updated_candidates() {
		$access_token = $this->zoho_api->get_access_token();
		if ( is_wp_error( $access_token ) ) {
			error_log( 'Zoho Cron Candidate Sync Error: ' . $access_token->get_error_message() );
			return $access_token;
		}

		// Get registered WordPress user emails
		$users = get_users( [ 'fields' => [ 'user_email' ] ] );
		$emails = array_map( function( $user ) {
			return $user->user_email;
		}, $users );

		if ( empty( $emails ) ) {
			error_log( 'Zoho Cron Candidate Sync: No registered users found' );
			update_option( 'zoho_cron_last_candidates_sync', time() );
			return 'No registered users to sync candidates.';
		}

		$last_sync = get_option( 'zoho_cron_last_candidates_sync', 0 );
		$last_sync_time = $last_sync ? gmdate( 'Y-m-d\TH:i:s+00:00', $last_sync ) : '';
		$criteria = $last_sync_time ? '((Updated_On:greater_than:' . $last_sync_time . ')OR(Created_Time:greater_than:' . $last_sync_time . '))' : '';
		$api_base_url = 'https://recruit.zoho.eu/recruit/v2/';
		$endpoint = $api_base_url . 'Candidates/search' . ($criteria ? '?criteria=' . urlencode( $criteria ) : '');
		error_log( 'Zoho Cron Candidate Sync Request: ' . $endpoint );

		$response = wp_remote_get( $endpoint, [
			'headers' => [
				'Authorization' => 'Zoho-oauthtoken ' . $access_token,
			],
			'timeout' => 30,
		]);

		if ( is_wp_error( $response ) ) {
			error_log( 'Zoho Cron Candidate Sync Error: ' . $response->get_error_message() );
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		error_log( 'Zoho Cron Candidate Sync Response: ' . print_r( $body, true ) );

		if ( ! isset( $body['data'] ) || empty( $body['data'] ) ) {
			error_log( 'Zoho Cron Candidate Sync: No updated candidates found' );
			update_option( 'zoho_cron_last_candidates_sync', time() );
			return 'No updated candidates found.';
		}

		$synced_candidates = 0;

		foreach ( $body['data'] as $candidate ) {
			if ( empty( $candidate['Email'] ) || ! in_array( $candidate['Email'], $emails ) ) {
				error_log( 'Zoho Cron Candidate Sync: Skipping candidate ID ' . ($candidate['id'] ?? 'unknown') . ' - Email not registered' );
				continue;
			}

			$user = get_user_by( 'email', $candidate['Email'] );
			if ( ! $user ) {
				error_log( 'Zoho Cron Candidate Sync: No WP user found for email ' . $candidate['Email'] );
				continue;
			}

			$user_id = $user->ID;
			$zoho_id = $candidate['id'] ?? '';
			$zoho_candidate_id = $candidate['Candidate_ID'] ?? '';

			if ( empty( $zoho_id ) ) {
				error_log( 'Zoho Cron Candidate Sync: Missing ID for candidate email ' . $candidate['Email'] );
				continue;
			}

			$field_mappings = [
				'First_Name' => [ 'wp_field' => 'first_name', 'type' => 'text', 'source' => 'user' ],
				'Last_Name' => [ 'wp_field' => 'last_name', 'type' => 'text', 'source' => 'user' ],
				'Email' => [ 'wp_field' => 'user_email', 'type' => 'email', 'source' => 'user' ],
				'Candidate_ID' => [ 'wp_field' => 'candidate_id', 'type' => 'text', 'source' => 'meta' ],
				'id' => [ 'wp_field' => 'id', 'type' => 'text', 'source' => 'meta' ],
				'Mother_tongue' => [ 'wp_field' => 'mother_tongue', 'type' => 'taxonomy_single', 'source' => 'acf', 'taxonomy' => 'language' ],
				'Fluent_Languages' => [ 'wp_field' => 'fluent_languages', 'type' => 'taxonomy_multi', 'source' => 'acf', 'taxonomy' => 'language' ],
				'Phone' => [ 'wp_field' => 'phone', 'type' => 'text', 'source' => 'acf' ],
				'Mobile' => [ 'wp_field' => 'mobile', 'type' => 'text', 'source' => 'acf' ],
				'LinkedIn__s' => [ 'wp_field' => 'linkedin__s', 'type' => 'url', 'source' => 'acf' ],
				'Street' => [ 'wp_field' => 'street', 'type' => 'text', 'source' => 'acf' ],
				'City' => [ 'wp_field' => 'city', 'type' => 'text', 'source' => 'acf' ],
				'State' => [ 'wp_field' => 'state', 'type' => 'text', 'source' => 'acf' ],
				'Zip_Code' => [ 'wp_field' => 'zip_code', 'type' => 'text', 'source' => 'acf' ],
				'Country' => [ 'wp_field' => 'country', 'type' => 'taxonomy_single', 'source' => 'acf', 'taxonomy' => 'country' ],
				'Experience_in_Years' => [ 'wp_field' => 'experience_in_years', 'type' => 'number', 'source' => 'acf' ],
				'Current_Job_Title' => [ 'wp_field' => 'current_job_title', 'type' => 'text', 'source' => 'acf' ],
				'Type_of_work_you_are_interested_in' => [ 'wp_field' => 'type_of_work_you_are_interested_in', 'type' => 'multi_select', 'source' => 'acf' ],
				'Locations_you_are_willing_to_work' => [ 'wp_field' => 'locations_you_are_willing_to_work', 'type' => 'textarea', 'source' => 'acf' ],
				'Sector_Type_of_role_you_are_interested_in' => [ 'wp_field' => 'sector_type_of_role_you_are_interested_in', 'type' => 'taxonomy_multi', 'source' => 'acf', 'taxonomy' => 'sector' ],
				'Job_Roles_you_are_interested_in' => [ 'wp_field' => 'job_roles_you_are_interested_in', 'type' => 'multi_select', 'source' => 'acf' ],
				'Highest_Qualification_Held' => [ 'wp_field' => 'highest_qualification_held', 'type' => 'text', 'source' => 'acf' ],
				'Current_Salary' => [ 'wp_field' => 'current_salary', 'type' => 'number', 'source' => 'acf' ],
				'Expected_Salary' => [ 'wp_field' => 'expected_salary', 'type' => 'number', 'source' => 'acf' ],
				'Additional_Info' => [ 'wp_field' => 'additional_info', 'type' => 'textarea', 'source' => 'acf' ],
				'Skill_Set' => [ 'wp_field' => 'skill_set', 'type' => 'taxonomy_multi', 'source' => 'acf', 'taxonomy' => 'skill' ],
			];

			foreach ( $field_mappings as $zoho_field => $mapping ) {
				if ( isset( $candidate[$zoho_field] ) && $candidate[$zoho_field] !== '' ) {
					$value = $candidate[$zoho_field];
					if ( $mapping['type'] === 'taxonomy_single' && isset( $mapping['taxonomy'] ) ) {
						$term = term_exists( $value, $mapping['taxonomy'] );
						if ( $term ) {
							$value = (int) $term['term_id'];
						} else {
							$new_term = wp_insert_term( $value, $mapping['taxonomy'] );
							$value = ! is_wp_error( $new_term ) ? (int) $new_term['term_id'] : '';
							if ( ! is_wp_error( $new_term ) ) {
								error_log( 'Zoho Cron Candidate Sync: Created term ' . $value . ' for taxonomy ' . $mapping['taxonomy'] . ' for user ID ' . $user_id );
							}
						}
					} elseif ( $mapping['type'] === 'taxonomy_multi' && isset( $mapping['taxonomy'] ) ) {
						$values = is_array( $value ) ? $value : explode( ', ', $value );
						$term_ids = [];
						foreach ( $values as $val ) {
							if ( empty( $val ) ) {
								continue;
							}
							$term = term_exists( trim( $val ), $mapping['taxonomy'] );
							if ( $term ) {
								$term_ids[] = (int) $term['term_id'];
							} else {
								$new_term = wp_insert_term( trim( $val ), $mapping['taxonomy'] );
								if ( ! is_wp_error( $new_term ) ) {
									$term_ids[] = (int) $new_term['term_id'];
									error_log( 'Zoho Cron Candidate Sync: Created term ' . $val . ' for taxonomy ' . $mapping['taxonomy'] . ' for user ID ' . $user_id );
								}
							}
						}
						$value = $term_ids;
					} elseif ( $mapping['type'] === 'multi_select' ) {
						$value = is_array( $value ) ? implode( ', ', array_map( [ $this->sanitization, 'sanitize_text' ], $value ) ) : $this->sanitization->sanitize_text( $value );
					} elseif ( $mapping['type'] === 'number' ) {
						$value = (int) $value;
					} elseif ( $mapping['type'] === 'text' || $mapping['type'] === 'email' || $mapping['type'] === 'url' || $mapping['type'] === 'textarea' ) {
						$value = $this->sanitization->sanitize_text( $value );
					}
					if ( $mapping['source'] === 'user' ) {
						wp_update_user( [ 'ID' => $user_id, $mapping['wp_field'] => $value ] );
						error_log( 'Zoho Cron Candidate Sync: Updated user field ' . $mapping['wp_field'] . ' for user ID ' . $user_id );
					} elseif ( $mapping['source'] === 'meta' || $mapping['source'] === 'acf' ) {
						update_field( $mapping['wp_field'], $value, 'user_' . $user_id );
						error_log( 'Zoho Cron Candidate Sync: Updated field ' . $mapping['wp_field'] . ' for user ID ' . $user_id );
					}
				}
			}

			update_user_meta( $user_id, 'id', $zoho_id );
			update_user_meta( $user_id, 'candidate_id', $zoho_candidate_id );
			update_field( 'field_candidate_id', $zoho_candidate_id, 'user_' . $user_id );
			update_field( 'field_id', (string) $zoho_id, 'user_' . $user_id );
			error_log( 'Zoho Cron Candidate Sync: Updated user ID ' . $user_id . ' with Zoho ID ' . $zoho_id . ', Candidate_ID ' . $zoho_candidate_id );
			$synced_candidates++;
		}

		update_option( 'zoho_cron_last_candidates_sync', time() );
		$message = sprintf( 'Successfully synchronized %d candidates.', $synced_candidates );
		error_log( 'Zoho Cron Candidate Sync Success: ' . $message );
		return $message;
	}
}
?>
