<?php
/**
 * Class for registering job post types.
 * Path: wp-content/plugins/origin-recruitment-utilities/classes/ORU_Post_Types.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class ORU_Post_Types {
	public function __construct() {
		add_action( 'init', [ $this, 'register_post_types' ] );
	}

	public function register_post_types() {
		register_post_type( 'job', [
			'labels' => [
				'name' => __( 'Jobs' ),
				'singular_name' => __( 'Job' ),
				'menu_name' => __( 'Jobs', 'Admin Menu text', 'textdomain' ),
				'name_admin_bar' => __( 'Job', 'Add New on Toolbar', 'textdomain' ),
				'add_new' => __( 'Add New', 'textdomain' ),
				'add_new_item' => __( 'Add Job', 'textdomain' ),
				'new_item' => __( 'New Job', 'textdomain' ),
				'edit_item' => __( 'Edit Job', 'textdomain' ),
				'view_item' => __( 'View Job', 'textdomain' ),
				'all_items' => __( 'All Jobs', 'textdomain' ),
				'search_items' => __( 'Search Jobs', 'textdomain' ),
				'parent_item_colon' => __( 'Parent Jobs:', 'textdomain' ),
				'not_found' => __( 'No jobs found.', 'textdomain' ),
				'not_found_in_trash' => __( 'No jobs found in Trash.', 'textdomain' ),
				'featured_image' => _x( 'Job Cover Image', 'Overrides the “Featured Image” phrase for this post type. Added in 4.3', 'textdomain' ),
				'set_featured_image' => _x( 'Set cover image', 'Overrides the “Set featured image” phrase for this post type. Added in 4.3', 'textdomain' ),
				'remove_featured_image' => _x( 'Remove cover image', 'Overrides the “Remove featured image” phrase for this post type. Added in 4.3', 'textdomain' ),
				'use_featured_image' => _x( 'Use as cover image', 'Overrides the “Use as featured image” phrase for this post type. Added in 4.3', 'textdomain' ),
				'archives' => _x( 'Job archives', 'The post type archive label used in nav menus. Default “Post Archives”. Added in 4.4', 'textdomain' ),
				'insert_into_item' => _x( 'Insert into job', 'Overrides the “Insert into post”/”Insert into page” phrase (used when inserting media into a post). Added in 4.4', 'textdomain' ),
				'uploaded_to_this_item' => _x( 'Uploaded to this job', 'Overrides the “Uploaded to this post”/”Uploaded to this page” phrase (used when viewing media attached to a post). Added in 4.4', 'textdomain' ),
				'filter_items_list' => _x( 'Filter jobs list', 'Screen reader text for the filter links heading on the post type listing screen. Default “Filter posts list”/”Filter pages list”. Added in 4.4', 'textdomain' ),
				'items_list_navigation' => _x( 'Jobs list navigation', 'Screen reader text for the pagination heading on the post type listing screen. Default “Posts list navigation”/”Pages list navigation”. Added in 4.4', 'textdomain' ),
				'items_list' => _x( 'Jobs list', 'Screen reader text for the items list heading on the post type listing screen. Default “Posts list”/”Pages list”. Added in 4.4', 'textdomain' ),
			],
			'description' => 'A WordPress custom post type for jobs.',
			'public' => true,
			'publicly_queryable' => true,
			'show_ui' => true,
			'supports' => [ 'title', 'editor', 'excerpt', 'custom-fields' ],
			'show_in_rest' => true,
			'hierarchical' => false,
			'rewrite' => [ 'slug' => 'jobs', 'with_front' => false ],
			'capability_type' => 'post',
			'taxonomies' => [ 'country' ],
			'has_archive' => true,
			'menu_icon' => 'dashicons-businessman',
		] );
	}
}
