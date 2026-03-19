<?php

if (!defined('ABSPATH')) {
    exit;
}

function asfw_options_page_html()
{
    wp_enqueue_style(
        'asfw-admin-styles',
        AntiSpamForWordPressPlugin::$admin_css_src,
        array(),
        ASFW_VERSION,
        'all'
    );
    ?>
    <div class="altcha-head">
      <div class="altcha-logo" aria-hidden="true" style="display:flex;align-items:center;justify-content:center;background:#202038;color:#fff;font-size:1.5rem;font-weight:700;">
        AS
      </div>

      <div style="flex-grow: 1;">
        <div class="altcha-title"><?php echo esc_html__('Anti Spam for WordPress', 'anti-spam-for-wordpress'); ?></div>
        <div class="altcha-subtitle"><?php echo esc_html__('Self-hosted spam protection for WordPress forms.', 'anti-spam-for-wordpress'); ?></div>
      </div>
    </div>

    <div class="wrap">
      <hr>

      <p><?php echo esc_html__('Anti Spam for WordPress is a fork of the ALTCHA WordPress plugin v1, adapted and maintained by Matthias Reinholz.', 'anti-spam-for-wordpress'); ?></p>

      <form action="options.php" method="post">
        <?php
        settings_errors();
        settings_fields('asfw_options');
        do_settings_sections('asfw_admin');
        submit_button();
        ?>
      </form>

      <div style="opacity: 0.8;">
        <p><?php
        echo sprintf(
            esc_html__(
                'Anti Spam for WordPress, plugin version %1$s, bundled widget version %2$s',
                'anti-spam-for-wordpress'
            ),
            AntiSpamForWordPressPlugin::$version,
            AntiSpamForWordPressPlugin::$widget_version
        );
        ?></p>
        <p>
          <a href="https://github.com/MatthiasReinholz/anti-spam-for-wordpress" target="_blank" rel="noopener noreferrer">
            <?php echo esc_html__('View the source on GitHub', 'anti-spam-for-wordpress'); ?>
          </a>
        </p>
      </div>
    </div>
    <?php
}

function asfw_general_section_callback()
{
    ?>
    <p><?php echo esc_html__('This plugin runs fully inside your WordPress installation and does not require an external API.', 'anti-spam-for-wordpress'); ?></p>
    <?php
}

function asfw_widget_section_callback()
{
    ?>
    <p><?php echo esc_html__('Customize the widget to fit your forms.', 'anti-spam-for-wordpress'); ?></p>
    <?php
}

function asfw_integrations_section_callback()
{
    ?>
    <p><?php echo esc_html__('Enable protection for these plugin integrations.', 'anti-spam-for-wordpress'); ?></p>
    <?php
}

function asfw_wordpress_section_callback()
{
    ?>
    <p><?php echo esc_html__('Enable protection for core WordPress screens.', 'anti-spam-for-wordpress'); ?></p>
    <?php
}

function asfw_settings_field_callback(array $args)
{
    $type = $args['type'];
    $name = $args['name'];
    $hint = isset($args['hint']) ? $args['hint'] : null;
    $description = isset($args['description']) ? $args['description'] : null;
    $setting = get_option($name);
    $value = isset($setting) ? esc_attr($setting) : '';
    if ($type === 'checkbox') {
        $value = 1;
    }
    ?>
    <input autocomplete="off" class="regular-text" type="<?php echo esc_attr($type); ?>" name="<?php echo esc_attr($name); ?>" id="<?php echo esc_attr($name); ?>" value="<?php echo esc_attr($value); ?>" <?php checked(1, $setting, $type === 'checkbox'); ?>>
    <label class="description" for="<?php echo esc_attr($name); ?>"><?php echo esc_html($description); ?></label>
    <?php if ($hint) { ?>
      <div style="opacity:0.7;font-size:85%;margin-top:3px"><?php echo esc_html($hint); ?></div>
    <?php } ?>
    <?php
}

function asfw_settings_select_callback(array $args)
{
    $name = $args['name'];
    $hint = isset($args['hint']) ? $args['hint'] : null;
    $disabled = isset($args['disabled']) ? $args['disabled'] : false;
    $description = isset($args['description']) ? $args['description'] : null;
    $options = isset($args['options']) ? $args['options'] : array();
    $setting = get_option($name);
    $value = isset($setting) ? esc_attr($setting) : '';
    ?>
    <select name="<?php echo esc_attr($name); ?>" id="<?php echo esc_attr($name); ?>" <?php echo $disabled ? 'disabled' : ''; ?>>
      <?php
      foreach ($options as $opt_key => $opt_value) {
          echo '<option value="' . esc_attr($opt_key) . '"' . selected($value, $opt_key, false) . '>' . esc_html($opt_value) . '</option>';
      }
      ?>
    </select>
    <label class="description" for="<?php echo esc_attr($name); ?>"><?php echo esc_html($description); ?></label>
    <?php if ($hint) { ?>
      <div style="opacity:0.7;font-size:85%;margin-top:3px"><?php echo esc_html($hint); ?></div>
    <?php } ?>
    <?php
}
