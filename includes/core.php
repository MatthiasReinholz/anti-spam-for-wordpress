<?php

if (!defined('ABSPATH')) {
    exit;
}

class AntiSpamForWordPressPlugin
{
    public static $instance;

    public static $language = '';

    public static $widget_script_src = '';

    public static $wp_script_src = '';

    public static $admin_script_src = '';

    public static $admin_css_src = '';

    public static $custom_script_src = '';

    public static $widget_style_src = '';

    public static $version = '0.0.0';

    public static $widget_version = '0.0.0';

    public static $option_secret = 'asfw_secret';

    public static $option_complexity = 'asfw_complexity';

    public static $option_expires = 'asfw_expires';

    public static $option_auto = 'asfw_auto';

    public static $option_floating = 'asfw_floating';

    public static $option_delay = 'asfw_delay';

    public static $option_hidefooter = 'asfw_hidefooter';

    public static $option_hidelogo = 'asfw_hidelogo';

    public static $option_integration_coblocks = 'asfw_integration_coblocks';

    public static $option_integration_contact_form_7 = 'asfw_integration_contact_form_7';

    public static $option_integration_custom = 'asfw_integration_custom';

    public static $option_integration_elementor = 'asfw_integration_elementor';

    public static $option_integration_enfold_theme = 'asfw_integration_enfold_theme';

    public static $option_integration_formidable = 'asfw_integration_formidable';

    public static $option_integration_forminator = 'asfw_integration_forminator';

    public static $option_integration_gravityforms = 'asfw_integration_gravityforms';

    public static $option_integration_woocommerce_login = 'asfw_integration_woocommerce_login';

    public static $option_integration_woocommerce_register = 'asfw_integration_woocommerce_register';

    public static $option_integration_woocommerce_reset_password = 'asfw_integration_woocommerce_reset_password';

    public static $option_integration_html_forms = 'asfw_integration_html_forms';

    public static $option_integration_wordpress_login = 'asfw_integration_wordpress_login';

    public static $option_integration_wordpress_register = 'asfw_integration_wordpress_register';

    public static $option_integration_wordpress_reset_password = 'asfw_integration_wordpress_reset_password';

    public static $option_integration_wordpress_comments = 'asfw_integration_wordpress_comments';

    public static $option_integration_wpdiscuz = 'asfw_integration_wpdiscuz';

    public static $option_integration_wpforms = 'asfw_integration_wpforms';

    public static $html_allowed_tags = array(
        'altcha-widget' => array(
            'challengeurl' => array(),
            'strings' => array(),
            'auto' => array(),
            'floating' => array(),
            'delay' => array(),
            'hidelogo' => array(),
            'hidefooter' => array(),
            'name' => array(),
        ),
        'div' => array(
            'class' => array(),
            'style' => array(),
        ),
        'input' => array(
            'class' => array(),
            'id' => array(),
            'name' => array(),
            'type' => array(),
            'value' => array(),
            'style' => array(),
        ),
        'noscript' => array(),
    );

    public static $hostname = null;

    public function init()
    {
        self::$instance = $this;
        self::$language = get_locale();

        if (defined('ASFW_VERSION')) {
            self::$version = ASFW_VERSION;
        }

        if (defined('ASFW_WIDGET_VERSION')) {
            self::$widget_version = ASFW_WIDGET_VERSION;
        }

        $url = wp_parse_url(get_site_url());
        self::$hostname = $url['host'] . (isset($url['port']) ? ':' . $url['port'] : '');
    }

    public function get_complexity()
    {
        return trim(get_option(self::$option_complexity));
    }

    public function get_expires()
    {
        return get_option(self::$option_expires);
    }

    public function get_secret()
    {
        return trim(get_option(self::$option_secret));
    }

    public function get_hidelogo()
    {
        return get_option(self::$option_hidelogo);
    }

    public function get_hidefooter()
    {
        return get_option(self::$option_hidefooter);
    }

    public function get_auto()
    {
        return trim(get_option(self::$option_auto));
    }

    public function get_floating()
    {
        return trim(get_option(self::$option_floating));
    }

    public function get_delay()
    {
        return trim(get_option(self::$option_delay));
    }

    public function get_integration_coblocks()
    {
        return trim(get_option(self::$option_integration_coblocks));
    }

    public function get_integration_contact_form_7()
    {
        return trim(get_option(self::$option_integration_contact_form_7));
    }

    public function get_integration_custom()
    {
        return trim(get_option(self::$option_integration_custom));
    }

    public function get_integration_elementor()
    {
        return trim(get_option(self::$option_integration_elementor));
    }

    public function get_integration_enfold_theme()
    {
        return trim(get_option(self::$option_integration_enfold_theme));
    }

