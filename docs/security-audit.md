# Security Audit

> Maintainer / contributor reference. This document records a security review of
> the OpenPorte Spam Protection plugin and the remediation taken for each
> finding. It complements `docs/architecture.md` (how verification works) and
> `AGENTS.md` (coding conventions). It is not user-facing.
>
> Scope reviewed: all PHP under `openporte.php`, `includes/`, `admin/`,
> `public/`, `integrations/` (including the `formidable/`, `gravityforms/` and
> `elementor/` subdirectories), `uninstall.php`, and the first-party JavaScript
> in `public/` (`script.js`, `custom.js`, `admin.js`). The vendored
> `public/altcha.min.js` is treated as a third-party dependency and was not
> audited line by line.
>
> Audited version: 1.27.1, with Appendix D (external advisory cross-check) and
> the Appendix B SSRF row re-checked against 1.28.0. Finding #1 was re-worked
> for 1.29.0, the release that fixes it.
> A prior review of the pre-fork v1.26.3 lives in
> `local/Security_Analysis.md`; several of its findings are now obsolete
> (`get_ip_address()` and the paid-SaaS challenge URL it flagged were removed in
> the 1.27.0 paid-SaaS removal, and the widget-attribute escaping it flagged is
> now handled by `esc_attr()` in `render_widget()`).
>
> A second pass maps the code against two external frameworks — the
> [WordPress Plugin Security Guidelines](https://developer.wordpress.org/plugins/security/)
> and the [OWASP Top 10 (2021)](https://owasp.org/Top10/) plus the
> [OWASP PHP Configuration / Input-Validation cheat sheets](https://cheatsheetseries.owasp.org/).
> The per-guideline coverage tables are Appendix A and Appendix B; the framework
> pass surfaced no new Medium/High issues but added the Info/Low observations
> #9–#11 below.
>
> Findings #13–#20 come from the review of 1.30.0-dev at commit `5c804b5`
> (2026-10-23) and the final review of the release branch (2026-10-24). All
> eight are fixed in 1.30.0. Each records the first release
> that carried the defect, so a site can tell whether it was affected; releases
> before OpenPorte 1.27.0 are those of the original ALTCHA Spam Protection
> plugin, whose code OpenPorte inherited.

## Summary

Baseline WordPress hygiene is strong:

- **Input** is read as `sanitize_text_field( wp_unslash( $_POST[...] ) )`
  everywhere a superglobal is touched.
- **Output** is escaped for context — `esc_html()` / `esc_attr()` on the admin
  page, and `wp_kses()` with the `OpenPortePlugin::$html_espace_allowed_tags`
  whitelist for every rendered widget.
- **Admin settings** go through the WordPress Settings API, which enforces the
  capability (`manage_options`) and the `options.php` nonce on save; every
  registered option has a `sanitize_callback`.
- **Direct access** is blocked by an `ABSPATH` (or `WP_UNINSTALL_PLUGIN`) guard
  in every PHP file.
- **Crypto** uses `random_bytes()` / `random_int()` for secrets and the PoW
  secret number, and `hash_equals()` for constant-time HMAC comparison.
- The single direct DB query (uninstall cleanup) uses `$wpdb->prepare()` with
  `$wpdb->esc_like()`.

No SQL injection, stored/reflected XSS, missing-capability, or broken-nonce
issues were found. The substantive weaknesses are in the **challenge-verification
logic**; the rest are low-severity hardening items.

| # | Title | Type | Location | Risk | Status |
| - | ----- | ---- | -------- | ---- | ------ |
| 1 | Solved tokens are not single-use (replay) | Replay | `verify()` / `verify_solution()` | Medium | **Mitigated (1.29.0)** — bounded reuse, residuals documented |
| 2 | Server-signature path skips `expire` / `verified` | Replay / weak verification | `verify_server_signature()` | Medium | **Fixed** |
| 3 | Decoded payload not validated before use | Input validation / robustness | `verify()` + sub-methods | Low | **Fixed** |
| 4 | Broken `autocomplete` attribute on settings inputs | Info exposure / best practice | `admin/options.php` | Low | **Fixed** |
| 5 | No rate limiting on the public challenge endpoint | DoS / abuse | REST route | Low | Accepted (documented) |
| 6 | No HTTPS enforcement on the custom challenge URL | MITM (operator-controlled) | `get_challengeurl()` / settings | Info | Accepted (documented) |
| 7 | Signing secret stored in plaintext in `wp_options` | Hardening | options | Info | Accepted (documented) |
| 8 | Form handlers add no nonce of their own | By design | `integrations/*` | Info | Accepted (documented) |
| 9 | HMAC signing key is 96-bit entropy | Crypto strength (defence in depth) | `random_secret()` | Low | **Fixed** |
| 10 | Formidable autoloader regex does not block path separators | Path traversal (theoretical) | `integrations/formidable.php` | Low | **Fixed** |
| 11 | Unused dead code from the removed paid-SaaS path | Attack surface / maintainability | `core.php` | Info | **Fixed** |
| 12 | Inline-script JSON not hardened against `</script>` breakout | XSS (defence in depth) | `integrations/custom.php` | Info | **Fixed** |
| 13 | Solved challenges accepted after their expiry | Replay / weak verification | `verify_solution()` | Medium | **Fixed (1.30.0)** |
| 14 | Login challenge checked only after the password | Brute force / oracle | `integrations/wordpress.php`, `integrations/woocommerce.php` | Medium | **Fixed (1.30.0)** |
| 15 | Forged WooCommerce field skips login and reset checks | CAPTCHA bypass | `integrations/wordpress.php`, `integrations/woocommerce.php` | Medium | **Fixed (1.30.0)** |
| 16 | CoBlocks reCAPTCHA overridden while the integration is off | CAPTCHA bypass | `integrations/coblocks.php` | Medium | **Fixed (1.30.0)** |
| 17 | Site endpoints issue challenges in Custom API mode | CAPTCHA policy bypass | challenge endpoints in `includes/core.php` | Low | **Fixed (1.30.0)** |
| 18 | Elementor field file ends the whole request | Robustness | `integrations/elementor/field.php` | Info | **Fixed (1.30.0)** |
| 19 | "Custom" preset without a value saved as 0 | Configuration integrity (admin only) | `includes/settings.php` | Info | **Fixed (1.30.0)** |
| 20 | Unterminated Custom-mode salts let solved challenges outlive their expiry | Replay / weak verification | `verify_solution()`, `admin/healthcheck.php` | Low | **Fixed (1.30.0)** |

> Findings 9–11 came from the framework review (Appendix A/B). None was
> exploitable; each was a non-breaking hardening and has now been applied.
> Finding 12 came from a follow-up hardening pass.

**Affected releases for findings #13–#20.** Every release from the one named
below up to and including OpenPorte 1.29.1 carries the defect; 1.30.0 fixes it.

| # | Present since | Affected configuration |
| - | ------------- | ---------------------- |
| 13 | ALTCHA Spam Protection 0.3.2 (the first release) | Every site; worse with Replay limit 0 (pre-1.29.0 behaviour) |
| 14 | ALTCHA Spam Protection 0.3.2 (the first release) | WordPress or WooCommerce login protection on |
| 15 | ALTCHA Spam Protection 1.13.1 | WooCommerce active, WordPress login or reset protection on, the matching WooCommerce protection off |
| 16 | ALTCHA Spam Protection 1.23.0 | CoBlocks active with its own reCAPTCHA configured, OpenPorte's CoBlocks integration off (the default) |
| 17 | ALTCHA Spam Protection 1.21.0 (Custom API mode added) | API mode Custom |
| 18 | ALTCHA Spam Protection 1.4.0 | Elementor active without Elementor Pro's form field classes |
| 19 | OpenPorte 1.28.0 (Expiration); 1.29.0 (Replay limit) | Only a hand-crafted settings POST by an administrator |
| 20 | ALTCHA Spam Protection 1.21.0 (Custom API mode added) | API mode Custom, with a backend whose salts do not end with `&` |

The same review corrected three statements of this document that had been
inaccurate since its 1.29.0 revision: the claim that the WordPress and
WooCommerce `authenticate` callbacks are mutually exclusive through their nonce
guards (finding #1; see #15), the CVE-2025-68113 cross-check's "not affected"
(Appendix D; see #13), and the statement that the challenge endpoints always
answer (finding #5; see #17).

---

## Findings

### 1. Solved tokens are not single-use (replay) — Medium — Mitigated (1.29.0)

**Type:** Replay attack / anti-automation bypass.
**Location:** `includes/core.php`, `verify()` → `verify_solution()` (and the
`verify_server_signature()` path).

**The weakness (through 1.28.1).** `verify_solution()` validates the algorithm,
the challenge hash, the HMAC signature, and — when the salt carries an
`?expires=` parameter — the expiry. It kept **no record of which solutions had
already been accepted**. The same base64 `altcha` payload therefore verified
successfully on every submission until it expired. With the default expiry of
5 minutes (300 s since 1.28.0; 1 hour before that) — and "None" (`0`) meaning
never — a bot could solve one proof-of-work and then replay that single token
across unlimited submissions, defeating the anti-spam purpose.

> Note: the 1.26.3 changelog entry "Fixed possible replay attacks via salt
> splicing" refers to a narrower salt-parsing bug, not to one-time-use.

**The fix (1.29.0).** `verify()` became a stateful policy wrapper around the two
stateless primitives, which keep doing pure cryptography. It adds two layers:

- a **per-request memo**, keyed both on the submitted bytes and on the verified
  signature, so repeated third-party or future integration calls cost one use
  even when the JSON envelope is re-encoded in between. Since 1.30.0 a core
  login does verify twice in one request — the pre-password gate and the
  `authenticate` backstop (finding #14) — and the memo answers the second
  check. The WordPress and WooCommerce login callbacks are mutually exclusive
  through `openporte_is_woocommerce_form_submission()` (finding #15);
- an **atomic reuse counter**, keyed on the token's HMAC-verified `signature`
  and bounded by the new **Replay limit** setting (`openporte_replaylimit`,
  default **5**, `0` = unlimited).

Three properties make the bound hold rather than merely exist:

1. **Atomic — by design, and as of 1.29.0 verified only by design.** A
   read-then-write counter loses updates under exactly the parallel burst a
   replay produces, so the bound would break precisely when it is needed. With
   a persistent object cache the counter is a `wp_cache_incr()`; otherwise it is
   one guarded `UPDATE … WHERE CAST(option_value AS UNSIGNED) < limit` that
   InnoDB row-locks, so the check and the increment cannot be separated by
   another worker. The first claim is a guarded `INSERT IGNORE` against
   `wp_options`' `UNIQUE KEY option_name`, deliberately *not* `add_option()` —
   core implements that as `INSERT … ON DUPLICATE KEY UPDATE` behind a cached
   existence check, so two concurrent workers can both believe they created the
   row.

   **State of verification.** This is a reasoned argument, checked link by link
   in the v1.29.0 acceptance record, not a measured result: neither test suite
   can produce genuine concurrency (one is single-process against a fake
   `$wpdb`, the other runs a single worker by design). Its one environmental
   assumption *was* checked on the bench (WordPress 7.1, MariaDB 11.8.8):
   `wp_options` is InnoDB and carries `UNIQUE KEY option_name`, which is what
   makes the first-use `INSERT IGNORE` an atomic create and the guarded
   `UPDATE` row-locked. What remains unproven is the behaviour of the whole
   under load — a design argument catches a wrong shape; only parallel workers
   catch a wrong assumption. The parallel-replay test that would measure it is
   deferred to issue #102; until it lands, do not read this section as evidence
   that atomicity has been demonstrated under concurrency.
2. **Keyed on a verified field.** The signature is HMAC-checked before the
   counter is touched, so it is neither forgeable nor sensitive to how the JSON
   envelope happens to be encoded — unlike the raw payload, which a replay can
   re-encode at will.
3. **Lifetime = the token's own remaining validity** (60 s floor), read through
   the shared `payload_expires()` helper that also feeds the crypto gate. The
   database backend has no ceiling, so an expiring token is bounded to N uses
   over its *whole life*. Persistent object-cache TTLs are capped at 30 days:
   Memcached treats anything higher as an absolute Unix timestamp and would
   otherwise expire the counter immediately. A longer-lived token is therefore
   bounded to N uses per 30-day window on that backend rather than silently
   becoming unlimited.

The counter lives in transient-shaped `wp_options` rows, so WordPress's own
garbage collection reclaims it — no schema, no cron. It is written **only after
full cryptographic success**, so junk, forged and expired tokens never create
state and the open challenge endpoints (REST and, since 1.30.0, admin-ajax)
stay stateless (finding #5 is unaffected).

**Custom-backend ("GateCHA") mode is covered by the same mechanism.** This also
closes a gap that was never recorded here: OpenPorte verifies custom-backend
tokens **locally**, with `verify_solution()` and the shared secret — it makes no
server-to-server verify call — so a custom backend's own replay protection, if
it has any, never applied to OpenPorte's form endpoints. Enforcement sits
*after* the dispatch in `verify()`, keyed on the `signature` both payload shapes
carry, so self-hosted, custom and server-signature tokens are all bounded by
OpenPorte-owned state, with no dependency on the backend being stateful and no
protocol change. A custom backend **should** still set `expires` in the salt it
serves, so that the crypto gate and the counter's lifetime both bound the token;
the settings-page health check now warns when it does not.

**Why bounded reuse and not strict single-use.** Strict single-use carries a
real false-rejection risk: when a submission fails for an unrelated reason
("username already taken", a missing field) and the visitor resubmits, the
still-valid token would be rejected as a replay unless the widget re-solves on
re-render — which it usually does, but not in every configuration. A default of
5 keeps those resubmissions working while cutting amplification from unbounded
to five. This is a **deliberate deviation from upstream ALTCHA**, whose v2
"Next" plugin keeps a strict used-challenge registry. The trade-off is revisited
once the widget can re-solve after a replay rejection (visitor-recovery UX,
issue #103) — that is the prerequisite for lowering the default toward strict.

**Residual risk — the arithmetic.** The bound is real, not absolute:

- **Amplification equals the configured limit** (default 5). One solved
  proof-of-work buys five submissions, not one.
- **Fail-open on a broken store.** If the counter cannot be written the
  submission is accepted, degrading to pre-1.29 behaviour. Deliberate — a store
  outage must not lock visitors out — and observable: the plugin fires
  `openporte_replay_store_unavailable` and reports recent episodes on the
  settings page.
- **Per-node / per-site caches don't share counters.** APCu, or a node-local
  Redis, gives each node its own budget, multiplying the effective limit by the
  node count. A shared object cache, or the database path, does not.
- **Direct calls to the primitives bypass it.** `verify_solution()` and
  `verify_server_signature()` remain public and stateless; both are now
  deprecated for direct use (`_deprecated_function`, removal scheduled for 2.0),
  and `verify()` is the sole supported entry point.
- **Tokens with no expiry get a window, not a lifetime.** A self-hosted expiry
  of `0` ("None"), or a custom backend that omits `expires` from the salt,
  leaves nothing to track, so the counter falls back to a 4-hour window that
  resets: N uses per 4 h — a slow drip rather than the previous unbounded burst.
  The `0` case closes for good when "None" is removed (issue #103); the
  custom-backend case is detected at the endpoint health check and escalates to
  an operator-chosen policy in the same release.
- **`replaylimit = 0` switches the counter off.** It is the documented escape
  hatch for a site that genuinely needs the old stateless behaviour, and the
  settings page warns for as long as it is set.
- **A solved token creates state before downstream form checks.** An attacker
  willing to pay the proof-of-work can create one row pair per solved token even
  when a later comment, duplicate, or form-validation check rejects the
  submission. The cost is bounded by the proof-of-work; each row is small,
  non-autoloaded and reclaimed with the counter's expiry.

**Invariant to preserve (CVE-2025-68113).** The counter's lifetime is derived
from `expires`, so `expires` must stay bound by the signature: the signature
covers the challenge, the challenge covers the salt, and `expires` lives in the
salt — editing it breaks the challenge hash. The trailing `&` that
`generate_challenge()` appends terminates the query string, so a crafted secret
number cannot splice an extra parameter onto it. Never sign anything the
challenge does not cover, and never drop that delimiter. Both are pinned by
regression tests in `tests/phpunit/VerifyPrimitivesTest.php`.

**Not a mitigation: Verification Delay.** The `openporte_delay` setting is
emitted only as a client-side widget attribute; the widget applies it as a
browser `setTimeout` *before* it fetches and solves the challenge, and **no PHP
path sleeps** (there is no `sleep`/`usleep` anywhere in the plugin, and
`verify()` never reads the setting). A replayed token is a bare HTTP POST — the
widget JS never runs, so there is nothing to skip — and a bot that solves the
proof-of-work itself bypasses it just as completely. It is a perception knob,
not defence in depth: **never count it toward this finding.**

---

### 2. Server-signature verification skips `expire` / `verified` — Medium — Fixed

**Type:** Replay / weakened verification (custom-backend / spam-filter mode).
**Location:** `includes/core.php`, `verify_server_signature()`.

When a `custom` backend returns a server-signed payload, the original code
verified only the HMAC signature and then returned `true` whenever
`classification !== 'BAD'`. It did **not** check the `expire` timestamp or the
`verified` flag carried in `verificationData`. Two consequences:

1. **No expiry** — a captured server-signed payload is accepted forever (replay),
   even though the proof-of-work path (`verify_solution()`) already enforces
   expiry. The ALTCHA reference implementation requires
   `verified === true && expire > now`.
2. **`verified` ignored** — a payload the backend explicitly marked *not* verified
   would still be accepted as long as `classification` was not the literal `BAD`.

Additionally, reading `$this->spamfilter_result['classification']` without an
`isset()` guard emits a PHP warning and fails open (treats a missing
classification as "not BAD") when the key is absent.

**Fix applied.** After the HMAC check passes, parse `verificationData` and:

- reject when `expire` is present, numeric, and in the past;
- reject when `verified` is present and falsy (`''`, `0`, `false`, `no`);
- guard the `classification` read with `isset()`.

The `expire` / `verified` checks are **defensive (only-when-present)** so a
minimal custom backend that omits those fields is not broken, while the
reference backend (which sends both) gets the stricter behaviour. The
load-bearing raw-binary `true` flag on `hash('sha256', …, true)` is preserved
(removing it breaks all verification — see `AGENTS.md`).

```php
public function verify_server_signature($payload, $hmac_key = null)
{
  if ($hmac_key === null) {
    $hmac_key = $this->get_secret();
  }
  $data = $this->decode_payload($payload);
  // Guard the payload shape before touching properties (see finding #3).
  if ($data === null || !isset($data->algorithm, $data->verificationData, $data->signature)) {
    return false;
  }
  $alg_ok = ($data->algorithm === 'SHA-256');
  // The raw-binary (true) flag is load-bearing; removing it breaks verification.
  $calculated_hash = hash('sha256', $data->verificationData, true);
  $calculated_signature = hash_hmac('sha256', $calculated_hash, $hmac_key);
  // hash_equals: constant-time comparison so the HMAC can't be recovered via timing.
  if (!($alg_ok && hash_equals($calculated_signature, $data->signature))) {
    return false;
  }
  $this->spamfilter_result = array();
  parse_str($data->verificationData, $this->spamfilter_result);
  // Mirror verify_solution() and the ALTCHA reference (verified === true &&
  // expire > now). Checked only when the backend supplies the field, so minimal
  // custom backends that omit them keep working.
  if (isset($this->spamfilter_result['expire'])) {
    $expire = intval($this->spamfilter_result['expire'], 10);
    if ($expire > 0 && $expire < time()) {
      return false;
    }
  }
  if (isset($this->spamfilter_result['verified'])) {
    $verified_flag = strtolower((string) $this->spamfilter_result['verified']);
    if (in_array($verified_flag, array('', '0', 'false', 'no'), true)) {
      return false;
    }
  }
  return !isset($this->spamfilter_result['classification'])
    || $this->spamfilter_result['classification'] !== 'BAD';
}
```

**Since 1.28.0.** The remediation above is still in force — the `expire` and
`verified` checks, which are what this finding is about, are unchanged. The
snippet itself shows the 1.27.2 code and has since drifted in two ways, neither
of which weakens the fix: the digest is no longer hardcoded (`get_algorithm()`
and `hash_ident()` now supply it, so a site can run SHA-384/512), and the
spam-filter plumbing is gone (#6) — `$this->spamfilter_result` is a local
`$verification` array and the trailing `classification !== 'BAD'` return became
a plain `return true`. `verify_server_signature()` in `includes/core.php` is the
current reference.

---

### 3. Decoded payload not validated before use — Low — Fixed

**Type:** Input validation / robustness.
**Location:** `includes/core.php`, `verify()`, `verify_solution()`,
`verify_server_signature()`.

The verification methods decoded the submitted token as
`json_decode( base64_decode( $payload ) )` and then read properties
(`$data->algorithm`, `$data->salt`, …) without checking that decoding produced a
valid object. A non-base64 or non-JSON `altcha` value yields `false` / `null`,
so every property access emits a PHP warning ("Attempt to read property … on
null"). The result still fails closed, but the warnings pollute the log and, on
strict configurations, can interfere with output.

**Fix applied.** A private `decode_payload()` helper performs strict decoding;
`verify()` dispatches only on a valid object, and each sub-method guards the
properties it needs with `isset()` so it stays self-contained when called
directly.

```php
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
```

`verify_solution()` gains
`if ($data === null || !isset($data->algorithm, $data->salt, $data->number, $data->challenge, $data->signature)) { return false; }`
at the top; `verify_server_signature()` gains the equivalent guard shown in
finding #2. Behaviour on valid payloads is unchanged; malformed input still
fails closed, now without warnings.

---

### 4. Broken `autocomplete` attribute on settings inputs — Low — Fixed

**Type:** Information exposure / WordPress best practice.
**Location:** `admin/options.php`, `openporte_settings_field_callback()`.

The shared settings-field `<input>` carried `autcomplete="none"` — a misspelling
of `autocomplete`, so the intended autocomplete suppression never took effect.
Because the same callback renders the **Signing secret** field as a plain text
input with its value pre-filled, browsers and password managers could capture
and re-offer the HMAC signing secret.

**Fix applied.** Correct the attribute to `autocomplete="off"`. (The field
remains `type="text"`: the admin needs to read and copy the secret, and the
value is only ever exposed on the `manage_options`-gated settings page. The
plaintext-in-DB aspect is finding #7.)

```php
<input autocomplete="off" class="regular-text" ...>
```

---

### 5. No rate limiting on the public challenge endpoint — Low — Accepted

**Type:** Denial of service / abuse.
**Location:** `includes/core.php`, the `rest_api_init` route with
`permission_callback => '__return_true'`, and `openporte_generate_challenge_endpoint()`;
since 1.30.0 also the `wp_ajax_openporte_challenge` /
`wp_ajax_nopriv_openporte_challenge` actions and
`openporte_generate_challenge_ajax_endpoint()` (issue #100).

`GET /wp-json/openporte/v1/challenge` (and the deprecated `altcha/v1` alias) is
unauthenticated and uncapped, and so is its admin-ajax twin
`/wp-admin/admin-ajax.php?action=openporte_challenge`. Both are always
registered, whichever Self-hosted transport is selected; since 1.30.0 both
refuse while API mode is Custom (finding #17). Otherwise both call the same
`generate_challenge()`: each call runs `random_int()` plus a couple of hashes
and returns a fresh signed challenge with `Cache-Control: no-cache, no-store,
max-age=0`. The Ajax action deliberately has no nonce or capability check: it
changes no state, and logged-out visitors are exactly who must reach it.

**Decision — Accepted, not fixed.** The endpoint *must* be publicly reachable
for the CAPTCHA to function. The usual mitigation, per-IP throttling via
transients, would require reading `REMOTE_ADDR` and so conflicts with the
plugin's explicit "collects no visitor IP address" privacy promise (see
`docs/architecture.md` → Privacy stance). The per-request cost is low and
proof-of-work already prevents forging a *valid* solution, so the residual risk
is generic request-flooding, best handled at the web-server / WAF / caching
layer rather than in-plugin. Operators who need it can rate-limit the route
upstream — both of them: limiting only the selected transport leaves the other
URL open.

---

### 6. No HTTPS enforcement on the custom challenge URL — Info — Accepted

**Type:** Man-in-the-middle (operator-controlled configuration).
**Location:** `includes/core.php`, `get_challengeurl()`; the
`openporte_api_custom_url` option (sanitised by `openporte_sanitize_challenge_url()`
with `esc_url_raw()`, which permits `http://`).

In `custom` mode the operator supplies the challenge backend URL. It is not
forced to HTTPS, so a misconfigured `http://` backend would expose challenges
and solutions in transit.

**Decision — Accepted, not fixed.** The value is set by an administrator, not a
visitor, and forcing HTTPS would break legitimate local/development backends
(`http://localhost`, container hostnames). A future UI hint recommending HTTPS
is preferable to a hard block. `esc_url_raw()` already strips dangerous schemes
(e.g. `javascript:`), so there is no injection vector — only a configuration
recommendation.

---

### 7. Signing secret stored in plaintext in `wp_options` — Info — Accepted

**Type:** Hardening / defence in depth.
**Location:** activation (`openporte_activate()`), `OpenPortePlugin::$option_secret`.

The HMAC signing secret is stored unencrypted in `wp_options`. A database
compromise lets an attacker forge valid challenges and solutions.

**Decision — Accepted.** This is standard for WordPress plugins that need a
server-side secret available on every request; there is no materially better
store without a custom encryption-key-management scheme, and the key itself
would still have to live somewhere readable by PHP. The secret is generated with
`random_bytes()`, never sent to the frontend, and is only displayed on the
`manage_options`-gated settings page. Accepted as residual risk.

---

### 8. Form handlers add no nonce of their own — Info — Accepted

**Type:** CSRF (by design).
**Location:** all `integrations/*` POST handlers (each annotated with
`// phpcs:ignore WordPress.Security.NonceVerification.Missing`).

The integration handlers read `$_POST['altcha']` (or `openporte_register`)
without verifying a nonce of their own.

**Decision — Accepted, by design.** Each handler hooks into the host flow's own
processing (`register_post`, `authenticate`, `preprocess_comment`,
`wpcf7_spam`, Gravity Forms / Elementor / Formidable field validation, …), which
already performs that plugin's CSRF handling. The OpenPorte payload is itself an
unforgeable, HMAC-signed anti-automation token — adding a separate nonce would
be redundant and, for the public login/register/comment flows, is not how
WordPress core gates those endpoints. The dispatch reads are presence/value
checks only and are fully sanitised.

---

### 9. HMAC signing key is 96-bit entropy — Low — Fixed

**Type:** Cryptographic strength / defence in depth (OWASP A02).
**Location:** `includes/core.php`, `random_secret()` (used to seed
`OpenPortePlugin::$option_secret` at activation).

`random_secret()` returns `bin2hex(random_bytes(12))` — 12 random bytes, i.e.
**96 bits of entropy**, rendered as a 24-character hex HMAC key. The CSPRNG
(`random_bytes`) is correct, but for HMAC-SHA256 the modern minimum is 128 bits
(256 ideal). 96 bits is not practically brute-forceable, so this is hardening,
not an exploitable weakness.

**Fix applied.** `random_secret()` now returns `bin2hex(random_bytes(32))`
(256-bit). Because the secret is generated only when absent (`add_option` is a
no-op when set), existing installs keep their current key and previously issued
challenges keep verifying — only fresh installs get the stronger key.

---

### 10. Formidable autoloader regex does not block path separators — Low — Fixed

**Type:** Path traversal / file inclusion (theoretical) (OWASP A03).
**Location:** `integrations/formidable.php`, `openporte_forms_autoloader()`.

```php
if ( ! preg_match( '/^OpenPorte.+$/', $class_name ) ) { return; }
$filepath = dirname( __FILE__ ) . '/formidable/' . $class_name . '.php';
if ( file_exists( $filepath ) ) { require( $filepath ); }
```

The `.+` in the guard matches any character, including `/`, `\` and `.`, so the
class name is concatenated into the include path without a path-separator check.
This is **not practically exploitable** — the value is a PHP class name supplied
by the autoload mechanism, not by request input, and PHP class names cannot
contain `/` — but it is looser than necessary.

**Fix applied.** The guard is now `^OpenPorte[A-Za-z0-9_]+$`, which rejects path
separators and dots before the class name is concatenated into the include path.

---

### 11. Unused dead code from the removed paid-SaaS path — Info — Fixed

**Type:** Attack surface / maintainability (OWASP A04 Insecure Design).
**Location:** `includes/core.php` — `flatten_post()`, `sanitize_data()`,
`remove_private_keys()`.

These public methods are defined but never called anywhere in the plugin
(confirmed by grep). They are leftovers from the removed paid-SaaS classifier,
which flattened and POSTed form data to the external API. Dead code is not a
vulnerability, but removing unreachable code shrinks the attack surface and the
maintenance burden.

**Fix applied.** The three methods were deleted. Minor caveat: they were
`public`, so third-party code could in theory have called them — unlikely, but
worth a changelog note when this ships in a release.

### 12. Inline-script JSON not hardened against `</script>` breakout — Info — Fixed

**Type:** Cross-site scripting (defence in depth).
**Location:** `integrations/custom.php`, the `wp_add_inline_script()` that exposes
`window.OPENPORTE_WIDGET_ATTRS`.

The widget attributes were encoded with `wp_json_encode()` (no flags) and printed
verbatim inside a `<script>` block. `wp_json_encode()` does not escape `<`, `>` or
`&`, so a value containing the literal `</script>` would close the script element
early and allow HTML injection.

**Not exploitable in practice:** every attribute is admin- or developer-supplied
(`challengeurl` passes through `esc_url_raw()`, which strips `<`/`>`; `strings`
comes from translations; `name` is a code literal), with no visitor-controlled
path. Recorded as defence in depth.

**Fix applied.** Encode with the script-context flags so the output cannot break
out of the `<script>` element:

```php
$attrs = wp_json_encode(
  $plugin->get_widget_attrs($mode),
  JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
);
```

### 13. Solved challenges accepted after their expiry — Medium — Fixed (1.30.0)

**Type:** Replay / weak verification.
**Location:** `includes/core.php`, `verify_solution()` and
`has_payload_fields()`.
**Present since:** ALTCHA Spam Protection 0.3.2 (the first release).

The challenge digest covers the salt and the solution number concatenated; the
signature covers the digest, so the *bytes* are signed but the *boundary*
between salt and number is not. The verifier accepted any scalar as `number`
and read the expiry only from the salt. A submitted token could therefore be
reshaped so that its signature still verified while the expiry was no longer
read, and a solved challenge stayed acceptable after it expired. With the
replay counter (1.29.0) such a token was still bounded to Replay limit uses
per counter window (4 hours for a token without expiry); with Replay limit 0,
not at all. Applies to Self-hosted and Custom mode alike, because OpenPorte
verifies Custom tokens locally. No signing secret is needed, only one solved
challenge.

**Fix applied.** `verify_solution()` accepts `number` only as a non-negative
integer or a digit-only string (`is_solution_number()`), before any hashing, so
a refused token writes no replay state. Pinned by `VerifyPrimitivesTest`
(accepted shapes, 14 refused shapes each correctly signed, integral JSON floats,
and the boundary regression for all three algorithms).

### 14. Login challenge checked only after the password — Medium — Fixed (1.30.0)

**Type:** Brute force / password-correctness oracle.
**Location:** `integrations/wordpress.php`, `integrations/woocommerce.php`.
**Present since:** ALTCHA Spam Protection 0.3.2 (the first release).

The login checks ran on `authenticate` at priority 20, after core's own
credential check registered at the same priority (and so running first). A wrong
password failed before the challenge was looked at, so password guessing cost
no proof-of-work, and a correct guess stood out by getting the CAPTCHA error
instead of "incorrect password".

**Fix applied.** Both integrations check the challenge on `wp_authenticate_user`,
which core applies after the user lookup and before `wp_check_password()`, in
both its username and email paths. A missing or invalid token is refused
whatever the password. The `authenticate` callbacks stay as a backstop for
authentication providers that skip `wp_authenticate_user`; the per-request
verify memo makes their second check free, so one login costs one use. Residual:
an unknown username still returns core's `invalid_username` before the gate —
core's own messages already disclose that. Pinned by `LoginIntegrationTest`.

### 15. Forged WooCommerce field skips login and reset checks — Medium — Fixed (1.30.0)

**Type:** CAPTCHA bypass.
**Location:** `integrations/wordpress.php`, `integrations/woocommerce.php`.
**Present since:** ALTCHA Spam Protection 1.13.1.

The WordPress handlers deferred to WooCommerce whenever WooCommerce was active
and the request carried WooCommerce's nonce field; the WooCommerce handlers
acted only on their own toggle. Nothing on `wp-login.php` verifies that field,
so with WordPress protection on and the matching WooCommerce protection off,
adding it to a `wp-login.php` login or lost-password request skipped both
checks. Since #14 is fixed, the login half mostly matters for completing a login
with known credentials; the reset half allowed reset-email spam without
proof-of-work.

**Fix applied.** Ownership is decided in one helper,
`openporte_is_woocommerce_form_submission()` (`includes/helpers.php`), which
ignores the field on `wp-login.php` — the only screen that fires `login_init`.
Both integrations route through it. Pinned by `LoginIntegrationTest`.

### 16. CoBlocks reCAPTCHA overridden while the integration is off — Medium — Fixed (1.30.0)

**Type:** CAPTCHA bypass.
**Location:** `integrations/coblocks.php`.
**Present since:** ALTCHA Spam Protection 1.23.0 (the integration's first
release).

The integration replaces CoBlocks' reCAPTCHA check with the ALTCHA one by
spoofing the reCAPTCHA keys, token and verify response (see the AGENTS.md
gotcha). It armed that spoof on every CoBlocks submission whatever the toggle
said, and answered "success" while the toggle was off. A site relying on
CoBlocks' own reCAPTCHA, with OpenPorte's CoBlocks integration off (the
default), had it silently disabled.

**Fix applied.** Each of the three steps (`render_block_data()`,
`before_form_submit()`, `verify()`) acts only while the integration is on;
`verify()` returns the original response otherwise. Pinned by
`CoblocksIntegrationTest`.

### 17. Site endpoints issue challenges in Custom API mode — Low — Fixed (1.30.0)

**Type:** CAPTCHA policy bypass.
**Location:** `includes/core.php`, `openporte_generate_challenge_endpoint()` and
`openporte_generate_challenge_ajax_endpoint()`.
**Present since:** ALTCHA Spam Protection 1.21.0, which added Custom API mode.

In Custom mode the configured backend issues the challenges, signed with the
shared secret OpenPorte verifies with. The site's own endpoints kept answering
with the same secret, so their challenges verified too: a bot could bypass
whatever the backend enforces at issuance (difficulty, rate limits, expiry
policy, statistics).

**Fix applied.** Both endpoints, and the deprecated `altcha/v1` alias, refuse
with a 403 `openporte_challenge_unavailable` error while API mode is Custom
(`openporte_challenge_unavailable()`). They stay registered, so the transport
setting still only chooses a URL. Pinned by `ChallengeTransportTest`.

### 18. Elementor field file ends the whole request — Info — Fixed (1.30.0)

**Type:** Robustness.
**Location:** `integrations/elementor/field.php`, `integrations/elementor.php`.
**Present since:** ALTCHA Spam Protection 1.4.0.

`field.php` called `die()` when Elementor Pro's field base class was missing,
ending whatever request loaded it. Only reachable through Elementor Pro's own
registration hook, so not exploitable. **Fixed:** the file returns instead, and
`openporte_register_form_field()` checks the class before requiring it.

### 19. "Custom" preset without a value saved as 0 — Info — Fixed (1.30.0)

**Type:** Configuration integrity.
**Location:** `includes/settings.php`, `openporte_sanitize_expires()` and
`openporte_sanitize_replaylimit()`.
**Present since:** OpenPorte 1.28.0 for Expiration, 1.29.0 for Replay limit.

A settings save posting `custom` without the companion number input stored
`absint('custom')` / `intval('custom')` = 0: "never expires", or replay
protection off. Only a hand-crafted `options.php` POST by a `manage_options`
user produces it. **Fixed:** the stored value is kept. Pinned by
`SettingsSanitizerTest`.

### 20. Unterminated Custom-mode salts let solved challenges outlive their expiry — Low — Fixed (1.30.0)

**Type:** Replay / weak verification.
**Location:** `includes/core.php`, `verify_solution()`;
`admin/healthcheck.php`, `openporte_evaluate_challenge_response()`.
**Present since:** ALTCHA Spam Protection 1.21.0, which added Custom API mode.

This is the original CVE-2025-68113 case, on the side OpenPorte does not
control. OpenPorte's own challenges end the salt with `&` (inherited from
ALTCHA Spam Protection 1.26.3), but in Custom mode the backend builds the salt.
A backend that does not end it with `&`, such as one built on an ALTCHA library
older than the CVE fix, issues challenges whose solved token can be reshaped so
that the signature still verifies while the salt carries a later expiry. The
`number` check of finding #13 does not stop it, because the reshaping moves
only digits. A solved challenge then stayed acceptable long after it expired,
bounded only by the Replay limit; with Replay limit 0, not at all. Found during
the final review of the 1.30.0 branch, with a proof of concept against
`verify()`.

**Fix applied.** Refusing unterminated salts outright would have made every
submission fail on sites whose backend issues them, the login form included,
from the moment 1.30.0 is installed. Instead `verify_solution()` refuses a token
whose expiry lies more than `OpenPortePlugin::$expires_horizon` (one day)
ahead. Moving even one digit onto the expiry multiplies it at least tenfold,
centuries ahead (on 32-bit PHP the parsed value saturates in January 2038,
still far past the horizon), while no genuine challenge is valid for anywhere
near a day (the self-hosted maximum is 4 hours). Staying under 30 days also
keeps every acceptable token's reuse counter alive as long as the token on the
object-cache path, whose counter lifetimes are capped at 30 days. Genuine tokens from such a backend
keep working; reshaped ones are refused. The settings-page backend check
reports an unterminated salt as a warning that names the risk and advises a
backend update, and an expiry past the horizon (a backend writing milliseconds,
whose tokens never expired before) as an error, since every submission would
now fail. Pinned by `VerifyPrimitivesTest`
(`test_an_unterminated_salt_cannot_be_given_a_later_expiry`,
`test_an_expiry_beyond_the_horizon_is_refused`) and `HealthcheckTest`.

---

## Appendix C — Coding-standards tooling (WPCS)

A `phpcs.xml.dist` ruleset and a `PHPCS` GitHub workflow run the WordPress
Coding Standards, scoped to **security and correctness** (the `WordPress.Security`,
`WordPress.DB`, `WordPress.WP`, `WordPress.PHP`, `WordPress.NamingConventions.PrefixAllGlobals`
and `WordPress.WP.I18n` rule groups) plus `PHPCompatibilityWP` (`testVersion 8.0-`).
Whole-file formatting sniffs (Yoda conditions, indentation) are intentionally
excluded — the code base inherits ALTCHA's two-space style and reformatting is out
of scope. The `base64` "obfuscation" warnings are excluded because ALTCHA payloads
are legitimately `base64(JSON)`.

The first clean run surfaced and fixed three low-risk correctness items (none a
vulnerability):

- **Loose `in_array()`** in `has_active_integrations()` and the settings select
  callback → now strict (`in_array( …, true )`). (The method has since been
  rewritten to use `array_filter()` during the spam-filter removal, and
  deprecated in 1.28.0 — see #62.)
- **File-scope globals `$plugin` / `$mode`** in `integrations/elementor.php` (and
  the equivalent hook-closure locals in `integrations/wpmembers.php`) shadowed
  WordPress's admin globals of the same name → renamed to `$openporte_plugin` /
  `$openporte_mode`.
- **"Wordpress" misspelling** corrected to "WordPress" in two comments and two
  user-facing strings.

Run locally with `composer install && vendor/bin/phpcs`; `vendor/bin/phpcbf`
applies the auto-fixable subset.

---

## Appendix A — WordPress Plugin Security Guidelines coverage

Mapped against <https://developer.wordpress.org/plugins/security/>.

| Guideline | Status | Evidence |
| --------- | ------ | -------- |
| Sanitize inputs | Pass | `sanitize_text_field( wp_unslash( … ) )` on every `$_POST` read; no `$_GET`/`$_REQUEST`/`$_COOKIE`/`$_SERVER` reads (grep-confirmed) |
| Validate data | Pass (hardened) | Token validation added in finding #3; every `register_setting` has a `sanitize_callback` |
| Escape output | Pass | `esc_html()`/`esc_attr()` on admin pages; `wp_kses()` with the `$html_espace_allowed_tags` whitelist for all widget HTML |
| Nonces / CSRF | Pass / by design | Save path uses the Settings API nonce; public form handlers delegate to the host flow (finding #8) |
| Capability checks | Pass | `add_options_page( …, 'manage_options', … )`; Settings API enforces the cap on save; comment handler skips for `manage_options` |
| Avoid direct file access | Pass | `ABSPATH` (or `WP_UNINSTALL_PLUGIN`) guard at the top of every PHP file |
| Prepared SQL | Pass | Only direct query is `uninstall.php`, using `$wpdb->prepare()` + `$wpdb->esc_like()` |
| Secure REST endpoints | Pass (permissive by design) | `permission_callback` present; intentionally public for challenge generation (finding #5). The admin-ajax twin (since 1.30.0) is public by the same design |
| No dynamic file inclusion from input | Pass | All `require`/`include` are static literals; the one variable include is the guarded Formidable autoloader (finding #10) |
| No `eval`/`system`/`unserialize`/`extract` | Pass | grep-confirmed absent |
| Don't trust proxy/`$_SERVER` headers | Pass | No `$_SERVER` reads; `get_ip_address()` removed in 1.27.0 |

## Appendix B — OWASP coverage

OWASP Top 10 (2021):

| Category | Status | Notes |
| -------- | ------ | ----- |
| A01 Broken Access Control | Pass | Admin gated by `manage_options`; the only public surface (the challenge endpoint, over REST and, since 1.30.0, admin-ajax) is intentional and exposes no secret |
| A02 Cryptographic Failures | Pass (note) | `random_bytes`/`random_int` CSPRNG, HMAC-SHA256, constant-time `hash_equals`; key length is finding #9 |
| A03 Injection | Pass | SQL prepared; no command/code injection sinks; output escaped; autoloader note in finding #10 |
| A04 Insecure Design | Pass (note) | Stateless-PoW replay is **bounded since 1.29.0** (finding #1) — an atomic reuse counter in `verify()`, with the residuals recorded there; dead code in finding #11 |
| A05 Security Misconfiguration | Pass (note) | Secure defaults; no debug output; PoW complexity has no seeded default → falls to the 100–10000 range (consider defaulting to medium/high) |
| A06 Vulnerable & Outdated Components | Monitor | Vendored `public/altcha.min.js` (widget 2.3.0) — track upstream advisories on re-vendor |
| A07 Identification & Auth Failures | Pass | Delegates to WP/WooCommerce auth; adds an anti-automation layer, does not weaken auth |
| A08 Software & Data Integrity Failures | Pass | Uses `json_decode` (not `unserialize`); submitted tokens are HMAC-signed and verified |
| A09 Security Logging & Monitoring | Note | No built-in logging; operators can hook the `openporte_verify_result` action |
| A10 SSRF | Pass (note) | One server-side fetch, added in 1.28.0: the custom-endpoint health check `wp_remote_get()`s the configured Challenge URL (`admin/healthcheck.php`). Operator-supplied (`manage_options`), never visitor-supplied, and only on OpenPorte's own settings screen. Deliberately **not** `wp_safe_remote_get()` — private-network backends (a LAN host or NAS running e.g. GateCHA) are a primary use case that the "safe" variant blocks. No other `wp_remote_*`/`file_get_contents`/cURL of an external URL (grep-confirmed) |

OWASP PHP cheat-sheet spot checks:

- **Type juggling:** security-critical comparisons use strict `===` (algorithm,
  challenge) and `hash_equals` (signatures); the `verified` check uses
  `in_array( …, true )`. No loose `==` in a security decision. The challenge
  digest moves to `hash_equals` in v1.29.0 for uniformity — see Appendix D and
  [#84](https://github.com/openporte/openporte/issues/84).
- **Error handling / info leak:** finding #3 removes the PHP warnings that junk
  tokens used to emit, reducing noise/leak in logs.
- **File uploads / sessions:** none — the plugin handles no uploads and sets no
  cookies/sessions.

---

## Appendix D — External advisory cross-check: SecuPress, ALTCHA v2 ≤ 2.2 (2025-12-03)

On 2025-12-03 SecuPress published nine findings against **Altcha GDPR Compliant
Captcha and Bot Protection ≤ 2.2**
(<https://secupress.me/blog/altcha-2-2-multiple-vulnerabilities/>).

**Scope note, and the reason most rows below read "N/A":** that advisory targets
the **closed-source v2** product from altcha-org, which is a different code line
from the open-source v1.x plugin OpenPorte forked. Seven of the nine findings
describe subsystems v2 added and v1 never had — a license check, an "under
attack" mode, IP bypass lists, cookie exceptions, `get_edk()`, `rate_limit()`,
and the `includes/admin/actions.php` admin-AJAX surface. "N/A" here means *this
code does not exist in OpenPorte*, not *we looked and it was fine*. Only findings
5 and 6 touch logic the two lines share by ancestry; both were already fixed
independently in this audit before the advisory was read.

| # | SecuPress finding (v2 ≤ 2.2) | OpenPorte | Evidence |
| - | --------------------------- | --------- | -------- |
| 1 | IDOR — no nonce in `includes/admin/actions.php` | N/A | No `wp_ajax_*`/`admin_post_*` handler existed at the cross-check (grep-confirmed). Since 1.30.0 the only one is the public, read-only challenge action (finding #5): it writes no state, so the IDOR/CSRF class does not apply. Admin writes go through the Settings API — `settings_fields( 'openporte_options' )` in `admin/options.php` supplies the `options.php` nonce, and the page is registered with `manage_options` in `includes/admin.php` |
| 2–4 | 3× CSRF on the "set" functions | N/A | Same absent surface. The settings involved (license, IP bypass, cookie exceptions, under-attack) do not exist here |
| 5 | Timing side-channel — `===` instead of `hash_equals()` | **Fixed** | `hash_equals` guards every HMAC comparison: `verify_server_signature()` and `verify_solution()` in `includes/core.php`, plus the health check in `admin/healthcheck.php`. See the note below on the remaining non-secret `===` |
| 6 | Insufficient entropy #1 — 12-byte (96-bit) secret | **Fixed** — finding #9 | `random_secret()` is `bin2hex( random_bytes( 32 ) )`. Independently found and fixed here before the advisory was read. Residual for upgraded installs: see the closing note |
| 7 | Insufficient entropy #2 — `get_edk()` predictable IP/UA hash | N/A | No such function, and no IP- or UA-derived identifier anywhere: the plugin reads no `$_SERVER` at all |
| 8 | Race condition — non-atomic transient `rate_limit()` | N/A | No rate limiter exists to race. See the note below on the adjacent live item |
| 9 | IP spoofing via `X-Forwarded-For` | N/A | `get_ip_address()` was removed in the 1.27.0 paid-SaaS removal; no `$_SERVER`, `REMOTE_ADDR` or `HTTP_X_FORWARDED_FOR` read remains (grep-confirmed, Appendix A) |

Two points recorded so a future reader does not re-open them from the table
alone:

- **The remaining `===` is not finding 5.** `verify_solution()` still compares
  the challenge digest with `===` (`$data->challenge === $calculated_challenge`),
  as does the `$data->algorithm` label check. Neither operand is secret — both
  are recomputable by the submitter from the `salt` and `number` carried in
  their own token — so nothing leaks through the comparison's timing. The
  digest comparison is nonetheless moving to `hash_equals` for uniformity in
  v1.29.0, tracked in
  [#84](https://github.com/openporte/openporte/issues/84); the algorithm label
  stays `===`, being a public identifier rather than a digest.
- **Finding 8's impact does not transfer.** OpenPorte's unauthenticated
  challenge endpoint is a DoS/abuse surface (finding #5, accepted), not a
  verification bypass: there is no rate limit for a parallel request to defeat,
  and issuing extra challenges grants nothing — each still has to be solved and
  its HMAC verified. Per-IP throttling remains rejected on privacy grounds.
  **Its *lesson* did transfer, though:** a non-atomic transient counter is
  exactly what v1.29.0's replay counter must not be, and is why that counter is
  a cache `INCR` or a single row-locked `UPDATE` rather than a
  read-check-write (finding #1).

**CVE-2025-68113 (ALTCHA salt splicing) — cross-check.**

| Advisory | OpenPorte | Evidence |
| -------- | --------- | -------- |
| CVE-2025-68113 — a solved token's `expires` could be edited because the salt's query string was not terminated, letting a crafted secret number splice a later expiry onto it | **Self-hosted: not affected by this transformation.** Custom mode with a backend whose salts lack the delimiter: affected until 1.30.0 (finding #20). A related transformation was fixed in 1.30.0 (finding #13) | `generate_challenge()` appends a trailing `&` to the salt, terminating the query string, and `expires` lives inside the salt that the challenge hashes and the signature covers: editing it breaks the challenge digest. Pinned by `tests/phpunit/VerifyPrimitivesTest.php` (`test_editing_the_salt_expiry_breaks_the_challenge`, `test_appending_a_parameter_to_the_salt_breaks_the_challenge`). The delimiter does not stop the salt/number boundary from being moved the other way; until 1.30.0 that could drop the expiry entirely (finding #13). Since 1.30.0 `verify_solution()` accepts only a digit-only `number`, pinned by `test_moving_the_expiry_out_of_the_salt_is_refused`. That leaves the original transformation for Custom-mode backends whose salts lack the `&`; since 1.30.0 an expiry more than a day ahead is refused, which closes it (finding #20) |

This matters more since 1.29.0 than it did before: the replay counter's lifetime
is derived from `expires`, so a token whose expiry could be forged forward would
also carry its reuse budget forward.

**Residual on finding 6.** The 256-bit key seeds *new* installs only:
`add_option()` is a no-op once the row exists, so a site upgraded from ALTCHA
1.x or early OpenPorte still holds its original 96-bit secret. That is
deliberate — silently rotating would fail every challenge issued in the
preceding expiry window. The remedy is a manual one, tracked in
[#70](https://github.com/openporte/openporte/issues/70) (Copy/Regenerate actions
on the Shared Secret field, v1.29.0). 96 bits is not practically
brute-forceable, so this is hardening rather than exposure.

---

## Maintainer decisions on record

- **Finding 1 (replay / one-time-use) — reversed in v1.29.0.** Through 1.28.1
  this was an accepted limitation: no behavioural change, to avoid the
  resubmission false-rejection risk. v1.29.0 fixes it with *bounded* reuse
  rather than strict single-use, which is what resolves that objection — a
  default of 5 leaves room for a visitor to resubmit after an unrelated form
  error while cutting amplification from unbounded to five. Three further calls
  on record:
  - **Atomic from the first release.** An earlier draft shipped a non-atomic
    transient counter in 1.29.0 and made it atomic in 1.29.1. Rejected: the
    lost-update window is precisely the parallel burst a replay attack
    produces, so a non-atomic counter would fail exactly when it mattered.
  - **Expiry stays advisory in 1.29.0.** `0` ("None") and values under 60 s are
    warned about, not rejected or migrated. Safe to do because the counter now
    bounds replay independently of the expiry; hard bounds are a breaking-config
    change and belong to [#103](https://github.com/openporte/openporte/issues/103).
  - **Local, never delegated.** Replay protection is OpenPorte-local even in
    custom-backend mode. Delegating it would mean calling the backend's verify
    API at submit time — something the plugin does not do today — and is a
    separate release ([#104](https://github.com/openporte/openporte/issues/104)).
    A bare "Off" is deliberately not offered: an operator who disabled local
    protection believing the backend covered it would have none at all.
- **Finding 5 (REST rate limiting):** documented as accepted risk; per-IP
  throttling rejected because it conflicts with the no-visitor-IP privacy
  promise. Handle upstream if needed.
- **SecuPress ALTCHA v2 advisory (reviewed 2026-07-28):** cross-checked in
  Appendix D. No plugin code change required — the two applicable findings were
  already fixed, the other seven target v2-only code. The legacy 96-bit secret
  on upgraded installs is not auto-rotated; admins get a manual Regenerate
  action instead ([#70](https://github.com/openporte/openporte/issues/70)). The
  non-secret challenge `===` is tracked separately as uniformity work
  ([#84](https://github.com/openporte/openporte/issues/84)), not as a security
  fix.
