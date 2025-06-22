<?php
/**
 * Class for Zoho job synchronization.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
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
                    'post_title' => $this->sanitization->sanitize_text( $job['Job_Opening_Name'] ?? 'Untitled Job' ),
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
                            $value = $this->sanitization->sanitize_text( $value );
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
                        $term_name = $this->sanitization->sanitize_text( trim( $term_name ) );
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