<?php
/**
 * Plugin Name: HG Spam
 * Description: Filtra spam em formulários do Contact Form 7 usando um modelo de decisão (Ollama ou OpenRouter).
 * Version: 0.1.0
 * Requires PHP: 8.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/includes/class-decision-exception.php';
require_once __DIR__ . '/includes/class-client.php';
require_once __DIR__ . '/includes/class-settings.php';
require_once __DIR__ . '/includes/class-alerts.php';
require_once __DIR__ . '/includes/class-filter.php';

add_action(
	'plugins_loaded',
	static function () {
		HgSpam\Settings::init();
		HgSpam\Alerts::init();
		HgSpam\Filter::init();
	}
);
