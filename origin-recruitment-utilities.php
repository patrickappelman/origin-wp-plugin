<?php
/*
 * Plugin Name: Origin Recruitment - Utilities
 * Description: A custom plugin developed for Origin Recruitment by Appelman Designs to augment WordPress to include a Jobs post type, as well as custom tag taxonomy such as Languages, Countries, and Industries.
 * Version: 1.0.12
 * Author: Appelman Designs
 * Author URI: https://appelmandesigns.com/
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// Load configuration
require_once plugin_dir_path( __FILE__ ) . 'config.php';

// Load classes
require_once plugin_dir_path( __FILE__ ) . 'classes/OriginRecruitmentUtilities.php';
require_once plugin_dir_path( __FILE__ ) . 'classes/ORU_Post_Types.php';
require_once plugin_dir_path( __FILE__ ) . 'classes/ORU_Taxonomies.php';
require_once plugin_dir_path( __FILE__ ) . 'classes/ORU_Sanitization.php';
require_once plugin_dir_path( __FILE__ ) . 'classes/ORU_Zoho_Auth.php';
require_once plugin_dir_path( __FILE__ ) . 'classes/ORU_Zoho_API.php';
require_once plugin_dir_path( __FILE__ ) . 'classes/ORU_Zoho_Sync.php';
require_once plugin_dir_path( __FILE__ ) . 'classes/ORU_Admin_Settings.php';

// Initialize the plugin
OriginRecruitmentUtilities::get_instance();
