<?php
declare(strict_types=1);

if (! defined('ABSPATH')) {
	define('ABSPATH', dirname(__DIR__) . '/');
}

require_once dirname(__DIR__, 2) . '/wp-stubs.php';

if (! empty($GLOBALS['asfw_wp_tests_filters']['muplugins_loaded']) && is_array($GLOBALS['asfw_wp_tests_filters']['muplugins_loaded'])) {
	foreach ($GLOBALS['asfw_wp_tests_filters']['muplugins_loaded'] as $callback) {
		if (is_callable($callback)) {
			$callback();
		}
	}
}

if (function_exists('do_action')) {
	do_action('rest_api_init');
}
