<?php

if (!defined('ABSPATH')) {
    exit;
}

add_filter('script_loader_tag', 'asfw_script_tags', 10, 3);

function asfw_script_tags($tag, $handle, $src)
{
    if ($handle === 'asfw-widget') {
        return str_replace('<script', '<script async defer type="module"', $tag);
    }

    return $tag;
}
