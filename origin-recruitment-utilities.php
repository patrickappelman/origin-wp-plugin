<?php
/*
 * Plugin Name: Origin Recruitment - Utilities
 * Description: A custom plugin developed for Origin Recruitment by Appelman Designs to augment WordPress to include a Jobs post type, as well as custom tag taxonomy such as Languages, Countries, and Industries.
 * Version: 1.0.16
 * Author: Appelman Designs
 * Author URI: https://appelmandesigns.com/
 * Path: wp-content/plugins/origin-recruitment-utilities/origin-recruitment-utilities.php
 */

if (!defined('ABSPATH')) {
	exit;
}

// Load configuration
require_once plugin_dir_path(__FILE__) . 'config.php';

// Debug multiple instantiations
add_action( 'plugins_loaded', function() {
	error_log( 'ORU: plugins_loaded triggered, backtrace: ' . wp_debug_backtrace_summary() );
});

// Load classes
require_once plugin_dir_path( __FILE__ ) . 'classes/OriginRecruitmentUtilities.php';
require_once plugin_dir_path( __FILE__ ) . 'classes/ORU_Post_Types.php';
require_once plugin_dir_path( __FILE__ ) . 'classes/ORU_Taxonomies.php';
require_once plugin_dir_path( __FILE__ ) . 'classes/ORU_Sanitization.php';
require_once plugin_dir_path( __FILE__ ) . 'classes/ORU_Zoho_Auth.php';
require_once plugin_dir_path( __FILE__ ) . 'classes/ORU_Zoho_API.php';
require_once plugin_dir_path( __FILE__ ) . 'classes/ORU_Zoho_Sync.php';
require_once plugin_dir_path( __FILE__ ) . 'classes/ORU_Zoho_Cron.php';
require_once plugin_dir_path( __FILE__ ) . 'classes/ORU_Admin_Settings.php';
require_once plugin_dir_path( __FILE__ ) . 'classes/ORU_Candidate_Registration.php';
require_once plugin_dir_path( __FILE__ ) . 'classes/ORU_Candidate_Application.php';

// Initialize the plugin
OriginRecruitmentUtilities::get_instance();

// Register assets for job filtering (archive page)
add_action( 'wp_enqueue_scripts', 'jobs_filter_enqueue_assets', 20 );
function jobs_filter_enqueue_assets() {
	if ( is_post_type_archive( 'job' ) ) {
		wp_enqueue_script(
			'jobs-filter',
			plugin_dir_url( __FILE__ ) . 'assets/js/jobs-filter.js',
			[ 'ourmainjs' ],
			null,
			true
		);
		wp_localize_script( 'jobs-filter', 'jobsFilter', [
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'jobs_filter_nonce' ),
			'archiveurl' => get_post_type_archive_link( 'job' ),
		] );
	}
}

// Register assets for job application (single job page)
add_action( 'wp_enqueue_scripts', 'job_application_enqueue_assets', 20 );
function job_application_enqueue_assets() {
	if ( is_singular( 'job' ) ) {
		wp_enqueue_script(
			'job-application',
			plugin_dir_url( __FILE__ ) . 'assets/js/job-application.js',
			[ 'ourmainjs' ],
			null,
			true
		);
		wp_localize_script( 'job-application', 'jobApplication', [
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
			'application_nonce' => wp_create_nonce( 'oru_submit_application_nonce' ),
		] );
	}
}

// Override archive template for 'job' post type
add_filter( 'archive_template', 'jobs_filter_archive_template' );
function jobs_filter_archive_template( $template ) {
	if ( is_post_type_archive( 'job' ) ) {
		$plugin_template = plugin_dir_path( __FILE__ ) . 'templates/archive-job.php';
		if ( file_exists( $plugin_template ) ) {
			return $plugin_template;
		}
	}
	return $template;
}

