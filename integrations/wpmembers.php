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
            if (asfw_verify_posted_widget('wordpress:register', 'asfw_register') === false) {
                global $wpmem_themsg;
                $wpmem_themsg = esc_html__('Registration failed. Please try again later.', 'anti-spam-for-wordpress');
            }
        }
    },
    10,
    0
);
