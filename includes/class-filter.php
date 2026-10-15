<?php
namespace HgSpam;

class Filter {
	public const MAX_FIELD_CHARS = 2000;
	public const MAX_STATE_CHARS = 6000;

	/** Texto de debug da requisição atual (null = nada a anexar). */
	private static ?string $note = null;

	public static function init(): void {
		add_filter( 'wpcf7_spam', array( self::class, 'check' ), 10, 2 );
		add_filter( 'wpcf7_display_message', array( self::class, 'append_debug' ), 10, 2 );

		if ( is_admin() ) {
			add_action( 'admin_notices', array( self::class, 'notice_missing_cf7' ) );
		}
	}

	public static function notice_missing_cf7(): void {
		if ( class_exists( 'WPCF7_ContactForm' ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		echo '<div class="notice notice-warning"><p><strong>HG Spam:</strong> o plugin Contact Form 7 não está ativo; nenhum formulário está sendo filtrado.</p></div>';
	}

	/**
	 * @param bool                $spam       Decisão acumulada do CF7.
	 * @param \WPCF7_Submission   $submission
	 */
	public static function check( $spam, $submission ) {
		self::$note = null;

		if ( $spam ) {
			return $spam;
		}

		$state = self::limit_state( self::build_state( (array) $submission->get_posted_data() ) );
		if ( '' === $state ) {
			return $spam;
		}

		$config    = array();
		$model     = '';
		$threshold = 80;
		$debug     = false;

		try {
			$config    = Settings::all();
			$model     = Settings::active_model();
			$threshold = (int) $config['threshold'];
			$debug     = ! empty( $config['debug'] );

			$result = Client::from_settings()->decide( $state );
		} catch ( DecisionException $e ) {
			Alerts::report( $e->getErrorCode(), $e->getMessage(), (string) ( $config['server'] ?? '' ), $model );
			if ( $debug ) {
				self::$note = self::format_debug( $model, null, $threshold, null, $e->getMessage() );
			}
			return $spam; // fail-open
		} catch ( \Throwable $e ) {
			Alerts::report( 'internal', $e->getMessage(), (string) ( $config['server'] ?? '' ), $model );
			if ( $debug ) {
				self::$note = self::format_debug( $model, null, $threshold, null, $e->getMessage() );
			}
			return $spam; // fail-open
		}

		Alerts::clear();

		if ( $debug ) {
			self::$note = self::format_debug( $model, $result['score'], $threshold, $result['raw'], null );
		}

		if ( self::is_spam( $result['score'], $threshold ) ) {
			if ( method_exists( $submission, 'add_spam_log' ) ) {
				$submission->add_spam_log(
					array(
						'agent'  => 'hg-spam',
						'reason' => sprintf( 'score %.2f%% >= limite %d%% (modelo %s)', $result['score'] * 100, $threshold, $model ),
					)
				);
			}
			return true;
		}

		return $spam;
	}

	public static function append_debug( $message, $status ) {
		if ( null === self::$note || ! in_array( $status, array( 'mail_sent_ok', 'mail_sent_ng', 'spam' ), true ) ) {
			return $message;
		}

		return $message . ' ' . self::$note;
	}

	/** Uma linha "campo: valor" por campo enviado; ignora campos internos (_*) e vazios. */
	public static function build_state( array $posted ): string {
		$lines = array();

		foreach ( $posted as $name => $value ) {
			if ( str_starts_with( (string) $name, '_' ) ) {
				continue;
			}
			if ( is_array( $value ) ) {
				$value = implode( ', ', array_filter( $value, 'is_scalar' ) );
			}
			$value = mb_substr( trim( (string) $value ), 0, self::MAX_FIELD_CHARS );
			if ( '' === $value ) {
				continue;
			}
			$lines[] = $name . ': ' . $value;
		}

		return implode( "\n", $lines );
	}

	/** Limita o state total mantendo início e fim, para o spam não ficar escondido no meio. */
	public static function limit_state( string $state, int $max = self::MAX_STATE_CHARS ): string {
		if ( mb_strlen( $state ) <= $max ) {
			return $state;
		}

		return mb_substr( $state, 0, intdiv( $max, 2 ) ) . "\n[...]\n" . mb_substr( $state, -intdiv( $max, 2 ) );
	}

	public static function is_spam( float $score, int $threshold ): bool {
		return round( $score * 100, 6 ) >= $threshold;
	}

	public static function format_debug( string $model, ?float $score, int $threshold, ?array $raw, ?string $error ): string {
		$parts = array( '[hg-spam] modelo=' . $model );

		if ( null !== $error ) {
			$parts[] = 'erro=' . strip_tags( $error );
		} else {
			$parts[] = sprintf( 'score=%.2f%%', (float) $score * 100 );
			$parts[] = 'limite=' . $threshold . '%';
			$parts[] = 'resposta=' . json_encode( $raw, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		}

		return implode( ' | ', $parts );
	}
}
