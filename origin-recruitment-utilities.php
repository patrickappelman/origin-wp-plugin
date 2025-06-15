<?php
/*
* Plugin Name: Origin Recruitment - Utilities
* Description: A custom plugin developed for Origin Recruitment by Appelman Designs to augment Wordpress to include a Jobs post type, as well as custom tag taxonomy such as Languages, Countries, and Industries.
* Version: 1.0.0
* Author: Appelman Designs
* Author URI: https://appelmandesigns.com/
*/



/* DEFINE CUSTOM POST TYPES */

function or_create_posttypes() {

  // Register custom post type: Jobs

  register_post_type( 'job',
    array(
      'labels' => array(
        'name' => __( 'Jobs' ),
        'singular_name'         => __( 'Job' ),
        'menu_name'             => __( 'Jobs', 'Admin Menu text', 'textdomain' ),
        'name_admin_bar'        => __( 'Job', 'Add New on Toolbar', 'textdomain' ),
        'add_new'               => __( 'Add New', 'textdomain' ),
        'add_new_item'          => __( 'Add Job', 'textdomain' ),
        'new_item'              => __( 'New Job', 'textdomain' ),
        'edit_item'             => __( 'Edit Job', 'textdomain' ),
        'view_item'             => __( 'View Job', 'textdomain' ),
        'all_items'             => __( 'All Jobs', 'textdomain' ),
        'search_items'          => __( 'Search Jobs', 'textdomain' ),
        'parent_item_colon'     => __( 'Parent Jobs:', 'textdomain' ),
        'not_found'             => __( 'No jobs found.', 'textdomain' ),
        'not_found_in_trash'    => __( 'No jobs found in Trash.', 'textdomain' ),
        'featured_image'        => _x( 'Job Cover Image', 'Overrides the “Featured Image” phrase for this post type. Added in 4.3', 'textdomain' ),
        'set_featured_image'    => _x( 'Set cover image', 'Overrides the “Set featured image” phrase for this post type. Added in 4.3', 'textdomain' ),
        'remove_featured_image' => _x( 'Remove cover image', 'Overrides the “Remove featured image” phrase for this post type. Added in 4.3', 'textdomain' ),
        'use_featured_image'    => _x( 'Use as cover image', 'Overrides the “Use as featured image” phrase for this post type. Added in 4.3', 'textdomain' ),
        'archives'              => _x( 'Job archives', 'The post type archive label used in nav menus. Default “Post Archives”. Added in 4.4', 'textdomain' ),
        'insert_into_item'      => _x( 'Insert into job', 'Overrides the “Insert into post”/”Insert into page” phrase (used when inserting media into a post). Added in 4.4', 'textdomain' ),
        'uploaded_to_this_item' => _x( 'Uploaded to this job', 'Overrides the “Uploaded to this post”/”Uploaded to this page” phrase (used when viewing media attached to a post). Added in 4.4', 'textdomain' ),
        'filter_items_list'     => _x( 'Filter jobs list', 'Screen reader text for the filter links heading on the post type listing screen. Default “Filter posts list”/”Filter pages list”. Added in 4.4', 'textdomain' ),
        'items_list_navigation' => _x( 'Jobs list navigation', 'Screen reader text for the pagination heading on the post type listing screen. Default “Posts list navigation”/”Pages list navigation”. Added in 4.4', 'textdomain' ),
        'items_list'            => _x( 'Jobs list', 'Screen reader text for the items list heading on the post type listing screen. Default “Posts list”/”Pages list”. Added in 4.4', 'textdomain' ),
      ),
      'description'        => 'A Wordpress custom post type for jobs.',
      'public'             => true,
      'publicly_queryable' => true,
      'show_ui'            => true,
      'supports'           => array( 'title', 'editor', 'excerpt', 'custom-fields' ),
      'show_in_rest'       => true,
      'hierarchical'       => false,
      'rewrite'            => array('slug' => 'jobs', 'with_front' => false),

      'capability_type'    => 'post',
      'taxonomies'         => array('country'),
      'has_archive'        => false,
      'menu_icon'          => 'dashicons-businessman',
      
    )
  );

}

add_action( 'init', 'or_create_posttypes' );



