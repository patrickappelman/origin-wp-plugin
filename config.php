<?php
/**
 * Configuration file for Origin Recruitment Utilities plugin.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// Define redirect URI
if ( defined( 'WP_LOCAL_DEV' ) && WP_LOCAL_DEV ) {
    $current_host = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? 'origin-recruitment:8890';
    define( 'ZOHO_RECRUIT_REDIRECT_URI', $current_host !== 'origin-recruitment:8890'
        ? rtrim( ( isset( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http' ) . '://' . $current_host, '/' ) . '/wp-admin/admin.php?page=zoho-recruit-auth-callback'
        : 'https://origin-recruitment.com/wp-admin/admin.php?page=zoho-recruit-auth-callback' );
} else {
    define( 'ZOHO_RECRUIT_REDIRECT_URI', 'https://origin-recruitment.com/wp-admin/admin.php?page=zoho-recruit-auth-callback' );
}