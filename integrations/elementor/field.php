<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('\ElementorPro\Modules\Forms\Fields\Field_Base')) {
    die();
}

class Elementor_Form_AntiSpamWidget_Field extends \ElementorPro\Modules\Forms\Fields\Field_Base
{
    public function get_type()
    {
        return 'anti_spam_widget';
    }

    public function get_name()
    {
        return esc_html__('Anti Spam Widget', 'anti-spam-for-wordpress');
    }

    public function render($item, $item_index, $form)
    {
        $plugin = AntiSpamForWordPressPlugin::$instance;
        $mode = $plugin->get_integration_elementor();
        if (empty($mode)) {
            return '';
        }

        echo wp_kses(
            "<div style=\"flex-basis:100%\">" . $plugin->render_widget($mode, false) . '</div>',
            AntiSpamForWordPressPlugin::$html_allowed_tags
        );
        echo wp_kses(
            '<input type="hidden" ' . $form->get_render_attribute_string('input' . $item_index) . '>',
            AntiSpamForWordPressPlugin::$html_allowed_tags
        );
    }

    public function update_controls($widget)
    {
        $elementor = \ElementorPro\Plugin::elementor();
        $control_data = $elementor->controls_manager->get_control_from_stack($widget->get_unique_name(), 'form_fields');
        if (is_wp_error($control_data)) {
            return;
        }

        $control_data = $this->remove_control_form_field_type('required', $control_data);
        $widget->update_control('form_fields', $control_data);
    }

    private function remove_control_form_field_type($control_name, $control_data)
    {
        foreach ($control_data['fields'] as $index => $field) {
            if ($control_name !== $field['name']) {
                continue;
            }
            foreach ($field['conditions']['terms'] as $condition_index => $terms) {
                if (!isset($terms['name']) || 'field_type' !== $terms['name'] || !isset($terms['operator']) || '!in' !== $terms['operator']) {
                    continue;
                }
                $control_data['fields'][$index]['conditions']['terms'][$condition_index]['value'][] = $this->get_type();
                break;
            }
            break;
        }

        return $control_data;
    }

    public function validation($field, $record, $ajax_handler)
    {
        $plugin = AntiSpamForWordPressPlugin::$instance;
        $mode = $plugin->get_integration_elementor();
        if (!empty($mode) && $mode === 'captcha') {
            $payload = isset($_POST['asfw']) ? trim(sanitize_text_field($_POST['asfw'])) : '';
            if ($plugin->verify($payload) === false) {
                $ajax_handler->add_error(
                    $field['id'],
                    esc_html__('Verification failed.', 'anti-spam-for-wordpress')
                );
            }
        }
    }
}

if (AntiSpamForWordPressPlugin::$instance->get_integration_elementor()) {
    asfw_enqueue_scripts();
    asfw_enqueue_styles();
}
