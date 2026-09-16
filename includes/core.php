<?php

if (!defined('ABSPATH')) exit;

class OpenPortePlugin
{
  public static $instance;
  public static $language = "";
  public static $widget_script_src = "";
  public static $wp_script_src = "";
  public static $admin_script_src = "";
  public static $admin_css_src = "";
  public static $custom_script_src = "";
  public static $widget_style_src = "";
  public static $version = "0.0.0";
  public static $widget_version = "0.0.0";
  public static $option_version = "openporte_version";
  public static $option_api = "openporte_api";
  public static $option_api_custom_url = "openporte_api_custom_url";
  public static $option_secret = "openporte_secret";
  public static $option_complexity = "openporte_complexity";
  public static $option_expires = "openporte_expires";
  public static $option_replaylimit = "openporte_replaylimit";
  public static $option_replay_health = "openporte_replay_health";
  public static $option_algorithm = "openporte_algorithm";
  public static $option_auto = "openporte_auto";
  public static $option_floating = "openporte_floating";
  public static $option_delay = "openporte_delay";
  public static $option_hidefooter = "openporte_hidefooter";
  public static $option_hidelogo = "openporte_hidelogo";
  public static $option_integration_coblocks = "openporte_integration_coblocks";
  public static $option_integration_contact_form_7 = "openporte_integration_contact_form_7";
  public static $option_integration_custom = "openporte_integration_custom";
  public static $option_integration_elementor = "openporte_integration_elementor";
  public static $option_integration_formidable = "openporte_integration_formidable";
  public static $option_integration_forminator = "openporte_integration_forminator";
  public static $option_integration_gravityforms = "openporte_integration_gravityforms";
  public static $option_integration_woocommerce_login = "openporte_integration_woocommerce_login";
  public static $option_integration_woocommerce_register = "openporte_integration_woocommerce_register";
  public static $option_integration_woocommerce_reset_password = "openporte_integration_woocommerce_reset_password";
  public static $option_integration_html_forms = "openporte_integration_html_forms";
  public static $option_integration_wordpress_login = "openporte_integration_wordpress_login";
  public static $option_integration_wordpress_register = "openporte_integration_wordpress_register";
  public static $option_integration_wordpress_reset_password = "openporte_integration_wordpress_reset_password";
  public static $option_integration_wordpress_comments = "openporte_integration_wordpress_comments";
  public static $option_integration_wpdiscuz = "openporte_integration_wpdiscuz";
  public static $option_integration_wpforms = "openporte_integration_wpforms";
  public static $option_integration_enfold_theme = "openporte_integration_enfold_theme";

  /**
   * Extra pause, in milliseconds, added to verification when the Verification
   * Delay setting is on. Single source of truth: the widget attribute and the
   * settings-page hint are both derived from it.
   *
   * This is a perception knob, not a security control. ALTCHA v2's `delay` is a
   * flat sleep stacked on top of the proof-of-work, not a minimum-duration floor
   * (that is a v3 attribute the bundled widget does not have), so it costs an
   * attacker nothing — a bot simply runs more threads while others wait. It is
   * offered because a visible pause reads as work being done, which raises
   * perceived trustworthiness. Complexity is the setting that actually raises
   * the cost for bots.
   *
   * 500 is a deliberate guess, not a measured or tuned value.
   *
   * @since 1.28.0
   * @var int
   */
  public static $delay_ms = 500;

  /**
   * Default value of the Replay limit setting: how many times one solved token
   * may be accepted before it is refused.
   *
   * 5 rather than strict single use because a legitimate visitor re-submits
   * the same still-valid token whenever a form comes back with an unrelated
   * error (a missing field, a mistyped password). Until the widget can re-solve
   * on a replay rejection, a strict default would turn those into dead ends.
   *
   * @since 1.29.0
   * @var int
   */
  public static $replaylimit_default = 5;

  /**
   * Name prefix of the reuse counter's storage.
   *
   * The counter lives in transient-shaped option rows
   * ('_transient_' . $replay_key_prefix . <hash>) so WordPress's own transient
   * garbage collection reclaims it — no schema, no cron. Pinned here as the
   * single source of truth: uninstall.php's cleanup sweep has to match it.
   *
   * @since 1.29.0
   * @var string
   */
  public static $replay_key_prefix = "openporte_replay_";

  /**
   * Object-cache group holding the reuse counter when a persistent object
   * cache is available (an atomic INCR instead of the guarded option-row
   * update used on plain database installs).
   *
   * @since 1.29.0
   * @var string
   */
  public static $replay_cache_group = "openporte_replay";

  /**
   * Counter lifetime, in seconds, for a token that carries no expiry at all:
   * a self-hosted "None" (0) expiry, or a custom backend that omits `expires`
   * from the salt it serves.
   *
   * Such a token has no validity window to track, so the bound becomes N uses
   * per window rather than N uses per token — a slow drip instead of today's
   * unbounded replay. Every token that does carry an expiry is tracked for
   * exactly its own remaining validity (see enforce_replay_limit()).
   *
   * @since 1.29.0
   * @var int
   */
  public static $replay_ttl_fallback = 14400;

  /**
   * Per-request verification memo, keyed both on the submitted bytes and on
   * the verified signature.
   *
   * One submission must cost one use of its token even when a third-party
   * caller or future integration verifies it more than once in the same
   * request, including through a re-encoded JSON envelope. No shipped
   * integration currently does so: the WordPress and WooCommerce authentication
   * callbacks are mutually exclusive through their nonce guards.
   *
   * Cleared on `init` by reset_request_state(), so persistent-worker SAPIs
   * (FrankenPHP, RoadRunner, Swoole) cannot leak it into the next request.
   *
   * @since 1.29.0
   * @var array<string,bool>
   */
  private $verify_memo = array();

  /**
   * True only while verify() is dispatching to one of the verification
   * primitives, so their deprecation notice fires for third-party callers and
   * never for the internal dispatch.
   *
   * @since 1.29.0
   * @var bool
   */
  private $in_verify = false;

  public static $html_espace_allowed_tags = array(
    'altcha-widget' => array(
      'debug' => array(),
      'challengeurl' => array(),
      'strings' => array(),
      'auto' => array(),
      'floating' => array(),
      'delay' => array(),
      'hidelogo' => array(),
      'hidefooter' => array(),
      'name' => array(),
    ),
    'div' => array(
      'class' => array(),
      'style' => array(),
    ),
    'input' => array(
      'class' => array(),
      'id' => array(),
      'name' => array(),
      'type' => array(),
      'value' => array(),
      'style' => array(),
    ),
    'noscript' => array(),
  );

