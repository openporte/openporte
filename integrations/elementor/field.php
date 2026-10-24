<?php

if (!defined('ABSPATH')) exit;

// Without Elementor Pro's field base class there is nothing to extend. Stop
// loading this file only: die() here would end the whole request, blanking
// whatever page happened to load it. openporte_register_form_field() checks
// the same class before requiring this file.
if (!class_exists('\ElementorPro\Modules\Forms\Fields\Field_Base')) {
  return;
}

class OpenPorte_Elementor_Form_Field extends \ElementorPro\Modules\Forms\Fields\Field_Base
{
  public function get_type()
  {
    return 'altcha';
  }

  public function get_name()
  {
    return esc_html__('OpenPorte', 'openporte');
  }

  public function render($item, $item_index, $form)
  {
    $plugin = OpenPortePlugin::$instance;
    $active = $plugin->get_integration_elementor();
    if (!$active) {
      return '';
    }
    echo wp_kses("<div style=\"flex-basis:100%\">" . $plugin->render_widget($active, false) . "</div>", OpenPortePlugin::$html_espace_allowed_tags);
    // shadow element for error reporting
		echo wp_kses('<input type="hidden" ' . $form->get_render_attribute_string('input' . $item_index) . '>', OpenPortePlugin::$html_espace_allowed_tags);
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

  /**
   * Verify the challenge when Elementor Pro validates the form.
   *
   * @since 1.26.3
   * @since 1.30.0 Names its surface ('elementor') for the replay limit.
   *
   * @param array  $field        The field being validated.
   * @param object $record       Elementor Pro's form record; unused.
   * @param object $ajax_handler Elementor Pro's AJAX handler, for errors.
   * @return void
   */
  public function validation($field, $record, $ajax_handler)
  {
    $plugin = OpenPortePlugin::$instance;
    $active = $plugin->get_integration_elementor();
    if ($active) {
      $altcha = isset($_POST['altcha']) ? trim(sanitize_text_field(wp_unslash($_POST['altcha']))) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
      if ($plugin->verify($altcha, null, 'elementor') === false) {
        $ajax_handler->add_error(
          $field['id'],
          esc_html__('Verification failed.', 'openporte')
        );
      }
    }
  }
}

if (OpenPortePlugin::$instance->get_integration_elementor()) {
  // Since Elementor Pro 3.31.2, script enqueuing does not seem to work properly when the widget is rendered.
  // Always enqueue scripts when the integration is active.
  openporte_enqueue_scripts();
  openporte_enqueue_styles();
}
