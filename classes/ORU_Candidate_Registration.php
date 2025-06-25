<?php
/**
 * Class for handling candidate registration and Zoho syncing.
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
	}
}
