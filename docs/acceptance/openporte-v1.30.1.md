# Acceptance criteria

Acceptance record for **v1.30.1**. Run the automated checks first, then the
manual wp-env steps. Tick the boxes as evidence while performing the release
validation (Phase 5 of `docs/release-preparation.md`).

Recorded 2026-10-30 and 2026-10-31 on the `release/1.30.1` branch: ceiling
bench PHP 8.5.12 / WordPress 7.1.3 (wp-env, MariaDB, no persistent object
cache), reset with `clean all` on 2026-10-30; and the optional OpenLiteSpeed
bench (LiteSpeed Cache 7.9.2, default page and REST caching, #133). A patch
release: the floor bench and the upgrade paths were not re-run, see (d) and
(e). Unticked boxes were **not run** this cycle; the note says why.

## Automated checks

1. [X] `npm run release:check` — `php -l`/`bash -n` pass (blocking); `phpcs`/`phpstan`
       output reviewed, no unexplained new findings (informative)
2. [X] PHPUnit unit suite (`npm run test:unit`) **green**, no skipped or risky tests
       (233 tests, 525 assertions)
3. [X] E2E suite (`cd tests/e2e && npm test`) **all green** on the ceiling bench
       with the final release tree (2026-10-31, all four fixes and the 1.30.1
       version): 86 tests — the settings matrix for every driver with its
       negative controls, the replay-limit suite, the shared-secret controls,
       the login gate and the settings page
4. [X] Settings matrix over the Ajax transport (`npm run test:ajax`): 61 tests green
5. [X] Earlier full run on the ceiling bench, 2026-10-30, with the C-04 and I-09
       fixes (before I-10): 86 tests over REST and 61 over Ajax, all green
6. [X] OpenLiteSpeed bench, 2026-10-30, the whole suite with LiteSpeed Cache
       purged after every settings write (procedure in `tests/e2e/README.md`):
       85 of 86 over REST and 60 of 61 over Ajax. Both failures are the same
       combo, `contact-form-7 › auto=onload floating=on`: on that fast bench the
       form is sent before the challenge is solved and is refused (fails
       closed). Not a 1.30.1 regression: the same code passes it on wp-env
7. [ ] Floor bench (PHP 8.0 / WP 5.6) — not run this cycle: no change touches
       the PHP or WordPress floor

## wp-env manual test steps

Tests **(a)**, **(b)** and **(c)** are our regression tests.

**(a) Self-hosted form submits and verifies**

1. [X] wp-env start (ceiling bench, see above)
2. [X] Activate the plugin; Settings → OpenPorte Anti-spam shows "API Mode:
       Self-hosted" by default (new screenshot 2, captured on this bench)
3. [X] Enable an integration (WordPress → Comments)
4. [X] Submit a comment with the OpenPorte widget — the entry is created (E2E
       settings matrix, core comments driver)

**(b) Switching to "custom" mode shows the Challenge URL field**

1. [X] Custom mode enables the Challenge URL field immediately, and Self-hosted
       mode disables it (`admin-settings.spec.js`)

**(c) No PHP notices in wp-env logs, no error or warning in browser dev console**

1. [X] `debug.log` after the final E2E run and Plugin Check holds no warning,
       notice or error from OpenPorte. Present and not ours: WP-CLI's own PHP
       8.5 deprecation (`php-cli-tools`), WooCommerce's
       `_load_textdomain_just_in_time` notice and a wpDiscuz cron message
2. [ ] Browser console — not checked by hand this cycle; the E2E run reported
       no page errors

**(d) Upgrade successful**

1. [ ] Not re-run this cycle. No option, default or activation step changed
       since 1.30.0; the version stamp `openporte_version` moves on the next
       request as before

**(e) PHP and WordPress compatibility verification**

1. [ ] PHP 8.0 and WordPress 5.6 — not run this cycle (see automated check 7)
2. [X] PHP 8.5 and WordPress 7.1 — newest PHP, newest WP (7.1.3)

**(f) v1.30.1 fixes**

1. [ ] Gravity Forms, OpenPorte field before the last page of a multi-page form
       (C-04, audit #21): refused with the placement message, without spending
       the token; last page and single-page forms verify as before; the form
       editor warns only when the field is misplaced. Unit-tested
       (`GravityFormsFieldTest`, 6 tests). **Not verified on a real Gravity
       Forms install** (paid plugin, not on the bench), so not ticked
2. [X] Server-signature tokens (#128, audit #2): accepted only with `verified`
       `true` or `1` and an `expire` in the future within the one-day horizon;
       every other case refused (`VerifyPrimitivesTest`). The tests reach the
       whole check: it is pure verification code, and nothing on the bench
       issues these tokens
3. [X] Token size limit (#128, audit #3): a valid token of exactly 4096 bytes is
       accepted, the next valid length (4100) refused without writing state,
       also through the deprecated primitives (`VerifyPrimitivesTest`). Genuine
       tokens on every driver still verify (automated checks 3 and 4)
4. [X] LiteSpeed Cache (#133): on the OpenLiteSpeed bench with the release tree
       (2026-10-31), three consecutive requests to the REST challenge endpoint
       returned three different challenges, each with
       `x-litespeed-cache-control: no-cache`; the suite passes there
       (automated check 6)
5. [X] Self-hosted transport help names page caches and CDNs (#133); the new
       text renders on the settings page (screenshot 2)
6. [X] Docs: the readme FAQ and the README say what login protection does not
       cover (XML-RPC, REST API authentication) (#128)
7. [X] Translations: the `.pot` regenerated; the 14 fully translated locales are
       complete (185 of 185 strings), the visitor-strings tier unchanged (6);
       all 30 `.po` files pass `msgfmt --check`
8. [X] WordPress.org listing: five new screenshots matching the readme
       captions, captured on the ceiling bench

**(g) v1.30.0 checks (regression)**

1. [X] Login challenge before the password, the WooCommerce-field routing on
       wp-login.php, and the replay limits 5 / 1 / 0 (`login-gate.spec.js`,
       `replay-limit.spec.js`, in automated check 3)
2. [X] Settings UI: Custom presets, Custom-mode greying, shared-secret actions
       (`admin-settings.spec.js`, `shared-secret.spec.js`, in automated check 3)

**(h) WordPress Plugin Check**

1. [X] Plugin Check 2.1.0 on the ceiling bench (WP 7.1.3), with the release tree:
       "Checks complete. No errors found."
