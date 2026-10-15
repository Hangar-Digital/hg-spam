<?php
use HgSpam\Client;
use HgSpam\DecisionException;
use PHPUnit\Framework\TestCase;

final class ClientTest extends TestCase {

	public function test_build_payload_follows_contract(): void {
		$p = Client::build_payload( 'clef-flash', 'nome: Ana', 'Isso é spam?' );

		$this->assertSame( 'clef-flash', $p['model'] );
		$this->assertSame( 'nome: Ana', $p['state'] );
		$q = $p['questions']['safe_to_run'];
		$this->assertSame( 'noul', $q['type'] );
		$this->assertSame( 'Isso é spam?', $q['instructions'] );
		$this->assertSame( array( 'true' => 'Sim', 'false' => 'Não' ), $q['criteria'] );
	}

	public function test_parse_score_returns_noul_as_float(): void {
		$body = array( 'answers' => array( 'safe_to_run' => array( 'type' => 'noul', 'noul' => 0.5 ) ) );
		$this->assertSame( 0.5, Client::parse_score( $body ) );
	}

	public function test_parse_score_accepts_integer_bounds(): void {
		$one  = array( 'answers' => array( 'safe_to_run' => array( 'noul' => 1 ) ) );
		$zero = array( 'answers' => array( 'safe_to_run' => array( 'noul' => 0 ) ) );
		$this->assertSame( 1.0, Client::parse_score( $one ) );
		$this->assertSame( 0.0, Client::parse_score( $zero ) );
	}

	public function test_parse_score_rejects_missing_answer(): void {
		$this->expectException( DecisionException::class );
		Client::parse_score( array( 'model' => 'x' ) );
	}

	public function test_parse_score_rejects_non_numeric(): void {
		$this->expectException( DecisionException::class );
		Client::parse_score( array( 'answers' => array( 'safe_to_run' => array( 'noul' => 'alto' ) ) ) );
	}

	public function test_parse_score_rejects_above_one(): void {
		$this->expectException( DecisionException::class );
		Client::parse_score( array( 'answers' => array( 'safe_to_run' => array( 'noul' => 1.2 ) ) ) );
	}

	public function test_parse_score_rejects_negative(): void {
		$this->expectException( DecisionException::class );
		Client::parse_score( array( 'answers' => array( 'safe_to_run' => array( 'noul' => -0.1 ) ) ) );
	}

	public function test_parse_score_error_code_is_invalid_response(): void {
		try {
			Client::parse_score( array() );
			$this->fail( 'Deveria lançar DecisionException' );
		} catch ( DecisionException $e ) {
			$this->assertSame( 'invalid_response', $e->getErrorCode() );
		}
	}

	public function test_endpoint_ollama_trims_trailing_slash(): void {
		$c = new Client( 'ollama', 'm', 'i', 10, 'http://host:11434/' );
		$this->assertSame( 'http://host:11434/v1/systemone', $c->endpoint() );
	}

	public function test_endpoint_openrouter(): void {
		$c = new Client( 'openrouter', 'm', 'i', 10, '', 'k' );
		$this->assertSame( 'https://openrouter.ai/api/alpha/decisions', $c->endpoint() );
	}

	public function test_headers_openrouter_has_bearer_token(): void {
		$c = new Client( 'openrouter', 'm', 'i', 10, '', 'sk-123' );
		$h = $c->headers();
		$this->assertSame( 'Bearer sk-123', $h['Authorization'] );
		$this->assertSame( 'application/json', $h['Content-Type'] );
	}

	public function test_headers_ollama_has_no_authorization(): void {
		$c = new Client( 'ollama', 'm', 'i', 10, 'http://h' );
		$this->assertArrayNotHasKey( 'Authorization', $c->headers() );
	}

	public function test_missing_config_reports_each_problem(): void {
		$this->assertNotNull( ( new Client( 'openrouter', 'm', 'i', 10, '', '' ) )->missing_config() );
		$this->assertNotNull( ( new Client( 'ollama', 'm', 'i', 10, '' ) )->missing_config() );
		$this->assertNotNull( ( new Client( 'ollama', '', 'i', 10, 'http://h' ) )->missing_config() );
		$this->assertNull( ( new Client( 'ollama', 'm', 'i', 10, 'http://h' ) )->missing_config() );
		$this->assertNull( ( new Client( 'openrouter', 'm', 'i', 10, '', 'k' ) )->missing_config() );
	}
}
