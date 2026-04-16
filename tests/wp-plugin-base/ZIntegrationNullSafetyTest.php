<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/tests/support/phpstan-bootstrap.php';
require_once dirname(__DIR__, 2) . '/integrations/elementor/class-elementor-form-antispamwidget-field.php';
require_once dirname(__DIR__, 2) . '/integrations/gravityforms/class-asfw-gfforms-field.php';

final class IntegrationNullSafetyTest extends AsfwPluginTestCase
{
	private function withNullPluginInstance( callable $callback ): void {
		$original = AntiSpamForWordPressPlugin::$instance;
		AntiSpamForWordPressPlugin::$instance = null;

		try {
			$callback();
		} finally {
			AntiSpamForWordPressPlugin::$instance = $original;
		}
	}

	public function test_helpers_and_registered_hooks_skip_without_plugin_instance(): void
	{
		$this->withNullPluginInstance(
			function (): void {
				$this->assertSame( '', asfw_render_widget_markup( 'captcha', 'wordpress:login' ) );
				$this->assertFalse( asfw_verify_posted_widget( 'wordpress:login' ) );

				ob_start();
				asfw_render_wordpress_widget( 'captcha', 'wordpress:login' );
				$this->assertSame( '', (string) ob_get_clean() );

				ob_start();
				do_action( 'wpdiscuz_button_actions' );
				$this->assertSame( '', (string) ob_get_clean() );

				$comment = array(
					'comment_type'    => 'comment',
					'comment_content' => 'Hello world',
				);

				$this->assertSame( $comment, apply_filters( 'preprocess_comment', $comment ) );
			}
		);
	}

	public function test_elementor_field_skips_without_plugin_instance(): void
	{
		$this->withNullPluginInstance(
			function (): void {
				$field = new Elementor_Form_AntiSpamWidget_Field();

				ob_start();
				$this->assertSame( '', $field->render( array(), 0, new class() {
					public function get_render_attribute_string( $name ) {
						return 'data-test="' . $name . '"';
					}
				} ) );
				$this->assertSame( '', (string) ob_get_clean() );

				$ajax_handler = new class() {
					public $errors = array();

					public function add_error( $field_id, $message ) {
						$this->errors[] = array(
							'field_id' => $field_id,
							'message'  => $message,
						);
					}
				};

				$field->validation( array( 'id' => 'asfw' ), null, $ajax_handler );
				$this->assertSame( array(), $ajax_handler->errors );
			}
		);
	}

	public function test_gravityforms_field_skips_without_plugin_instance(): void
	{
		$this->withNullPluginInstance(
			function (): void {
				$field = new ASFW_GFForms_Field();

				$this->assertSame( '', $field->get_field_input( array() ) );

				$field->validate( null, array() );

				$this->assertFalse( $field->failed_validation );
				$this->assertSame( '', $field->validation_message );
			}
		);
	}
}
