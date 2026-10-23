<?php
/**
 * A small WordPress stand-in, so the unit suite can exercise OpenPorte's own
 * logic without a WordPress installation, a database or Docker.
 *
 * Scope, deliberately narrow: only the core functions the plugin actually
 * calls, and only faithfully enough for the behaviour under test. Anything
 * that depends on real WordPress or real MySQL semantics — the concurrency of
 * the replay counter's guarded INSERT/UPDATE above all — does NOT belong here;
 * it belongs in the wp-env bench (see tests/README.md), because a fake that
 * agrees with itself proves nothing about InnoDB.
 *
 * State lives on OpenPorte_Test_Env; call OpenPorte_Test_Env::reset() in
 * setUp() so tests cannot leak into one another.
 *
 * @package OpenPorte\Tests
 */

/**
 * Mutable state of the fake WordPress: the options table, the object cache,
 * the hooks that fired, and the switches tests flip to simulate failure.
 */
class OpenPorte_Test_Env
{
  /** @var array<string,mixed> The options table, option_name => option_value. */
  public static $options = array();

  /** @var array<string,array<string,mixed>> Object cache, group => key => value. */
  public static $cache = array();

  /** @var array<int,array> Every wp_cache_add() call, as [key, value, group, ttl]. */
  public static $cache_adds = array();

  /** @var array<int,array> Every do_action() call, as [$tag, ...$args]. */
  public static $actions = array();

  /** @var array<int,array> Every add_action() call made while loading the plugin. */
  public static $registered_actions = array();

  /** @var array<int,string> Every _deprecated_function()/_doing_it_wrong() subject, and every deprecated hook that had a callback. */
  public static $deprecations = array();

  /** @var array<string,callable> Filter callbacks, keyed by hook name. */
  public static $filters = array();

  /** @var string Value returned by current_filter(). */
  public static $current_filter = '';

  /** @var bool Whether wp_using_ext_object_cache() reports a persistent cache. */
  public static $external_object_cache = false;

  /** @var bool When true, wp_cache_incr() always fails — a broken drop-in. */
  public static $broken_cache_incr = false;

  /** @var bool When true, wp_cache_incr() returns a numeric string. */
  public static $string_cache_incr = false;

  /** @var bool When true, replay-counter timeout rows cannot be created. */
  public static $broken_timeout_add = false;

  /** @var bool When true, every $wpdb write reports failure — a broken database. */
  public static $broken_database = false;

  /**
   * Plugin basenames is_plugin_active() reports as active.
   *
   * @since 1.30.0
   * @var array<int,string>
   */
  public static $active_plugins = array();

  /**
   * What is_user_logged_in() reports.
   *
   * @since 1.30.0
   * @var bool
   */
  public static $logged_in = false;

  /** @var array|WP_Error Canned response returned by wp_remote_get(). */
  public static $http_response = array();

  /**
   * Last payload passed to wp_send_json().
   *
   * @since 1.30.0
   * @var mixed
   */
  public static $sent_json = null;

  /**
   * Last HTTP status passed to wp_send_json().
   *
   * @since 1.30.0
   * @var int|null
   */
  public static $sent_json_status = null;

  /**
   * Restore a pristine environment. Call from setUp().
   *
   * @param array<string,mixed> $options Options to seed the table with.
   */
  public static function reset($options = array())
  {
    self::$options = $options;
    self::$cache = array();
    self::$cache_adds = array();
    self::$actions = array();
    self::$deprecations = array();
    self::$filters = array();
    self::$current_filter = '';
    self::$external_object_cache = false;
    self::$broken_cache_incr = false;
    self::$string_cache_incr = false;
    self::$broken_timeout_add = false;
    self::$broken_database = false;
    self::$http_response = array();
    self::$sent_json = null;
    self::$sent_json_status = null;
    self::$active_plugins = array();
    self::$logged_in = false;
  }

  /**
   * Every do_action() call made under one hook name.
   *
   * @param string $tag Hook name.
   * @return array<int,array> Matching entries, each [$tag, ...$args].
   */
  public static function actions($tag)
  {
    return array_values(array_filter(self::$actions, function ($call) use ($tag) {
      return $call[0] === $tag;
    }));
  }

  /** Option names currently holding a replay counter (value or timeout row). */
  public static function replay_rows()
  {
    return array_values(preg_grep('/openporte_replay_/', array_keys(self::$options)));
  }
}

