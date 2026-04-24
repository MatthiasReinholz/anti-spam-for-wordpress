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
$GLOBALS['asfw_test_http_requests'] = $GLOBALS['asfw_test_http_requests'] ?? array();
$GLOBALS['asfw_test_http_responses'] = $GLOBALS['asfw_test_http_responses'] ?? array();
$GLOBALS['asfw_test_registered_settings'] = $GLOBALS['asfw_test_registered_settings'] ?? array();
$GLOBALS['asfw_test_settings_sections'] = $GLOBALS['asfw_test_settings_sections'] ?? array();
$GLOBALS['asfw_test_settings_fields'] = $GLOBALS['asfw_test_settings_fields'] ?? array();
$GLOBALS['asfw_test_db_tables'] = $GLOBALS['asfw_test_db_tables'] ?? array();
$GLOBALS['asfw_test_db_fetch_args'] = $GLOBALS['asfw_test_db_fetch_args'] ?? array();
$GLOBALS['asfw_test_db_queries'] = $GLOBALS['asfw_test_db_queries'] ?? array();
$GLOBALS['asfw_test_dbdelta_queries'] = $GLOBALS['asfw_test_dbdelta_queries'] ?? array();
$GLOBALS['asfw_test_cron_events'] = $GLOBALS['asfw_test_cron_events'] ?? array();
$GLOBALS['asfw_test_cli_commands'] = $GLOBALS['asfw_test_cli_commands'] ?? array();
$GLOBALS['asfw_test_locale'] = $GLOBALS['asfw_test_locale'] ?? 'en_US';
$GLOBALS['asfw_active_plugins'] = $GLOBALS['asfw_active_plugins'] ?? array(
	'woocommerce/woocommerce.php',
	'html-forms/html-forms.php',
	'wpdiscuz/class.WpdiscuzCore.php',
);

if (!defined('DAY_IN_SECONDS')) {
    define('DAY_IN_SECONDS', 86400);
}

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

