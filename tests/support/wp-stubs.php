<?php
declare(strict_types=1);

if (!defined('ARRAY_A')) {
    define('ARRAY_A', 'ARRAY_A');
}

$GLOBALS['asfw_test_hooks'] = $GLOBALS['asfw_test_hooks'] ?? array();
$GLOBALS['asfw_test_options'] = $GLOBALS['asfw_test_options'] ?? array();
$GLOBALS['asfw_test_transients'] = $GLOBALS['asfw_test_transients'] ?? array();
$GLOBALS['asfw_test_shortcodes'] = $GLOBALS['asfw_test_shortcodes'] ?? array();
$GLOBALS['asfw_test_rest_routes'] = $GLOBALS['asfw_test_rest_routes'] ?? array();
$GLOBALS['asfw_test_locale'] = $GLOBALS['asfw_test_locale'] ?? 'en_US';
$GLOBALS['asfw_active_plugins'] = $GLOBALS['asfw_active_plugins'] ?? array();

function __($text, $domain = null)
{
    return (string) $text;
}

function esc_html__($text, $domain = null)
{
    return __($text, $domain);
}

function esc_html($text)
{
    return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
}

function esc_attr($text)
{
    return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
}

function wp_kses($content, $allowed_html)
{
    return (string) $content;
}

function sanitize_text_field($value)
{
    return trim(strip_tags((string) $value));
}

function sanitize_key($value)
{
    $value = strtolower((string) $value);

    return preg_replace('/[^a-z0-9_\-]/', '', $value);
}

function wp_unslash($value)
{
    if (is_array($value)) {
        return array_map('wp_unslash', $value);
    }

    return stripslashes((string) $value);
}

function wp_strip_all_tags($value)
{
    return strip_tags((string) $value);
}

function esc_url_raw($value)
{
    return trim((string) $value);
}

function wp_parse_url($url, $component = -1)
{
    return parse_url((string) $url, $component);
}

function wp_salt($scheme = 'auth')
{
    return 'asfw-test-salt-' . $scheme;
}

function wp_json_encode($value)
{
    return json_encode($value);
}

function get_locale()
{
    return $GLOBALS['asfw_test_locale'];
}

function switch_to_locale($locale)
{
    $GLOBALS['asfw_test_locale'] = (string) $locale;

    return true;
}

function load_plugin_textdomain($domain, $deprecated = false, $plugin_rel_path = false)
{
    return true;
}

function plugin_dir_path($file)
{
    return rtrim(dirname((string) $file), '/\\') . '/';
}

function plugin_dir_url($file)
{
    return 'https://example.test/' . basename(rtrim(dirname((string) $file), '/\\')) . '/';
}

function plugin_basename($file)
{
    return basename(dirname((string) $file)) . '/' . basename((string) $file);
}

function get_rest_url($blog_id = null, $path = '', $scheme = 'rest')
{
    return 'https://example.test/wp-json' . $path;
}

function add_query_arg($key, $value = null, $url = '')
{
    if (is_array($key)) {
        $query_args = $key;
        $url = (string) $value;
    } else {
        $query_args = array((string) $key => $value);
    }

    $parts = parse_url((string) $url);
    $query = array();
    if (!empty($parts['query'])) {
        parse_str($parts['query'], $query);
    }

    $query = array_merge($query, $query_args);
    $parts['query'] = http_build_query($query);

    $result = '';
    if (!empty($parts['scheme'])) {
        $result .= $parts['scheme'] . '://';
    }
    if (!empty($parts['host'])) {
        $result .= $parts['host'];
    }
    if (!empty($parts['path'])) {
        $result .= $parts['path'];
    }
    if (!empty($parts['query'])) {
        $result .= '?' . $parts['query'];
    }

    return $result;
}

function get_site_url($blog_id = null, $path = '', $scheme = null)
{
    return 'https://example.test';
}

function absint($value)
{
    return abs((int) $value);
}

function get_option($option, $default = false)
{
    if (array_key_exists($option, $GLOBALS['asfw_test_options'])) {
        return $GLOBALS['asfw_test_options'][$option];
    }

    return $default;
}

function update_option($option, $value, $autoload = null)
{
    $GLOBALS['asfw_test_options'][$option] = $value;

    return true;
}

function add_option($option, $value = '', $deprecated = '', $autoload = false)
{
    if (array_key_exists($option, $GLOBALS['asfw_test_options'])) {
        return false;
    }

    $GLOBALS['asfw_test_options'][$option] = $value;

    return true;
}

function delete_option($option)
{
    unset($GLOBALS['asfw_test_options'][$option]);

    return true;
}