if (!defined('ABSPATH')) { define('ABSPATH', dirname(__DIR__, 2) . '/'); }
if (!defined('MINUTE_IN_SECONDS')) { define('MINUTE_IN_SECONDS', 60); }
if (!defined('HOUR_IN_SECONDS')) { define('HOUR_IN_SECONDS', 3600); }
if (!defined('DAY_IN_SECONDS')) { define('DAY_IN_SECONDS', 86400); }
if (!defined('OPENPORTE_VERSION')) { define('OPENPORTE_VERSION', '1.29.0'); }
if (!defined('OPENPORTE_WIDGET_VERSION')) { define('OPENPORTE_WIDGET_VERSION', '2.3.0'); }
if (!defined('ALTCHA_WEBSITE')) { define('ALTCHA_WEBSITE', 'https://altcha.org/'); }
if (!defined('OPENPORTE_PLUGIN_BASE')) { define('OPENPORTE_PLUGIN_BASE', 'openporte/openporte.php'); }

/* ------------------------------------------------------------------ options */

function get_option($name, $default = false)
{
  return array_key_exists($name, OpenPorte_Test_Env::$options)
    ? OpenPorte_Test_Env::$options[$name]
    : $default;
}

function update_option($name, $value, $autoload = null)
{
  OpenPorte_Test_Env::$options[$name] = $value;
  return true;
}

function add_option($name, $value = '', $deprecated = '', $autoload = null)
{
  if (OpenPorte_Test_Env::$broken_timeout_add
    && 0 === strpos($name, '_transient_timeout_openporte_replay_')) {
    return false;
  }
  if (array_key_exists($name, OpenPorte_Test_Env::$options)) {
    return false;
  }
  OpenPorte_Test_Env::$options[$name] = $value;
  return true;
}

function delete_option($name)
{
  unset(OpenPorte_Test_Env::$options[$name]);
  return true;
}

/* --------------------------------------------------------------- transients */

function get_transient($key)
{
  $timeout = get_option('_transient_timeout_' . $key, null);
  // Core's lazy expiry sweep: a read drops a pair whose window has elapsed.
  if ($timeout !== null && intval($timeout) < time()) {
    delete_option('_transient_' . $key);
    delete_option('_transient_timeout_' . $key);
    return false;
  }
  return get_option('_transient_' . $key, false);
}

function set_transient($key, $value, $ttl = 0)
{
  update_option('_transient_' . $key, $value);
  if ($ttl > 0) {
    update_option('_transient_timeout_' . $key, time() + $ttl);
  }
  return true;
}

/* ------------------------------------------------------------ object cache */

function wp_using_ext_object_cache()
{
  return OpenPorte_Test_Env::$external_object_cache;
}

function wp_cache_add($key, $value, $group = '', $ttl = 0)
{
  OpenPorte_Test_Env::$cache_adds[] = array($key, $value, $group, $ttl);
  if (isset(OpenPorte_Test_Env::$cache[$group][$key])) {
    return false;
  }
  OpenPorte_Test_Env::$cache[$group][$key] = $value;
  return true;
}

function wp_cache_incr($key, $offset = 1, $group = '')
{
  if (OpenPorte_Test_Env::$broken_cache_incr) {
    return false;
  }
  if (!isset(OpenPorte_Test_Env::$cache[$group][$key])) {
    return false;
  }
  OpenPorte_Test_Env::$cache[$group][$key] = intval(OpenPorte_Test_Env::$cache[$group][$key]) + $offset;
  return OpenPorte_Test_Env::$string_cache_incr
    ? (string) OpenPorte_Test_Env::$cache[$group][$key]
    : OpenPorte_Test_Env::$cache[$group][$key];
}

/** uninstall.php flushes the object cache after its sweep. */
function wp_cache_flush()
{
  OpenPorte_Test_Env::$cache = array();
  return true;
}

/* ---------------------------------------------------------- hooks & filters */

/** Record action registration so the suite can assert load-time wiring. */
function add_action($tag, $callback, $priority = 10, $args = 1)
{
  OpenPorte_Test_Env::$registered_actions[] = array($tag, $callback, $priority, $args);
  return true;
}
function add_filter($tag, $callback, $priority = 10, $args = 1) { return true; }
function remove_action($tag, $callback, $priority = 10) { return true; }
function remove_filter($tag, $callback, $priority = 10) { return true; }

function do_action($tag, ...$args)
{
  OpenPorte_Test_Env::$actions[] = array_merge(array($tag), $args);
}

function do_action_deprecated($tag, $args, $version, $replacement = '') { }

