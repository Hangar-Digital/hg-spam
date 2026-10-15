<?php
namespace HgSpam;

class Alerts {
	public const OPTION = 'hg_spam_last_error';

	public static function init(): void {
		add_action( 'admin_notices', array( self::class, 'render_notice' ) );
		add_action( 'admin_post_hg_spam_clear', array( self::class, 'handle_clear' ) );
	}

	/** Registra a falha, e envia e-mail ao admin no máximo 1x/hora por tipo de erro. */
	public static function report( string $code, string $message, string $server, string $model ): void {
		$error = array(
			'code'    => $code,
			'message' => $message,
			'server'  => $server,
			'model'   => $model,
			'time'    => time(),
		);
		update_option( self::OPTION, $error, false );

		$transient = 'hg_spam_mail_' . $code;
		if ( false !== get_transient( $transient ) ) {
			return;
		}
		set_transient( $transient, 1, HOUR_IN_SECONDS );

		$subject = sprintf( '[%s] HG Spam: falha no servidor de decisão (%s)', wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ), $code );
		$body    = implode(
			"\n",
			array(
				'O plugin HG Spam não conseguiu consultar o servidor de decisão.',
				'Os formulários do Contact Form 7 estão sendo enviados SEM filtro de spam até o problema ser resolvido.',
				'',
				'Erro: ' . $message,
				'Servidor: ' . $server,
				'Modelo: ' . $model,
				'Quando: ' . wp_date( 'd/m/Y H:i:s', $error['time'] ),
				'',
				'Configurações: ' . admin_url( 'options-general.php?page=' . Settings::PAGE ),
				'',
				'Novos e-mails deste tipo de erro só serão enviados após 1 hora.',
			)
		);

		wp_mail( get_option( 'admin_email' ), $subject, $body );
	}

	/** Chamado em toda consulta bem-sucedida: remove o aviso. */
	public static function clear(): void {
		if ( false !== get_option( self::OPTION, false ) ) {
			delete_option( self::OPTION );
		}
	}

	public static function render_notice(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$error = get_option( self::OPTION, false );
		if ( ! is_array( $error ) ) {
			return;
		}

		$settings_url = admin_url( 'options-general.php?page=' . Settings::PAGE );
		$clear_url    = wp_nonce_url( admin_url( 'admin-post.php?action=hg_spam_clear' ), 'hg_spam_clear' );

		printf(
			'<div class="notice notice-error"><p><strong>HG Spam:</strong> falha ao consultar o servidor de decisão; os formulários estão sendo enviados sem filtro de spam. %1$s (%2$s / %3$s, %4$s) &mdash; <a href="%5$s">Configurações</a> | <a href="%6$s">Limpar aviso</a></p></div>',
			esc_html( (string) $error['message'] ),
			esc_html( (string) $error['server'] ),
			esc_html( (string) $error['model'] ),
			esc_html( wp_date( 'd/m/Y H:i', (int) $error['time'] ) ),
			esc_url( $settings_url ),
			esc_url( $clear_url )
		);
	}

	public static function handle_clear(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Sem permissão.', 403 );
		}
		check_admin_referer( 'hg_spam_clear' );

		delete_option( self::OPTION );

		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url() );
		exit;
	}
}
