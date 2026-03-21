<?php

if (!defined('ABSPATH')) {
    exit;
}

if (is_admin()) {
    add_action('admin_init', 'asfw_settings_init');

    function asfw_settings_init()
    {
        $options = array(
            AntiSpamForWordPressPlugin::$option_secret,
            AntiSpamForWordPressPlugin::$option_complexity,
            AntiSpamForWordPressPlugin::$option_expires,
            AntiSpamForWordPressPlugin::$option_hidefooter,
            AntiSpamForWordPressPlugin::$option_hidelogo,
            AntiSpamForWordPressPlugin::$option_footer_text,
            AntiSpamForWordPressPlugin::$option_privacy_page,
            AntiSpamForWordPressPlugin::$option_privacy_url,
            AntiSpamForWordPressPlugin::$option_privacy_new_tab,
            AntiSpamForWordPressPlugin::$option_auto,
            AntiSpamForWordPressPlugin::$option_floating,
            AntiSpamForWordPressPlugin::$option_delay,
            AntiSpamForWordPressPlugin::$option_lazy,
            AntiSpamForWordPressPlugin::$option_rate_limit_max_challenges,
            AntiSpamForWordPressPlugin::$option_rate_limit_max_failures,
            AntiSpamForWordPressPlugin::$option_rate_limit_window,
            AntiSpamForWordPressPlugin::$option_honeypot,
            AntiSpamForWordPressPlugin::$option_min_submit_time,
            AntiSpamForWordPressPlugin::$option_integration_coblocks,
            AntiSpamForWordPressPlugin::$option_integration_contact_form_7,
            AntiSpamForWordPressPlugin::$option_integration_custom,
            AntiSpamForWordPressPlugin::$option_integration_elementor,
            AntiSpamForWordPressPlugin::$option_integration_enfold_theme,
            AntiSpamForWordPressPlugin::$option_integration_formidable,
            AntiSpamForWordPressPlugin::$option_integration_forminator,
            AntiSpamForWordPressPlugin::$option_integration_gravityforms,
            AntiSpamForWordPressPlugin::$option_integration_woocommerce_login,
            AntiSpamForWordPressPlugin::$option_integration_woocommerce_register,
            AntiSpamForWordPressPlugin::$option_integration_woocommerce_reset_password,
            AntiSpamForWordPressPlugin::$option_integration_html_forms,
            AntiSpamForWordPressPlugin::$option_integration_wordpress_comments,
            AntiSpamForWordPressPlugin::$option_integration_wordpress_login,
            AntiSpamForWordPressPlugin::$option_integration_wordpress_register,
            AntiSpamForWordPressPlugin::$option_integration_wordpress_reset_password,
            AntiSpamForWordPressPlugin::$option_integration_wpdiscuz,
            AntiSpamForWordPressPlugin::$option_integration_wpforms,
        );

        foreach ($options as $option) {
            register_setting('asfw_options', $option);
        }

        add_settings_section(
            'asfw_general_settings_section',
            __('General', 'anti-spam-for-wordpress'),
            'asfw_general_section_callback',
            'asfw_admin'
        );

        add_settings_field(
            'asfw_settings_secret_field',
            __('Secret key', 'anti-spam-for-wordpress'),
            'asfw_settings_field_callback',
            'asfw_admin',
            'asfw_general_settings_section',
            array(
                'name' => AntiSpamForWordPressPlugin::$option_secret,
                'hint' => __('Used to sign and verify local proof-of-work challenges.', 'anti-spam-for-wordpress'),
                'type' => 'text',
            )
        );

        add_settings_field(
            'asfw_settings_complexity_field',
            __('Complexity', 'anti-spam-for-wordpress'),
            'asfw_settings_select_callback',
            'asfw_admin',
            'asfw_general_settings_section',
            array(
                'name' => AntiSpamForWordPressPlugin::$option_complexity,
                'hint' => __('Select the proof-of-work complexity for new challenges.', 'anti-spam-for-wordpress'),
                'options' => array(
                    'low' => __('Low', 'anti-spam-for-wordpress'),
                    'medium' => __('Medium', 'anti-spam-for-wordpress'),
                    'high' => __('High', 'anti-spam-for-wordpress'),
                ),
            )
        );

        add_settings_field(
            'asfw_settings_expires_field',
            __('Expiration', 'anti-spam-for-wordpress'),
            'asfw_settings_select_callback',
            'asfw_admin',
            'asfw_general_settings_section',
            array(
                'name' => AntiSpamForWordPressPlugin::$option_expires,
                'hint' => __('How long a challenge stays valid.', 'anti-spam-for-wordpress'),
                'options' => array(
                    '120' => __('2 minutes', 'anti-spam-for-wordpress'),
                    '300' => __('5 minutes', 'anti-spam-for-wordpress'),
                    '600' => __('10 minutes', 'anti-spam-for-wordpress'),
                    '1800' => __('30 minutes', 'anti-spam-for-wordpress'),
                ),
            )
        );

        add_settings_section(
            'asfw_security_settings_section',
            __('Security hardening', 'anti-spam-for-wordpress'),
            'asfw_security_section_callback',
            'asfw_admin'
        );

        add_settings_field(
            'asfw_settings_lazy_field',
            __('Lazy challenge loading', 'anti-spam-for-wordpress'),
            'asfw_settings_field_callback',
            'asfw_admin',
            'asfw_security_settings_section',
            array(
                'name' => AntiSpamForWordPressPlugin::$option_lazy,
                'description' => __('Yes', 'anti-spam-for-wordpress'),
                'hint' => __('Load challenge data on first interaction instead of immediately on page load.', 'anti-spam-for-wordpress'),
                'type' => 'checkbox',
            )
        );

        add_settings_field(
            'asfw_settings_rate_limit_window_field',
            __('Rate limit window', 'anti-spam-for-wordpress'),
            'asfw_settings_select_callback',
            'asfw_admin',
            'asfw_security_settings_section',
            array(
                'name' => AntiSpamForWordPressPlugin::$option_rate_limit_window,
                'hint' => __('Window used for challenge and failure rate limits.', 'anti-spam-for-wordpress'),
                'options' => array(
                    '300' => __('5 minutes', 'anti-spam-for-wordpress'),
                    '600' => __('10 minutes', 'anti-spam-for-wordpress'),
                    '900' => __('15 minutes', 'anti-spam-for-wordpress'),
                ),
            )
        );

        add_settings_field(
            'asfw_settings_rate_limit_challenges_field',
            __('Max challenges per window', 'anti-spam-for-wordpress'),
            'asfw_settings_select_callback',
            'asfw_admin',
            'asfw_security_settings_section',
            array(
                'name' => AntiSpamForWordPressPlugin::$option_rate_limit_max_challenges,
                'hint' => __('Limit repeated challenge fetches from the same visitor.', 'anti-spam-for-wordpress'),
                'options' => array(
                    '0' => __('Disabled', 'anti-spam-for-wordpress'),
                    '15' => '15',
                    '30' => '30',
                    '60' => '60',
                    '120' => '120',
                ),
            )
        );

        add_settings_field(
            'asfw_settings_rate_limit_failures_field',
            __('Max failed verifications per window', 'anti-spam-for-wordpress'),
            'asfw_settings_select_callback',
            'asfw_admin',
            'asfw_security_settings_section',
            array(
                'name' => AntiSpamForWordPressPlugin::$option_rate_limit_max_failures,
                'hint' => __('Throttle repeated bad submissions from the same visitor.', 'anti-spam-for-wordpress'),
                'options' => array(
                    '0' => __('Disabled', 'anti-spam-for-wordpress'),
                    '5' => '5',
                    '10' => '10',
                    '20' => '20',
                    '50' => '50',
                ),
            )
        );

        add_settings_field(
            'asfw_settings_honeypot_field',
            __('Honeypot field', 'anti-spam-for-wordpress'),
            'asfw_settings_field_callback',
            'asfw_admin',
            'asfw_security_settings_section',
            array(
                'name' => AntiSpamForWordPressPlugin::$option_honeypot,
                'description' => __('Yes', 'anti-spam-for-wordpress'),
                'hint' => __('Add an off-screen trap field to catch simple bots.', 'anti-spam-for-wordpress'),
                'type' => 'checkbox',
            )
        );

        add_settings_field(
            'asfw_settings_min_submit_time_field',
            __('Minimum submit time', 'anti-spam-for-wordpress'),
            'asfw_settings_select_callback',
            'asfw_admin',
            'asfw_security_settings_section',
            array(
                'name' => AntiSpamForWordPressPlugin::$option_min_submit_time,
                'hint' => __('Reject submissions that complete too quickly.', 'anti-spam-for-wordpress'),
                'options' => array(
                    '0' => __('Disabled', 'anti-spam-for-wordpress'),
                    '2' => __('2 seconds', 'anti-spam-for-wordpress'),
                    '3' => __('3 seconds', 'anti-spam-for-wordpress'),
                    '5' => __('5 seconds', 'anti-spam-for-wordpress'),
                    '10' => __('10 seconds', 'anti-spam-for-wordpress'),
                ),
            )
        );

        add_settings_section(
            'asfw_widget_settings_section',
            __('Widget customization', 'anti-spam-for-wordpress'),
            'asfw_widget_section_callback',
            'asfw_admin'
        );

        add_settings_field(
            'asfw_settings_auto_field',
            __('Auto verification', 'anti-spam-for-wordpress'),
            'asfw_settings_select_callback',
            'asfw_admin',
            'asfw_widget_settings_section',
            array(
                'name' => AntiSpamForWordPressPlugin::$option_auto,
                'hint' => __('Choose when the widget should start verification.', 'anti-spam-for-wordpress'),
                'options' => array(
                    '' => __('Disabled', 'anti-spam-for-wordpress'),
                    'onload' => __('On page load', 'anti-spam-for-wordpress'),
                    'onfocus' => __('On form focus', 'anti-spam-for-wordpress'),
                    'onsubmit' => __('On form submit', 'anti-spam-for-wordpress'),
                ),
            )
        );

        add_settings_field(
            'asfw_settings_floating_field',
            __('Floating UI', 'anti-spam-for-wordpress'),
            'asfw_settings_field_callback',
            'asfw_admin',
            'asfw_widget_settings_section',
            array(
                'name' => AntiSpamForWordPressPlugin::$option_floating,
                'description' => __('Yes', 'anti-spam-for-wordpress'),
                'hint' => __('Enable the widget floating UI.', 'anti-spam-for-wordpress'),
                'type' => 'checkbox',
            )
        );

        add_settings_field(
            'asfw_settings_delay_field',
            __('Delay', 'anti-spam-for-wordpress'),
            'asfw_settings_field_callback',
            'asfw_admin',
            'asfw_widget_settings_section',
            array(
                'name' => AntiSpamForWordPressPlugin::$option_delay,
                'description' => __('Yes', 'anti-spam-for-wordpress'),
                'hint' => __('Add a 1.5 second delay before verification completes.', 'anti-spam-for-wordpress'),
                'type' => 'checkbox',
            )
        );

        add_settings_field(
            'asfw_settings_hidelogo_field',
            __('Hide logo', 'anti-spam-for-wordpress'),
            'asfw_settings_field_callback',
            'asfw_admin',
            'asfw_widget_settings_section',
            array(
                'name' => AntiSpamForWordPressPlugin::$option_hidelogo,
                'description' => __('Yes', 'anti-spam-for-wordpress'),
                'type' => 'checkbox',
            )
        );

        add_settings_field(
            'asfw_settings_footer_text_field',
            __('Footer text', 'anti-spam-for-wordpress'),
            'asfw_settings_field_callback',
            'asfw_admin',
            'asfw_widget_settings_section',
            array(
                'name' => AntiSpamForWordPressPlugin::$option_footer_text,
                'hint' => __('Shown in the widget footer when the footer is visible.', 'anti-spam-for-wordpress'),
                'type' => 'text',
            )
        );

        add_settings_field(
            'asfw_settings_privacy_target_field',
            __('Privacy link', 'anti-spam-for-wordpress'),
            'asfw_settings_privacy_target_callback',
            'asfw_admin',
            'asfw_widget_settings_section',
            array(
                'name' => AntiSpamForWordPressPlugin::$option_privacy_page,
                'hint' => __('Choose a page or switch to a custom URL for the footer privacy link.', 'anti-spam-for-wordpress'),
            )
        );

        add_settings_field(
            'asfw_settings_privacy_url_field',
            __('Privacy URL', 'anti-spam-for-wordpress'),
            'asfw_settings_field_callback',
            'asfw_admin',
            'asfw_widget_settings_section',
            array(
                'name' => AntiSpamForWordPressPlugin::$option_privacy_url,
                'hint' => __('Used when no privacy page is selected.', 'anti-spam-for-wordpress'),
                'type' => 'url',
            )
        );

        add_settings_field(
            'asfw_settings_privacy_new_tab_field',
            __('Open privacy link in new tab', 'anti-spam-for-wordpress'),
            'asfw_settings_field_callback',
            'asfw_admin',
            'asfw_widget_settings_section',
            array(
                'name' => AntiSpamForWordPressPlugin::$option_privacy_new_tab,
                'description' => __('Yes', 'anti-spam-for-wordpress'),
                'type' => 'checkbox',
            )
        );

        add_settings_field(
            'asfw_settings_hidefooter_field',
            __('Hide footer', 'anti-spam-for-wordpress'),
            'asfw_settings_field_callback',
            'asfw_admin',
            'asfw_widget_settings_section',
            array(
                'name' => AntiSpamForWordPressPlugin::$option_hidefooter,
                'description' => __('Yes', 'anti-spam-for-wordpress'),
                'type' => 'checkbox',
            )
        );

        add_settings_section(
            'asfw_integrations_settings_section',
            __('Integrations', 'anti-spam-for-wordpress'),
            'asfw_integrations_section_callback',
            'asfw_admin'
        );

        asfw_add_integration_field(
            'asfw_settings_coblocks_integration_field',
            __('CoBlocks', 'anti-spam-for-wordpress'),
            AntiSpamForWordPressPlugin::$option_integration_coblocks,
            !asfw_plugin_active('coblocks')
        );
        asfw_add_integration_field(
            'asfw_settings_contact_form_7_integration_field',
            __('Contact Form 7', 'anti-spam-for-wordpress'),
            AntiSpamForWordPressPlugin::$option_integration_contact_form_7,
            !asfw_plugin_active('contact-form-7'),
            true
        );
        asfw_add_integration_field(
            'asfw_settings_elementor_integration_field',
            __('Elementor Pro Forms', 'anti-spam-for-wordpress'),
            AntiSpamForWordPressPlugin::$option_integration_elementor,
            !asfw_plugin_active('elementor')
        );
        asfw_add_integration_field(
            'asfw_settings_enfold_theme_integration_field',
            __('Enfold Theme', 'anti-spam-for-wordpress'),
            AntiSpamForWordPressPlugin::$option_integration_enfold_theme,
            empty(array_filter(wp_get_themes(), function ($theme) {
                return stripos($theme, 'enfold') !== false;
            }))
        );
        asfw_add_integration_field(
            'asfw_settings_formidable_integration_field',
            __('Formidable Forms', 'anti-spam-for-wordpress'),
            AntiSpamForWordPressPlugin::$option_integration_formidable,
            !asfw_plugin_active('formidable')
        );
        asfw_add_integration_field(
            'asfw_settings_forminator_integration_field',
            __('Forminator', 'anti-spam-for-wordpress'),
            AntiSpamForWordPressPlugin::$option_integration_forminator,
            !asfw_plugin_active('forminator')
        );
        asfw_add_integration_field(
            'asfw_settings_gravityforms_integration_field',
            __('Gravity Forms', 'anti-spam-for-wordpress'),
            AntiSpamForWordPressPlugin::$option_integration_gravityforms,
            !asfw_plugin_active('gravityforms')
        );
        asfw_add_integration_field(
            'asfw_settings_html_forms_integration_field',
            __('HTML Forms', 'anti-spam-for-wordpress'),
            AntiSpamForWordPressPlugin::$option_integration_html_forms,
            !asfw_plugin_active('html-forms'),
            true
        );
        asfw_add_integration_field(
            'asfw_settings_wpdiscuz_integration_field',
            __('WPDiscuz', 'anti-spam-for-wordpress'),
            AntiSpamForWordPressPlugin::$option_integration_wpdiscuz,
            !asfw_plugin_active('wpdiscuz')
        );
        asfw_add_integration_field(
            'asfw_settings_wpforms_integration_field',
            __('WPForms', 'anti-spam-for-wordpress'),
            AntiSpamForWordPressPlugin::$option_integration_wpforms,
            !asfw_plugin_active('wpforms')
        );
        asfw_add_integration_field(
            'asfw_settings_woocommerce_register_integration_field',
            __('WooCommerce register page', 'anti-spam-for-wordpress'),
            AntiSpamForWordPressPlugin::$option_integration_woocommerce_register,
            !asfw_plugin_active('woocommerce')
        );
        asfw_add_integration_field(
            'asfw_settings_woocommerce_reset_password_integration_field',
            __('WooCommerce reset password page', 'anti-spam-for-wordpress'),
            AntiSpamForWordPressPlugin::$option_integration_woocommerce_reset_password,
            !asfw_plugin_active('woocommerce')
        );
        asfw_add_integration_field(
            'asfw_settings_woocommerce_login_integration_field',
            __('WooCommerce login page', 'anti-spam-for-wordpress'),
            AntiSpamForWordPressPlugin::$option_integration_woocommerce_login,
            !asfw_plugin_active('woocommerce')
        );
        add_settings_field(
            'asfw_settings_custom_integration_field',
            __('Custom HTML', 'anti-spam-for-wordpress'),
            'asfw_settings_select_callback',
            'asfw_admin',
            'asfw_integrations_settings_section',
            array(
                'name' => AntiSpamForWordPressPlugin::$option_integration_custom,
                'hint' => sprintf(
                    /* translators: %s is a shortcode tag. */
                    __('Use the %s shortcode anywhere in your form markup.', 'anti-spam-for-wordpress'),
                    '[anti_spam_widget]'
                ),
                'options' => array(
                    '' => __('Disable', 'anti-spam-for-wordpress'),
                    'captcha' => __('Captcha', 'anti-spam-for-wordpress'),
                ),
            )
        );

        do_action('asfw_settings_integrations');

        add_settings_section(
            'asfw_wordpress_settings_section',
            __('WordPress', 'anti-spam-for-wordpress'),
            'asfw_wordpress_section_callback',
            'asfw_admin'
        );

        asfw_add_wordpress_field(
            'asfw_settings_wordpress_register_integration_field',
            __('Register page', 'anti-spam-for-wordpress'),
            AntiSpamForWordPressPlugin::$option_integration_wordpress_register
        );
        asfw_add_wordpress_field(
            'asfw_settings_wordpress_reset_password_integration_field',
            __('Reset password page', 'anti-spam-for-wordpress'),
            AntiSpamForWordPressPlugin::$option_integration_wordpress_reset_password
        );
        asfw_add_wordpress_field(
            'asfw_settings_wordpress_login_integration_field',
            __('Login page', 'anti-spam-for-wordpress'),
            AntiSpamForWordPressPlugin::$option_integration_wordpress_login
        );
        asfw_add_wordpress_field(
            'asfw_settings_wordpress_comments_integration_field',
            __('Comments', 'anti-spam-for-wordpress'),
            AntiSpamForWordPressPlugin::$option_integration_wordpress_comments
        );
    }

    function asfw_add_integration_field($field_id, $label, $option_name, $disabled = false, $allow_shortcode = false)
    {
        $options = array(
            '' => __('Disable', 'anti-spam-for-wordpress'),
            'captcha' => __('Captcha', 'anti-spam-for-wordpress'),
        );
        if ($allow_shortcode) {
            $options['shortcode'] = __('Shortcode', 'anti-spam-for-wordpress');
        }

        add_settings_field(
            $field_id,
            $label,
            'asfw_settings_select_callback',
            'asfw_admin',
            'asfw_integrations_settings_section',
            array(
                'name' => $option_name,
                'disabled' => $disabled,
                'options' => $options,
            )
        );
    }

    function asfw_add_wordpress_field($field_id, $label, $option_name)
    {
        add_settings_field(
            $field_id,
            $label,
            'asfw_settings_select_callback',
            'asfw_admin',
            'asfw_wordpress_settings_section',
            array(
                'name' => $option_name,
                'options' => array(
                    '' => __('Disable', 'anti-spam-for-wordpress'),
                    'captcha' => __('Captcha', 'anti-spam-for-wordpress'),
                ),
            )
        );
    }
}
