<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action(
    'wpdiscuz_button_actions',
    function () {
        $plugin = AntiSpamForWordPressPlugin::$instance;
        $mode = $plugin->get_integration_wpdiscuz();
        if (!empty($mode)) {
            $output = '<div class="asfw-widget-wrap-wpdiscuz">';
            $output .= $plugin->render_widget($mode, false, null, 'asfw', 'wpdiscuz:comments');
            $output .= '</div>';
            echo wp_kses($output, AntiSpamForWordPressPlugin::$html_allowed_tags);
        }
    },
    10,
    0
);
