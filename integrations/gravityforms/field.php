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

	/**
	 * Render the widget, or a placeholder in the form editor.
	 *
	 * @since 1.26.3
	 * @since 1.30.1 The editor placeholder warns when the field is not on the
	 *               form's last page, where validate() refuses it.
	 *
	 * @param array      $form  The form being rendered.
	 * @param string     $value The field's value; unused.
	 * @param array|null $entry The entry being edited, if any; unused.
	 * @return string The field's input markup.
	 */
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
				. '<div><span>' . esc_html__('OpenPorte placeholder', 'openporte') . '</span></div>'
				. '</div>';
			if (!$this->is_on_last_page($form)) {
				$widget_html .= '<div class="gfield_description validation_message">' . esc_html(self::get_misplaced_message()) . '</div>';
			}
		} else {
			$widget_html = wp_kses($plugin->render_widget($active), OpenPortePlugin::$html_espace_allowed_tags);
		}
		return sprintf("<div class='ginput_container ginput_container_%s gfield--type-html'>%s</div>", $this->type, $widget_html);
	}

	/**
	 * Whether the field sits on the form's last page.
	 *
	 * Read from the form's layout alone, never from the page numbers a
	 * submission posts: those are the submitter's to choose.
	 *
	 * @since 1.26.3
	 * @since 1.30.1 A form without page breaks counts as on its last page,
	 *               whatever the field's stored page number.
	 *
	 * @param array $form The form the field belongs to.
	 * @return bool True when no page break follows the field.
	 */
	private function is_on_last_page($form)
	{
		$pages = GFAPI::get_fields_by_type($form, array('page'));
		// A single-page form has only a last page; this also keeps one whose
		// field lacks a page number (an API-created form) verifying normally.
		if (count($pages) === 0) {
			return true;
		}
		return count($pages) + 1 === (int) $this->pageNumber;
	}

	/**
	 * Message shown when the field is placed before the form's last page.
	 *
	 * The same text serves the form editor's notice and the refused
	 * submission, so a site owner who tests the form reads what to change.
	 *
	 * @since 1.30.1
	 *
	 * @return string Translated message.
	 */
	private static function get_misplaced_message()
	{
		return __('This form cannot be submitted until its OpenPorte field is moved to the last page.', 'openporte');
	}

	/**
	 * Verify the challenge when Gravity Forms validates the field.
	 *
	 * Gravity Forms validates a page's fields when the visitor leaves that
	 * page, and every field again at the final submission. Only a widget on
	 * the last page can still be checked at that point, so a field placed on
	 * an earlier page is refused outright: the form stays closed until the
	 * field is moved, instead of accepting a final submission nothing checked.
	 *
	 * @since 1.26.3
	 * @since 1.30.0 Names its surface ('gravityforms') for the replay limit.
	 * @since 1.30.1 Refuses a field placed before the last page, which was
	 *               skipped at the final submission.
	 *
	 * @param mixed $value The field's submitted value; unused.
	 * @param array $form  The form being validated.
	 * @return void Sets failed_validation and validation_message on failure.
	 */
	public function validate($value, $form)
	{
		$plugin = OpenPortePlugin::$instance;
		if (!$plugin->get_integration_gravityforms()) {
			return;
		}
		if (!$this->is_on_last_page($form)) {
			// Refused before verify(), so a misplaced field never spends a
			// token's use on a submission it would refuse at the end anyway.
			$this->failed_validation  = true;
			$this->validation_message = self::get_misplaced_message();
			return;
		}
		$altcha = isset($_POST['altcha']) ? trim(sanitize_text_field(wp_unslash($_POST['altcha']))) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ($plugin->verify($altcha, null, 'gravityforms') === false) {
			$this->failed_validation  = true;
			$this->validation_message = __('Could not verify you are not a robot.', 'openporte');
		}
	}
}

GF_Fields::register(new OPENPORTE_GFForms_Field());