  // Formatting tags allowed in settings hints (see admin/options.php).
  // Everything else is stripped.
  public static $hint_allowed_tags = array(
    'br'     => array(),
    'em'     => array(),
    'strong' => array(),
    'code'   => array(),
    'a'      => array( 'href' => true, 'target' => true, 'rel' => true ),
  );

  public function init()
  {
    OpenPortePlugin::$instance = $this;
    OpenPortePlugin::$language = get_locale();
    if (defined('OPENPORTE_VERSION')) {
      OpenPortePlugin::$version = OPENPORTE_VERSION;
    }
    if (defined('OPENPORTE_WIDGET_VERSION')) {
      OpenPortePlugin::$widget_version = OPENPORTE_WIDGET_VERSION;
    }
  }

  public function get_api()
  {
    return trim(get_option(OpenPortePlugin::$option_api));
  }

  public function get_api_custom_url()
  {
    return trim(get_option(OpenPortePlugin::$option_api_custom_url));
  }

  public function get_complexity()
  {
    return trim(get_option(OpenPortePlugin::$option_complexity));
  }

  public function get_expires()
  {
    return get_option(OpenPortePlugin::$option_expires);
  }

  /**
   * How many times one solved token may be accepted.
   *
   * 0 means unlimited — the pre-1.29 stateless behaviour, kept as an escape
   * hatch for a site that genuinely needs it.
   *
   * The openporte_replay_limit filter is passed the current hook name as its
   * context, so a site can be stricter on login than on comments without
   * touching any call site. Its return value is re-clamped: a filter returning
   * nonsense must not be able to switch protection off by accident.
   *
   * Called outside any hook, current_filter() returns false and the context is
   * the empty string. Filters should read that as "no hook context", never as a
   * hook name.
   *
   * @since 1.29.0
   *
   * @param string $context Optional hook/context name; defaults to the filter
   *                        currently running.
   * @return int Maximum accepted uses per token; 0 for unlimited.
   */
  public function get_replaylimit($context = '')
  {
    $stored = OpenPortePlugin::clamp_replaylimit(get_option(OpenPortePlugin::$option_replaylimit, null));
    $limit = $stored === null ? OpenPortePlugin::$replaylimit_default : $stored;
    if ($context === '') {
      $context = (string) current_filter();
    }
    $filtered = OpenPortePlugin::clamp_replaylimit(
      apply_filters('openporte_replay_limit', $limit, $context)
    );

    return $filtered === null ? $limit : $filtered;
  }

  /**
   * Read a Replay limit out of any source, or refuse it.
   *
   * The single definition of the setting's range, shared by get_replaylimit()
   * above and by the settings-screen renderer (admin/options.php), so the
   * value the admin is shown is always the value verify() enforces.
   *
   * Only integer-like input is accepted. intval() would turn a mistyped 0.5
   * into 0 — silently switching protection off — and '1e6' into a million,
   * neither of which anyone chose; those are refused so the caller can fall
   * back to the default. An out-of-range integer is clamped instead, because
   * 250 is a legible intent.
   *
   * Deliberately *not* shared with openporte_sanitize_replaylimit(): the
   * sanitizer coerces what an administrator typed into a value the settings
   * page then shows them (-5 becomes 0, visibly wrong), while this refuses
   * values that can only have arrived out of band. Keep the two apart.
   *
   * @since 1.29.0
   *
   * @param mixed $value Candidate value from any source.
   * @return int|null Limit clamped to 0-100, or null when $value is not an
   *                  integer-like, non-negative number.
   */
  public static function clamp_replaylimit($value)
  {
    $limit = filter_var($value, FILTER_VALIDATE_INT);
    if ($limit === false || $limit < 0) {
      return null;
    }

    return min(100, $limit);
  }

  public static function get_allowed_algorithms()
  {
    // Mirrors the ALTCHA spec's permitted algorithms. The vendored widget
    // enforces the same set internally (a module-scope constant in
    // public/altcha.min.js, not exposed on its public API — and a browser
    // runtime value would be unreachable from PHP anyway), so this array is
    // our server-side copy of the spec constant. Re-check on widget upgrade;
    // see docs/agents/altcha-upstream.md (upgrade procedure).
    return array('SHA-256', 'SHA-384', 'SHA-512');
  }

  public function get_algorithm()
  {
    $algorithm = get_option(OpenPortePlugin::$option_algorithm);
    // Default to SHA-256 when unset or invalid: every release before 1.28
    // hardcoded it, so upgraded sites keep verifying their in-flight
    // challenges, and custom ALTCHA-compatible backends default to it too.
    // New installs are seeded with SHA-512 on activation.
    return in_array($algorithm, OpenPortePlugin::get_allowed_algorithms(), true)
      ? $algorithm
      : 'SHA-256';
  }

  /**
   * Map an ALTCHA algorithm label to the PHP hash() identifier.
   * 'SHA-256' -> 'sha256', 'SHA-384' -> 'sha384', 'SHA-512' -> 'sha512'.
   */
  public static function hash_ident($algorithm)
  {
    return strtolower(str_replace('-', '', $algorithm));
  }

  public function get_secret()
  {
    return trim(get_option(OpenPortePlugin::$option_secret));
  }

  public function get_hidelogo()
  {
    return get_option(OpenPortePlugin::$option_hidelogo);
  }

  public function get_hidefooter()
  {
    return get_option(OpenPortePlugin::$option_hidefooter);
  }

  public function get_auto()
  {
    return trim(get_option(OpenPortePlugin::$option_auto));
  }

  public function get_floating()
  {
    return get_option(OpenPortePlugin::$option_floating);
  }

  public function get_delay()
  {
    return get_option(OpenPortePlugin::$option_delay);
  }

  public function get_integration_coblocks()
  {
    return get_option(OpenPortePlugin::$option_integration_coblocks);
  }

  public function get_integration_contact_form_7()
  {
    return get_option(OpenPortePlugin::$option_integration_contact_form_7);
  }