/**
 * Like core: how many times an action has fired in this request. Backed by
 * the do_action() log, so a test simulates wp-login.php with
 * do_action('login_init').
 *
 * @since 1.30.0
 *
 * @param string $tag Action name.
 * @return int
 */
function did_action($tag) { return count(OpenPorte_Test_Env::actions($tag)); }

function apply_filters($tag, $value, ...$args)
{
  if (!isset(OpenPorte_Test_Env::$filters[$tag])) {
    return $value;
  }
  return call_user_func_array(OpenPorte_Test_Env::$filters[$tag], array_merge(array($value), $args));
}

/**
 * Like core: a deprecated hook runs its callbacks, and is reported, only when
 * something is attached to it.
 *
 * @since 1.29.0
 * @since 1.30.0 Dispatch attached callbacks and record deprecated hooks.
 *
 * @param string $tag         Deprecated filter name.
 * @param array  $args        Filter arguments, beginning with the value.
 * @param string $version     Version that deprecated the filter.
 * @param string $replacement Replacement filter name.
 * @return mixed Filtered value.
 */
function apply_filters_deprecated( $tag, $args, $version, $replacement = '' ) {
  if ( ! isset( OpenPorte_Test_Env::$filters[ $tag ] ) ) {
    return $args[0];
  }
  OpenPorte_Test_Env::$deprecations[] = $tag;
  return apply_filters( $tag, ...$args );
}

function current_filter() { return OpenPorte_Test_Env::$current_filter; }

function _deprecated_function($function_name, $version, $replacement = '')
{
  OpenPorte_Test_Env::$deprecations[] = $function_name;
}

function _doing_it_wrong($function_name, $message, $version)
{
  OpenPorte_Test_Env::$deprecations[] = $function_name;
}

/* ------------------------------------------------------- strings & escaping */

function __($text, $domain = '') { return $text; }
function _x($text, $context, $domain = '') { return $text; }
function esc_html__($text, $domain = '') { return $text; }
function esc_attr__($text, $domain = '') { return $text; }
function esc_html($text) { return $text; }
function esc_attr($text) { return $text; }
function esc_url($url) { return $url; }
function esc_url_raw($url) { return $url; }
function sanitize_text_field($value) { return is_string($value) ? trim($value) : $value; }
function wp_kses($string, $allowed) { return $string; }
function wp_kses_post($string) { return $string; }
function wp_unslash($value) { return $value; }
function absint($value) { return abs(intval($value)); }
function wp_json_encode($value) { return json_encode($value); }
function selected($a, $b = true, $echo = true)
{
  $result = (string) $a === (string) $b ? " selected='selected'" : '';
  if ($echo)
  {
    echo $result; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed test-shim markup.
  }
  return $result;
}
function checked($a, $b = true, $echo = true) { return ''; }
/** Mirrors WordPress' disabled attribute helper for renderer tests. */
function disabled($a, $b = true, $echo = true)
{
  $result = (string) $a === (string) $b ? " disabled='disabled'" : '';
  if ($echo) {
    echo $result; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed test-shim markup.
  }
  return $result;
}
function number_format_i18n($n, $decimals = 0) { return number_format($n, $decimals); }

function _n($single, $plural, $number, $domain = '')
{
  return 1 === $number ? $single : $plural;
}

/**
 * Simplified human_time_diff(): enough for message assertions, not a
 * reimplementation of core's rounding.
 */
function human_time_diff($from, $to = 0)
{
  $to = $to ?: time();
  $seconds = abs($to - $from);
  if ($seconds < HOUR_IN_SECONDS) {
    return max(1, (int) round($seconds / MINUTE_IN_SECONDS)) . ' mins';
  }
  if ($seconds < DAY_IN_SECONDS) {
    return max(1, (int) round($seconds / HOUR_IN_SECONDS)) . ' hours';
  }
  return max(1, (int) round($seconds / DAY_IN_SECONDS)) . ' days';
}

/* -------------------------------------------------------------- environment */

function get_locale() { return 'en_US'; }
function switch_to_locale($locale) { return true; }
function is_admin() { return false; }
function plugin_dir_path($file) { return dirname($file) . '/'; }
function plugin_dir_url($file) { return 'https://example.test/wp-content/plugins/openporte/'; }
function get_rest_url($blog_id = null, $path = '') { return 'https://example.test/wp-json' . $path; }
function get_admin_url() { return 'https://example.test/wp-admin/'; }
/**
 * Build an admin URL on the fixed test site.
 *
 * @since 1.30.0
 *
 * @param string $path   Path relative to the admin directory.
 * @param string $scheme Scheme requested by the caller; unused by this shim.
 * @return string Absolute admin URL.
 */
