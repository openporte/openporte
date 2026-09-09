# Architecture

> Maintainer / contributor reference. User-facing essentials (how to choose a
> mode, the privacy stance) live in `readme.txt`; coding conventions live in
> `AGENTS.md`. This document reflects the codebase as of **1.28.0** — after the
> paid-SaaS removal and the OpenPorte rebrand (1.27.0), and after the
> spam-filter removal and the configurable challenge settings (1.28.0).
>
> Naming note: the plugin class (`OpenPortePlugin`), the DB option keys, the
> public hooks, the REST namespace and the text domain were moved to the
> `openporte` namespace in 1.27.0, with the old `altcha_*` / `AltchaPlugin`
> names kept as deprecated aliases. Some internal function and settings-field
> identifiers still carry the `altcha_` prefix pending a follow-up cleanup.
> References are by function name rather than line number on purpose — line
> numbers in this codebase have already shifted several times.

## Overview and modes

The plugin operates in two modes, both fully self-contained with no external
service dependency. A third mode — a paid SaaS classifier hosted on
`altcha.org` — was removed.

- **`selfhosted`** (default): proof-of-work. Challenges are served by a
  WordPress REST endpoint at `wp-json/openporte/v1/challenge`. No API key, no
  external service, no account.
- **`custom`**: the challenge URL points to a backend the site operator runs
  themselves. This is the legitimate self-hostable backend path — it is *not* a
  paid or remote service.

The mode is selected via the `altcha_api` option. In `get_challengeurl()`,
`custom` returns the operator-supplied URL stored in `altcha_api_custom_url`;
any other value — including legacy `"eu"` / `"us"` values left in the database
by old installs — falls back to the local REST endpoint.

### What Custom mode actually does — and what it does not

Easy to get wrong, so stated plainly (confirmed against source while designing
the 1.29.0 replay work):