    public function get_integration_formidable()
    {
        return trim(get_option(self::$option_integration_formidable));
    }

    public function get_integration_forminator()
    {
        return trim(get_option(self::$option_integration_forminator));
    }

    public function get_integration_gravityforms()
    {
        return trim(get_option(self::$option_integration_gravityforms));
    }

    public function get_integration_woocommerce_register()
    {
        return trim(get_option(self::$option_integration_woocommerce_register));
    }

    public function get_integration_woocommerce_reset_password()
    {
        return trim(get_option(self::$option_integration_woocommerce_reset_password));
    }

    public function get_integration_woocommerce_login()
    {
        return trim(get_option(self::$option_integration_woocommerce_login));
    }

    public function get_integration_html_forms()
    {
        return trim(get_option(self::$option_integration_html_forms));
    }

    public function get_integration_wordpress_register()
    {
        return trim(get_option(self::$option_integration_wordpress_register));
    }

    public function get_integration_wordpress_reset_password()
    {
        return trim(get_option(self::$option_integration_wordpress_reset_password));
    }

    public function get_integration_wordpress_login()
    {
        return trim(get_option(self::$option_integration_wordpress_login));
    }

    public function get_integration_wordpress_comments()
    {
        return trim(get_option(self::$option_integration_wordpress_comments));
    }

    public function get_integration_wpdiscuz()
    {
        return trim(get_option(self::$option_integration_wpdiscuz));
    }

    public function get_integration_wpforms()
    {
        return trim(get_option(self::$option_integration_wpforms));
    }

    public function get_challenge_url()
    {
        $challenge_url = get_rest_url(null, '/anti-spam-for-wordpress/v1/challenge');

        return apply_filters('asfw_challenge_url', $challenge_url);
    }

    public function get_translations($language = null)
    {
        $original_language = null;

        if ($language !== null) {
            $original_language = get_locale();
            switch_to_locale($language);
        }

        $website = constant('ASFW_WEBSITE');
        $translations = array(
            'error' => __('Verification failed. Try again later.', 'anti-spam-for-wordpress'),
            'footer' => sprintf(
                /* translators: the placeholders contain opening and closing tags for a link (<a> tag) */
                __('Protected by %sAnti Spam for WordPress%s', 'anti-spam-for-wordpress'),
                '<a href="' . esc_url($website) . '" target="_blank" rel="noopener noreferrer">',
                '</a>'
            ),
            'label' => __('I\'m not a robot', 'anti-spam-for-wordpress'),
            'verified' => __('Verified', 'anti-spam-for-wordpress'),
            'verifying' => __('Verifying...', 'anti-spam-for-wordpress'),
            'waitAlert' => __('Verifying... please wait.', 'anti-spam-for-wordpress'),
        );

        $translations = apply_filters('asfw_translations', $translations, $language);

        if ($original_language !== null) {
            switch_to_locale($original_language);
        }

        return $translations;
    }

    public function get_integrations()
    {
        $integrations = array(
            $this->get_integration_contact_form_7(),
            $this->get_integration_custom(),
            $this->get_integration_elementor(),
            $this->get_integration_enfold_theme(),
            $this->get_integration_forminator(),
            $this->get_integration_gravityforms(),
            $this->get_integration_html_forms(),
            $this->get_integration_woocommerce_register(),
            $this->get_integration_woocommerce_login(),
            $this->get_integration_woocommerce_reset_password(),
            $this->get_integration_wordpress_register(),
            $this->get_integration_wordpress_login(),
            $this->get_integration_wordpress_reset_password(),
            $this->get_integration_wordpress_comments(),
            $this->get_integration_wpforms(),
        );

        return apply_filters('asfw_integrations', $integrations);
    }

    public function has_active_integrations()
    {
        $integrations = $this->get_integrations();

        return in_array('captcha', $integrations, true) || in_array('shortcode', $integrations, true);
    }

    public function random_secret()
    {
        return bin2hex(random_bytes(12));
    }

    public function verify($payload, $hmac_key = null)
    {
        if ($hmac_key === null) {
            $hmac_key = $this->get_secret();
        }

        if (empty($payload) || empty($hmac_key)) {
            do_action('asfw_verify_result', false);

            return false;
        }

        $data = json_decode(base64_decode($payload));
        $result = $this->verify_solution($payload, $hmac_key);

        do_action('asfw_verify_result', $result);

        return $result;
    }