function admin_url( $path = '', $scheme = 'admin' ) {
  return 'https://example.test/wp-admin/' . ltrim( $path, '/' );
}

/**
 * Append one query argument to a URL within the plugin's supported test cases.
 *
 * Support only an explicit URL, string key and string value. Fragments and
 * existing keys require more of WordPress's URL handling than this shim models,
 * so reject them instead of silently producing a misleading result. Values
 * arrive already encoded, matching the convention used by the plugin's callers.
 *
 * @since 1.29.0
 * @since 1.30.0 Build supported URLs and reject unsupported inputs.
 *
 * @param string      $key   Query argument name.
 * @param string      $value Query argument value.
 * @param string|null $url   Explicit URL with no fragment or existing key.
 * @return string URL with the appended query argument.
 * @throws RuntimeException When an input needs unsupported URL handling.
 */
function add_query_arg( $key, $value = '', $url = null ) {
  if ( ! is_string( $key ) || ! is_string( $value ) || ! is_string( $url ) ) {
    throw new RuntimeException( 'add_query_arg() shim: only explicit (key, value, url) strings are implemented.' );
  }
  if ( false !== strpos( $url, '#' ) ) {
    throw new RuntimeException( 'add_query_arg() shim: URL fragments are not implemented.' );
  }

  // Decode query keys so percent-encoded or array-shaped keys cannot evade the guard.
  $query      = parse_url( $url, PHP_URL_QUERY );
  $query_args = array();
  if ( is_string( $query ) ) {
    parse_str( $query, $query_args );
  }
  if ( array_key_exists( $key, $query_args ) ) {
    throw new RuntimeException( 'add_query_arg() shim: replacing existing query arguments is not implemented.' );
  }

  $separator = ( false !== strpos( $url, '?' ) ) ? '&' : '?';
  return $url . $separator . $key . '=' . $value;
}
function wp_parse_url($url, $component = -1) { return parse_url($url, $component); }
function is_plugin_active($plugin) { return in_array($plugin, OpenPorte_Test_Env::$active_plugins, true); }
function get_template() { return 'twentytwentyfive'; }
function is_user_logged_in() { return OpenPorte_Test_Env::$logged_in; }
function register_setting($group, $name, $args = array()) { return true; }
function add_settings_section(...$args) { return true; }
function add_settings_field(...$args) { return true; }
function add_options_page(...$args) { return ''; }

/* --------------------------------------------------------------- HTTP layer */

class WP_Error
{
  /** @var array<string,array<int,string>> Messages, keyed by error code. */
  private $errors = array();

  /** @var array<string,mixed> Data, keyed by error code. */
  private $error_data = array();

  public function __construct($code = '', $message = '', $data = '')
  {
    if ('' !== $code) {
      $this->errors[$code][] = $message;
      if ('' !== $data) {
        $this->error_data[$code] = $data;
      }
    }
  }

  public function get_error_data($code = '')
  {
    $code = '' === $code ? $this->get_error_code() : $code;
    return isset($this->error_data[$code]) ? $this->error_data[$code] : null;
  }

  public function add($code, $message) { $this->errors[$code][] = $message; }
  public function get_error_codes() { return array_keys($this->errors); }
  public function get_error_code() { $codes = $this->get_error_codes(); return $codes ? $codes[0] : ''; }
  public function has_errors() { return !empty($this->errors); }

  public function get_error_message()
  {
    $first = reset($this->errors);
    return $first ? $first[0] : '';
  }
}

/**
 * Stand-in for an authenticated user, as core's credential checks hand it to
 * the `authenticate` and `wp_authenticate_user` filters.
 *
 * @since 1.30.0
 */
class WP_User
{
  /** @var int */
  public $ID = 1;
}

function is_wp_error($thing) { return $thing instanceof WP_Error; }
function wp_remote_get($url, $args = array()) { return OpenPorte_Test_Env::$http_response; }
function wp_remote_retrieve_response_code($response)
{
  return isset($response['response']['code']) ? $response['response']['code'] : 0;
}
function wp_remote_retrieve_body($response)
{
  return isset($response['body']) ? $response['body'] : '';
}

/* --------------------------------------------------------------- JSON output */

/**
 * Signals that the fake wp_send_json() terminated the request.
 *
 * Core ends wp_send_json() with die(), which a test cannot catch; the shim
 * throws this instead, so a test can both observe what was sent and prove
 * the handler terminated.
 *
 * @since 1.30.0
 */
class OpenPorte_JsonSent extends \Exception
{
}