function or_register_custom_taxonomies() {

	// Register Taxonomy - Languages

	register_taxonomy( 'language', [ 'post', 'page', 'job' ], array(
		'labels'            => array(
			'name'              => _x( 'Languages', 'taxonomy general name' ),
			'singular_name'     => _x( 'Language', 'taxonomy singular name' ),
			'search_items'      => __( 'Search Languages' ),
			'all_items'         => __( 'All Languages' ),
			'parent_item'       => __( 'Parent Language' ),
			'parent_item_colon' => __( 'Parent Language:' ),
			'edit_item'         => __( 'Edit Language' ),
			'update_item'       => __( 'Update Language' ),
			'add_new_item'      => __( 'Add New Language' ),
			'new_item_name'     => __( 'New Language Name' ),
			'menu_name'         => __( 'Languages' ),
		),
		'hierarchical'      => false, // make it hierarchical (like categories)
		'show_ui'           => true,
		'show_in_menu'      => true,
		'show_in_rest'      => true,
		'show_in_nav_menus' => true,
		'meta_box_cb'       => 'post_tags_meta_box',
		'show_admin_column' => true,
		'query_var'         => true,
		'rewrite'           => [ 'slug' => 'languages', 'with_front' => false, 'hierarchical' => false ],
	));



	// Register Taxonomy - Countries

	register_taxonomy( 'country', [ 'post', 'page', 'job' ], array(
		'labels'            => array(
			'name'              => _x( 'Countries', 'taxonomy general name' ),
			'singular_name'     => _x( 'Country', 'taxonomy singular name' ),
			'search_items'      => __( 'Search Countries' ),
			'all_items'         => __( 'All Countries' ),
			'parent_item'       => __( 'Parent Country' ),
			'parent_item_colon' => __( 'Parent Country:' ),
			'edit_item'         => __( 'Edit Country' ),
			'update_item'       => __( 'Update Country' ),
			'add_new_item'      => __( 'Add New Country' ),
			'new_item_name'     => __( 'New Country Name' ),
			'menu_name'         => __( 'Countries' ),
		),
		'hierarchical'      => false, // make it hierarchical (like categories)
		'show_ui'           => true,
		'show_in_menu'      => true,
		'show_in_rest'      => true,
		'show_in_nav_menus' => true,
		'meta_box_cb'       => 'post_tags_meta_box',
		'show_admin_column' => true,
		'query_var'         => true,
		'rewrite'           => [ 'slug' => 'locations', 'with_front' => false, 'hierarchical' => false ],
	));



	// Register Taxonomy - Industries

	register_taxonomy( 'industry', [ 'post', 'page', 'job' ], array(
		'labels'            => array(
			'name'              => _x( 'Industries', 'taxonomy general name' ),
			'singular_name'     => _x( 'Industry', 'taxonomy singular name' ),
			'search_items'      => __( 'Search Industries' ),
			'all_items'         => __( 'All Industries' ),
			'parent_item'       => __( 'Parent Industry' ),
			'parent_item_colon' => __( 'Parent Industry:' ),
			'edit_item'         => __( 'Edit Industry' ),
			'update_item'       => __( 'Update Industry' ),
			'add_new_item'      => __( 'Add New Industry' ),
			'new_item_name'     => __( 'New Industry Name' ),
			'menu_name'         => __( 'Industries' ),
		),
		'hierarchical'      => false, // make it hierarchical (like categories)
		'show_ui'           => true,
		'show_in_menu'      => true,
		'show_in_rest'      => true,
		'show_in_nav_menus' => true,
		'meta_box_cb'       => 'post_categories_meta_box',
		'show_admin_column' => true,
		'query_var'         => true,
		'rewrite'           => [ 'slug' => 'industries', 'with_front' => false, 'hierarchical' => false ],
	));



	// Register Taxonomy - Sectors

	register_taxonomy( 'sector', [ 'post', 'page', 'job' ], array(
		'labels'            => array(
			'name'              => _x( 'Sectors', 'taxonomy general name' ),
			'singular_name'     => _x( 'Sector', 'taxonomy singular name' ),
			'search_items'      => __( 'Search Sectors' ),
			'all_items'         => __( 'All Sectors' ),
			'parent_item'       => __( 'Parent Sector' ),
			'parent_item_colon' => __( 'Parent Sector:' ),
			'edit_item'         => __( 'Edit Sector' ),
			'update_item'       => __( 'Update Sector' ),
			'add_new_item'      => __( 'Add New Sector' ),
			'new_item_name'     => __( 'New Sector Name' ),
			'menu_name'         => __( 'Sectors' ),
		),
		'hierarchical'      => false, // make it hierarchical (like categories)
		'show_ui'           => true,
		'show_in_menu'      => true,
		'show_in_rest'      => true,
		'show_in_nav_menus' => true,
		'meta_box_cb'       => 'post_categories_meta_box',
		'show_admin_column' => true,
		'query_var'         => true,
		'rewrite'           => [ 'slug' => 'sectors', 'with_front' => false, 'hierarchical' => false ],
	));



}

add_action( 'init', 'or_register_custom_taxonomies' );
