<?php

if (!function_exists('is_plugin_active')) {
    function is_plugin_active($plugin)
    {
        return in_array((string) $plugin, $GLOBALS['asfw_active_plugins'] ?? array(), true);
    }
}