/**
 * Record a JSON payload and emulate request termination.
 *
 * @since 1.30.0
 *
 * @param mixed    $data        Payload passed to wp_send_json().
 * @param int|null $status_code HTTP status requested; recorded for assertions.
 * @return void
 * @throws OpenPorte_JsonSent Always, to emulate termination without exiting PHPUnit.
 */
function wp_send_json( $data, $status_code = null ) {
  OpenPorte_Test_Env::$sent_json = $data;
  OpenPorte_Test_Env::$sent_json_status = $status_code;
  throw new OpenPorte_JsonSent();
}

/* --------------------------------------------------------------- fake $wpdb */

/**
 * The three statements includes/core.php issues against wp_options, plus the
 * prefix DELETE uninstall.php sweeps it with, backed by the in-memory options
 * table.
 *
 * This is a stand-in for MySQL's *result*, never for its concurrency: the
 * guarded INSERT/UPDATE is atomic because InnoDB row-locks it, and no
 * single-process fake can demonstrate that. Prove atomicity on the bench.
 */
class OpenPorte_Fake_Wpdb
{
  public $options = 'wp_options';

  /** Same escaping as core: LIKE wildcards and the escape character itself. */
  public function esc_like($text)
  {
    return addcslashes($text, '_%\\');
  }

  public function prepare($query, ...$args)
  {
    $i = 0;
    return preg_replace_callback('/%[sd]/', function ($m) use (&$i, $args) {
      $value = $args[$i++];
      return '%d' === $m[0] ? (string) intval($value) : "'" . addslashes((string) $value) . "'";
    }, $query);
  }

  public function query($query)
  {
    if (OpenPorte_Test_Env::$broken_database) {
      return false;
    }
    if (preg_match("/INSERT IGNORE INTO .* VALUES \('([^']*)', '([^']*)', 'no'\)/", $query, $m)) {
      if (array_key_exists($m[1], OpenPorte_Test_Env::$options)) {
        return 0;
      }
      OpenPorte_Test_Env::$options[$m[1]] = $m[2];
      return 1;
    }
    if (preg_match("/UPDATE .* WHERE option_name = '([^']*)' AND CAST\(option_value AS UNSIGNED\) < (\d+)/", $query, $m)) {
      if (!array_key_exists($m[1], OpenPorte_Test_Env::$options)) {
        return 0;
      }
      if (intval(OpenPorte_Test_Env::$options[$m[1]]) >= intval($m[2])) {
        return 0;
      }
      OpenPorte_Test_Env::$options[$m[1]] = (string) (intval(OpenPorte_Test_Env::$options[$m[1]]) + 1);
      return 1;
    }
    if (preg_match("/^DELETE FROM .* WHERE option_name LIKE '((?:[^'\\\\]|\\\\.)*)'$/", $query, $m)) {
      $regex = self::like_to_regex(stripslashes($m[1]));
      $deleted = 0;
      foreach (array_keys(OpenPorte_Test_Env::$options) as $name) {
        if (preg_match($regex, (string) $name)) {
          unset(OpenPorte_Test_Env::$options[$name]);
          $deleted++;
        }
      }
      return $deleted;
    }
    throw new RuntimeException('Fake $wpdb saw an unexpected query: ' . $query);
  }

  /**
   * Translate a LIKE pattern into an anchored regex: % and _ are wildcards,
   * a backslash makes the next character literal (MySQL's default escape).
   */
  private static function like_to_regex($pattern)
  {
    $regex = '';
    $length = strlen($pattern);
    for ($i = 0; $i < $length; $i++) {
      $char = $pattern[$i];
      if ('\\' === $char && $i + 1 < $length) {
        $regex .= preg_quote($pattern[++$i], '/');
      } elseif ('%' === $char) {
        $regex .= '.*';
      } elseif ('_' === $char) {
        $regex .= '.';
      } else {
        $regex .= preg_quote($char, '/');
      }
    }
    return '/^' . $regex . '$/s';
  }

  public function get_var($query)
  {
    if (OpenPorte_Test_Env::$broken_database) {
      return null;
    }
    if (preg_match("/SELECT option_value FROM .* WHERE option_name = '([^']*)'/", $query, $m)) {
      return array_key_exists($m[1], OpenPorte_Test_Env::$options)
        ? OpenPorte_Test_Env::$options[$m[1]]
        : null;
    }
    throw new RuntimeException('Fake $wpdb saw an unexpected read: ' . $query);
  }
}

$GLOBALS['wpdb'] = new OpenPorte_Fake_Wpdb();