function set_transient($transient, $value, $expiration = 0)
{
    $expires_at = $expiration > 0 ? time() + (int) $expiration : 0;
    $GLOBALS['asfw_test_transients'][$transient] = array(
        'value' => $value,
        'expires_at' => $expires_at,
    );

    return true;
}

function get_transient($transient)
{
    if (!isset($GLOBALS['asfw_test_transients'][$transient])) {
        return false;
    }

    $transient_state = $GLOBALS['asfw_test_transients'][$transient];
    if ($transient_state['expires_at'] > 0 && $transient_state['expires_at'] < time()) {
        unset($GLOBALS['asfw_test_transients'][$transient]);

        return false;
    }

    return $transient_state['value'];
}

function delete_transient($transient)
{
    unset($GLOBALS['asfw_test_transients'][$transient]);

    return true;
}

function asfw_test_add_hook($hook_name, $callback, $priority, $accepted_args)
{
    if (!isset($GLOBALS['asfw_test_hooks'][$hook_name])) {
        $GLOBALS['asfw_test_hooks'][$hook_name] = array();
    }
    if (!isset($GLOBALS['asfw_test_hooks'][$hook_name][$priority])) {
        $GLOBALS['asfw_test_hooks'][$hook_name][$priority] = array();
    }

    $GLOBALS['asfw_test_hooks'][$hook_name][$priority][] = array(
        'callback' => $callback,
        'accepted_args' => $accepted_args,
    );

    ksort($GLOBALS['asfw_test_hooks'][$hook_name]);

    return true;
}

function add_action($hook_name, $callback, $priority = 10, $accepted_args = 1)
{
    return asfw_test_add_hook($hook_name, $callback, (int) $priority, (int) $accepted_args);
}

function add_filter($hook_name, $callback, $priority = 10, $accepted_args = 1)
{
    return asfw_test_add_hook($hook_name, $callback, (int) $priority, (int) $accepted_args);
}

function do_action($hook_name, ...$args)
{
    if (empty($GLOBALS['asfw_test_hooks'][$hook_name])) {
        return;
    }

    foreach ($GLOBALS['asfw_test_hooks'][$hook_name] as $callbacks) {
        foreach ($callbacks as $callback_config) {
            $callback_args = array_slice($args, 0, $callback_config['accepted_args']);
            call_user_func_array($callback_config['callback'], $callback_args);
        }
    }
}

function apply_filters($hook_name, $value, ...$args)
{
    if (empty($GLOBALS['asfw_test_hooks'][$hook_name])) {
        return $value;
    }

    foreach ($GLOBALS['asfw_test_hooks'][$hook_name] as $callbacks) {
        foreach ($callbacks as $callback_config) {
            $callback_args = array_merge(array($value), $args);
            $callback_args = array_slice($callback_args, 0, $callback_config['accepted_args']);
            $value = call_user_func_array($callback_config['callback'], $callback_args);
        }
    }

    return $value;
}

function add_shortcode($tag, $callback)
{
    $GLOBALS['asfw_test_shortcodes'][$tag] = $callback;

    return true;
}

function shortcode_atts($pairs, $atts, $shortcode = '')
{
    $atts = is_array($atts) ? $atts : array();

    return array_merge($pairs, array_intersect_key($atts, $pairs));
}

function shortcode_parse_atts($text)
{
    $attributes = array();
    if (preg_match_all('/([a-zA-Z0-9_-]+)="([^"]*)"/', (string) $text, $matches, PREG_SET_ORDER) !== false) {
        foreach ($matches as $match) {
            $attributes[$match[1]] = $match[2];
        }
    }

    return $attributes;
}

function do_shortcode($content)
{
    return preg_replace_callback(
        '/\[([a-zA-Z0-9_-]+)([^\]]*)\]/',
        function ($matches) {
            $tag = $matches[1];
            if (!isset($GLOBALS['asfw_test_shortcodes'][$tag])) {
                return $matches[0];
            }

            $attributes = shortcode_parse_atts($matches[2]);

            return (string) call_user_func($GLOBALS['asfw_test_shortcodes'][$tag], $attributes);
        },
        (string) $content
    );
}

function register_activation_hook($file, $callback)
{
    return true;
}

function register_deactivation_hook($file, $callback)
{
    return true;
}

function is_admin()
{
    return false;
}

function wp_enqueue_script($handle, $src = '', $deps = array(), $ver = false, $args = false)
{
    return true;
}

function wp_enqueue_style($handle, $src = '', $deps = array(), $ver = false, $media = 'all')
{
    return true;
}

