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
            AntiSpamForWordPressPlugin::$option_auto,
            AntiSpamForWordPressPlugin::$option_floating,
            AntiSpamForWordPressPlugin::$option_delay,
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
                    '3600' => __('1 hour', 'anti-spam-for-wordpress'),
                    '14400' => __('4 hours', 'anti-spam-for-wordpress'),
                    '0' => __('No expiration', 'anti-spam-for-wordpress'),
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