function wp_verify_nonce($nonce, $action = -1)
{
    unset($action);

    return is_string($nonce) && '' !== trim($nonce);
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

function asfw_test_queue_http_response($response)
{
    $GLOBALS['asfw_test_http_responses'][] = $response;
}

function asfw_test_last_http_request()
{
    if (empty($GLOBALS['asfw_test_http_requests'])) {
        return null;
    }

    return $GLOBALS['asfw_test_http_requests'][count($GLOBALS['asfw_test_http_requests']) - 1];
}

function is_wp_error($thing)
{
    return $thing instanceof WP_Error;
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

function home_url($path = '', $scheme = null)
{
    return 'https://example.test' . ('' !== (string) $path ? '/' . ltrim((string) $path, '/') : '');
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
    $old_value = array_key_exists($option, $GLOBALS['asfw_test_options']) ? $GLOBALS['asfw_test_options'][$option] : null;
    $GLOBALS['asfw_test_options'][$option] = $value;
    do_action('updated_option', $option, $old_value, $value);

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

function asfw_test_remove_hook($hook_name, $callback, $priority)
{
    if (empty($GLOBALS['asfw_test_hooks'][$hook_name][$priority])) {
        return false;
    }

    $removed = false;
    foreach ($GLOBALS['asfw_test_hooks'][$hook_name][$priority] as $index => $callback_config) {
        if ($callback_config['callback'] === $callback) {
            unset($GLOBALS['asfw_test_hooks'][$hook_name][$priority][$index]);
            $removed = true;
        }
    }

    if (empty($GLOBALS['asfw_test_hooks'][$hook_name][$priority])) {
        unset($GLOBALS['asfw_test_hooks'][$hook_name][$priority]);
    }

    if (empty($GLOBALS['asfw_test_hooks'][$hook_name])) {
        unset($GLOBALS['asfw_test_hooks'][$hook_name]);
    }

    return $removed;
}

function add_action($hook_name, $callback, $priority = 10, $accepted_args = 1)
{
    return asfw_test_add_hook($hook_name, $callback, (int) $priority, (int) $accepted_args);
}

function add_filter($hook_name, $callback, $priority = 10, $accepted_args = 1)
{
    return asfw_test_add_hook($hook_name, $callback, (int) $priority, (int) $accepted_args);
}

function remove_action($hook_name, $callback, $priority = 10)
{
    return asfw_test_remove_hook($hook_name, $callback, (int) $priority);
}

function remove_filter($hook_name, $callback, $priority = 10)
{
    return asfw_test_remove_hook($hook_name, $callback, (int) $priority);
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

function dbDelta($queries)
{
    $GLOBALS['asfw_test_dbdelta_queries'][] = $queries;

    if (preg_match('/CREATE TABLE\s+([^\s(]+)/i', (string) $queries, $matches)) {
        $table = trim($matches[1], '`');
        if (!isset($GLOBALS['asfw_test_db_tables'][$table])) {
            $GLOBALS['asfw_test_db_tables'][$table] = array();
        }
    }

    return true;
}

function wp_next_scheduled($hook, $args = array())
{
    return isset($GLOBALS['asfw_test_cron_events'][$hook]) ? $GLOBALS['asfw_test_cron_events'][$hook]['timestamp'] : false;
}

function wp_schedule_event($timestamp, $recurrence, $hook, $args = array(), $wp_error = false)
{
    $GLOBALS['asfw_test_cron_events'][$hook] = array(
        'timestamp' => (int) $timestamp,
        'recurrence' => (string) $recurrence,
        'args' => $args,
    );

    return true;
}

function wp_clear_scheduled_hook($hook, $args = array())
{
    unset($GLOBALS['asfw_test_cron_events'][$hook]);

    return 1;
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

function wp_remote_request($url, $args = array())
{
    $GLOBALS['asfw_test_http_requests'][] = array(
        'url'  => (string) $url,
        'args' => $args,
    );

    if (!empty($GLOBALS['asfw_test_http_responses'])) {
        return array_shift($GLOBALS['asfw_test_http_responses']);
    }

    return array(
        'response' => array(
            'code'    => 200,
            'message' => 'OK',
        ),
        'headers'  => array(),
        'body'     => '{}',
    );
}

function wp_remote_get($url, $args = array())
{
    $args['method'] = 'GET';

    return wp_remote_request($url, $args);
}

function wp_remote_post($url, $args = array())
{
    $args['method'] = 'POST';

    return wp_remote_request($url, $args);
}

function wp_remote_retrieve_response_code($response)
{
    if (is_array($response) && isset($response['response']['code'])) {
        return (int) $response['response']['code'];
    }

    return 0;
}

function wp_remote_retrieve_body($response)
{
    if (is_array($response) && isset($response['body'])) {
        return (string) $response['body'];
    }

    return '';
}

function wp_remote_retrieve_headers($response)
{
    if (is_array($response) && isset($response['headers'])) {
        return $response['headers'];
    }

    return array();
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
    $GLOBALS['asfw_test_registered_settings'][] = array(
        'option_group' => $option_group,
        'option_name' => $option_name,
        'args' => $args,
    );

    return true;
}

function add_settings_section($id, $title, $callback, $page)
{
    $GLOBALS['asfw_test_settings_sections'][] = array(
        'id' => $id,
        'title' => $title,
        'callback' => $callback,
        'page' => $page,
    );

    return true;
}

function add_settings_field($id, $title, $callback, $page, $section = 'default', $args = array())
{
    $GLOBALS['asfw_test_settings_fields'][] = array(
        'id' => $id,
        'title' => $title,
        'callback' => $callback,
        'page' => $page,
        'section' => $section,
        'args' => $args,
    );

    return true;
}

function add_options_page($page_title, $menu_title, $capability, $menu_slug, $callback = '', $position = null)
{
    return true;
}

function add_submenu_page($parent_slug, $page_title, $menu_title, $capability, $menu_slug, $callback = '', $position = null)
{
    return true;
}

function get_admin_url($blog_id = null, $path = '', $scheme = 'admin')
{
    return 'https://example.test/wp-admin/';
}

function admin_url($path = '', $scheme = 'admin')
{
    return 'https://example.test/wp-admin/' . ltrim((string) $path, '/');
}

function wp_safe_redirect($location, $status = 302, $x_redirect_by = 'WordPress')
{
    $GLOBALS['asfw_last_safe_redirect'] = array(
        'location' => (string) $location,
        'status' => (int) $status,
        'x_redirect_by' => (string) $x_redirect_by,
    );

    return true;
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
    $GLOBALS['asfw_test_http_requests'] = array();
    $GLOBALS['asfw_test_http_responses'] = array();
    $GLOBALS['asfw_test_cli_commands'] = array();
    $GLOBALS['asfw_test_registered_settings'] = array();
    $GLOBALS['asfw_test_settings_sections'] = array();
    $GLOBALS['asfw_test_settings_fields'] = array();
    $GLOBALS['asfw_test_db_tables'] = array();
    $GLOBALS['asfw_test_db_fetch_args'] = array();
    $GLOBALS['asfw_test_db_queries'] = array();
    $GLOBALS['asfw_test_dbdelta_queries'] = array();
    $GLOBALS['asfw_test_cron_events'] = array();
    $GLOBALS['asfw_test_rest_routes'] = array();

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
    } else {
        $GLOBALS['asfw_active_plugins'] = array(
            'woocommerce/woocommerce.php',
            'html-forms/html-forms.php',
            'wpdiscuz/class.WpdiscuzCore.php',
        );
    }

    if (class_exists('WP_CLI', false)) {
        WP_CLI::$commands = array();
        WP_CLI::$logs = array();
        WP_CLI::$successes = array();
        WP_CLI::$warnings = array();
        WP_CLI::$errors = array();
    }

    if (function_exists('asfw_activate')) {
        asfw_activate();
    }

    do_action('rest_api_init');

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

    public function get_error_data($code = '')
    {
        if ($code === '') {
            $code = $this->get_error_code();
        }

        if ($code === '' || !isset($this->errors[$code])) {
            return null;
        }

        $entries = $this->errors[$code];
        $last = end($entries);

        return is_array($last) && array_key_exists('data', $last) ? $last['data'] : null;
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

    public function get_params()
    {
        return $this->params;
    }

    public function get_json_params()
    {
        return $this->params;
    }

    public function get_query_params()
    {
        return $this->params;
    }

    public function get_header($name)
    {
        return '';
    }

    public function set_param($name, $value)
    {
        $this->params[$name] = $value;
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

    public function get_data()
    {
        return $this->data;
    }

    public function get_headers()
    {
        return $this->headers;
    }
}

class wpdb
{
    public $prefix = 'wp_';
    public $options = 'wp_options';
    public $insert_id = 0;

    public function get_charset_collate()
    {
        return 'DEFAULT CHARSET=utf8mb4';
    }

    protected function ensure_table($table)
    {
        if (!isset($GLOBALS['asfw_test_db_tables'][$table])) {
            $GLOBALS['asfw_test_db_tables'][$table] = array();
        }
    }

    protected function asfw_row_value(array $row, array $keys, $default = '')
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $row) && null !== $row[$key] && '' !== $row[$key]) {
                return $row[$key];
            }
        }

        foreach ($keys as $key) {
            if (array_key_exists($key, $row)) {
                return $row[$key];
            }
        }

        return $default;
    }

    protected function asfw_normalize_event_row(array $row)
    {
        $created_at = (string) $this->asfw_row_value($row, array('created_at', 'created_at_gmt'), '');
        if ('' === $created_at) {
            $created_at = gmdate('Y-m-d H:i:s');
        }

        $context = (string) $this->asfw_row_value($row, array('context', 'event_context'), '');
        $feature = (string) $this->asfw_row_value($row, array('feature', 'module_name', 'module'), '');
        $decision = (string) $this->asfw_row_value($row, array('decision', 'event_status', 'status'), '');
        $ip_hash = $this->asfw_row_value($row, array('ip_hash', 'actor_hash'), null);
        $email_hash = $this->asfw_row_value($row, array('email_hash'), null);

        $row['created_at'] = $created_at;
        $row['created_at_gmt'] = $created_at;
        $row['context'] = $context;
        $row['event_context'] = $context;
        $row['feature'] = $feature;
        $row['module_name'] = $feature;
        $row['decision'] = $decision;
        $row['event_status'] = $decision;
        $row['ip_hash'] = null === $ip_hash || '' === $ip_hash ? null : (string) $ip_hash;
        $row['actor_hash'] = null === $ip_hash || '' === $ip_hash ? '' : (string) $ip_hash;
        $row['email_hash'] = null === $email_hash || '' === $email_hash ? null : (string) $email_hash;
        if (!array_key_exists('subject_hash', $row)) {
            $row['subject_hash'] = '';
        }

        return $row;
    }

    public function asfw_insert_event($table, array $row)
    {
        $this->ensure_table($table);
        $row['id'] = count($GLOBALS['asfw_test_db_tables'][$table]) + 1;
        $GLOBALS['asfw_test_db_tables'][$table][] = $row;
        $this->insert_id = $row['id'];

        return true;
    }

    public function insert($table, $data, $format = array())
    {
        return $this->asfw_insert_event($table, (array) $data);
    }

    public function asfw_fetch_events($table, array $args)
    {
        $GLOBALS['asfw_test_db_fetch_args'][] = $args;
        $this->ensure_table($table);
        $rows = array_map(array($this, 'asfw_normalize_event_row'), $GLOBALS['asfw_test_db_tables'][$table]);

        foreach (array(
            array('filter' => 'type', 'row' => 'event_type'),
            array('filter' => 'feature', 'row' => 'feature'),
            array('filter' => 'module', 'row' => 'feature'),
            array('filter' => 'module_name', 'row' => 'feature'),
            array('filter' => 'decision', 'row' => 'decision'),
            array('filter' => 'status', 'row' => 'decision'),
            array('filter' => 'event_status', 'row' => 'decision'),
            array('filter' => 'context', 'row' => 'context'),
            array('filter' => 'event_context', 'row' => 'context'),
        ) as $mapping) {
            if (!isset($args[$mapping['filter']]) || '' === trim((string) $args[$mapping['filter']])) {
                continue;
            }

            $rows = array_values(array_filter($rows, static function ($row) use ($mapping, $args) {
                return isset($row[$mapping['row']]) && (string) $row[$mapping['row']] === (string) $args[$mapping['filter']];
            }));
        }

        $dateFrom = $this->asfw_normalize_date_input($args['date_from'] ?? '', false);
        $dateTo = $this->asfw_normalize_date_input($args['date_to'] ?? '', true);
        if ($dateFrom !== '' || $dateTo !== '') {
            $rows = array_values(array_filter($rows, static function ($row) use ($dateFrom, $dateTo) {
                $createdAt = (string) ($row['created_at'] ?? '');
                if ($createdAt === '') {
                    return false;
                }

                if ($dateFrom !== '' && $createdAt < $dateFrom) {
                    return false;
                }

                if ($dateTo !== '' && $createdAt > $dateTo) {
                    return false;
                }

                return true;
            }));
        }

        usort($rows, static function ($left, $right) {
            return ($right['id'] ?? 0) <=> ($left['id'] ?? 0);
        });

        $offset = max(0, (int) ($args['offset'] ?? 0));
        $limit = max(0, (int) ($args['limit'] ?? 50));
        if ($limit > 0) {
            $rows = array_slice($rows, $offset, $limit);
        }

        return array_values($rows);
    }

    public function asfw_count_events($table, array $args)
    {
        return count($this->asfw_fetch_events($table, array_merge($args, array('limit' => PHP_INT_MAX, 'offset' => 0))));
    }

    protected function asfw_normalize_date_input($value, bool $endOfDay = false): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return '';
        }

        return gmdate($endOfDay ? 'Y-m-d 23:59:59' : 'Y-m-d 00:00:00', $timestamp);
    }

    public function query($query)
    {
        $query = (string) $query;
        $GLOBALS['asfw_test_db_queries'][] = $query;

        if (preg_match('/DROP TABLE IF EXISTS\s+([^\s;]+)/i', $query, $matches)) {
            unset($GLOBALS['asfw_test_db_tables'][trim($matches[1], '`')]);

            return 1;
        }

        if (preg_match('/DELETE FROM\s+([^\s]+)\s+WHERE option_name LIKE \'([^\']*)\'/i', $query, $matches)) {
            $pattern = str_replace(array('\\_', '\\%'), array('_', '%'), $matches[2]);
            $regex = '/^' . str_replace('%', '.*', preg_quote($pattern, '/')) . '$/';
            $deleted = 0;
            foreach (array_keys($GLOBALS['asfw_test_options']) as $optionName) {
                if (preg_match($regex, (string) $optionName)) {
                    unset($GLOBALS['asfw_test_options'][$optionName]);
                    $deleted++;
                }
            }

            return $deleted;
        }

        return 0;
    }

    public function prepare($query, ...$args)
    {
        if (count($args) === 1 && is_array($args[0])) {
            $args = $args[0];
        }

        foreach ($args as $arg) {
            $replacement = is_int($arg) ? (string) $arg : "'" . addslashes((string) $arg) . "'";
            $query = preg_replace('/%[sd]/', $replacement, (string) $query, 1);
        }

        return (string) $query;
    }

    public function esc_like($text)
    {
        return addcslashes((string) $text, '_%\\');
    }

    public function asfw_type_counts($table)
    {
        $this->ensure_table($table);
        $counts = array();

        foreach ($GLOBALS['asfw_test_db_tables'][$table] as $row) {
            $type = $row['event_type'] ?? '';
            if ('' === $type) {
                continue;
            }

            $counts[$type] = ($counts[$type] ?? 0) + 1;
        }

        arsort($counts);

        return $counts;
    }

    public function asfw_module_counts($table)
    {
        $this->ensure_table($table);
        $counts = array();

        foreach (array_map(array($this, 'asfw_normalize_event_row'), $GLOBALS['asfw_test_db_tables'][$table]) as $row) {
            $feature = $row['feature'] ?? '';
            if ('' === $feature) {
                $feature = 'core';
            }

            $counts[$feature] = ($counts[$feature] ?? 0) + 1;
        }

        arsort($counts);

        return $counts;
    }

    public function asfw_daily_counts($table, $days)
    {
        $this->ensure_table($table);
        $counts = array();
        $today = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        for ($offset = $days - 1; $offset >= 0; $offset--) {
            $counts[$today->sub(new DateInterval('P' . $offset . 'D'))->format('Y-m-d')] = 0;
        }

        foreach (array_map(array($this, 'asfw_normalize_event_row'), $GLOBALS['asfw_test_db_tables'][$table]) as $row) {
            if (empty($row['created_at'])) {
                continue;
            }

            $day = substr((string) $row['created_at'], 0, 10);
            if (isset($counts[$day])) {
                $counts[$day]++;
            }
        }

        return $counts;
    }

    public function asfw_prune_events($table, $cutoff)
    {
        $this->ensure_table($table);
        $kept = array();
        $deleted = 0;

        foreach (array_map(array($this, 'asfw_normalize_event_row'), $GLOBALS['asfw_test_db_tables'][$table]) as $row) {
            if (!empty($row['created_at']) && (string) $row['created_at'] < (string) $cutoff) {
                $deleted++;
                continue;
            }

            $kept[] = $row;
        }

        $GLOBALS['asfw_test_db_tables'][$table] = $kept;

        return $deleted;
    }

    public function asfw_purge_events($table)
    {
        $this->ensure_table($table);
        $deleted = count($GLOBALS['asfw_test_db_tables'][$table]);
        $GLOBALS['asfw_test_db_tables'][$table] = array();

        return $deleted;
    }
}

if (!isset($GLOBALS['wpdb'])) {
    $GLOBALS['wpdb'] = new wpdb();
}

if (!class_exists('WP_CLI', false)) {
    class WP_CLI
    {
        public static $commands = array();
        public static $logs = array();
        public static $successes = array();
        public static $warnings = array();
        public static $errors = array();

        public static function add_command($name, $callable)
        {
            self::$commands[(string) $name] = $callable;
            $GLOBALS['asfw_test_cli_commands'][$name] = $callable;
        }

        public static function log($message)
        {
            self::$logs[] = (string) $message;
        }

        public static function success($message)
        {
            self::$successes[] = (string) $message;
            self::log($message);
        }

        public static function warning($message)
        {
            self::$warnings[] = (string) $message;
            self::log($message);
        }

        public static function error($message)
        {
            self::$errors[] = (string) $message;
            throw new RuntimeException((string) $message);
        }
    }
}
