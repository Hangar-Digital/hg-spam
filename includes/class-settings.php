<?php
namespace HgSpam;

class Settings {
	public const OPTION = 'hg_spam_options';
	public const GROUP  = 'hg_spam';
	public const PAGE   = 'hg-spam';

	public static function init(): void {
		add_action( 'admin_menu', array( self::class, 'add_menu' ) );
		add_action( 'admin_init', array( self::class, 'register' ) );
	}

	public static function defaults(): array {
		return array(
			'server'            => 'ollama',
			'ollama_url'        => 'http://localhost:11434',
			'openrouter_key'    => '',
			'models_ollama'     => "jev\nclef\nclef-flash",
			'models_openrouter' => "cloudflare/jev\ncloudflare/clef\ncloudflare/clef-flash",
			'model_ollama'      => 'clef-flash',
			'model_openrouter'  => 'cloudflare/clef-flash',
			'threshold'         => 80,
			'instructions'      => 'Essa mensagem é um spam? Sou empresa que vende produtos alimentícios para a indústria.',
			'timeout'           => 10,
			'debug'             => 0,
		);
	}

	public static function all(): array {
		$saved = get_option( self::OPTION, array() );

		return array_merge( self::defaults(), is_array( $saved ) ? $saved : array() );
	}

	public static function parse_models( string $text ): array {
		$lines = preg_split( '/\R/', $text );
		$lines = array_map( 'trim', $lines );
		$lines = array_filter( $lines, static fn( $l ) => '' !== $l );

		return array_values( array_unique( $lines ) );
	}

	public static function models( string $server ): array {
		$c   = self::all();
		$key = 'openrouter' === $server ? 'models_openrouter' : 'models_ollama';

		return self::parse_models( (string) $c[ $key ] );
	}

	public static function active_model(): string {
		$c = self::all();

		return (string) ( 'openrouter' === $c['server'] ? $c['model_openrouter'] : $c['model_ollama'] );
	}

	public static function clamp( int $value, int $min, int $max ): int {
		return max( $min, min( $max, $value ) );
	}

	public static function add_menu(): void {
		add_options_page( 'HG Spam', 'HG Spam', 'manage_options', self::PAGE, array( self::class, 'render_page' ) );
	}

