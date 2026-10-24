# Acceptance criteria

Acceptance record for **v1.30.0**. Run the automated checks first, then the
manual wp-env steps. Tick the boxes as evidence while performing the release
validation (Phase 5 of `docs/release-preparation.md`).

Recorded 2026-10-24 on the `release/1.30.0` branch, ceiling bench PHP 8.5.12 /
WordPress 7.1.3 (MariaDB, no persistent object cache) and floor bench PHP
8.0.30 / WordPress 5.6, each started from `./wp-env.sh cleanup --force`.
Unticked boxes were **not run** this cycle; the note says why.

## Automated checks

1. [X] `npm run release:check` — `php -l`/`bash -n` pass (blocking); `phpcs`/`phpstan`
       output reviewed, no unexplained new findings (informative)
2. [X] PHPUnit unit suite (`npm run test:unit`) **green**, no skipped or risky tests
       (198 tests, 436 assertions)
3. [X] E2E suite (`cd tests/e2e && npm test`, see `tests/e2e/README.md`) **all
       green** on the ceiling bench: 86 tests — the settings matrix for every
       driver with its negative controls, the replay-limit suite, the
       shared-secret controls, the wp-login.php login gate (`login-gate.spec.js`)
       and the settings page (`admin-settings.spec.js`)
4. [X] Settings matrix over the Ajax transport (`npm run test:ajax`): 61 tests green
5. [X] Floor bench (PHP 8.0 / WP 5.6): the same suite minus wpDiscuz (no version
       runs on WP 5.6, #80) — 76 tests green. Integration plugins at the versions
       listed in `tests/bin/wp-init.sh` for the floor (Contact Form 7 5.4.2,
       WooCommerce 6.2.3, WPForms Lite 2.0.0.2, Formidable 6.25.1, Forminator
       1.33.0, HTML Forms 1.6.4), installed from their versioned zip URLs

## wp-env manual test steps

Tests **(a)**, **(b)** and **(c)** are our regression tests.

**(a) Self-hosted form submits and verifies**

1. [X] wp-env start
2. [X] Activate the plugin; confirm Settings → OpenPorte Anti-spam shows "API Mode:
       Self-hosted" by default
3. [X] Enable an integration (e.g. WordPress → Comments)
4. [X] Submit a comment with the OpenPorte widget — confirm the entry is created; no PHP
       errors in wp-env logs (E2E settings matrix, core comments driver)
5. [X] No outgoing network requests to altcha.org: the widget on the Test Page
       (`[altcha]` and `[openporte]`) solved with zero requests to an `altcha.org`
       host (checked with a Playwright request listener; altcha.org appears only
       as the footer link text)

**(b) Switching to "custom" mode shows the Challenge URL field**

1. [X] In Settings → OpenPorte Anti-spam, change "API Mode" to "Custom"
2. [X] Confirm the "Challenge URL" text input becomes enabled immediately (JS toggle)
       (`admin-settings.spec.js`)
3. [X] Enter a URL; confirm it saves; confirm the widget's challengeurl attribute
       reflects it in the rendered HTML
4. [X] Negative test: in Self-hosted mode, the Challenge URL field is disabled
       (`admin-settings.spec.js`)

**(c) No PHP notices in wp-env logs, no error or warning in browser dev console**

1. [X] `wp-env logs` and `wp-content/debug.log` hold no PHP warning, notice or
       error from OpenPorte on either bench. Present and not ours: WP-CLI's own
       PHP 8.5 deprecations (`php-cli-tools`), WooCommerce's
       `_load_textdomain_just_in_time` notice, and on the floor Contact Form 7
       5.4.2's `SERVER_NAME` warnings under WP-CLI
2. [X] Browser console: no warning or error on the Test Page (widget solved) and on
       the settings page (load and save), checked with a Playwright console
       listener

**(d) Upgrade successful**

1. [X] Legacy install: `openporte_api` set to `eu` → the widget's challenge URL is
       the local REST endpoint
2. [X] ALTCHA → OpenPorte migration (`tests/README.md`): ALTCHA 1.26.3 configured
       (Custom mode + URL, complexity medium, Contact Form 7 `captcha`), deactivated,
       OpenPorte activated → every setting mirrored (`captcha` normalised to `1`),
       shared secret unchanged, `altcha_*` options still present, algorithm
       **SHA-256**, transport **rest**, Replay limit **5**. Both shortcodes render.
       The three challenge endpoints answer **403** in the imported Custom mode
       and **200** with a challenge after switching to Self-hosted
3. [X] Fresh activation on a clean bench → algorithm **SHA-512** (served challenge
       agrees), Replay limit **5**, transport **rest**
4. [X] Plugin update 1.29.1 → 1.30.0, simulated with the published WordPress.org
       1.29.1 build installed in its own plugin folder and the active plugin swapped
       to the working tree **without** the activation hook (as an update does):
       `openporte_version` moves to 1.30.0 on the next request, Replay limit (3),
       Expiration (600) and Complexity (high) are kept, the transport option stays
       **absent** and the widget keeps the REST URL. The 1.29.1 copy was removed
       afterwards
5. [X] Deactivate + reactivate keeps an admin's choices: Replay limit 1 and
       transport ajax unchanged, algorithm unchanged
6. [X] Uninstall, running `uninstall.php` as `uninstall_plugin()` does: with
       "Keep settings" **on**, every `openporte_*` option stays and all
       `_transient_openporte_*` / `_transient_timeout_openporte_*` rows are removed;
       with it **off**, zero rows match any of the three patterns

**(e) PHP and WordPress compatibility verification**

1. [X] PHP 8.0 and WordPress 5.6 — minimum supported combo (see automated check 5)
2. [ ] PHP 8.3 and WordPress 6.8 — not run this cycle (#125 scoped floor and
       ceiling only)
3. [X] PHP 8.5 and WordPress 7.1 — newest PHP, newest WP (7.1.3)

**(f) v1.30.0 features and fixes**

1. [X] Self-hosted transport: REST by default; Ajax serves the same challenge
       (settings matrix over Ajax, `ChallengeTransportTest`); the field is disabled
       in Custom mode and a Custom-mode save keeps the stored value
       (`admin-settings.spec.js`)
2. [X] Custom API mode: the REST endpoint, its `altcha/v1` alias and the admin-ajax
       action answer **403** `openporte_challenge_unavailable` with no challenge;
       against the GateCHA backend the settings-page health check reports success
       and a GateCHA-issued token is accepted on a real comment form
3. [X] Login challenge checked before the password (`login-gate.spec.js`): without a
       token, a wrong and a correct password get the same CAPTCHA error; with a
       solved challenge, core answers "incorrect password" or logs in
4. [X] WordPress login and lost-password checks are not skipped by a WooCommerce
       nonce field on wp-login.php, with WooCommerce active and only WordPress
       protection on (`login-gate.spec.js`)
5. [X] Expired solved challenges stay refused (unit regression in
       `VerifyPrimitivesTest`; on the bench, an expired GateCHA token is refused on
       a real comment form in both its original and altered form)
6. [ ] CoBlocks with the integration off leaves CoBlocks' own reCAPTCHA alone —
       unit-tested (`CoblocksIntegrationTest`); not run on the bench, CoBlocks is
       not part of the bench plugin set
7. [X] Replay limit per form: the `openporte_replay_limit` filter receives the form
       surface (`wordpress_login`, `woocommerce_login`, …) and the hook name
       (unit-tested in `ReplayLimitTest` and `LoginIntegrationTest`); the strict
       login replay test still logs in, now through two checks in one request
8. [X] "Keep settings when the plugin is deleted" (see (d)6)
9. [X] Shared-secret in-field actions: Show/Hide, Copy and Regenerate
       (`shared-secret.spec.js`)
10. [X] "Settings saved." appears exactly once after saving the settings page
11. [ ] Elementor field without Elementor Pro's field classes no longer ends the
        request — not testable on the bench (Elementor Pro is paid)
12. [X] Translations: with the site in French, the settings page shows the new
        strings ("Transport auto-hébergé", "Conserver les paramètres lors de la
        suppression du plugin.", the "Copier le secret partagé" action); all 30
        `.po` files pass `msgfmt --check`

**(g) v1.29.0 checks (regression)**

1. [X] Replay limit 5 / 1 / 0 behave as specified, and logging in still works at a
       strict limit (E2E replay-limit suite)
2. [ ] Counter rows and lifetime on the database path — not re-run by hand this
       cycle (covered by the unit suite against the fake `$wpdb`)
3. [ ] Object-cache path — no persistent object cache on the bench
4. [ ] Concurrency — still verified by design only; the measurement is tracked in
       #124, without a milestone
5. [ ] Fail-open observability and the deprecation notice of the raw primitives —
       not re-run by hand this cycle (both unchanged since 1.29.0, unit-tested)
6. [X] Settings UI: Custom presets reveal their number input; Custom mode greys out
       Complexity, Expiration (and the Self-hosted transport) with their notes, and
       saving in Custom mode changes none of them (`admin-settings.spec.js`)

**(h) WordPress Plugin Check**

1. [X] Plugin Check 2.1.0 on the ceiling bench (WP 7.1.3): "Checks complete. No
       errors found." (`./wp-env.sh plugin-check openporte`)
