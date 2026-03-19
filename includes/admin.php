<?php

if (!defined('ABSPATH')) {
    exit;
}

require plugin_dir_path(__FILE__) . '../admin/options.php';

if (is_admin()) {
    add_action('admin_menu', 'asfw_options_page');
    add_filter('plugin_action_links_' . plugin_basename(ASFW_FILE), 'asfw_settings_link');

    function asfw_options_page()
    {
        add_options_page(
            __('Anti Spam for WordPress', 'anti-spam-for-wordpress'),
            __('Anti Spam for WordPress', 'anti-spam-for-wordpress'),
            'manage_options',
            'asfw_admin',
            'asfw_options_page_html',
            30
        );
    }

    function asfw_settings_link($links)
    {
        $url = esc_url(
            add_query_arg(
                'page',
                'asfw_admin',
                get_admin_url() . 'options-general.php'
            )
        );

        array_unshift(
            $links,
            "<a href='$url'>" . __('Settings', 'anti-spam-for-wordpress') . '</a>'
        );

        return $links;
    }
}
