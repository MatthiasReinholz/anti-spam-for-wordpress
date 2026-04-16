<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'ASFW_Widget_Renderer', false ) ) {
	class ASFW_Widget_Renderer {

		private $context_helper_service;
		private $options_service;

		private function context_helper_service() {
			if ( ! $this->context_helper_service instanceof ASFW_Context_Helper ) {
				$this->context_helper_service = new ASFW_Context_Helper();
			}

			return $this->context_helper_service;
		}

		private function options_service() {
			if ( ! $this->options_service instanceof ASFW_Options ) {
				$this->options_service = new ASFW_Options();
			}

			return $this->options_service;
		}

		public function get_widget_provider() {
			return apply_filters( 'asfw_widget_provider', 'asfw' );
		}

		public function get_widget_tag_name() {
			return apply_filters( 'asfw_widget_tag_name', 'asfw-widget' );
		}

		public function get_translations( $language = null ) {
			$original_language = null;

			if ( null !== $language ) {
				$original_language = get_locale();
				switch_to_locale( $language );
			}

			$translations = array(
				'error'     => __( 'Verification failed. Try again later.', 'anti-spam-for-wordpress' ),
				'footer'    => $this->options_service()->get_footer_text(),
				'label'     => __( 'I\'m not a robot', 'anti-spam-for-wordpress' ),
				'privacy'   => __( 'Privacy', 'anti-spam-for-wordpress' ),
				'required'  => __( 'Please verify before submitting.', 'anti-spam-for-wordpress' ),
				'verified'  => __( 'Verified', 'anti-spam-for-wordpress' ),
				'verifying' => __( 'Verifying...', 'anti-spam-for-wordpress' ),
				'waitAlert' => __( 'Verifying... please wait.', 'anti-spam-for-wordpress' ),
			);

			$translations = apply_filters( 'asfw_translations', $translations, $language );

			if ( null !== $original_language ) {
				switch_to_locale( $original_language );
			}

			return $translations;
		}

		public function get_challenge_url( $context = null ) {
			$challenge_url = get_rest_url( null, '/anti-spam-for-wordpress/v1/challenge' );
			if ( ! empty( $context ) ) {
				$challenge_url = add_query_arg(
					'context',
					$this->context_helper_service()->normalize_context( $context ),
					$challenge_url
				);
			}

			return apply_filters( 'asfw_challenge_url', $challenge_url, $context );
		}

			public function get_widget_attrs( $mode, $language = null, $name = null, $context = null, $context_resolved = false ) {
				$floating   = $this->options_service()->get_floating();
				$delay      = $this->options_service()->get_delay();
				$field_name = 'asfw';
			if ( null !== $name && '' !== $name ) {
				$field_name = sanitize_key( $name );
			}
				if ( ! $context_resolved ) {
					$context = $this->context_helper_service()->get_widget_context( $mode, $field_name, $context );
				}
				$strings = wp_json_encode( $this->get_translations( $language ) );
			$auto    = $this->options_service()->get_auto();
			$lazy    = $this->options_service()->get_lazy();
			$attrs   = array(
				'data-asfw-context'         => $context,
				'data-asfw-field'           => $field_name,
				'data-asfw-lazy'            => $lazy ? '1' : '0',
				'data-asfw-min-submit-time' => (string) max( 0, $this->options_service()->get_min_submit_time() ),
				'data-asfw-provider'        => $this->get_widget_provider(),
				'strings'                   => $strings,
			);

			$privacy_url = $this->options_service()->get_privacy_url();
			if ( '' !== $privacy_url ) {
				$attrs['data-asfw-privacy-url']     = $privacy_url;
				$attrs['data-asfw-privacy-new-tab'] = $this->options_service()->get_privacy_new_tab() ? '1' : '0';
			}

			$challenge_url = $this->get_challenge_url( $context );
			if ( $lazy && 'onload' !== $auto ) {
				$attrs['data-asfw-challengeurl'] = $challenge_url;
			} else {
				$attrs['challengeurl'] = $challenge_url;
			}

			$attrs['name'] = $field_name;

			if ( $auto ) {
				$attrs['auto'] = $auto;
			}

			if ( $floating ) {
				$attrs['floating'] = 'auto';
			}

			if ( $delay ) {
				$attrs['delay'] = '1500';
			}

			if ( $this->options_service()->get_hidelogo() ) {
				$attrs['hidelogo'] = '1';
			}

			if ( $this->options_service()->get_hidefooter() ) {
				$attrs['hidefooter'] = '1';
			}

			return apply_filters( 'asfw_widget_attrs', $attrs, $mode, $language, $field_name, $context );
		}

		public function render_widget_auxiliary_fields( $field_name = 'asfw', $context = 'generic' ) {
			$html  = '<input type="hidden" name="' . esc_attr( $this->context_helper_service()->get_started_field_name( $field_name ) ) . '" value="">';
			$html .= '<input type="hidden" name="' . esc_attr( $this->context_helper_service()->get_context_field_name( $field_name ) ) . '" value="' . esc_attr( $context ) . '">';
			$html .= '<input type="hidden" name="' . esc_attr( $this->context_helper_service()->get_context_signature_field_name( $field_name ) ) . '" value="' . esc_attr( $this->context_helper_service()->sign_widget_context( $context, $field_name ) ) . '">';

			if ( $this->options_service()->get_honeypot() ) {
				$html .= '<div class="asfw-honeypot" aria-hidden="true">';
				$html .= '<input type="text" autocomplete="off" tabindex="-1" name="' . esc_attr( $this->context_helper_service()->get_honeypot_field_name( $field_name ) ) . '" value="">';
				$html .= '</div>';
			}

			return $html;
		}

		public function render_widget( $mode, $wrap = false, $language = null, $name = null, $context = null ) {
			if ( $this->options_service()->is_kill_switch_enabled() ) {
				return '';
			}

			asfw_enqueue_scripts();
			asfw_enqueue_styles();

			$field_name = 'asfw';
			if ( null !== $name && '' !== $name ) {
				$field_name = sanitize_key( $name );
			}
			$normalized_context = $this->context_helper_service()->get_widget_context( $mode, $field_name, $context );
				$attrs              = $this->get_widget_attrs( $mode, $language, $field_name, $normalized_context, true );
			$signed_context     = $normalized_context;
			if ( isset( $attrs['data-asfw-context'] ) ) {
				$signed_context = $this->context_helper_service()->normalize_context( $attrs['data-asfw-context'] );
			}
			$attributes = join(
				' ',
				array_map(
					function ( $key ) use ( $attrs ) {
						if ( is_bool( $attrs[ $key ] ) ) {
							return $attrs[ $key ] ? $key : '';
						}

						return esc_attr( $key ) . '="' . esc_attr( $attrs[ $key ] ) . '"';
					},
					array_keys( $attrs )
				)
			);

			$tag_name = $this->get_widget_tag_name();
			$html     = '<' . $tag_name . ' ' . $attributes . '></' . $tag_name . '>';
			$html    .= $this->render_widget_auxiliary_fields( $field_name, $signed_context );
			$html    .= '<noscript><div class="asfw-no-javascript">';
			$html    .= esc_html__( 'This form requires JavaScript.', 'anti-spam-for-wordpress' );
			$html    .= '</div></noscript>';

			if ( $wrap ) {
				$html = '<div class="asfw-widget-wrap">' . $html . '</div>';
			}

			return apply_filters( 'asfw_widget_html', $html, $mode, $language, $field_name, $signed_context );
		}
	}
}