    public function verify_solution($payload, $hmac_key = null)
    {
        if ($hmac_key === null) {
            $hmac_key = $this->get_secret();
        }

        $data = json_decode(base64_decode($payload));
        $salt_url = wp_parse_url($data->salt);
        if (isset($salt_url['query']) && !empty($salt_url['query'])) {
            parse_str($salt_url['query'], $salt_params);
            if (!empty($salt_params['expires'])) {
                $expires = intval($salt_params['expires'], 10);
                if ($expires > 0 && $expires < time()) {
                    return false;
                }
            }
        }

        $alg_ok = ($data->algorithm === 'SHA-256');
        $calculated_challenge = hash('sha256', $data->salt . $data->number);
        $challenge_ok = ($data->challenge === $calculated_challenge);
        $calculated_signature = hash_hmac('sha256', $data->challenge, $hmac_key);
        $signature_ok = ($data->signature === $calculated_signature);

        return $alg_ok && $challenge_ok && $signature_ok;
    }

    public function generate_challenge($hmac_key = null, $complexity = null, $expires = null)
    {
        if ($hmac_key === null) {
            $hmac_key = $this->get_secret();
        }

        if ($complexity === null) {
            $complexity = $this->get_complexity();
        }

        if ($expires === null) {
            $expires = intval($this->get_expires(), 10);
        }

        $salt = $this->random_secret();
        if ($expires > 0) {
            $salt .= '?' . http_build_query(
                array(
                    'expires' => time() + $expires,
                )
            );
        }

        if (!str_ends_with($salt, '&')) {
            $salt .= '&';
        }

        switch ($complexity) {
            case 'low':
                $min_secret = 100;
                $max_secret = 1000;
                break;
            case 'medium':
                $min_secret = 1000;
                $max_secret = 20000;
                break;
            case 'high':
                $min_secret = 10000;
                $max_secret = 100000;
                break;
            default:
                $min_secret = 100;
                $max_secret = 10000;
        }

        $secret_number = random_int($min_secret, $max_secret);
        $challenge = hash('sha256', $salt . $secret_number);
        $signature = hash_hmac('sha256', $challenge, $hmac_key);

        return array(
            'algorithm' => 'SHA-256',
            'challenge' => $challenge,
            'maxnumber' => $max_secret,
            'salt' => $salt,
            'signature' => $signature,
        );
    }

    public function get_widget_attrs($mode, $language = null, $name = null)
    {
        $floating = $this->get_floating();
        $delay = $this->get_delay();
        $strings = wp_json_encode($this->get_translations($language));
        $attrs = array(
            'challengeurl' => $this->get_challenge_url(),
            'strings' => $strings,
        );

        if ($name) {
            $attrs['name'] = $name;
        }

        if ($this->get_auto()) {
            $attrs['auto'] = $this->get_auto();
        }

        if ($floating) {
            $attrs['floating'] = 'auto';
        }

        if ($delay) {
            $attrs['delay'] = '1500';
        }

        if ($this->get_hidelogo()) {
            $attrs['hidelogo'] = '1';
        }

        if ($this->get_hidefooter()) {
            $attrs['hidefooter'] = '1';
        }

        return apply_filters('asfw_widget_attrs', $attrs, $mode, $language, $name);
    }

    public function render_widget($mode, $wrap = false, $language = null, $name = null)
    {
        asfw_enqueue_scripts();
        asfw_enqueue_styles();

        $attrs = $this->get_widget_attrs($mode, $language, $name);
        $attributes = join(
            ' ',
            array_map(
                function ($key) use ($attrs) {
                    if (is_bool($attrs[$key])) {
                        return $attrs[$key] ? $key : '';
                    }

                    return esc_attr($key) . '="' . esc_attr($attrs[$key]) . '"';
                },
                array_keys($attrs)
            )
        );

        $html = '<altcha-widget ' . $attributes . '></altcha-widget>';
        $html .= '<noscript><div class="altcha-no-javascript">';
        $html .= esc_html__('This form requires JavaScript.', 'anti-spam-for-wordpress');
        $html .= '</div></noscript>';

        if ($wrap) {
            $html = '<div class="altcha-widget-wrap">' . $html . '</div>';
        }

        return apply_filters('asfw_widget_html', $html, $mode, $language, $name);
    }
}

if (!isset(AntiSpamForWordPressPlugin::$instance)) {
    $asfw_plugin_instance = new AntiSpamForWordPressPlugin();
    $asfw_plugin_instance->init();
}

require plugin_dir_path(__FILE__) . 'admin.php';
require plugin_dir_path(__FILE__) . 'settings.php';

add_action(
    'rest_api_init',
    function () {
        register_rest_route(
            'anti-spam-for-wordpress/v1',
            'challenge',
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => 'asfw_generate_challenge_endpoint',
                'permission_callback' => '__return_true',
            )
        );
    }
);

function asfw_generate_challenge_endpoint()
{
    $response = new WP_REST_Response(AntiSpamForWordPressPlugin::$instance->generate_challenge());
    $response->set_headers(array('Cache-Control' => 'no-cache, no-store, max-age=0'));

    return $response;
}
