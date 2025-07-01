<?php
/**
 * Class for registering taxonomies.
 * Path: wp-content/plugins/origin-recruitment-utilities/classes/ORU_Taxonomies.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class ORU_Taxonomies {
	public function __construct() {
		add_action( 'init', [ $this, 'register_taxonomies' ] );
	}

	public function register_taxonomies() {
		$taxonomies = [
			'language' => [
				'labels' => [
					'name' => _x( 'Languages', 'taxonomy general name' ),
					'singular_name' => _x( 'Language', 'taxonomy singular name' ),
					'search_items' => __( 'Search Languages' ),
					'all_items' => __( 'All Languages' ),
					'parent_item' => __( 'Parent Language' ),
					'parent_item_colon' => __( 'Parent Language:' ),
					'edit_item' => __( 'Edit Language' ),
					'update_item' => __( 'Update Language' ),
					'add_new_item' => __( 'Add New Language' ),
					'new_item_name' => __( 'New Language Name' ),
					'menu_name' => __( 'Languages' ),
				],
				'slug' => 'languages',
				'meta_box_cb' => 'post_tags_meta_box',
				'hierarchical' => false,
			],
			'country' => [
				'labels' => [
					'name' => _x( 'Countries', 'taxonomy general name' ),
					'singular_name' => _x( 'Country', 'taxonomy singular name' ),
					'search_items' => __( 'Search Countries' ),
					'all_items' => 'All Countries',
					'parent_item' => __( 'Parent Country' ),
					'parent_item_colon' => 'Parent Country:',
					'edit_item' => 'Edit Country',
					'update_item' => __( 'Update Country' ),
					'add_new_item' => __( 'Add New Country' ),
					'new_item_name' => 'New Country Name',
					'menu_name' => __( 'Countries' ),
				],
				'slug' => 'locations',
				'meta_box_cb' => 'post_tags_meta_box',
				'hierarchical' => false,
			],
			'industry' => [
				'labels' => [
					'name' => _x( 'Industries', 'taxonomy general name' ),
					'singular_name' => _x( 'Industry', 'taxonomy singular name' ),
					'search_items' => __( 'Search Industries' ),
					'all_items' => __( 'All Industries' ),
					'parent_item' => __( 'Parent Industry' ),
					'parent_item_colon' => __( 'Parent Industry:' ),
					'edit_item' => __( 'Edit Industry' ),
					'update_item' => __( 'Update Industry' ),
					'add_new_item' => __( 'Add New Industry' ),
					'new_item_name' => 'New Industry Name',
					'menu_name' => __( 'Industries' ),
				],
				'slug' => 'industries',
				'meta_box_cb' => 'post_categories_meta_box',
				'hierarchical' => false,
			],
			'sector' => [
				'labels' => [
					'name' => _x( 'Sectors', 'taxonomy general name' ),
					'singular_name' => _x( 'Sector', 'taxonomy singular name' ),
					'search_items' => __( 'Search Sectors' ),
					'all_items' => __( 'All Sectors' ),
					'parent_item' => __( 'Parent Sector' ),
					'parent_item_colon' => __( 'Parent Sector:' ),
					'edit_item' => __( 'Edit Sector' ),
					'update_item' => __( 'Update Sector' ),
					'add_new_item' => __( 'Add New Sector' ),
					'new_item_name' => 'New Sector Name',
					'menu_name' => __( 'Sectors' ),
				],
				'slug' => 'sectors',
				'meta_box_cb' => 'post_categories_meta_box',
				'hierarchical' => false,
			],
			'skill' => [
				'labels' => [
					'name' => _x( 'Skills', 'taxonomy general name' ),
					'singular_name' => _x( 'Skill', 'taxonomy singular name' ),
					'search_items' => __( 'Search Skills' ),
					'all_items' => __( 'All Skills' ),
					'parent_item' => __( 'Parent Skill' ),
					'parent_item_colon' => __( 'Parent Skill:' ),
					'edit_item' => __( 'Edit Skill' ),
					'update_item' => __( 'Update Skill' ),
					'add_new_item' => __( 'Add New Skill' ),
					'new_item_name' => 'New Skill Name',
					'menu_name' => __( 'Skills' ),
				],
				'slug' => 'skills',
				'meta_box_cb' => 'post_tags_meta_box',
				'hierarchical' => false,
			],
		];

		foreach ( $taxonomies as $taxonomy => $args ) {
			register_taxonomy( $taxonomy, [ 'post', 'page', 'job' ], [
				'labels' => $args['labels'],
				'hierarchical' => $args['hierarchical'],
				'show_ui' => true,
				'show_in_menu' => true,
				'show_in_rest' => true,
				'show_in_nav_menus' => true,
				'meta_box_cb' => $args['meta_box_cb'],
				'show_admin_column' => true,
				'query_var' => true,
				'rewrite' => [ 'slug' => $args['slug'], 'with_front' => false, 'hierarchical' => false ],
			] );
		}
	}
}