	public static function register(): void {
		register_setting(
			self::GROUP,
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( self::class, 'sanitize' ),
				'default'           => self::defaults(),
			)
		);
	}

	public static function sanitize( $input ): array {
		$input    = is_array( $input ) ? $input : array();
		$defaults = self::defaults();

		$server = $input['server'] ?? $defaults['server'];
		$server = in_array( $server, array( 'ollama', 'openrouter' ), true ) ? $server : $defaults['server'];

		$models_ollama     = self::parse_models( sanitize_textarea_field( (string) ( $input['models_ollama'] ?? $defaults['models_ollama'] ) ) );
		$models_openrouter = self::parse_models( sanitize_textarea_field( (string) ( $input['models_openrouter'] ?? $defaults['models_openrouter'] ) ) );

		$model_ollama     = sanitize_text_field( (string) ( $input['model_ollama'] ?? '' ) );
		$model_openrouter = sanitize_text_field( (string) ( $input['model_openrouter'] ?? '' ) );

		$ollama_url   = esc_url_raw( trim( (string) ( $input['ollama_url'] ?? '' ) ) );
		$instructions = sanitize_textarea_field( (string) ( $input['instructions'] ?? '' ) );

		return array(
			'server'            => $server,
			'ollama_url'        => '' !== $ollama_url ? $ollama_url : $defaults['ollama_url'],
			'openrouter_key'    => sanitize_text_field( trim( (string) ( $input['openrouter_key'] ?? '' ) ) ),
			'models_ollama'     => implode( "\n", $models_ollama ),
			'models_openrouter' => implode( "\n", $models_openrouter ),
			'model_ollama'      => in_array( $model_ollama, $models_ollama, true ) ? $model_ollama : ( $models_ollama[0] ?? '' ),
			'model_openrouter'  => in_array( $model_openrouter, $models_openrouter, true ) ? $model_openrouter : ( $models_openrouter[0] ?? '' ),
			'threshold'         => self::clamp( (int) ( $input['threshold'] ?? $defaults['threshold'] ), 0, 100 ),
			'instructions'      => '' !== $instructions ? $instructions : $defaults['instructions'],
			'timeout'           => self::clamp( (int) ( $input['timeout'] ?? $defaults['timeout'] ), 1, 60 ),
			'debug'             => empty( $input['debug'] ) ? 0 : 1,
		);
	}

	private static function model_select( string $field, array $models, string $current ): void {
		printf( '<select name="%s[%s]">', esc_attr( self::OPTION ), esc_attr( $field ) );
		foreach ( $models as $model ) {
			printf( '<option value="%1$s"%2$s>%1$s</option>', esc_attr( $model ), selected( $current, $model, false ) );
		}
		echo '</select>';
	}

	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$c    = self::all();
		$name = esc_attr( self::OPTION );
		?>
		<div class="wrap">
			<h1>HG Spam</h1>
			<form method="post" action="options.php">
				<?php settings_fields( self::GROUP ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">Servidor</th>
						<td>
							<label><input type="radio" name="<?php echo $name; ?>[server]" value="ollama" <?php checked( $c['server'], 'ollama' ); ?>> Ollama</label>
							&nbsp;
							<label><input type="radio" name="<?php echo $name; ?>[server]" value="openrouter" <?php checked( $c['server'], 'openrouter' ); ?>> OpenRouter</label>
						</td>
					</tr>
					<tr <?php self::server_row( 'ollama', $c['server'] ); ?>>
						<th scope="row"><label for="hg-ollama-url">URL do Ollama</label></th>
						<td><input id="hg-ollama-url" type="url" class="regular-text" name="<?php echo $name; ?>[ollama_url]" value="<?php echo esc_attr( $c['ollama_url'] ); ?>"></td>
					</tr>
					<tr <?php self::server_row( 'openrouter', $c['server'] ); ?>>
						<th scope="row"><label for="hg-key">API key do OpenRouter</label></th>
						<td><input id="hg-key" type="password" class="regular-text" autocomplete="off" name="<?php echo $name; ?>[openrouter_key]" value="<?php echo esc_attr( $c['openrouter_key'] ); ?>"></td>
					</tr>
					<tr <?php self::server_row( 'ollama', $c['server'] ); ?>>
						<th scope="row"><label for="hg-models-ollama">Modelos (Ollama)</label></th>
						<td>
							<textarea id="hg-models-ollama" rows="4" class="regular-text" name="<?php echo $name; ?>[models_ollama]"><?php echo esc_textarea( $c['models_ollama'] ); ?></textarea>
							<p class="description">Um por linha.</p>
						</td>
					</tr>
					<tr <?php self::server_row( 'ollama', $c['server'] ); ?>>
						<th scope="row">Modelo ativo (Ollama)</th>
						<td><?php self::model_select( 'model_ollama', self::models( 'ollama' ), (string) $c['model_ollama'] ); ?>
							<p class="description">Salve para o select refletir mudanças na lista.</p></td>
					</tr>
					<tr <?php self::server_row( 'openrouter', $c['server'] ); ?>>
						<th scope="row"><label for="hg-models-or">Modelos (OpenRouter)</label></th>
						<td>
							<textarea id="hg-models-or" rows="4" class="regular-text" name="<?php echo $name; ?>[models_openrouter]"><?php echo esc_textarea( $c['models_openrouter'] ); ?></textarea>
							<p class="description">Um por linha, com o ID completo (ex.: <code>cloudflare/clef-flash</code>).</p>
						</td>
					</tr>
					<tr <?php self::server_row( 'openrouter', $c['server'] ); ?>>
						<th scope="row">Modelo ativo (OpenRouter)</th>
						<td><?php self::model_select( 'model_openrouter', self::models( 'openrouter' ), (string) $c['model_openrouter'] ); ?></td>
					</tr>
					<tr>
						<th scope="row"><label for="hg-threshold">Limite de spam (%)</label></th>
						<td>
							<input id="hg-threshold" type="number" min="0" max="100" step="1" name="<?php echo $name; ?>[threshold]" value="<?php echo esc_attr( (string) $c['threshold'] ); ?>">
							<p class="description">É spam quando a probabilidade devolvida pelo modelo for maior ou igual a este valor.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="hg-instructions">Instrução</label></th>
						<td>
							<textarea id="hg-instructions" rows="12" class="large-text" name="<?php echo $name; ?>[instructions]"><?php echo esc_textarea( $c['instructions'] ); ?></textarea>
							<p class="description">Descreva o que é spam e o que não é. Os servidores limitam o tamanho: o <code>laya</code>, por exemplo, aceita até 183 tokens (cerca de 550 caracteres). Acima do limite o servidor recusa, o formulário passa sem filtro e o aviso de falha aparece no admin.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="hg-timeout">Timeout (s)</label></th>
						<td><input id="hg-timeout" type="number" min="1" max="60" step="1" name="<?php echo $name; ?>[timeout]" value="<?php echo esc_attr( (string) $c['timeout'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row">Debug</th>
						<td>
							<label><input type="checkbox" name="<?php echo $name; ?>[debug]" value="1" <?php checked( $c['debug'], 1 ); ?>> Mostrar a resposta do modelo na mensagem do CF7</label>
							<p class="description">Visível para qualquer visitante que envie o formulário. Não deixe ligado em produção.</p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<script>
			( function () {
				var radios = document.querySelectorAll( 'input[name="<?php echo $name; ?>[server]"]' );
				var rows = document.querySelectorAll( 'tr[data-hg-server]' );
				function sync() {
					var current = document.querySelector( 'input[name="<?php echo $name; ?>[server]"]:checked' );
					rows.forEach( function ( row ) {
						row.style.display = current && row.dataset.hgServer === current.value ? '' : 'none';
					} );
				}
				radios.forEach( function ( radio ) { radio.addEventListener( 'change', sync ); } );
				sync();
			}() );
		</script>
		<?php
	}

	/** Atributos de uma linha que só vale para um servidor (o JS do painel alterna a exibição). */
	private static function server_row( string $server, string $current ): void {
		printf( 'data-hg-server="%s"%s', esc_attr( $server ), $server === $current ? '' : ' style="display:none"' );
	}
}