  /**
   * Returns the "Custom HTML" integration option value.
   *
   * @since 1.28.0 Deprecated — the Custom HTML integration is scheduled for
   *               removal in the next major release; place the [openporte]
   *               shortcode instead.
   * @deprecated 1.28.0
   */
  public function get_integration_custom()
  {
    _deprecated_function(__METHOD__, '1.28.0', 'the [openporte] shortcode');
    return get_option(OpenPortePlugin::$option_integration_custom);
  }

  public function get_integration_elementor()
  {
    return get_option(OpenPortePlugin::$option_integration_elementor);
  }

  public function get_integration_enfold_theme() {
    return get_option(OpenPortePlugin::$option_integration_enfold_theme);
  }

  public function get_integration_formidable()
  {
    return get_option(OpenPortePlugin::$option_integration_formidable);
  }

  public function get_integration_forminator()
  {
    return get_option(OpenPortePlugin::$option_integration_forminator);
  }

  public function get_integration_gravityforms()
  {
    return get_option(OpenPortePlugin::$option_integration_gravityforms);
  }

  public function get_integration_woocommerce_register()
  {
    return get_option(OpenPortePlugin::$option_integration_woocommerce_register);
  }

  public function get_integration_woocommerce_reset_password()
  {
    return get_option(OpenPortePlugin::$option_integration_woocommerce_reset_password);
  }

  public function get_integration_woocommerce_login()
  {
    return get_option(OpenPortePlugin::$option_integration_woocommerce_login);
  }

  public function get_integration_html_forms()
  {
    return get_option(OpenPortePlugin::$option_integration_html_forms);
  }

  public function get_integration_wordpress_register()
  {
    return get_option(OpenPortePlugin::$option_integration_wordpress_register);
  }

  public function get_integration_wordpress_reset_password()
  {
    return get_option(OpenPortePlugin::$option_integration_wordpress_reset_password);
  }

  public function get_integration_wordpress_login()
  {
    return get_option(OpenPortePlugin::$option_integration_wordpress_login);
  }

  public function get_integration_wordpress_comments()
  {
    return get_option(OpenPortePlugin::$option_integration_wordpress_comments);
  }

  public function get_integration_wpdiscuz()
  {
    return get_option(OpenPortePlugin::$option_integration_wpdiscuz);
  }

  public function get_integration_wpforms()
  {
    return get_option(OpenPortePlugin::$option_integration_wpforms);
  }


  public function get_challengeurl()
  {
    $api = $this->get_api();
    if ($api === "custom") {
      $challenge_url = $this->get_api_custom_url();
    } else { /* default to selfhosted */
      $challenge_url = get_rest_url(null, "/openporte/v1/challenge");
    }

    $challenge_url = apply_filters('openporte_challenge_url', $challenge_url);
    // Deprecated alias kept for back-compat; remove in a future release.
    return apply_filters_deprecated('altcha_challenge_url', array($challenge_url), '1.27.0', 'openporte_challenge_url');
  }

  public function get_translations($language = null)
  {
    $originalLanguage = null;

    if ($language !== null) {
      $originalLanguage = get_locale();
      switch_to_locale($language);
    }

    $ALTCHA_WEBSITE = constant('ALTCHA_WEBSITE');
    $translations = array(
      "error" => __('Verification failed. Try again later.', 'openporte'),
      "footer" => sprintf(
        /* translators: %1$s and %2$s are the opening and closing tags for a link (<a> tag) */
        __('Protected by %1$sALTCHA%2$s', 'openporte'),
        '<a href="' . $ALTCHA_WEBSITE . '" target="_blank" rel="noopener noreferrer">',
        "</a>",
      ),
      "label" => __('I\'m not a robot', 'openporte'),
      "verified" => __('Verified', 'openporte'),
      "verifying" => __('Verifying...', 'openporte'),
      "waitAlert" => __('Verifying... please wait.', 'openporte'),
    );

    $translations = apply_filters('openporte_translations', $translations, $language);
    // Deprecated alias kept for back-compat; remove in a future release.
    $translations = apply_filters_deprecated('altcha_translations', array($translations, $language), '1.27.0', 'openporte_translations');

    if ($originalLanguage !== null) {
      switch_to_locale($originalLanguage);
    }

    return $translations;
  }

  /**
   * Collects every integration option value into one list.
   *
   * Existed solely to feed has_active_integrations(); dead code together with
   * it since upstream 1.21.0 removed the global script-enqueue gate (see #62).
   * The openporte_integrations filter inside only ever fired through this
   * method, so it goes with it.
   *
   * @since 1.28.0 Deprecated, no replacement — removal in the next major release.
   * @deprecated 1.28.0
   */
  public function get_integrations()
  {
    _deprecated_function(__METHOD__, '1.28.0');
    $integrations = array(
      $this->get_integration_contact_form_7(),
      // Reads the option directly: the deprecated getter would log a second,
      // misleading "use the shortcode" notice for callers of this method.
      get_option(OpenPortePlugin::$option_integration_custom),
      $this->get_integration_elementor(),
      $this->get_integration_enfold_theme(),
      $this->get_integration_forminator(),
      $this->get_integration_gravityforms(),
      $this->get_integration_html_forms(),
      $this->get_integration_woocommerce_register(),
      $this->get_integration_woocommerce_login(),
      $this->get_integration_woocommerce_reset_password(),
      $this->get_integration_wordpress_register(),
      $this->get_integration_wordpress_login(),
      $this->get_integration_wordpress_reset_password(),
      $this->get_integration_wordpress_comments(),
      $this->get_integration_wpforms(),
    );

    $integrations = apply_filters('openporte_integrations', $integrations);
    // Deprecated alias kept for back-compat; remove in a future release.
    return apply_filters_deprecated('altcha_integrations', array($integrations), '1.27.0', 'openporte_integrations');
  }

  /**
   * Whether at least one integration option is enabled.
   *
   * Dead code since upstream 1.21.0: its only caller was the global
   * script-enqueue gate removed in that release (see #62).
   *
   * @since 1.28.0 Deprecated, no replacement — removal in the next major release.
   * @deprecated 1.28.0
   */
  public function has_active_integrations()
  {
    _deprecated_function(__METHOD__, '1.28.0');
    $integrations = $this->get_integrations();

    // Checkbox era: any truthy option value means the integration is enabled
    // (legacy mode strings are normalized to 0/1 on upgrade).
    return array_filter($integrations) !== array();
  }

