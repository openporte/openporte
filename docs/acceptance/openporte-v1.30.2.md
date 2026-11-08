# Acceptance criteria

Acceptance record for **v1.30.2**. Run the automated checks first, then the
manual wp-env steps. Tick the boxes as evidence while performing the release
validation (Phase 5 of `docs/release-preparation.md`).

Recorded 2026-11-08 on the `release/1.30.2` branch: ceiling bench PHP 8.5.12 /
WordPress 7.1.4 (wp-env, MariaDB, no persistent object cache), reset with
`cleanup --force` before the final run, with WooCommerce 11.2.2. A patch release:
the floor bench and the upgrade paths were not re-run, see (d) and (e). Unticked
boxes were **not run** this cycle; the note says why.

## Automated checks

1. [X] `npm run release:check` — `php -l`/`bash -n` pass (blocking); `phpcs`/`phpstan`
       output reviewed, no findings
2. [X] PHPUnit unit suite (`npm run test:unit`) **green**, no skipped or risky tests
       (238 tests, 530 assertions)
3. [X] E2E suite (`cd tests/e2e && npm test`) **all green** on the reset ceiling
       bench with the final release tree: 89 tests — the settings matrix for
       every driver with its negative controls, the replay-limit suite, the
       shared-secret controls, the login gate, the settings page and the new
       WooCommerce checkout spec
4. [X] Settings matrix over the Ajax transport (`npm run test:ajax`): 61 tests green
5. [ ] Floor bench (PHP 8.0 / WP 5.6) — not run this cycle: no change touches
       the PHP or WordPress floor
6. [ ] OpenLiteSpeed bench — not run this cycle: no change touches the challenge
       endpoints or caching

## wp-env manual test steps

Tests **(a)**, **(b)** and **(c)** are our regression tests.

**(a) Self-hosted form submits and verifies**

1. [X] wp-env start (ceiling bench, see above)
2. [ ] Settings → OpenPorte Anti-spam shows "API Mode: Self-hosted" by default —
       not checked by hand this cycle; the E2E baseline sets the mode itself
3. [X] Enable an integration (WordPress → Comments)
4. [X] Submit a comment with the OpenPorte widget — the entry is created (E2E
       settings matrix, core comments driver)

**(b) Switching to "custom" mode shows the Challenge URL field**

1. [X] Custom mode enables the Challenge URL field immediately, and Self-hosted
       mode disables it (`admin-settings.spec.js`)

**(c) No PHP notices in wp-env logs, no error or warning in browser dev console**

1. [X] `debug.log` after the final E2E run holds no warning, notice or error from
       OpenPorte. Present and not ours: WP-CLI's own PHP 8.5 deprecation
       (`php-cli-tools`) and WooCommerce's `_load_textdomain_just_in_time` notice
2. [ ] Browser console — not checked by hand this cycle

**(d) Upgrade successful**

1. [ ] Not re-run this cycle. No option, default or activation step changed
       since 1.30.1; the version stamp `openporte_version` moves on the next
       request as before

**(e) PHP and WordPress compatibility verification**

1. [ ] PHP 8.0 and WordPress 5.6 — not run this cycle (see automated check 5)
2. [X] PHP 8.5 and WordPress 7.1 — newest PHP, newest WP (7.1.4)

**(f) v1.30.2 fix (#142, PR #143)**

The fix stops accounts created during an order being refused. It adds no
challenge to those forms; that is part 2 of #142, planned for 1.31.0.

1. [X] Classic checkout, "Create an account?" with registration protection on:
       order placed and account created (`woocommerce-checkout.spec.js`; before
       the fix, refused with "Could not verify you are not a robot.")
2. [X] Block checkout, "Create an account": order placed and account created
       (same spec; refused before the fix). Also done by hand by the maintainer
       on 2026-11-08: no widget shown, account created
3. [X] `wp wc customer create` succeeds (same spec; refused before the fix). It
       reaches the same `WC_Customer::save()` path as the REST API
4. [X] Order confirmation page account form, by hand by the maintainer on
       2026-11-08: refused before the fix; with it, account created, no widget.
       No automated test drives this form
5. [X] My Account registration stays protected: solved submissions accepted in
       every auto-verification and floating combination; missing and tampered
       tokens rejected, over REST and Ajax (E2E settings matrix,
       `woocommerce-register` driver; `WooCommerceRegistrationTest`)
6. [X] `openporte_replay_limit` filters keep `woocommerce_register_post` as the
       legacy context (`WooCommerceRegistrationTest`)
7. [ ] Third-party forms rendering `woocommerce_register_form` — not tested. The
       widget still renders there, but the server no longer verifies it unless
       the form applies `woocommerce_process_registration_errors`
8. [X] Translations: the `.pot` regenerated for the reworded plugin description;
       the 14 fully translated locales are complete (185 of 185 strings), the
       visitor-strings tier unchanged (6); all 30 `.po` files pass `msgfmt --check`
9. [X] `Tested up to` removed from the `openporte.php` header, kept in `readme.txt`

**(g) v1.30.1 and v1.30.0 checks (regression)**

1. [X] Login challenge before the password, the WooCommerce-field routing on
       wp-login.php, and the replay limits 5 / 1 / 0 (`login-gate.spec.js`,
       `replay-limit.spec.js`, in automated check 3)
2. [X] Settings UI: Custom presets, Custom-mode greying, shared-secret actions
       (`admin-settings.spec.js`, `shared-secret.spec.js`, in automated check 3)
3. [X] Server-signature tokens and the token size limit (`VerifyPrimitivesTest`,
       in automated check 2)

**(h) WordPress Plugin Check**

1. [X] Plugin Check 2.2.0 on the ceiling bench (WP 7.1.4), with the release tree:
       "Checks complete. No errors found."