function wp_localize_script($handle, $object_name, $l10n)
{
    return true;
}

function wp_register_script($handle, $src = '', $deps = array(), $ver = false, $args = false)
{
    return true;
}

function wp_add_inline_script($handle, $data, $position = 'after')
{
    return true;
}

function get_post($post_id, $output = OBJECT, $filter = 'raw')
{
    return null;
}

function get_permalink($post_id = 0, $leavename = false)
{
    return 'https://example.test/privacy-policy';
}

function get_pages($args = array())
{
    return array();
}

function selected($selected, $current = true, $display = true)
{
    if ((string) $selected === (string) $current) {
        return $display ? ' selected="selected"' : ' selected="selected"';
    }

    return '';
}

function checked($checked, $current = true, $display = true)
{
    if ((string) $checked === (string) $current) {
        return $display ? ' checked="checked"' : ' checked="checked"';
    }

    return '';
}

function settings_errors()
{
    return true;
}

function settings_fields($option_group)
{
    return true;
}

function do_settings_sections($page)
{
    return true;
}

function submit_button($text = null)
{
    return true;
}

function register_setting($option_group, $option_name, $args = array())
{
    return true;
}

function add_settings_section($id, $title, $callback, $page)
{
    return true;
}

function add_settings_field($id, $title, $callback, $page, $section = 'default', $args = array())
{
    return true;
}

function add_options_page($page_title, $menu_title, $capability, $menu_slug, $callback = '', $position = null)
{
    return true;
}

function get_admin_url($blog_id = null, $path = '', $scheme = 'admin')
{
    return 'https://example.test/wp-admin/';
}

function wp_get_themes($args = array())
{
    return array();
}

function is_user_logged_in()
{
    return false;
}

function current_user_can($capability, ...$args)
{
    return false;
}

function wp_die($message = '')
{
    throw new RuntimeException(is_string($message) ? $message : 'wp_die');
}

function register_rest_route($namespace, $route, $args = array(), $override = false)
{
    $GLOBALS['asfw_test_rest_routes'][$namespace . '/' . ltrim((string) $route, '/')] = $args;

    return true;
}

function __return_true()
{
    return true;
}

function asfw_test_reset_state(array $options = array(), ?array $active_plugins = null)
{
    $_POST = array();
    $_GET = array();

    $GLOBALS['asfw_test_options'] = array();
    $GLOBALS['asfw_test_transients'] = array();

    foreach (array(
        'REMOTE_ADDR',
        'HTTP_USER_AGENT',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_REAL_IP',
        'HTTP_FORWARDED',
        'HTTP_CF_CONNECTING_IP',
    ) as $server_key) {
        unset($_SERVER[$server_key]);
    }

    if ($active_plugins !== null) {
        $GLOBALS['asfw_active_plugins'] = $active_plugins;
    }

    if (function_exists('asfw_activate')) {
        asfw_activate();
    }

    foreach ($options as $name => $value) {
        update_option($name, $value);
    }
}

class WP_Error
{
    protected $errors = array();

    public function __construct($code = '', $message = '', $data = '')
    {
        if ($code !== '') {
            $this->add($code, $message, $data);
        }
    }

    public function add($code, $message, $data = '')
    {
        if (!isset($this->errors[$code])) {
            $this->errors[$code] = array();
        }

        $this->errors[$code][] = array(
            'message' => $message,
            'data' => $data,
        );

        return $this;
    }

    public function get_error_code()
    {
        $codes = array_keys($this->errors);

        return $codes ? $codes[0] : '';
    }

    public function get_error_messages($code = '')
    {
        if ($code !== '') {
            if (!isset($this->errors[$code])) {
                return array();
            }

            return array_map(
                function ($entry) {
                    return $entry['message'];
                },
                $this->errors[$code]
            );
        }

        $messages = array();
        foreach ($this->errors as $entries) {
            foreach ($entries as $entry) {
                $messages[] = $entry['message'];
            }
        }

        return $messages;
    }
}

class WP_Post
{
    public $post_type = 'page';
    public $post_status = 'publish';
}

class WP_REST_Server
{
    public const READABLE = 'GET';
}

class WP_REST_Request
{
    protected $params;

    public function __construct(array $params = array())
    {
        $this->params = $params;
    }

    public function get_param($name)
    {
        return $this->params[$name] ?? null;
    }
}

class WP_REST_Response
{
    public $data;
    public $headers = array();

    public function __construct($data = null, $status = 200, array $headers = array())
    {
        $this->data = $data;
        $this->headers = $headers;
    }

    public function set_headers(array $headers)
    {
        $this->headers = $headers;
    }
}