  public function random_secret()
  {
    // 32 bytes → a 256-bit HMAC key. Only seeds NEW installs: add_option() is a
    // no-op once the option exists, so existing secrets — and the challenges
    // already signed with them — are left untouched.
    return bin2hex(random_bytes(32));
  }

  /**
   * Strictly decode a submitted token into an object, or null if malformed.
   *
   * Tokens are base64(JSON). Decoding defensively here lets the verify methods
   * bail out before reading properties, instead of emitting PHP warnings
   * ("Attempt to read property … on null/false") on every junk submission.
   */
  private function decode_payload($payload)
  {
    if (!is_string($payload) || $payload === '') {
      return null;
    }
    $decoded = base64_decode($payload, true); // strict: reject non-base64 input
    if ($decoded === false) {
      return null;
    }
    $data = json_decode($decoded);
    if (json_last_error() !== JSON_ERROR_NONE || !is_object($data)) {
      return null;
    }
    return $data;
  }

  /**
   * Whether a decoded payload carries every named field, at a usable type.
   *
   * decode_payload() only guarantees an object: JSON lets a submitter send any
   * field as an array or a nested object, and hash(), parse_str() and
   * hash_equals() all raise a TypeError on one — a fatal error on an
   * unauthenticated form POST, which is what security-audit.md finding #3
   * exists to prevent. Both verification primitives type-check through here
   * before they touch a field, so a malformed token is refused rather than
   * fatal.
   *
   * @since 1.29.0
   *
   * @param object            $data    Decoded token payload.
   * @param array<int,string> $strings Field names that must be strings.
   * @param array<int,string> $scalars Field names that need only be scalar
   *                                   (the salted `number`, which is
   *                                   concatenated rather than compared).
   * @return bool True when every named field is present and usable.
   */
  private static function has_payload_fields($data, array $strings, array $scalars = array())
  {
    foreach ($strings as $field) {
      if (!isset($data->$field) || !is_string($data->$field)) {
        return false;
      }
    }
    foreach ($scalars as $field) {
      if (!isset($data->$field) || !is_scalar($data->$field)) {
        return false;
      }
    }

    return true;
  }

  /**
   * Drop the per-request verification memo.
   *
   * Hooked on `init`, which always runs before any verification does. Ordinary
   * PHP-FPM requests get a fresh instance anyway; persistent-worker SAPIs
   * (FrankenPHP, RoadRunner, Swoole) keep the singleton alive between requests,
   * where a leaked memo would let one visitor's accepted token short-circuit
   * the next visitor's submission.
   *
   * That makes calling verify() before `init` unsupported under those SAPIs:
   * the memo would still hold the previous request's entries. Every shipped
   * integration verifies well after `init` (login, comments, AJAX and REST all
   * do), and under PHP-FPM the fresh process makes it moot either way.
   *
   * @since 1.29.0
   */
  public function reset_request_state()
  {
    $this->verify_memo = array();
    $this->in_verify = false;
  }

  /**
   * Fire the verification-result hooks exactly once per verify() call and hand
   * the result straight back to the caller.
   *
   * @since 1.29.0
   *
   * @param bool $result Verification outcome.
   * @return bool The same outcome, so callers can `return $this->emit_verify_result(...)`.
   */
  private function emit_verify_result($result)
  {
    do_action('openporte_verify_result', $result);
    // Deprecated alias kept for back-compat; remove in a future release.
    do_action_deprecated('altcha_verify_result', array($result), '1.27.0', 'openporte_verify_result');

    return $result;
  }

  /**
   * Absolute expiry timestamp carried by a token, whichever shape it has.
   *
   * A proof-of-work payload carries it as an `expires` parameter inside the
   * salt; a server-signature payload as `expire` inside its verification data.
   * One reader for both means the crypto gate and the reuse counter's lifetime
   * are derived from the very same number, which is what guarantees the
   * counter can never die while the token is still acceptable — including in
   * Custom mode, where the expiry is set by the backend and OpenPorte's own
   * Expiration setting is inert.
   *
   * @since 1.29.0
   *
   * @param object|null $data Decoded token payload.
   * @return int Unix timestamp, or 0 when the token carries no expiry.
   */
  private function payload_expires($data)
  {
    if (!is_object($data)) {
      return 0;
    }
    if (isset($data->verificationData) && is_string($data->verificationData)) {
      $verification = array();
      parse_str($data->verificationData, $verification);
      if (isset($verification['expire']) && is_scalar($verification['expire'])) {
        return max(0, intval($verification['expire'], 10));
      }
      return 0;
    }
    if (!isset($data->salt)) {
      return 0;
    }

    return OpenPortePlugin::salt_expires($data->salt);
  }

  /**
   * Expiry timestamp embedded in a challenge salt, if any.
   *
   * ALTCHA carries it as an `expires` query parameter appended to the salt
   * (`<random>?expires=<unix>&`), which is what makes it part of the signed
   * challenge. Self-hosted challenges get it from generate_challenge(); in
   * Custom mode the backend sets it, and a backend that omits it issues
   * challenges that never time out — which is why the settings-page health
   * check reads salts through this same parser.
   *
   * @since 1.29.0
   *
   * @param mixed $salt Challenge salt as served or submitted; anything that is
   *                     not a string counts as carrying no expiry.
   * @return int Unix timestamp, or 0 when the salt carries no expiry.
   */
  public static function salt_expires($salt)
  {
    if (!is_string($salt) || $salt === '') {
      return 0;
    }
    $salt_url = wp_parse_url($salt);
    if (empty($salt_url['query'])) {
      return 0;
    }
    parse_str($salt_url['query'], $salt_params);
    if (empty($salt_params['expires']) || !is_scalar($salt_params['expires'])) {
      return 0;
    }
    return max(0, intval($salt_params['expires'], 10));
  }