- **Custom mode is verified locally.** The widget fetches a *standard
  proof-of-work challenge* from the operator's backend and solves it in the
  browser; OpenPorte then verifies that solution **itself**, with
  `verify_solution()` and the shared secret. There is **no server-to-server call
  at verify time** — the plugin makes exactly one outbound request anywhere, the
  settings-page health check (see `grep wp_remote_*`). A backend's own
  verify-and-consume endpoint (e.g. GateCHA's `/api/v1/verify`) is never called,
  so whatever state that backend keeps is irrelevant to what OpenPorte accepts.
  This is why replay protection has to be OpenPorte-local; see
  [`docs/security-audit.md`](security-audit.md), finding #1.
- **The backend owns the expiry in Custom mode.** It embeds `expires` in the
  salt of each challenge it serves, and that is the value `verify_solution()`
  enforces. OpenPorte's own **Expiration setting is inert** there — since 1.29.0
  the field is rendered disabled, and the health check reports the backend's
  expiry (and warns when the backend sets none) instead.
- **`verify_server_signature()` is dormant.** It handles the ALTCHA Sentinel
  `verificationData` payload, which arrives only via a `verifyurl` widget
  attribute that OpenPorte never renders (`wp_kses` strips it) and the vendored
  widget never emits. It remains a functional back-compat path, but nothing in
  OpenPorte's own surfaces reaches it.

**Which settings are active in which mode:**

| Setting | `selfhosted` | `custom` |
| ------- | ------------ | -------- |
| Challenge URL | — (built from the REST route) | **active** |
| Shared secret | active (signs and verifies) | active (must match the backend's) |
| Algorithm | active (generation + verification) | active — must match what the backend serves |
| Complexity | active | — (the backend sets the difficulty) |
| Expiration | active | **inert** — the backend embeds it in the salt |
| Replay limit | active | **active** — enforcement is local in both modes |
| Widget settings (auto, floating, delay, logo/footer) | active | active |

### Challenge tuning (1.28.0)

Three properties of the proof-of-work challenge became configurable in 1.28.0.
All three are read through accessors on the singleton, so a caller never touches
the option directly:

- **Algorithm** — `get_algorithm()`, validated against
  `get_allowed_algorithms()` (`SHA-256`, `SHA-384`, `SHA-512`; the ALTCHA spec's
  permitted set, mirrored server-side because the widget's own copy is a
  module-scope constant unreachable from PHP). It falls back to **SHA-256** when
  unset or invalid: every release before 1.28 hardcoded it, so upgraded sites
  keep verifying challenges already in flight and minimal custom backends keep
  working. New installs are seeded with **SHA-512** at activation.
  `hash_ident()` maps the label to the PHP `hash()` identifier.
- **Expiration** — `get_expires()`. Presets plus a custom value, clamped to
  0–14400 seconds, where `0` means no expiry and 14400 (4 hours) is the
  historical maximum. `get_expires()` returns the stored value **unmodified**,
  including `0`: substituting a finite value at generation time would make a
  saved `0` cosmetic — the settings page would read "never expires" while the
  plugin quietly minted expiring challenges. Since 1.29.0, `0` and values under
  60 s raise an advisory instead (a `_doing_it_wrong` at sanitize time and an
  admin notice on the settings page); neither is rejected or migrated, which the
  reuse counter makes safe. Hard bounds are a later, breaking-config release.
- **Complexity** — `get_complexity()` selects a low/medium/high band from
  `get_complexity_matrix()`, which is filterable via
  **`openporte_complexity_matrix`** so a site can retune the ranges. A `low`
  entry must always exist.

### Replay limit (1.29.0)

A fourth knob, and the one that made the expiry advisory-only rather than
enforced: **`get_replaylimit()`** reads `openporte_replaylimit` (default **5**,
range 0–100, `0` = unlimited) and bounds how many times one solved challenge is
accepted. It is filterable via **`openporte_replay_limit`**, which receives the
current hook name as context so a site can be stricter on login than on comments
without touching any call site; the return value is re-clamped, so a filter that
returns nonsense cannot silently switch protection off. Enforcement lives in
`verify()` — see "Stateless primitives, stateful wrapper" below, and
[`docs/replay-protection.md`](replay-protection.md) for the full design: the
reasoning behind the mechanism, the residuals it accepts, the alternatives that
were rejected, and how much of it has been proven.

**Verification Delay is not part of this, and is not a security control.** The
`openporte_delay` setting is emitted only as a client-side widget attribute; the
widget applies it as a browser `setTimeout` *before* it fetches and solves the
challenge, and no PHP path sleeps (`verify()` never reads the setting; there is
no `sleep`/`usleep` in the plugin). A replayed token is a bare HTTP POST, so
there is nothing to skip, and a bot solving the proof-of-work itself bypasses it
just as completely. It is a perception knob — a visible pause reads as work
being done — and must never be counted as defence in depth. Complexity is the
setting that actually raises the cost for bots.

When API mode is `custom`, `admin/healthcheck.php` fetches one challenge from
the configured Challenge URL as the settings page loads and reports the outcome
as an admin notice — unreachable endpoint, non-ALTCHA response, unsupported
algorithm, signing-secret mismatch, or a backend algorithm differing from the
configured one. One request validates all three settings, because an ALTCHA
challenge declares its own algorithm and `hmac(algorithm, challenge, secret)`
must equal the served signature. The result is cached in a short transient keyed
on (url, secret, algorithm), so the check re-runs right after a save but does
not hammer the backend on every page load.

Since 1.29.0 the same file carries two more settings-page checks, and the
endpoint probe reads one more thing:

- The endpoint probe also parses the served **salt for a future `expires`** and
  warns when there is none (challenges that never time out) or when it is under
  a minute (likely to expire before a slow device finishes solving). This is the
  only place OpenPorte can see a misconfigured backend *before* visitors are
  affected.
- In self-hosted mode, the **Expiration value** raises a red error-style notice
  at `0` and a warning below 60 s. Advisory only — the save still goes through.
- **Replay protection status**: the configured limit, whether the counter is
  backed by the persistent object cache or the database, and any fail-open
  episode in the last day. Without it, a site whose counter store has broken
  would discover the degradation only by listening for
  `openporte_replay_store_unavailable`.

Each check is split into a pure `openporte_evaluate_*()` function returning
`{level, message}`, with the notice rendering kept separate, so the logic is
unit-testable without an admin screen.

## Code map

Files are listed in roughly the order `openporte.php` requires them. Each
integration file self-registers its hooks at require time and no-ops unless its
`get_integration_*()` option is set.

```text
openporte.php              Entry point + plugin header. Defines constants, requires
                           every file in load order, registers the [openporte]/[altcha]
                           shortcodes, the activation + legacy-option migration hooks,
                           and the REST route.
uninstall.php              Deletes all openporte_* options on plugin deletion.
includes/
  core.php                 OpenPortePlugin singleton — shared logic: option accessors,
                           challenge generation, HMAC verification, widget HTML
                           rendering, the wp_kses attribute whitelist.
  helpers.php              Enqueue helpers + openporte_plugin_active() detection.
  admin.php                Admin menu (Settings → OpenPorte Anti-spam); requires
                           admin/healthcheck.php.
  settings.php             Settings API: option registration + sanitize callbacks,
                           sections and fields.
  index.php                Silence-is-golden guard.
admin/
  options.php              Settings-page HTML + the field/select render callbacks.
  healthcheck.php          Settings-screen checks surfaced as admin notices:
                           the Custom-mode Challenge URL probe (transient-cached),
                           the Expiration advisory, and replay-protection status.
                           See "Challenge tuning".
  index.php                Silence-is-golden guard.
integrations/              15 integration files (each self-registers its hooks):
  wordpress.php            WP login / register / comments / reset-password.
  woocommerce.php          WooCommerce login / register / reset-password.
  contact-form-7.php       Contact Form 7 (wpcf7_spam).
  gravityforms.php + gravityforms/{addon.php, field.php}    GF add-on + custom field.
  elementor.php  + elementor/field.php                      Elementor Pro custom field.
  formidable.php + formidable/{OpenPorteFieldType.php, builder-field.php, builder-settings.php}
                           Formidable custom field (autoloaded class).
  forminator.php           Forminator.
  wpforms.php              WPForms.
  coblocks.php             CoBlocks (intentional reCAPTCHA spoof — see Invariants).
  html-forms.php           HTML Forms.
  enfold-theme.php         Enfold theme contact / Mailchimp forms.
  wpdiscuz.php             wpDiscuz (renders into the WP comment flow).
  wpmembers.php            WP-Members (reuses the WP-registration option).
  custom.php               Shortcode / manual mode: enqueues the widget + its options.
  index.php                Silence-is-golden guard.
public/
  altcha.min.js            Vendored upstream ALTCHA web component (DO NOT EDIT; version =
                           OPENPORTE_WIDGET_VERSION). See "The vendored widget".
  altcha.min.js.sha256     Body-only SHA-256 of the vendored bundle, written by
                           `npm run altcha:update` and checked offline by
                           `npm run altcha:verify`. Excluded from the shipped zip.
  altcha.js                Comment-only companion (no executable code).
  altcha.css               Widget wrapper styles.
  script.js                Front-end: fixes the checkbox name attr; removes duplicate
                           widgets via a MutationObserver; holds and replays submit
                           clicks that race an in-flight proof-of-work solve.
  custom.js                Configures the widget from window.OPENPORTE_WIDGET_ATTRS.
  admin.js                 Settings-page toggle (enables Challenge URL in custom mode).
  admin.css                Settings-page styles.
  widget.php               Adds `async defer type="module"` to the widget <script> tag.
  index.php                Silence-is-golden guard.
languages/                 29 locales (.po/.mo) + openporte.pot. Workflow: docs/agents/i18n.md.
docs/                      Maintainer docs: architecture.md, security-audit.md,
                           replay-protection.md, maintenance-testing.md,
                           release-preparation.md, agents/, acceptance/.
bin/release/               Release tooling (npm run release:*): version, check, dist,
                           tag, i18n, WordPress.org asset sync, plus altcha-update.sh /
                           altcha-verify.sh for re-vendoring the widget.
tests/
  bin/                     Manual wp-env bench helpers (wp-init.sh and friends).
  e2e/                     Playwright settings-matrix suite (not shipped) — see
                           docs/maintenance-testing.md.
.github/workflows/         publish.yml (tag → WordPress.org SVN) + phpcs.yml, phpmd.yml,
                           phpstan.yml (static analysis → code scanning).
phpcs.xml.dist             WordPress Coding Standards config.
phpmd.xml.dist             PHPMD ruleset.
phpstan.neon.dist          PHPStan config.
.wordpress-org/            WordPress.org banner / icon / screenshot assets.
```

## Verification dispatch

Verification is dispatched on the **shape of the decoded payload**, not the
configured mode. This distinction matters: changing the mode does not change how
a challenge is verified. In `verify()`, the plugin decodes the submitted token
via `decode_payload()` — a strict `base64_decode` + `json_decode` that returns
`null` for anything malformed, so junk submissions fail closed without emitting
PHP warnings. A valid object carrying a `verificationData` field is routed to
`verify_server_signature()`; otherwise to `verify_solution()` for proof-of-work.
Each method re-checks that the fields it needs are present before using them.

`verify_server_signature()` checks the HMAC signature against the site secret
(`get_secret()`), then parses `verificationData`. It returns `true` only when the
signature is valid, the payload is unexpired (`expire`, when present) and
explicitly verified (`verified`, when present). The `expire`/`verified` checks
mirror the ALTCHA reference implementation and are applied defensively — only
when the backend actually supplies the field — so minimal custom backends keep
working. It no longer inspects a `classification` field: the spam-filter
plumbing was removed in 1.28.0 (see below).

`verify_solution()` performs proof-of-work verification: it validates the
challenge hash, its signature, and expiration, returning `true` only if all
checks pass.

The site secret is generated once at activation by `random_secret()` as a
256-bit key (`bin2hex(random_bytes(32))`), stored in `openporte_secret`, and
never regenerated for an existing install (so previously issued challenges keep
verifying). The full security review of this path is in
[`docs/security-audit.md`](security-audit.md).

### Stateless primitives, stateful wrapper (1.29.0)

Since 1.29.0 the split above is load-bearing rather than incidental. The two
primitives are **pure cryptography and hold no state**; all policy lives in
`verify()`, which is the **sole supported entry point**. Both primitives are
deprecated for direct external calls (`_deprecated_function`, removal scheduled
for 2.0) — a direct call skips the policy and accepts an unbounded token. The
notice is guarded by a private `$in_verify` flag, so it fires for third-party
callers and never for the internal dispatch.

`verify()` runs, in order:

1. **Payload memo** — a per-request cache keyed on the submitted bytes and the
   HMAC key.
2. **Decode and dispatch** to one primitive.
3. **Signature memo** — a second per-request cache keyed on the *verified*
   signature, so the same solved challenge re-encoded into different JSON bytes
   still counts once.
4. **`enforce_replay_limit()`** — only on full cryptographic success.
5. **One `openporte_verify_result` action** per `verify()` call.

The memo makes verification idempotent within one request: repeated third-party
calls, future integration paths, and re-encoded JSON envelopes still cost one
use. No shipped integration currently verifies twice in one request; the
WordPress and WooCommerce `authenticate` callbacks are mutually exclusive
through their nonce guards. The memo is cleared on `init` by
`reset_request_state()`, so persistent-worker SAPIs (FrankenPHP, RoadRunner,
Swoole) cannot leak one visitor's accepted token into the next visitor's
request.

The reuse counter is keyed on the token's HMAC-verified `signature` (hashed),
never on the raw payload, which a replay can re-encode at will. Its lifetime is
the token's own remaining validity — read through the shared `payload_expires()`
helper that also feeds the crypto gate — with a 60-second floor and a 4-hour
fallback for a token carrying no expiry at all. The database backend has no
ceiling. Persistent object-cache TTLs are capped at 30 days because Memcached
treats a higher value as an absolute Unix timestamp; a longer-lived token is
therefore bounded per 30-day window instead of becoming silently unlimited.
Storage is an atomic consume on existing infrastructure: `wp_cache_incr()`
where a persistent object cache is present, otherwise a transient-shaped
`wp_options` row pair claimed with a guarded `INSERT IGNORE` and spent with a
guarded `UPDATE` that InnoDB row-locks. No schema, no cron — WordPress's own
transient garbage collection reclaims the rows. State is written **only after**
cryptographic success, so junk and forged tokens never create any and the open
REST challenge endpoint stays stateless. A store that cannot count **fails
open** (the submission is accepted) but fires
`openporte_replay_store_unavailable` and is reported on the settings page.

The reasoning behind each of these choices — including the ones that look
arbitrary until you know what they are guarding against — is written up in
[`docs/replay-protection.md`](replay-protection.md).

**Invariant — CVE-2025-68113.** The counter's lifetime derives from `expires`,
so `expires` must remain bound by the signature. It is: the signature covers the
challenge, the challenge covers the salt, and `expires` lives in the salt, so
editing it breaks the challenge digest. The trailing `&` that
`generate_challenge()` appends terminates the query string so a crafted secret
number cannot splice an extra parameter onto it. Never sign anything the
challenge does not cover, and never drop that delimiter.

## What was removed, and why

The paid `altcha.org` regional SaaS classifier was removed to keep the plugin
free and self-hosted, with no dependency on external services. Removed:

- The regional SaaS modes (`eu` / `us`) and their API-key requirement
- `$option_api_key` and `get_api_key()`
- The regional branch of `get_challengeurl()` that built URLs to
  `https://{region}.altcha.org`
- `spam_filter_check()` and `spam_filter_call()`, which POSTed submissions to
  `https://{region}.altcha.org/api/v1/classify`
- `$option_send_ip`, the `$hostname` property, and `get_ip_address()`
- `flatten_post()`, `sanitize_data()` and `remove_private_keys()` — helpers that
  flattened and sanitised form data for the classifier POST. They had no callers
  after the SaaS removal and were deleted in the security-hardening pass (see
  [`docs/security-audit.md`](security-audit.md), finding #11).

None of these symbols exist in the current codebase. The verification dispatch
(payload-shape, not API mode) was deliberately preserved; the security-hardening
pass only *added* checks (`expire`/`verified`, strict payload decoding) without
changing how a valid or invalid challenge is routed.

**1.28.0** finished the job by removing the consumer-side spam-filter plumbing
that outlived the SaaS it was built for — see "Spam filter — removed in 1.28.0"
below. The same release deprecated (but kept) the "Custom HTML" integration and
the integration-listing helpers `get_integrations()` /
`has_active_integrations()`, which lost their only caller in 1.21.0; both are
slated for removal at the next major.

## Spam filter — removed in 1.28.0

**The plugin has never provided a spam classifier.** The classification engine
was a hosted ALTCHA service (commercial successor: Sentinel) and was never
open-source.

Until 1.28.0 the codebase still carried consumer-side plumbing that acted on
classification data if a `custom` backend supplied it. It had no effect in
`selfhosted` proof-of-work mode — no classification is produced there — so it
was dead weight for every supported configuration, and it was removed in 1.28.0
(#6). Verification now rests on the proof-of-work challenge and the HMAC
signature alone.

Gone, and not to be reintroduced without a classifier to justify them:
`$spamfilter_result`, `get_blockspam()`, `$option_blockspam`, the
`blockspam` / `spamfilter` widget attributes (also dropped from the `wp_kses`
whitelist), the classification branch in `verify_server_signature()`, and the
Gravity Forms branch that read `classification` / `score` / `reasons`.

## Privacy stance

In both supported modes the plugin requires no API key, makes no calls to
external paid services, collects no visitor IP address (`get_ip_address()` was
removed), sets no cookies, and performs no tracking.

## The vendored widget

`public/altcha.min.js` is the upstream ALTCHA widget, vendored as-is under the
MIT license. Its behavior is documented separately from this plugin's PHP.

The following is **upstream widget behavior**, established from an audit of the
widget source rather than from this repository's PHP: the widget enforces its
own attribution — ignoring `hidefooter` / `hidelogo` — only when it detects
"free SaaS" usage, i.e. a challenge URL on `*.altcha.org` carrying
`apiKey=ckey_`. Because this plugin never produces such a URL, `hidefooter` and
`hidelogo` always take effect in this plugin's context.

**Durability across a widget upgrade.** The replay design keys on *protocol*
fields — the verified `signature`, and `expires` inside the salt — not on any
widget behaviour, so it does not add a new coupling to the bundled version:
"survives the upgrade" reduces to "`verify()` survives the upgrade". Whether the
**v3 widget** still solves the classic challenge format OpenPorte generates is a
separate, open question (v3.0.0 is a rewrite around a new proof-of-work
mechanism, and `altcha-lib` v2 introduces a new challenge format); it is tracked
with the widget upgrade itself, not here. Either way the counter still has a
valid key, because both formats produce a challenge and an HMAC signature.

## Invariants for future maintainers (and AI agents)

These guard against mistakes that have actually been made while working on this
code:

- **`custom` mode is not the paid SaaS.** Do not remove it. It is the legitimate
  self-hostable backend path and is load-bearing for real users (e.g. operators
  running their own ALTCHA-compatible challenge server such as [GateCHA](https://gatecha.org/)).
- **The verification dispatch keys on payload shape, not on the API mode.** Any
  change to mode handling must not alter how a valid or invalid challenge is
  verified — breaking this breaks every protected form.
- **`verify()` is the only place policy lives.** The two primitives must stay
  stateless and pure; the memo and the reuse counter belong in `verify()`. Move
  enforcement into a primitive and the other path silently loses it.
- **The reuse counter must stay atomic.** A read-check-write counter loses
  updates under exactly the parallel burst a replay produces. Use the cache
  `INCR` or the single row-locked `UPDATE`; in particular `add_option()` is
  **not** a create-only mutex (core implements it as
  `INSERT … ON DUPLICATE KEY UPDATE` behind a cached existence check).
- **State is written only after cryptographic success.** Counting before
  verification would let unauthenticated junk create rows, turning the open
  challenge endpoint into a write amplifier.
- **The counter should cover the token's remaining lifetime** — its TTL comes
  from the same `payload_expires()` the crypto gate uses. The only ceiling is
  the persistent-cache backend's portable 30-day maximum; without it Memcached
  expires longer TTLs immediately, silently resetting the budget on every use.
- **`expires` must stay covered by the signature** (CVE-2025-68113): keep the
  trailing `&` in `generate_challenge()`, and never sign anything the challenge
  does not hash.
- **The counter fails open, deliberately.** A broken store must degrade to
  pre-1.29 behaviour, not lock visitors out — but it must stay observable
  (`openporte_replay_store_unavailable` plus the settings-page report).
- **Do not reintroduce any external-service dependency** (API keys, regional
  endpoints) **or visitor-IP collection.** "No external service" is a core
  promise of the fork.
- **Do not edit or rename `public/altcha.min.js`.** It is the vendored upstream
  widget; treat it as a third-party dependency. The MIT license permits
  modification, but edits would be lost when the widget is re-vendored on
  upgrade, and changing it is out of scope.
