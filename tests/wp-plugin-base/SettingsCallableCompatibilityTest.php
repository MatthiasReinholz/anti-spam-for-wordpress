<?php
declare(strict_types=1);

final class ASFW_Settings_Callable_Test_Renderer
{
    public static int $calls = 0;

    public static function render(): void
    {
        ++self::$calls;
    }

    public function renderInstance(): void
    {
        ++self::$calls;
    }

    public function __invoke(): void
    {
        ++self::$calls;
    }
}

final class SettingsCallableCompatibilityTest extends AsfwPluginTestCase
{
    /** @dataProvider fieldContracts */
    public function test_valid_render_callbacks_preserve_the_settings_payload(string $callbackKind, ?string $type, string $expectedType): void
    {
        require_once dirname(__DIR__, 2) . '/includes/rest-operations/settings-operations.php';
        ASFW_Settings_Callable_Test_Renderer::$calls = 0;
        $renderer = new ASFW_Settings_Callable_Test_Renderer();
        $callbacks = array(
            'closure' => static function (): void { ++ASFW_Settings_Callable_Test_Renderer::$calls; },
            'static array' => array(ASFW_Settings_Callable_Test_Renderer::class, 'render'),
            'object array' => array($renderer, 'renderInstance'),
            'invokable' => $renderer,
            'select' => 'asfw_settings_select_callback',
            'textarea' => 'asfw_settings_textarea_callback',
            'privacy' => 'asfw_settings_privacy_target_callback',
            'field' => 'asfw_settings_field_callback',
        );
        $callback = $callbacks[$callbackKind];
        $args = array('name' => 'asfw_callable_test', 'options' => array('saved' => 'Saved choice'));
        if (null !== $type) {
            $args['type'] = $type;
        }
        $filter = static function (array $fields) use ($callback, $args): array {
            $fields['asfw_general_settings_section'][] = array(
                'id' => 'asfw_callable_test_field',
                'section' => 'asfw_general_settings_section',
                'title' => 'Callable extension',
                'option' => 'asfw_callable_test',
                'sanitize_callback' => 'sanitize_text_field',
                'callback' => $callback,
                'args' => $args,
            );
            return $fields;
        };
        add_filter('asfw_settings_schema_fields', $filter);
        update_option('asfw_callable_test', 'saved');
        set_error_handler(static function (int $severity, string $message, string $file, int $line): void {
            throw new ErrorException($message, 0, $severity, $file, $line);
        });
        try {
            asfw_settings_init();
            $registeredFields = array_column($GLOBALS['asfw_test_settings_fields'], null, 'id');
            self::assertSame($callback, $registeredFields['asfw_callable_test_field']['callback']);

            $payload = asfw_rest_operation_settings_read(null, array());
            $sections = array_column($payload['sections'], null, 'id');
            $fields = array_column($sections['asfw_general_settings_section']['fields'], null, 'id');
            $field = $fields['asfw_callable_test_field'];
            self::assertSame($expectedType, $field['type']);
            self::assertSame('checkbox' === $expectedType ? true : 'saved', $field['value']);
            self::assertSame('Callable extension', $field['label']);
            if ('select' === $expectedType) {
                self::assertSame(array(array('value' => 'saved', 'label' => 'Saved choice')), $field['options']);
            }
            self::assertSame(0, ASFW_Settings_Callable_Test_Renderer::$calls, 'REST describes controls without executing PHP field renderers.');
        } finally {
            restore_error_handler();
            remove_filter('asfw_settings_schema_fields', $filter);
        }
    }

    public static function fieldContracts(): array
    {
        return array(
            'closure defaults to text' => array('closure', null, 'text'),
            'closure explicit textarea' => array('closure', 'textarea', 'textarea'),
            'static callable select' => array('static array', 'select', 'select'),
            'object callable checkbox' => array('object array', 'checkbox', 'checkbox'),
            'invokable explicit number' => array('invokable', 'number', 'number'),
            'built-in select keeps inference' => array('select', 'text', 'select'),
            'built-in textarea keeps inference' => array('textarea', 'text', 'textarea'),
            'built-in privacy keeps inference' => array('privacy', 'text', 'privacy_target'),
            'built-in generic field keeps explicit type' => array('field', 'email', 'email'),
        );
    }
}