  /**
   * Consume one use of a cryptographically valid token, or refuse it.
   *
   * Keyed on the token's signature: it is HMAC-verified before we get here, so
   * it is neither forgeable nor sensitive to how the JSON envelope happens to
   * be encoded — unlike the raw payload, which a replay can re-encode at will.
   * The counter lives exactly as long as the token does, so an expiring token
   * is bounded to N uses over its whole life rather than N uses per window.
   *
   * @since 1.29.0
   *
   * @param object $data Decoded token payload, already verified.
   * @return bool True when a use was still available, or protection is off.
   */
  private function enforce_replay_limit($data)
  {
    $limit = $this->get_replaylimit();
    if ($limit <= 0) {
      // Unlimited (legacy): pre-1.29 behaviour, and no state is written at all.
      return true;
    }
    if (!isset($data->signature) || !is_string($data->signature) || $data->signature === '') {
      return true;
    }
    // 128 bits of a SHA-256 over the signature: collision-free in practice and
    // short enough to keep the option name well inside WordPress's limit.
    $key = OpenPortePlugin::$replay_key_prefix . substr(hash('sha256', $data->signature), 0, 32);
    $expires = $this->payload_expires($data);
    // The floor can only make the counter outlive a nearly-expired token,
    // which is harmless — the crypto gate rejects such a token first. The
    // invariant that matters runs the other way: the counter must never expire
    // while the token it tracks is still acceptable.
    $ttl = $expires > 0
      ? max($expires - time(), MINUTE_IN_SECONDS)
      : OpenPortePlugin::$replay_ttl_fallback;
    $accepted = $this->consume_replay_slot($key, $limit, $ttl);
    if ($accepted === null) {
      // Fail open, but observably: a store that cannot count must degrade to
      // pre-1.29 behaviour rather than lock legitimate visitors out.
      $this->record_replay_failopen();
      do_action('openporte_replay_store_unavailable', $key, $limit, $ttl);

      return true;
    }

    return $accepted;
  }

  /**
   * Atomically claim one slot of a token's reuse budget.
   *
   * Atomicity is the whole point. A read-then-write counter loses updates
   * under exactly the parallel burst a replay attack produces, so the bound
   * would break precisely when it is needed. Two backends provide it: the
   * object cache's INCR, or a single guarded UPDATE that InnoDB row-locks.
   *
   * @since 1.29.0
   *
   * @param string $key   Counter key (unprefixed transient / cache name).
   * @param int    $limit Maximum number of accepted uses; always >= 1 here.
   * @param int    $ttl   Counter lifetime in seconds.
   * @return bool|null True when a slot was claimed, false at the cap, null
   *                   when the store is unavailable — the caller fails open.
   */
  private function consume_replay_slot($key, $limit, $ttl)
  {
    if (wp_using_ext_object_cache())
    {
      // Seed with the string '0', not the integer: some drop-ins serialize
      // non-string values, which turns INCR into a permanent, silent failure
      // — that is, an invisible fail-open.
      // Memcached interprets TTLs above 30 days as absolute Unix timestamps.
      // Cap every persistent-cache window at that portable maximum rather
      // than let a long-lived token's counter expire immediately.
      $cache_ttl = min($ttl, 30 * DAY_IN_SECONDS);
      wp_cache_add($key, '0', OpenPortePlugin::$replay_cache_group, $cache_ttl);
      $count = wp_cache_incr($key, 1, OpenPortePlugin::$replay_cache_group);
      if (!is_numeric($count)) {
        return null;
      }
      $count = intval($count);
      if ($count < 1) {
        return null;
      }

      return $count <= $limit;
    }

    return $this->consume_replay_slot_db($key, $limit, $ttl);
  }

  /**
   * Database backend for consume_replay_slot(): a transient-shaped pair of
   * option rows, claimed with a guarded INSERT and spent with a guarded UPDATE.
   *
   * Shaping the rows as a transient means core's own garbage collection
   * reclaims them, so the counter needs neither a table nor a cron job.
   *
   * @since 1.29.0
   *
   * @param string $key   Counter key (unprefixed transient name).
   * @param int    $limit Maximum number of accepted uses.
   * @param int    $ttl   Counter lifetime in seconds.
   * @return bool|null See consume_replay_slot().
   */
  private function consume_replay_slot_db($key, $limit, $ttl)
  {
    global $wpdb;
    if (!isset($wpdb) || !is_object($wpdb)) {
      return null;
    }
    // Core's lazy expiry sweep: reading the transient drops a pair whose window
    // has elapsed, so a token that outlives its counter starts from a fresh
    // budget instead of inheriting a spent one. Only reachable for tokens that
    // carry no expiry — any other token is refused by the crypto gate first.
    get_transient($key);
    // The expiry marker goes in first, because a value row without one would
    // never be garbage-collected, quietly turning the counter's lifetime into
    // "forever". The inverse — an interrupted claim leaving a timeout row with
    // no value row — is harmless but not self-healing: add_option() is a no-op
    // once the row exists, so the next use of the same token adopts the orphan
    // (keeping the interrupted attempt's expiry) and completes the pair, while
    // an orphan nothing reads again is missed by core's daily sweep, which
    // joins the two rows, and lingers as ~100 bytes. autoload 'no' keeps both
    // rows out of the alloptions cache (add_option()'s fourth argument has
    // meant "do not autoload" as both false and 'no' since WordPress 4.2).
    $timeout_row = '_transient_timeout_' . $key;
    $timeout_created = add_option($timeout_row, time() + $ttl, '', false);
    if (!$timeout_created && get_option($timeout_row, false) === false) {
      // false also means the row already existed. Only fail open when the
      // follow-up read proves that the expiry marker is genuinely absent.
      return null;
    }
    $value_row = '_transient_' . $key;
    // Deliberately not add_option(): core implements it as INSERT ... ON
    // DUPLICATE KEY UPDATE behind a cached existence check, so two concurrent
    // workers can both believe they created the row. A guarded INSERT leaves
    // the uniqueness decision to the option_name index, where it really is
    // atomic.
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Deliberately atomic: core's options API cannot express a create-only insert, and caching a counter would defeat it.
    $created = $wpdb->query(
      $wpdb->prepare(
        "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, '1', 'no')",
        $value_row
      )
    );
    if ($created === false) {
      return null;
    }
    if ($created > 0) {
      return 1 <= $limit;
    }
    // The row already exists, so one statement decides the outcome: the row
    // lock serialises concurrent workers, and "rows changed" is the verdict.
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- As above: the guarded UPDATE is the atomic consume itself.
    $consumed = $wpdb->query(
      $wpdb->prepare(
        "UPDATE {$wpdb->options} SET option_value = CAST(option_value AS UNSIGNED) + 1"
        . " WHERE option_name = %s AND CAST(option_value AS UNSIGNED) < %d",
        $value_row,
        $limit
      )
    );
    if ($consumed === false) {
      return null;
    }
    if ($consumed > 0) {
      return true;
    }
    // Nothing was updated: either the budget is spent, or nothing was ever
    // stored because the writes silently failed. Only the first is a rejection
    // — the second must fail open, so check which one happened.
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Only reached at the cap or on a broken store; reads the row just written.
    $stored = $wpdb->get_var(
      $wpdb->prepare(
        "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1",
        $value_row
      )
    );

    return $stored === null ? null : false;
  }

