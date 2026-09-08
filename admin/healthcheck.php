<?php

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Settings-screen health checks.
 *
 * Three checks run while the OpenPorte settings page loads, each reporting as
 * an admin notice: the custom-endpoint probe, an advisory on the Expiration
 * values under review, and the state of replay protection.
 *
 * Custom-mode endpoint health check
 * --------------------------------
 * When API mode is "Custom", fetch one challenge from the configured
 * Challenge URL while the OpenPorte settings page loads, and surface the
 * result as an admin notice: unreachable endpoint, non-ALTCHA response,
 * unsupported algorithm, signing-secret mismatch, or a backend algorithm
 * that differs from the configured one.
 *
 * A single request is enough to validate all three settings because an
 * ALTCHA challenge declares its own algorithm, and
 * hmac(algorithm, challenge, secret) must equal the served signature.
 * There is no need to probe each supported algorithm separately.
 *
 * The result is cached in a short transient keyed on (url, secret,
 * algorithm) so reloading the settings page does not hammer the backend;
 * changing any of the three settings busts the key, so the check re-runs
 * right after a save. Each uncached check consumes one challenge on the
 * backend (it will show up as issued-but-never-verified in e.g. GateCHA's
 * statistics dashboard).
 */

add_action('current_screen', 'openporte_maybe_check_custom_endpoint');
add_action('current_screen', 'openporte_maybe_check_replay_protection');

/**
 * Whether the current admin screen is OpenPorte's own settings page
 * (Settings > OpenPorte Anti-spam), the only place these notices belong.
 *
 * @since 1.29.0
 *
 * @param WP_Screen|null $screen Screen passed to the current_screen action.
 * @return bool
 */
function openporte_is_settings_screen($screen)
{
  return isset($screen->id) && $screen->id === 'settings_page_openporte_admin';
}

/**
 * Queue one health-check result as an admin notice.
 *
 * @since 1.29.0
 *
 * @param string $label  Short prefix naming the check, already translated.
 * @param array  $result {
 *     @type string $level   One of error|warning|success|info.
 *     @type string $message Plain-text notice body.
 *   }
 */
function openporte_queue_admin_notice($label, $result)
{
  // The level is an internal enum, never user input — whitelisting it says so,
  // and keeps a typo from rendering a div with no notice class at all.
  $level = in_array($result['level'], array('error', 'warning', 'success', 'info'), true)
    ? $result['level']
    : 'info';
  add_action('admin_notices', function () use ($label, $level, $result) {
    printf(
      '<div class="notice notice-%1$s is-dismissible"><p><strong>%2$s</strong> %3$s</p></div>',
      esc_attr($level),
      esc_html($label),
      esc_html($result['message'])
    );
  });
}

function openporte_maybe_check_custom_endpoint($screen)
{
  // Only on OpenPorte's own settings screen (Settings > OpenPorte Anti-spam).
  if (!openporte_is_settings_screen($screen)) {
    return;
  }
  $plugin = OpenPortePlugin::$instance;
  if ($plugin->get_api() !== 'custom') {
    return;
  }
  $result = openporte_check_custom_endpoint(
    $plugin->get_api_custom_url(),
    $plugin->get_secret(),
    $plugin->get_algorithm()
  );
  openporte_queue_admin_notice(__('OpenPorte endpoint check:', 'openporte'), $result);
}

/**
 * Report the state of replay protection, and of the Expiration value feeding
 * it, on the settings screen.
 *
 * Both are surfaced here rather than as inline field validation because
 * neither rejects a save: 1.29.0 warns about the Expiration values under
 * review and leaves them working, and the reuse counter is meant to be visible
 * even when nothing is wrong — otherwise a site only discovers that it has
 * degraded to fail-open by listening for an action hook.
 *
 * @since 1.29.0
 *
 * @param WP_Screen|null $screen Screen passed to the current_screen action.
 */
function openporte_maybe_check_replay_protection($screen)
{
  if (!openporte_is_settings_screen($screen)) {
    return;
  }
  $plugin = OpenPortePlugin::$instance;
  // Expiration is inert in Custom mode — the field is disabled there and the
  // backend's own expiry is reported by the endpoint check instead.
  if ($plugin->get_api() !== 'custom') {
    $expires = openporte_evaluate_expires_setting(intval($plugin->get_expires(), 10));
    if ($expires !== null) {
      openporte_queue_admin_notice(__('OpenPorte Expiration:', 'openporte'), $expires);
    }
  }
  openporte_queue_admin_notice(
    __('OpenPorte replay protection:', 'openporte'),
    openporte_evaluate_replay_protection()
  );
}

