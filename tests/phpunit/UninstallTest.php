<?php
/**
 * uninstall.php: what deleting the plugin removes, with and without the
 * "Keep settings when the plugin is deleted" toggle.
 *
 * Runs the real file against the fake $wpdb, whose LIKE matching follows
 * MySQL's wildcard and escape rules — enough to prove which rows each pattern
 * reaches, not anything about the real database.
 *
 * @package OpenPorte\Tests
 */
class UninstallTest extends OpenPorteTestCase
{
  protected function setUp(): void
  {
    parent::setUp();
    if (!defined('WP_UNINSTALL_PLUGIN')) {
      define('WP_UNINSTALL_PLUGIN', 'openporte/openporte.php');
    }
    OpenPorte_Test_Env::$options += array(
      'openporte_version' => '1.29.1',
      'openporte_api' => 'selfhosted',
      'openporte_integration_wordpress_login' => 1,
      // A replay counter row and its timeout companion, plus the health-check
      // cache: transients, so they live under WordPress's own prefixes.
      '_transient_' . OpenPortePlugin::$replay_key_prefix . 'abc' => '1',
      '_transient_timeout_' . OpenPortePlugin::$replay_key_prefix . 'abc' => (string) (time() + 300),
      '_transient_openporte_endpoint_check' => 'ok',
      // Legacy ALTCHA v1 configuration, never ours to delete.
      'altcha_secret' => 'legacy',
      // A row whose name only matches openporte_ if '_' were a wildcard.
      'openporteXsomething' => 'unrelated',
    );
  }

  /** Run uninstall.php the way WordPress does: a plain include. */
  private function uninstall()
  {
    include dirname(__DIR__, 2) . '/uninstall.php';
  }

  /** @return array<int,string> Names of the remaining rows starting with $prefix. */
  private function rows_starting_with($prefix)
  {
    return array_values(array_filter(
      array_keys(OpenPorte_Test_Env::$options),
      function ($name) use ($prefix) {
        return 0 === strpos($name, $prefix);
      }
    ));
  }

  private function assert_transients_removed()
  {
    $this->assertSame(array(), $this->rows_starting_with('_transient_openporte_'));
    $this->assertSame(array(), $this->rows_starting_with('_transient_timeout_openporte_'));
  }

  private function assert_foreign_rows_kept()
  {
    $this->assertSame('legacy', OpenPorte_Test_Env::$options['altcha_secret']);
    $this->assertSame('unrelated', OpenPorte_Test_Env::$options['openporteXsomething']);
  }

  public function test_without_the_toggle_everything_of_ours_is_removed()
  {
    // The default: an absent toggle keeps today's behaviour.
    $this->uninstall();

    $this->assertSame(array(), $this->rows_starting_with('openporte_'));
    $this->assert_transients_removed();
    $this->assert_foreign_rows_kept();
  }

  public function test_with_the_toggle_off_everything_of_ours_is_removed()
  {
    OpenPorte_Test_Env::$options[OpenPortePlugin::$option_uninstall_keep_data] = 0;

    $this->uninstall();

    $this->assertSame(array(), $this->rows_starting_with('openporte_'));
    $this->assert_transients_removed();
    $this->assert_foreign_rows_kept();
  }

  public function test_with_the_toggle_on_the_settings_survive_but_the_transients_do_not()
  {
    // Seeded through the property, not the literal: this is what pins the key
    // uninstall.php hardcodes to OpenPortePlugin::$option_uninstall_keep_data.
    // WordPress hands options back as strings, hence '1'.
    OpenPorte_Test_Env::$options[OpenPortePlugin::$option_uninstall_keep_data] = '1';
    $settings_before = $this->rows_starting_with('openporte_');

    $this->uninstall();

    // The toggle itself and openporte_version are among the survivors, so the
    // choice outlives the reinstall and the version-gated upgrade still runs.
    $this->assertSame($settings_before, $this->rows_starting_with('openporte_'));
    $this->assertContains(OpenPortePlugin::$option_uninstall_keep_data, $settings_before);
    $this->assertContains(OpenPortePlugin::$option_version, $settings_before);
    $this->assertSame(self::SECRET, OpenPorte_Test_Env::$options['openporte_secret']);
    $this->assert_transients_removed();
    $this->assert_foreign_rows_kept();
  }
}