  /**
   * Record that the reuse counter had to fail open.
   *
   * Without this the degradation is visible only to a site that happens to
   * listen for openporte_replay_store_unavailable; the settings page reads the
   * record instead (see admin/healthcheck.php). The count restarts once a day
   * has passed without an incident, so the report describes a store that is
   * failing now rather than one that hiccuped months ago.
   *
   * Deliberately approximate, in two ways. At most one incident is recorded per
   * minute: while the store is broken every accepted submission would otherwise
   * cost an option write, at a rate the submitter controls, and one sample a
   * minute says "the store is failing now" just as well. And the update is a
   * read-modify-write, so two concurrent fail-opens can lose an increment. The
   * record therefore means "the counter could not be stored, most recently at
   * `last`", not "exactly `count` submissions went uncounted" — health data,
   * not an audit trail. The settings-page notice is worded to match.
   *
   * @since 1.29.0
   */
  private function record_replay_failopen()
  {
    $health = get_option(OpenPortePlugin::$option_replay_health, array());
    if (!is_array($health)) {
      $health = array();
    }
    $now = time();
    $last = isset($health['last']) ? intval($health['last']) : 0;
    if ($last > 0 && ($now - $last) < MINUTE_IN_SECONDS) {
      // Already recorded within the last minute. A second write adds nothing
      // the report would show, and this is the path a submitter can drive.
      return;
    }
    $count = (isset($health['count']) && $last > 0 && ($now - $last) < DAY_IN_SECONDS)
      ? intval($health['count'])
      : 0;
    update_option(
      OpenPortePlugin::$option_replay_health,
      array('count' => $count + 1, 'last' => $now),
      false
    );
  }

  /**
   * Verify a submitted token and consume one of its allowed uses.
   *
   * The sole supported entry point. The two verification primitives below stay
   * pure, stateless cryptography; the policy around them lives here — a
   * per-request memo, then the bounded-reuse counter. Enforcement deliberately
   * sits *after* the dispatch, so it covers the self-hosted proof-of-work
   * path, Custom mode (whose tokens OpenPorte also verifies locally) and the
   * server-signature path alike, keyed on the verified signature all three
   * carry.
   *
   * State is written only after full cryptographic success, so junk, forged
   * and expired tokens never create any: the open REST challenge endpoint
   * stays stateless.
   *
   * @since 1.16.0
   * @since 1.29.0 Enforces the Replay limit setting and memoises the outcome
   *               for the rest of the request.
   *
   * @param string      $payload  The base64-encoded token from the widget.
   * @param string|null $hmac_key The HMAC key; falls back to the configured secret.
   * @return bool True if the token is valid and still had a use left.
   */
  public function verify($payload, $hmac_key = null)
  {
    if ($hmac_key === null) {
      $hmac_key = $this->get_secret();
    }
    if (empty($payload) || empty($hmac_key)) {
      return $this->emit_verify_result(false);
    }
    // Memo on the submitted bytes: the cheapest short-circuit for the common
    // case of one request verifying the very same string twice. Keyed on the
    // HMAC key as well, so a caller that verifies one payload against two
    // different secrets in a request gets two real answers, not a cached one.
    $payload_key = 'payload:' . hash('sha256', $hmac_key . "\0" . (string) $payload);
    if (isset($this->verify_memo[$payload_key])) {
      return $this->emit_verify_result($this->verify_memo[$payload_key]);
    }
    $data = $this->decode_payload($payload);
    if ($data === null) {
      // Malformed token: fail closed (and fire the result hooks) without
      // reaching the property reads in the verify_* methods below.
      $this->verify_memo[$payload_key] = false;

      return $this->emit_verify_result(false);
    }
    $this->in_verify = true;
    if (isset($data->verificationData)) {
      $result = $this->verify_server_signature($payload, $hmac_key);
    } else {
      $result = $this->verify_solution($payload, $hmac_key);
    }
    $this->in_verify = false;

    return $this->emit_verify_result(
      $this->settle_verify_result($data, $payload_key, $result)
    );
  }

  /**
   * Settle a dispatched result against the per-request memo and the reuse
   * counter, and record it for the rest of the request.
   *
   * The order is the contract. The payload memo (already consulted by verify())
   * short-circuits identical bytes. The signature memo catches the same solved
   * challenge re-encoded into another JSON envelope: different bytes, same
   * verified signature, and that has to keep counting as a single use.
   * Enforcement runs last, and is the only step here that writes shared state.
   *
   * @since 1.29.0
   *
   * @param object $data        Decoded token payload; verify() has already
   *                            bailed out on a malformed one, so never null.
   * @param string $payload_key Memo key for the submitted bytes.
   * @param bool   $result      Outcome of the cryptographic dispatch.
   * @return bool The final outcome, memoised under both keys.
   */
  private function settle_verify_result($data, $payload_key, $result)
  {
    $signature_key = null;
    if ($result === true && isset($data->signature) && is_string($data->signature)) {
      $signature_key = 'signature:' . hash('sha256', $data->signature);
      $result = isset($this->verify_memo[$signature_key])
        ? $this->verify_memo[$signature_key]
        : $this->enforce_replay_limit($data);
    }
    $this->verify_memo[$payload_key] = $result;
    if ($signature_key !== null) {
      $this->verify_memo[$signature_key] = $result;
    }

    return $result;
  }