/**
 * Evaluate a self-hosted Expiration value. Split from the notice so it stays
 * testable.
 *
 * Advisory only in 1.29.0: neither value is rejected or migrated, because the
 * reuse counter now bounds replay independently of the expiry. Hard bounds are
 * a later, breaking-config release, at which point these two notices become
 * moot and go away.
 *
 * @since 1.29.0
 *
 * @param int $expires Configured expiry in seconds.
 * @return array{level:string,message:string}|null Null when nothing to report.
 */
function openporte_evaluate_expires_setting($expires)
{
  // Thresholds come from openporte_expires_advisory_level() (includes/settings.php)
  // so this notice and the save-time _doing_it_wrong() advisory cannot drift
  // apart; only the wording is this surface's own.
  $level = openporte_expires_advisory_level($expires);
  if ($level === 'error') {
    return array(
      'level' => 'error',
      'message' => __('Expiration is set to 0, so a solved challenge never expires and only Replay limit bounds how often it can be resubmitted. This is strongly discouraged and is being evaluated for deprecation — set an expiry of 60 seconds or more.', 'openporte'),
    );
  }
  if ($level === 'warning') {
    return array(
      'level' => 'warning',
      'message' => sprintf(
        /* translators: %d is the configured Expiration value, in seconds */
        __('Expiration is set to %d seconds, which can elapse before a slow device finishes solving the challenge and turn legitimate submissions into failures. Values below 60 seconds are being evaluated for deprecation.', 'openporte'),
        $expires
      ),
    );
  }

  return null;
}

/**
 * Evaluate the state of the reuse counter. Split from the notice so it stays
 * testable.
 *
 * @since 1.29.0
 *
 * @return array{level:string,message:string}
 */
function openporte_evaluate_replay_protection()
{
  $health = get_option(OpenPortePlugin::$option_replay_health, array());
  $count = (is_array($health) && isset($health['count'])) ? intval($health['count']) : 0;
  $last = (is_array($health) && isset($health['last'])) ? intval($health['last']) : 0;
  // Only report a store that is failing now: the counter restarts by itself
  // once a day has passed without an incident.
  if ($count > 0 && $last > 0 && (time() - $last) < DAY_IN_SECONDS) {
    // No submission count in the message: the record is sampled (at most one
    // incident a minute) and its increment is not atomic, so what it honestly
    // supports is that this happened and when it last did. See
    // OpenPortePlugin::record_replay_failopen().
    return array(
      'level' => 'warning',
      'message' => sprintf(
        /* translators: %s is a duration such as "5 mins" */
        __('Submissions were accepted without being counted in the last %s, because the reuse counter could not be stored. Protection degrades to accepting valid challenges unconditionally while that lasts — check the persistent object cache or the database.', 'openporte'),
        human_time_diff($last)
      ),
    );
  }
  $limit = OpenPortePlugin::$instance->get_replaylimit();
  if ($limit <= 0) {
    return array(
      'level' => 'warning',
      'message' => __('Replay limit is 0, so replay protection is off: a solved challenge is accepted for as long as it stays valid, however many times it is submitted. This is the behaviour of releases before 1.29.0.', 'openporte'),
    );
  }

  return array(
    'level' => 'success',
    'message' => sprintf(
      /* translators: %1$d is the configured Replay limit, %2$s names where the counter is stored */
      _n(
        'Each solved challenge is accepted %1$d time, counted in %2$s.',
        'Each solved challenge is accepted up to %1$d times, counted in %2$s.',
        $limit,
        'openporte'
      ),
      $limit,
      wp_using_ext_object_cache()
        ? __('the persistent object cache', 'openporte')
        : __('the database', 'openporte')
    ),
  );
}

/**
 * Fetch one challenge from the custom endpoint and evaluate it.
 *
 * @param string $url                  Configured custom Challenge URL.
 * @param string $secret               Configured signing secret.
 * @param string $configured_algorithm Configured algorithm (e.g. 'SHA-256').
 * @return array{level:string,message:string} Notice level + message.
 */
function openporte_check_custom_endpoint($url, $secret, $configured_algorithm)
{
  if (empty($url)) {
    return array(
      'level' => 'warning',
      'message' => __('API mode is Custom but no Challenge URL is configured.', 'openporte'),
    );
  }
  $cache_key = 'openporte_endpoint_check_' . md5($url . '|' . $secret . '|' . $configured_algorithm);
  $cached = get_transient($cache_key);
  if (is_array($cached)) {
    return $cached;
  }
  // wp_remote_get, not wp_safe_remote_get: the URL is set by an administrator
  // (manage_options), and private-network backends (a LAN host or NAS running
  // e.g. GateCHA) are a primary use case that the "safe" variant would block.
  $response = wp_remote_get($url, array(
    'timeout' => 5,
    'headers' => array('accept' => 'application/json'),
  ));
  $result = openporte_evaluate_challenge_response($response, $secret, $configured_algorithm);
  set_transient($cache_key, $result, 2 * MINUTE_IN_SECONDS);
  return $result;
}