// AJAX handler for filtering jobs
add_action( 'wp_ajax_filter_jobs', 'jobs_filter_callback' );
add_action( 'wp_ajax_nopriv_filter_jobs', 'jobs_filter_callback' );
function jobs_filter_callback() {
	// Validate nonce
	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'jobs_filter_nonce' ) ) {
		wp_send_json_error( [ 'message' => 'Invalid nonce' ], 403 );
		wp_die();
	}

	// Validate query
	if ( ! isset( $_POST['query'] ) ) {
		wp_send_json_error( [ 'message' => 'Missing query parameter' ], 400 );
		wp_die();
	}

	$query_params = json_decode( stripslashes( $_POST['query'] ), true );
	if ( $query_params === null ) {
		wp_send_json_error( [ 'message' => 'Invalid query JSON' ], 400 );
		wp_die();
	}

	$paged = isset( $_POST['page'] ) ? max( 1, intval( $_POST['page'] ) ) : 1;

	// Validate post type and meta field
	if ( ! post_type_exists( 'job' ) ) {
		wp_send_json_error( [ 'message' => 'Post type "job" not registered' ], 500 );
		wp_die();
	}

	if ( ! function_exists( 'get_field' ) && ! metadata_exists( 'post', 0, 'job_opening_status' ) ) {
		wp_send_json_error( [ 'message' => 'ACF field "job_opening_status" not available' ], 500 );
		wp_die();
	}

	$args = [
		'post_type' => 'job',
		'posts_per_page' => 10,
		'paged' => $paged,
		'meta_query' => [
			'relation' => 'AND',
		],
		'tax_query' => [
			'relation' => 'AND',
		],
		's' => isset( $query_params['search'] ) ? sanitize_text_field( $query_params['search'] ) : '',
	];

	// Handle job_opening_status
	$status_query = isset( $query_params['job_opening_status'] ) ? array_map( 'sanitize_text_field', (array) $query_params['job_opening_status'] ) : [ 'in-progress' ];
	if ( ! in_array( 'all', $status_query ) ) {
		$args['meta_query'][] = [
			'key' => 'job_opening_status',
			'value' => $status_query,
			'compare' => 'IN',
		];
	}

	$taxonomies = [ 'language', 'country', 'industry', 'sector' ];
	foreach ( $taxonomies as $taxonomy ) {
		if ( ! empty( $query_params[$taxonomy] ) && taxonomy_exists( $taxonomy ) ) {
			$args['tax_query'][] = [
				'taxonomy' => $taxonomy,
				'field' => 'slug',
				'terms' => array_map( 'sanitize_text_field', (array) $query_params[$taxonomy] ),
			];
		} elseif ( ! empty( $query_params[$taxonomy] ) ) {
			error_log( "Jobs Filter AJAX: Taxonomy '$taxonomy' not registered" );
		}
	}

	// Fetch all applications for the logged-in user
	$applications = [];
	$user_id = get_current_user_id();
	$zoho_candidate_id = $user_id ? get_user_meta( $user_id, 'id', true ) : '';
	if ( $user_id && $zoho_candidate_id ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'job_applications';
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT zoho_job_id, zoho_application_status FROM $table_name WHERE zoho_candidate_id = %d",
				$zoho_candidate_id
			),
			ARRAY_A
		);
		if ( $results ) {
			foreach ( $results as $row ) {
				$applications[$row['zoho_job_id']] = $row['zoho_application_status'];
			}
		}
	}

	try {
		$query = new WP_Query( $args );
		ob_start();
		if ( $query->have_posts() ) :
			while ( $query->have_posts() ) : $query->the_post();
				$job_status = get_field( 'job_opening_status' );
				$zoho_job_id = get_field( 'id' );
				$application_status = isset( $applications[$zoho_job_id] ) ? $applications[$zoho_job_id] : '';
				$arr_job_status = [
					'In-progress' => [ 'alert-type' => 'success', 'can-apply' => true, 'msg' => 'This job is now accepting applications.' ],
					'Filled' => [ 'alert-type' => 'danger', 'can-apply' => false, 'msg' => 'This job has been filled.' ],
					'Cancelled' => [ 'alert-type' => 'danger', 'can-apply' => false, 'msg' => 'This job is no longer available.' ],
					'Declined' => [ 'alert-type' => 'danger', 'can-apply' => false, 'msg' => 'This job is no longer available.' ],
					'Inactive' => [ 'alert-type' => 'danger', 'can-apply' => false, 'msg' => 'This job is no longer available.' ],
					'Submitted by client' => [ 'alert-type' => 'warning', 'can-apply' => false, 'msg' => 'This job is no longer available.' ],
				];
				?>
				<article class="job-listing p-single text-gray-default dark:text-white-default bg-[#f5f5f5] dark:bg-[#222222]">
					<?php if ( ! $arr_job_status[$job_status]['can-apply'] ) : ?>
						<div class="alert-soft alert-soft--<?php echo esc_attr( $arr_job_status[$job_status]['alert-type'] ); ?> mb-half" role="alert" tabindex="-1" aria-labelledby="job-status-label">
							<span id="job-status-label" class="font-bold">Job Status:</span> <?php echo esc_html( $arr_job_status[$job_status]['msg'] ); ?>
						</div>
					<?php elseif ( $application_status ) : ?>
						<div class="alert-soft alert-soft--success mb-half" role="alert" tabindex="-1" aria-labelledby="application-status-label">
							<span id="application-status-label" class="font-bold">Application Status:</span> Applied
						</div>
					<?php endif; ?>
					<div class="job-listing__meta-row"><time datetime="<?php echo get_the_date( 'Y-m-d' ); ?>">Posted on <?php echo get_the_date( 'F j, Y' ); ?></time></div>
					<h3 class="job-listing__title mb-single 2xl:mb-half"><?php the_title(); ?></h3>
					<ul class="job-listing__details-list gap-6 mb-single 2xl:mb-half grid grid-cols-2">
						<li>
							<i class="fa-solid fa-fw fa-globe" alt="Languages"></i>
							<?php
							$arr_language_terms = get_the_terms( get_the_ID(), 'language' );
							$languages = ! empty( $arr_language_terms ) ? implode( ', ', wp_list_pluck( $arr_language_terms, 'name' ) ) : '';
							echo '<div>' . esc_html( $languages ) . '</div>';
							?>
						</li>
						<li>
							<i class="fa-solid fa-fw fa-location-dot" alt="Location"></i>
							<?php
							$arr_country_terms = get_the_terms( get_the_ID(), 'country' );
							$arr_location = [];
							if ( ! empty( get_field( 'city' ) ) ) {
								$arr_location['city'] = get_field( 'city' );
							}
							if ( ! empty( $arr_country_terms ) ) {
								$arr_location['country'] = $arr_country_terms[0]->name;
							}
							$location = ! empty( $arr_location ) ? implode( ', ', $arr_location ) : '';
							if ( get_field( 'remote_job' ) ) {
								$location = 'Remote';
							}
							echo '<div>' . esc_html( $location ) . '</div>';
							?>
						</li>
						<li>
							<i class="fa-solid fa-fw fa-building" alt="Industry"></i>
							<?php
							$arr_industry_terms = get_the_terms( get_the_ID(), 'industry' );
							$industries = ! empty( $arr_industry_terms ) ? implode( ', ', wp_list_pluck( $arr_industry_terms, 'name' ) ) : '';
							echo '<div>' . esc_html( $industries ) . '</div>';
							?>
						</li>
						<li>
							<i class="fa-solid fa-fw fa-tags" alt="Sector"></i>
							<?php
							$arr_sector_terms = get_the_terms( get_the_ID(), 'sector' );
							$sectors = ! empty( $arr_sector_terms ) ? implode( ', ', wp_list_pluck( $arr_sector_terms, 'name' ) ) : '';
							echo '<div>' . esc_html( $sectors ) . '</div>';
							?>
						</li>
						<li>
							<i class="fa-solid fa-fw fa-briefcase" alt="Employment Type"></i>
							<?php echo esc_html( get_field( 'job_type' ) ); ?>
						</li>
						<li class="col-span-2">
							<i class="fa-solid fa-fw fa-money-bills" alt="Salary"></i>
							<?php echo esc_html( get_field( 'salary' ) ); ?>
						</li>
					</ul>
					<div>
						<?php if ( $arr_job_status[$job_status]['can-apply'] ) : ?>
							<?php if ( !$application_status ) : ?>
								<a href="<?php echo esc_url( get_the_permalink() ); ?>#Apply" class="button mr-2.5"><i class="fa-solid fa-pen-to-square"></i> Apply Now</a>
							<?php endif; ?>
						<?php endif; ?>
						<a href="<?php echo esc_url( get_the_permalink() ); ?>" class="button button--outline">View full listing</a>
					</div>
				</article>
			<?php endwhile;

			$archive_url = get_post_type_archive_link( 'job' );
			echo '<nav class="pagination" aria-label="Pagination">';
			echo paginate_links( [
				'base' => str_replace( 999999999, '%#%', esc_url( $archive_url . '%_%' ) ),
				'format' => '?page=%#%',
				'total' => $query->max_num_pages,
				'current' => $paged,
				'show_all' => false,
				'prev_next' => true,
				'add_args' => array_filter( $query_params, fn( $value ) => $value !== '' && $value !== false && $value !== null ),
				'next_text' => '>',
				'prev_text' => '<',
				'add_fragment' => '#jobs-results',
			] );
			echo '</nav>';
			// jobs-results
		else :
			echo '<p>No jobs found matching your criteria.</p>';
		endif;
		wp_reset_postdata();

		$output = ob_get_clean();
		if ( ! is_string( $output ) ) {
			wp_send_json_error( [ 'message' => 'Invalid output format' ], 500 );
			wp_die();
		}
		wp_send_json_success( $output );
	} catch ( Exception $e ) {
		error_log( 'Jobs Filter AJAX Error: ' . $e->getMessage() );
		wp_send_json_error( [ 'message' => 'Server error: ' . $e->getMessage() ], 500 );
		wp_die();
	}
}
?>
