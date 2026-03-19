<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('insertBeforeKey')) {
    function insertBeforeKey($array, $key, $newKey, $newValue)
    {
        $newArray = array();

        foreach ($array as $k => $v) {
            if ($k === $key) {
                $newArray[$newKey] = $newValue;
            }
            $newArray[$k] = $v;
        }

        return $newArray;
    }
}

if (!function_exists('asfw_enfold_theme_add_captcha_field')) {
    function asfw_enfold_theme_add_captcha_field($elements)
    {
        $plugin = AntiSpamForWordPressPlugin::$instance;
        $mode = $plugin->get_integration_enfold_theme();
        if ($mode !== 'captcha') {
            return $elements;
        }

        $captcha = array(
            'id' => 'captcha',
            'type' => 'html',
            'content' => wp_kses($plugin->render_widget($mode, true), AntiSpamForWordPressPlugin::$html_allowed_tags),
        );

        return insertBeforeKey($elements, 'av-button', 'captcha', $captcha);
    }
}

add_filter('ava_mailchimp_contact_form_elements', 'asfw_enfold_theme_add_captcha_field');
add_filter('avia_contact_form_elements', 'asfw_enfold_theme_add_captcha_field');

add_filter(
    'avf_form_send',
    function ($proceed, $new_post, $form_params, $that) {
        /** @var avia_form $that */
        $plugin = AntiSpamForWordPressPlugin::$instance;
        $mode = $plugin->get_integration_enfold_theme();
        if (!empty($mode)) {
            $payload = isset($_POST['asfw']) ? trim(sanitize_text_field(urldecode($_POST['asfw']))) : '';
            if ($plugin->verify($payload) === false) {
                $that->submit_error = __('Verification failed. Try again later.', 'anti-spam-for-wordpress');
                error_log('asfw: verification failed');

                return null;
            }
        }

        return $proceed;
    },
    10,
    4
);

add_filter(
    'avf_mailchimp_subscriber_data',
    function ($data, $that) {
        /** @var avia_sc_mailchimp $that */
        $plugin = AntiSpamForWordPressPlugin::$instance;
        $mode = $plugin->get_integration_enfold_theme();
        if (!empty($mode)) {
            $payload = isset($_POST['asfw']) ? trim(sanitize_text_field(urldecode($_POST['asfw']))) : '';
            if ($plugin->verify($payload) === false) {
                $data['email_address'] = 'captcha failed';
                $data['status'] = 'THIS STATUS DOES NOT EXIST';
            }
        }

        return $data;
    },
    10,
    2
);
