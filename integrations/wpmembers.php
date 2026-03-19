<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action(
    'wpmem_pre_register_data',
    function () {
        $plugin = AntiSpamForWordPressPlugin::$instance;
        $mode = $plugin->get_integration_wordpress_register();
        if (!empty($mode)) {
            $payload = isset($_POST['asfw_register']) ? trim(sanitize_text_field($_POST['asfw_register'])) : '';
            if ($plugin->verify($payload) === false) {
                global $wpmem_themsg;
                $wpmem_themsg = esc_html__('Registration failed. Please try again later.', 'anti-spam-for-wordpress');
            }
        }
    },
    10,
    0
);
