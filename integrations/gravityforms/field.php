<?php

if ( ! defined( 'ABSPATH' ) ) exit;

class OPENPORTE_GFForms_Field extends GF_Field
{

	public $type = 'altcha';

	public function get_form_editor_field_title()
	{
		return 'OpenPorte';
	}

	public function get_form_editor_button()
	{
		return array(
			'group' => 'advanced_fields',
			'text'  => $this->get_form_editor_field_title(),
		);
	}

	function get_form_editor_field_settings()
	{
		return array(
			'label_setting',
			'description_setting',
			'label_placement_setting',
			'error_message_setting'
		);
	}

	public function get_form_editor_field_icon()
	{
		return 'dashicons-superhero';
	}

	public function is_conditional_logic_supported()
	{
		return true;
	}

	public function get_field_input($form, $value = '', $entry = null)
	{
		$plugin = OpenPortePlugin::$instance;
		$active = $plugin->get_integration_gravityforms();
		if (!$active) {
			return '';
		}
		if ($this->is_form_editor()) {
			$widget_html = '<div style="display:flex;gap:1rem;border: 1px solid lightgray;max-width:260px;padding: 1em;border-radius:4px;font-size:80%">'
				. '<div><span class="dashicons-before dashicons-superhero"></span></div>'
				. '<div><span>' . __("OpenPorte placeholder", 'openporte') . '</span></div>'
				. '</div>';
		} else {
			$widget_html = wp_kses($plugin->render_widget($active), OpenPortePlugin::$html_espace_allowed_tags);
		}
		return sprintf("<div class='ginput_container ginput_container_%s gfield--type-html'>%s</div>", $this->type, $widget_html);
	}

	private function is_on_last_page($form)
	{
		$pages = GFAPI::get_fields_by_type($form, array('page'));
		return count($pages) + 1 === (int) $this->pageNumber;
	}

	/**
	 * Verify the challenge when Gravity Forms validates the field.
	 *
	 * @since 1.26.3
	 * @since 1.30.0 Names its surface ('gravityforms') for the replay limit.
	 *
	 * @param mixed $value The field's submitted value; unused.
	 * @param array $form  The form being validated.
	 * @return void Sets failed_validation and validation_message on failure.
	 */
	public function validate($value, $form)
	{
		if (GFFormDisplay::is_last_page($form) && !$this->is_on_last_page($form)) {
			return;
		}
		$plugin = OpenPortePlugin::$instance;
		$active = $plugin->get_integration_gravityforms();
    if ($active) {
			$altcha = isset($_POST['altcha']) ? trim(sanitize_text_field(wp_unslash($_POST['altcha']))) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ($plugin->verify($altcha, null, 'gravityforms') === false) {
					$this->failed_validation  = true;
					$this->validation_message = __('Could not verify you are not a robot.', 'openporte');
			}
		}
	}
}

GF_Fields::register(new OPENPORTE_GFForms_Field());