  /**
   * Verify a signed server (ALTCHA Sentinel) response payload.
   *
   * A dormant back-compat path: the bundled widget never emits
   * `verificationData`, and the `verifyurl` attribute that would produce it is
   * stripped from rendered widget markup, so nothing in OpenPorte reaches it.
   *
   * @since 1.16.0
   * @since 1.29.0 Deprecated for direct use — call verify(), which adds the
   *               replay protection this primitive deliberately does not have.
   * @deprecated 1.29.0 Use verify() instead; removal is scheduled for 2.0.
   *
   * @param string      $payload  The base64-encoded token from the widget.
   * @param string|null $hmac_key The HMAC key; falls back to the configured secret.
   * @return bool True if the payload is signed, unexpired and marked verified.
   */
  public function verify_server_signature($payload, $hmac_key = null)
  {
    if (!$this->in_verify) {
      // Fires for third-party callers only: a direct call skips the reuse
      // counter verify() applies, so the token it accepts is unbounded.
      _deprecated_function(__METHOD__, '1.29.0', 'verify()');
    }
    if ($hmac_key === null) {
      $hmac_key = $this->get_secret();
    }
    $data = $this->decode_payload($payload);
    if ($data === null
      || !OpenPortePlugin::has_payload_fields($data, array('algorithm', 'verificationData', 'signature'))) {
      return false;
    }
    // Same configured algorithm as verify_solution(). Preserve the true (raw
    // binary) flag on hash() — removing it breaks all verification.
    $algorithm = $this->get_algorithm();
    $hash_ident = OpenPortePlugin::hash_ident($algorithm);
    $alg_ok = ($data->algorithm === $algorithm);
    $calculated_hash = hash($hash_ident, $data->verificationData, true);
    $calculated_signature = hash_hmac($hash_ident, $calculated_hash, $hmac_key);
    // hash_equals: constant-time comparison so the HMAC can't be recovered via timing.
    $signature_ok = hash_equals($calculated_signature, $data->signature);
    if (!($alg_ok && $signature_ok)) {
      return false;
    }
    // The spam-filter classification is gone (issue #6), but expire/verified
    // are protocol-level validity checks, not spam plumbing. Mirror the ALTCHA
    // reference (verified === true && expire > now): a signed server payload is
    // only valid while unexpired and explicitly verified — without the expire
    // check a once-valid token could be replayed forever. Each check applies
    // only when the backend supplies the field, so a minimal custom backend
    // that omits them keeps working.
    $verification = array();
    parse_str($data->verificationData, $verification);
    // Read through payload_expires() so the crypto gate and the reuse
    // counter's lifetime come from the very same number.
    $expire = $this->payload_expires($data);
    if ($expire > 0 && $expire < time()) {
      return false;
    }
    if (isset($verification['verified'])) {
      $verified_flag = strtolower((string) $verification['verified']);
      if (in_array($verified_flag, array('', '0', 'false', 'no'), true)) {
        return false;
      }
    }
    return true;
  }

  /**
   * Verify a proof-of-work token by recomputing the challenge hash and
   * validating the HMAC signature.
   *
   * @param string $payload  The base64-encoded token from the widget.
   * @param string|null $hmac_key The HMAC key; falls back to the configured secret.
   * @return bool True if the token is valid, false otherwise.
   * @since 1.16.0
   * @since 1.29.0 Deprecated for direct use — call verify(), which adds the
   *               replay protection this primitive deliberately does not have.
   * @deprecated 1.29.0 Use verify() instead; removal is scheduled for 2.0.
   */
  public function verify_solution($payload, $hmac_key = null)
  {
    if (!$this->in_verify) {
      // Fires for third-party callers only: a direct call skips the reuse
      // counter verify() applies, so the token it accepts is unbounded.
      _deprecated_function(__METHOD__, '1.29.0', 'verify()');
    }
    if ($hmac_key === null) {
      $hmac_key = $this->get_secret();
    }
    $data = $this->decode_payload($payload);
    if ($data === null
      || !OpenPortePlugin::has_payload_fields(
        $data,
        array('algorithm', 'salt', 'challenge', 'signature'),
        array('number')
      )) {
      return false;
    }
    // Read through payload_expires() so the crypto gate and the reuse
    // counter's lifetime come from the very same number.
    $expires = $this->payload_expires($data);
    if ($expires > 0 && $expires < time()) {
      return false;
    }
    // The payload must use the configured algorithm (see get_algorithm(); in
    // Custom mode this must match the backend, hence the settings hint).
    $algorithm = $this->get_algorithm();
    $hash_ident = OpenPortePlugin::hash_ident($algorithm);
    $alg_ok = ($data->algorithm === $algorithm);
    $calculated_challenge = hash($hash_ident, $data->salt . $data->number);
    // hash_equals: constant-time comparison for uniformity across the verification
    // path — see issue #84.
    $challenge_ok = hash_equals($data->challenge, $calculated_challenge);
    $calculated_signature = hash_hmac($hash_ident, $data->challenge, $hmac_key);
    // hash_equals: constant-time comparison so the HMAC can't be recovered via timing.
    $signature_ok = hash_equals($calculated_signature, $data->signature);
    $verified = ($alg_ok && $challenge_ok && $signature_ok);
    return $verified;
  }

  public function generate_challenge($hmac_key = null, $complexity = null, $expires = null)
  {
    if ($hmac_key === null) {
      $hmac_key = $this->get_secret();
    }
    if ($complexity === null) {
      $complexity = $this->get_complexity();
    }
    if ($expires === null) {
      $expires = intval($this->get_expires(), 10);
    }
    $salt = $this->random_secret();
    if ($expires > 0) {
      $salt = $salt . '?' . http_build_query(array(
        'expires' => time() + $expires
      ));
    }
    // Load-bearing, not cosmetic (CVE-2025-68113): the signature covers the
    // challenge, the challenge covers the salt, and the salt is where `expires`
    // lives — so the expiry is bound by the signature and cannot be edited
    // without breaking it. The trailing '&' terminates the query string so a
    // crafted secret number cannot splice an extra parameter onto it. Never
    // sign anything the challenge does not cover, and never drop this
    // delimiter. Avoid str_ends_with() (PHP 8.0 / WP 5.9 polyfill) to keep
    // compatibility with the declared "Requires at least: 5.6"; a plain substr
    // check is enough.
    if (substr($salt, -1) !== '&') {
      $salt .= '&';
    }
    $matrix = OpenPortePlugin::get_complexity_matrix();
    // 'low' is the fallback for unknown/legacy stored values.
    $range = isset($matrix[$complexity]) ? $matrix[$complexity] : $matrix['low'];
    $secret_number = random_int($range['min'], $range['max']);
    $algorithm = $this->get_algorithm();
    $hash_ident = OpenPortePlugin::hash_ident($algorithm);
    $challenge = hash($hash_ident, $salt . $secret_number);
    $signature = hash_hmac($hash_ident, $challenge, $hmac_key);
    $response = [
      'algorithm' => $algorithm,
      'challenge' => $challenge,
      'maxnumber' => $range['max'],
      'salt' => $salt,
      'signature' => $signature
    ];
    return $response;
  }

