<?php
namespace HgSpam;

/**
 * Falha ao consultar o servidor de decisão.
 * $error_code: config | connection | http | invalid_json | invalid_response
 */
class DecisionException extends \RuntimeException {
	private string $error_code;

	public function __construct( string $error_code, string $message ) {
		parent::__construct( $message );
		$this->error_code = $error_code;
	}

	public function getErrorCode(): string {
		return $this->error_code;
	}
}
