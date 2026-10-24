<?php

if (!defined('ABSPATH')) exit;

class OpenPorteFieldType extends FrmFieldType
{
	protected $type = 'altcha';

	protected $has_input = true;

	protected function field_settings_for_type()
	{
		$settings            = parent::field_settings_for_type();
		$settings['default'] = true;

		return $settings;
	}

	protected function extra_field_opts()
	{
		return array();
	}

	protected function include_form_builder_file()
	{
		return dirname(__FILE__) . '/builder-field.php';
	}

	public function displayed_field_type($field)
	{
		return array(
			$this->type => true,
		);
	}

	public function show_extra_field_choices($args)
	{
		include(dirname(__FILE__) . '/builder-settings.php');
	}

	protected function html5_input_type()
	{
		return 'text';
	}

	/**
	 * Verify the challenge when Formidable validates the form.
	 *
	 * @since 1.26.3
	 * @since 1.30.0 Names its surface ('formidable') for the replay limit.
	 *
	 * @param array $args Formidable's validation arguments; `id` is the field ID.
	 * @return array Errors keyed by field, empty when the challenge passes.
	 */
	public function validate($args)
	{
    $plugin = OpenPortePlugin::$instance;
    $active = $plugin->get_integration_formidable();
		$errors = array();
    if ($active) {
			$altcha = isset($_POST['altcha']) ? trim(sanitize_text_field(wp_unslash($_POST['altcha']))) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ($plugin->verify($altcha, null, 'formidable') === false) {
				$errors['field' . $args['id']] = esc_html__('Verification failed.', 'openporte');
			}
    }

		return $errors;
	}

	public function front_field_input($args, $shortcode_atts)
	{
		$plugin = OpenPortePlugin::$instance;
		$active = $plugin->get_integration_formidable();
		if ($active) {
			return wp_kses("<div style=\"flex-basis:100%\">" . $plugin->render_widget($active, false) . "</div>", OpenPortePlugin::$html_espace_allowed_tags);
		}
		return '';
	}
}