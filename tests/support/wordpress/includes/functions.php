<?php
declare(strict_types=1);

$GLOBALS['asfw_wp_tests_filters'] = $GLOBALS['asfw_wp_tests_filters'] ?? array();

function tests_add_filter($hook_name, $callback)
{
	if (! is_string($hook_name) || '' === $hook_name || ! is_callable($callback)) {
		return false;
	}

	if (! isset($GLOBALS['asfw_wp_tests_filters'][$hook_name]) || ! is_array($GLOBALS['asfw_wp_tests_filters'][$hook_name])) {
		$GLOBALS['asfw_wp_tests_filters'][$hook_name] = array();
	}

	$GLOBALS['asfw_wp_tests_filters'][$hook_name][] = $callback;

	return true;
}