  /**
   * Proof-of-work complexity matrix — the single authoritative definition of
   * the difficulty ranges. The widget brute-forces 0..maxnumber, so 'max'
   * bounds the worst case and the min..max window sets the average work.
   *
   * Worst-case solve times measured on real devices (SHA-512, worker pool,
   * top of each band) — see local/benchmarks/pow-benchmarks-2026-07.md:
   *
   *                                       Low        Medium     High
   *   desktop/laptop, 2019 or newer       0.1–0.8s   0.2–1.2s   0.3–1.7s
   *   phone/tablet, current, Low Power    1.0s       1.6s       2.3s
   *   phone, ~10 years old                1.6s       2.5s       3.7s
   *
   * Device age dominates, not the browser engine — the spread across engines
   * on one machine is far smaller than the spread across device age. The
   * widget sizes its worker pool from navigator.hardwareConcurrency, which is
   * commonly capped for fingerprinting resistance: WebKit returns a fixed 4 on
   * iOS and 8 on macOS whatever the SoC, while Firefox and Brave clamp it
   * under user-adjustable privacy settings. The times above are therefore the
   * capped case — the conservative one, and the one privacy-conscious visitors
   * actually get.
   *
   * Sized for hardware back to ~2016; pre-2015 devices still work but are
   * deliberately not sized for (they land near 2.6 / 4.1 / 5.9s).
   *
   * Filterable so a site can tune difficulty without forking the plugin:
   * add_filter('openporte_complexity_matrix', ...). A 'low' entry must always
   * exist — it is the fallback for unknown/legacy stored values.
   */
  public static function get_complexity_matrix()
  {
    $matrix = array(
      'low'    => array('min' =>  50000, 'max' =>  70000),
      'medium' => array('min' =>  90000, 'max' => 110000),
      'high'   => array('min' => 140000, 'max' => 160000),
    );
    return apply_filters('openporte_complexity_matrix', $matrix);
  }

  public function get_widget_attrs($mode, $language = null, $name = null)
  {
    $challengeurl = $this->get_challengeurl();
    $floating = $this->get_floating();
    $delay = $this->get_delay();
    $hidelogo = $this->get_hidelogo();
    $hidefooter = $this->get_hidefooter();
    $auto = $this->get_auto();
    $strings = wp_json_encode($this->get_translations($language));
    $attrs = array(
      'challengeurl' => $challengeurl,
      'strings' => $strings,
    );
    if ($name) {
      $attrs['name'] = $name;
    }
    if ($auto) {
      $attrs['auto'] = $auto;
    }
    if ($floating) {
      $attrs['floating'] = 'auto';
    }
    if ($delay) {
      $attrs['delay'] = (string) OpenPortePlugin::$delay_ms;
    }
    if ($hidelogo) {
      $attrs['hidelogo'] = '1';
    }
    if ($hidefooter) {
      $attrs['hidefooter'] = '1';
    }
    $attrs = apply_filters('openporte_widget_attrs', $attrs, $mode, $language, $name);
    // Deprecated alias kept for back-compat; remove in a future release.
    return apply_filters_deprecated('altcha_widget_attrs', array($attrs, $mode, $language, $name), '1.27.0', 'openporte_widget_attrs');
  }

  public function render_widget($mode, $wrap = false, $language = null, $name = null)
  {
    openporte_enqueue_scripts();
    openporte_enqueue_styles();
    $attrs = $this->get_widget_attrs($mode, $language, $name);
    $attributes = join(' ', array_map(function ($key) use ($attrs) {
      if (is_bool($attrs[$key])) {
        return $attrs[$key] ? $key : '';
      }
      return esc_attr($key) . '="' . esc_attr($attrs[$key]) . '"';
    }, array_keys($attrs)));
    $html =
      "<altcha-widget "
      . $attributes
      . "></altcha-widget>"
      . "<noscript>"
      /* translators: Displayed inside a <noscript> block when the visitor's browser has JavaScript disabled; the ALTCHA widget cannot function without it. */
      . "<div class=\"altcha-no-javascript\">" . esc_html__('This form requires JavaScript!', 'openporte') . "</div>"
      . "</noscript>";
    if ($wrap) {
      $html = '<div class="altcha-widget-wrap">' . $html . '</div>';
    }

    $html = apply_filters('openporte_widget_html', $html, $mode, $language, $name);
    // Deprecated alias kept for back-compat; remove in a future release.
    return apply_filters_deprecated('altcha_widget_html', array($html, $mode, $language, $name), '1.27.0', 'openporte_widget_html');
  }
}

// Deprecated back-compat alias for third-party code referencing the old class
// name; scheduled for removal in a future release.
class_alias('OpenPortePlugin', 'AltchaPlugin');

if (!isset(OpenPortePlugin::$instance)) {
  $openporte_plugin_instance = new OpenPortePlugin();
  $openporte_plugin_instance->init();
}

// Clear the per-request verification memo at the start of every request. Every
// verify() call site fires after `init`, and this is what keeps the memo from
// surviving into the next request on persistent-worker SAPIs.
add_action('init', array(OpenPortePlugin::$instance, 'reset_request_state'));

require plugin_dir_path(__FILE__) . 'admin.php';
require plugin_dir_path(__FILE__) . 'settings.php';

add_action(
  'rest_api_init',
  function () {
    $route = 'challenge';
    $args  = array(
      'methods'   => WP_REST_Server::READABLE,
      'callback'  => 'openporte_generate_challenge_endpoint',
      'permission_callback' => '__return_true'
    );
    register_rest_route('openporte/v1', $route, $args);
    // Deprecated alias namespace kept for back-compat; remove in a future release.
    register_rest_route('altcha/v1', $route, $args);
  }
);

function openporte_generate_challenge_endpoint()
{
  $resp = new WP_REST_Response(OpenPortePlugin::$instance->generate_challenge());
  $resp->set_headers(array('Cache-Control' => 'no-cache, no-store, max-age=0'));
  return $resp;
}