/**
 * Evaluate a challenge-endpoint HTTP response against the configured
 * secret and algorithm. Split from the fetch so it stays testable.
 *
 * @param array|WP_Error $response             Return value of wp_remote_get().
 * @param string         $secret               Configured signing secret.
 * @param string         $configured_algorithm Configured algorithm.
 * @return array{level:string,message:string}
 */
function openporte_evaluate_challenge_response($response, $secret, $configured_algorithm)
{
  if (is_wp_error($response)) {
    return array(
      'level' => 'error',
      'message' => sprintf(
        /* translators: %s is the connection error message */
        __('The Challenge URL is unreachable: %s', 'openporte'),
        $response->get_error_message()
      ),
    );
  }
  $status = wp_remote_retrieve_response_code($response);
  if ($status !== 200) {
    return array(
      'level' => 'error',
      'message' => sprintf(
        /* translators: %d is the HTTP status code returned by the backend */
        __('The Challenge URL responded with HTTP %d instead of a challenge. Check the URL (including any apiKey parameter) and the backend\'s domain/origin restrictions.', 'openporte'),
        $status
      ),
    );
  }
  $challenge = json_decode(wp_remote_retrieve_body($response), true);
  if (!is_array($challenge)
    || !isset($challenge['algorithm'], $challenge['challenge'], $challenge['salt'], $challenge['signature'])) {
    return array(
      'level' => 'error',
      'message' => __('The Challenge URL did not return an ALTCHA challenge (JSON with algorithm, challenge, salt and signature fields expected).', 'openporte'),
    );
  }
  $served_algorithm = (string) $challenge['algorithm'];
  if (!in_array($served_algorithm, OpenPortePlugin::get_allowed_algorithms(), true)) {
    return array(
      'level' => 'error',
      'message' => sprintf(
        /* translators: %s is the algorithm name reported by the backend */
        __('The backend issues challenges with the unsupported algorithm "%s".', 'openporte'),
        $served_algorithm
      ),
    );
  }
  // The signature is hmac(algorithm, challenge, secret) per the ALTCHA spec;
  // recomputing it with the backend's own declared algorithm isolates the
  // secret check from any algorithm mismatch handled below.
  $calculated = hash_hmac(
    OpenPortePlugin::hash_ident($served_algorithm),
    (string) $challenge['challenge'],
    $secret
  );
  if (!hash_equals($calculated, (string) $challenge['signature'])) {
    return array(
      'level' => 'error',
      'message' => __('The shared secret does not match the backend\'s HMAC secret — every form submission would fail verification. Copy the backend\'s HMAC secret into the Signing secret field.', 'openporte'),
    );
  }
  if ($served_algorithm !== $configured_algorithm) {
    return array(
      'level' => 'warning',
      'message' => sprintf(
        /* translators: %1$s is the algorithm used by the backend, %2$s the algorithm configured in OpenPorte */
        __('The backend issues %1$s challenges but the Algorithm setting is %2$s — form submissions would fail verification. Set Algorithm to %1$s.', 'openporte'),
        $served_algorithm,
        $configured_algorithm
      ),
    );
  }
  // In Custom mode the backend owns the expiry: it embeds it in the salt, and
  // that is the value OpenPorte enforces. Distinguish an omitted expiry from
  // one already in the past: the latter points to clock skew or stale caching,
  // not a backend policy that deliberately issues non-expiring challenges.
  $expires = OpenPortePlugin::salt_expires((string) $challenge['salt']);
  if (0 === $expires) {
    return array(
      'level' => 'warning',
      'message' => __('The backend issues challenges with no expiry (no future "expires" parameter in the salt), so a solved challenge never expires on its own and only Replay limit bounds how often it is accepted. Enable challenge expiry on the backend.', 'openporte'),
    );
  }
  if ($expires <= time()) {
    return array(
      'level' => 'warning',
      'message' => __('The backend returned a challenge whose expiry is already in the past. Its clock may be wrong or it may be serving a cached challenge; correct that before using Custom mode.', 'openporte'),
    );
  }
  $message = sprintf(
    /* translators: %s is the algorithm confirmed with the backend */
    __('The Challenge URL responds with a valid %s challenge and the shared secret matches.', 'openporte'),
    $served_algorithm
  );
  $message .= ' ' . sprintf(
    /* translators: %s is a duration such as "20 mins" */
    __('The backend expires its challenges after about %s.', 'openporte'),
    human_time_diff(time(), $expires)
  );
  if ($expires - time() < MINUTE_IN_SECONDS) {
    return array('level' => 'warning', 'message' => $message . ' ' . __('That is short enough to expire before a slow device finishes solving, which turns legitimate submissions into failures.', 'openporte'));
  }

  return array('level' => 'success', 'message' => $message);
}
