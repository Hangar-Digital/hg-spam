<?php
use HgSpam\Filter;
use PHPUnit\Framework\TestCase;

final class FilterTest extends TestCase {

	private function set_note( ?string $note ): void {
		( new \ReflectionProperty( Filter::class, 'note' ) )->setValue( null, $note );
	}

	protected function tearDown(): void {
		$this->set_note( null );
	}

	public function test_append_debug_returns_message_untouched_when_note_is_null(): void {
		$this->set_note( null );
		$this->assertSame( 'Obrigado', Filter::append_debug( 'Obrigado', 'mail_sent_ok' ) );
	}

	public function test_append_debug_appends_note_for_submission_outcome_statuses(): void {
		$this->set_note( '[hg-spam] nota' );
		$this->assertSame( 'Obrigado [hg-spam] nota', Filter::append_debug( 'Obrigado', 'mail_sent_ok' ) );
		$this->assertSame( 'x [hg-spam] nota', Filter::append_debug( 'x', 'mail_sent_ng' ) );
		$this->assertSame( 'x [hg-spam] nota', Filter::append_debug( 'x', 'spam' ) );
	}

	public function test_append_debug_ignores_validation_statuses(): void {
		$this->set_note( '[hg-spam] nota' );
		$this->assertSame( 'x', Filter::append_debug( 'x', 'validation_error' ) );
		$this->assertSame( 'x', Filter::append_debug( 'x', 'invalid_required' ) );
	}

	public function test_build_state_joins_fields_one_per_line(): void {
		$state = Filter::build_state( array( 'your-name' => 'Ana', 'your-message' => 'Oi' ) );
		$this->assertSame( "your-name: Ana\nyour-message: Oi", $state );
	}

	public function test_build_state_skips_internal_and_empty_fields(): void {
		$state = Filter::build_state( array( '_wpcf7' => '5', 'a' => '  ', 'b' => 'x' ) );
		$this->assertSame( 'b: x', $state );
	}

	public function test_build_state_flattens_array_values(): void {
		$state = Filter::build_state( array( 'cores' => array( 'azul', 'verde' ) ) );
		$this->assertSame( 'cores: azul, verde', $state );
	}

	public function test_build_state_empty_when_no_content(): void {
		$this->assertSame( '', Filter::build_state( array( '_x' => '1' ) ) );
	}

	public function test_build_state_truncates_long_field_values(): void {
		$state = Filter::build_state( array( 'msg' => str_repeat( 'a', 5000 ) ) );
		$this->assertSame( 'msg: ' . str_repeat( 'a', Filter::MAX_FIELD_CHARS ), $state );
	}

	public function test_limit_state_keeps_short_state_unchanged(): void {
		$this->assertSame( "a: b\nc: d", Filter::limit_state( "a: b\nc: d" ) );
	}

	public function test_limit_state_keeps_start_and_end_of_huge_state(): void {
		$state   = 'INICIO' . str_repeat( 'x', 10000 ) . 'FIM';
		$limited = Filter::limit_state( $state, 6000 );

		$this->assertLessThanOrEqual( 6000 + 7, mb_strlen( $limited ) );
		$this->assertStringStartsWith( 'INICIO', $limited );
		$this->assertStringEndsWith( 'FIM', $limited );
	}

	public function test_is_spam_at_and_above_threshold(): void {
		$this->assertTrue( Filter::is_spam( 0.8, 80 ) );
		$this->assertTrue( Filter::is_spam( 0.99, 80 ) );
		$this->assertFalse( Filter::is_spam( 0.79, 80 ) );
	}

	public function test_is_spam_ignores_float_noise(): void {
		// 0.29 * 100 = 28.999999999999996 em ponto flutuante.
		$this->assertTrue( Filter::is_spam( 0.29, 29 ) );
	}

	public function test_is_spam_threshold_zero_always_spam(): void {
		$this->assertTrue( Filter::is_spam( 0.0, 0 ) );
	}

	public function test_format_debug_success_shows_score_threshold_and_raw(): void {
		$raw  = array( 'answers' => array( 'safe_to_run' => array( 'noul' => 0.5 ) ) );
		$text = Filter::format_debug( 'clef-flash', 0.5, 80, $raw, null );

		$this->assertStringContainsString( 'modelo=clef-flash', $text );
		$this->assertStringContainsString( 'score=50.00%', $text );
		$this->assertStringContainsString( 'limite=80%', $text );
		$this->assertStringContainsString( '"noul":0.5', $text );
	}

	public function test_format_debug_error_shows_error_without_html(): void {
		$text = Filter::format_debug( 'm', null, 80, null, '<b>HTTP 500</b>' );

		$this->assertStringContainsString( 'erro=HTTP 500', $text );
		$this->assertStringNotContainsString( '<b>', $text );
		$this->assertStringNotContainsString( 'score=', $text );
	}

	public function test_format_debug_escapes_angle_brackets_in_raw_json(): void {
		$text = Filter::format_debug( 'm', 0.1, 80, array( 'x' => '<script>' ), null );
		$this->assertStringNotContainsString( '<script>', $text );
	}
}
