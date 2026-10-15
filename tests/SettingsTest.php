<?php
use HgSpam\Settings;
use PHPUnit\Framework\TestCase;

final class SettingsTest extends TestCase {

	public function test_parse_models_trims_dedupes_and_drops_blanks(): void {
		$text = "  jev\n\nclef\r\nclef-flash \njev";
		$this->assertSame( array( 'jev', 'clef', 'clef-flash' ), Settings::parse_models( $text ) );
	}

	public function test_parse_models_empty_string(): void {
		$this->assertSame( array(), Settings::parse_models( '' ) );
	}

	public function test_clamp_limits_value(): void {
		$this->assertSame( 0, Settings::clamp( -5, 0, 100 ) );
		$this->assertSame( 100, Settings::clamp( 250, 0, 100 ) );
		$this->assertSame( 80, Settings::clamp( 80, 0, 100 ) );
	}

	public function test_defaults_preregister_models_and_threshold(): void {
		$d = Settings::defaults();
		$this->assertSame( array( 'jev', 'clef', 'clef-flash' ), Settings::parse_models( $d['models_ollama'] ) );
		$this->assertSame( 80, $d['threshold'] );
		$this->assertSame( 0, $d['debug'] );
	}
}
