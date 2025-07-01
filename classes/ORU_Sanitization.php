<?php
/**
 * Class for sanitization logic.
 * Path: wp-content/plugins/origin-recruitment-utilities/classes/ORU_Sanitization.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ORU_Sanitization {
	public function __construct() {
		add_action( 'admin_init', [ $this, 'test_sanitization' ] );
	}

	public function sanitize_job_description( $content ) {
		$content = html_entity_decode( $content, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$content = str_replace( [ "\xc2\xa0", " " ], ' ', $content );
		error_log( 'Zoho Description Sanitization Input: ' . substr( $content, 0, 1000 ) );
		$allowed_tags = [
			'p' => [ 'class' => [], 'id' => [] ],
			'h1' => [ 'class' => [], 'id' => [] ],
			'h2' => [ 'class' => [], 'id' => [] ],
			'h3' => [ 'class' => [], 'id' => [] ],
			'h4' => [ 'class' => [], 'id' => [] ],
			'h5' => [ 'class' => [], 'id' => [] ],
			'h6' => [ 'class' => [], 'id' => [] ],
			'ul' => [ 'class' => [], 'id' => [] ],
			'ol' => [ 'class' => [], 'id' => [] ],
			'li' => [ 'class' => [], 'id' => [] ],
		];
		$sanitized = wp_kses( $content, $allowed_tags );
		error_log( 'Zoho Description Sanitization Output: ' . substr( $sanitized, 0, 1000 ) );
		return $sanitized;
	}

	public function sanitize_text( $text ) {
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$text = str_replace( [ "\xc2\xa0", " " ], ' ', $text );
		error_log( 'Zoho Text Sanitization Input: ' . $text );
		$sanitized = sanitize_text_field( $text );
		error_log( 'Zoho Text Sanitization Output: ' . $sanitized );
		return $sanitized;
	}

	public function test_sanitization() {
		if ( current_user_can( 'manage_options' ) && isset( $_GET['zoho_test_sanitization'] ) ) {
			$test_html = '<h2 style="color: red;">Test Heading</h2><p class="intro" style="font-size: 16px;">Test paragraph with <strong>bold</strong> and <em>italic</em>.</p><ul><li>Item 1</li><li>Item 2</li></ul><table><tr><td>Cell</td></tr></table><div>Invalid div</div><p>Test' . "\xc2\xa0" . 'paragraph</p>';
			$sanitized = $this->sanitize_job_description( $test_html );
			$test_text = 'Test Job Text' . "\xc2\xa0" . 'with Non-Breaking Space';
			$sanitized_text = $this->sanitize_text( $test_text );
			add_action( 'admin_notices', function() use ( $test_html, $sanitized, $test_text, $sanitized_text ) {
				echo '<div class="notice notice-info">';
				echo '<p><strong>Sanitization Test Input (Description):</strong> ' . esc_html( substr( $test_html, 0, 500 ) ) . '</p>';
				echo '<p><strong>Output (Description):</strong> ' . esc_html( substr( $sanitized, 0, 500 ) ) . '</p>';
				echo '<p><strong>Sanitization Test Input (Text):</strong> ' . esc_html( $test_text ) . '</p>';
				echo '<p><strong>Output (Text):</strong> ' . esc_html( $sanitized_text ) . '</p>';
				echo '</div>';
			});
			error_log( 'Zoho Description Sanitization Test Input: ' . $test_html );
			error_log( 'Zoho Description Sanitization Test Output: ' . $sanitized );
			error_log( 'Zoho Text Sanitization Test Input: ' . $test_text );
			error_log( 'Zoho Text Sanitization Test Output: ' . $sanitized_text );
		}
	}
}
