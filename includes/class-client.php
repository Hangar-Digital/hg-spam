<?php
namespace HgSpam;

/**
 * Cliente do servidor de decisão (Ollama ou OpenRouter). Não conhece o CF7.
 */
class Client {
	public const OPENROUTER_ENDPOINT = 'https://openrouter.ai/api/alpha/decisions';
	public const OLLAMA_PATH         = '/v1/systemone';

	private string $server;
	private string $model;
	private string $instructions;
	private int $timeout;
	private string $ollama_url;
	private string $api_key;

	public function __construct(
		string $server,
		string $model,
		string $instructions,
		int $timeout,
		string $ollama_url = '',
		string $api_key = ''
	) {
		$this->server       = $server;
		$this->model        = $model;
		$this->instructions = $instructions;
		$this->timeout      = $timeout;
		$this->ollama_url   = $ollama_url;
		$this->api_key      = $api_key;
	}

	public static function from_settings(): self {
		$c = Settings::all();

		return new self(
			$c['server'],
			Settings::active_model(),
			$c['instructions'],
			(int) $c['timeout'],
			$c['ollama_url'],
			$c['openrouter_key']
		);
	}

	public static function build_payload( string $model, string $state, string $instructions ): array {
		return array(
			'model'     => $model,
			'state'     => $state,
			'questions' => array(
				'safe_to_run' => array(
					'type'         => 'noul',
					'instructions' => $instructions,
					'criteria'     => array(
						'true'  => 'Sim',
						'false' => 'Não',
					),
				),
			),
		);
	}

	/**
	 * Probabilidade (0-1) de a resposta ser "Sim" (= spam).
	 *
	 * @throws DecisionException
	 */
	public static function parse_score( array $body ): float {
		$value = $body['answers']['safe_to_run']['noul'] ?? null;

		if ( ! is_int( $value ) && ! is_float( $value ) ) {
			throw new DecisionException( 'invalid_response', 'Resposta sem answers.safe_to_run.noul numérico.' );
		}
		if ( $value < 0 || $value > 1 ) {
			throw new DecisionException( 'invalid_response', 'Score fora do intervalo 0-1: ' . $value );
		}

		return (float) $value;
	}

	public function endpoint(): string {
		if ( 'openrouter' === $this->server ) {
			return self::OPENROUTER_ENDPOINT;
		}

		return rtrim( $this->ollama_url, '/' ) . self::OLLAMA_PATH;
	}

	public function headers(): array {
		$headers = array( 'Content-Type' => 'application/json' );

		if ( 'openrouter' === $this->server ) {
			$headers['Authorization'] = 'Bearer ' . $this->api_key;
		}

		return $headers;
	}

	/** Mensagem do problema de configuração, ou null se estiver completa. */
	public function missing_config(): ?string {
		if ( '' === $this->model ) {
			return 'Nenhum modelo selecionado.';
		}
		if ( 'openrouter' === $this->server && '' === $this->api_key ) {
			return 'API key do OpenRouter não configurada.';
		}
		if ( 'openrouter' !== $this->server && '' === $this->ollama_url ) {
			return 'URL do Ollama não configurada.';
		}

		return null;
	}

	/**
	 * Consulta o servidor.
	 *
	 * @return array{score: float, raw: array}
	 * @throws DecisionException
	 */
	public function decide( string $state ): array {
		$problem = $this->missing_config();
		if ( null !== $problem ) {
			throw new DecisionException( 'config', $problem );
		}

		$response = wp_remote_post(
			$this->endpoint(),
			array(
				'timeout' => $this->timeout,
				'headers' => $this->headers(),
				'body'    => wp_json_encode( self::build_payload( $this->model, $state, $this->instructions ) ),
			)
		);

		if ( is_wp_error( $response ) ) {
			throw new DecisionException( 'connection', $response->get_error_message() );
		}

		$code     = (int) wp_remote_retrieve_response_code( $response );
		$raw_body = wp_remote_retrieve_body( $response );

		if ( $code < 200 || $code >= 300 ) {
			throw new DecisionException( 'http', sprintf( 'HTTP %d: %s', $code, mb_substr( $raw_body, 0, 300 ) ) );
		}

		$body = json_decode( $raw_body, true );
		if ( ! is_array( $body ) ) {
			throw new DecisionException( 'invalid_json', 'Resposta não é JSON válido: ' . mb_substr( $raw_body, 0, 300 ) );
		}

		return array(
			'score' => self::parse_score( $body ),
			'raw'   => $body,
		);
	}
}
