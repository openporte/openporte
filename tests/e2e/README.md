# OpenPorte E2E suite

Browser-driven tests against a running wp-env bench. Five spec files, five axes:

- **`settings-matrix.spec.js`** — **integrations × auto-verification mode ×
  Floating UI**, so combinations too numerous to click through by hand get
  exercised automatically. Born from the wpDiscuz `auto="onsubmit"` race
  (submission AJAX winning against the proof-of-work solve).
- **`replay-limit.spec.js`** — what happens when the *same* solved token is
  submitted again (issue #101).
- **`shared-secret.spec.js`** — the in-field Show/Hide, Copy and Regenerate
  controls of the Shared secret setting (issue #107); see
  [Shared secret controls](#shared-secret-controls).
- **`login-gate.spec.js`** — wp-login.php login and lost-password protection
  (`docs/security-audit.md` #14 and #15). Without a token, a wrong and a
  correct password get the same CAPTCHA error; with a solved challenge, core
  answers "incorrect password" or logs the user in. With WooCommerce active and
  only WordPress protection on, a forged WooCommerce nonce field on wp-login.php
  no longer skips the login or the lost-password check.
- **`admin-settings.spec.js`** — the settings page's live behaviour (issue
  #102): API Mode enables and disables the mode-specific fields
  (Challenge URL, Complexity, Expiration, Self-hosted transport) and their
  notes, a "Custom" preset reveals its number input, and a save in Custom
  mode keeps the stored values of the fields the browser did not submit. It
  saves settings, so it restores the options it touches afterwards, and it
  switches WordPress login protection off while it runs so the admin can log
  in without solving a challenge.

## The settings matrix (`settings-matrix.spec.js`)

Per integration driver it runs:

- **8 acceptance combos** — auto ∈ {disabled, onload, onfocus, onsubmit} ×
  floating ∈ {off, on}: fill the form, solve (pre-solve where the mode allows,
  submit-triggered for onsubmit/floating — the race paths), submit, assert the
  submission was accepted.
- **2 negative controls** — the server must reject both:
  - *missing token*: strip the widget from the DOM and submit. Without this,
    a combo could "pass" because verification isn't wired up at all.
  - *forged token*: solve for real, then corrupt the payload's HMAC signature
    before submitting. Client-side everything looks verified; only the
    server's signature check can catch it.

One suite-wide extra negative (on the core-comments driver only, because the
server-side check is integration-agnostic): **expired token replay** — solve
with `openporte_expires=2`, capture the token, detach the widget (a live one
auto-refetches expired challenges), submit the stale token after the validity
window, expect rejection.

Drivers: WordPress core comments, Contact Form 7, wpDiscuz, WPForms Lite,
WooCommerce login, WooCommerce registration. The third WooCommerce surface
(reset password) is not driven: it shares the registration code path (render
hook + a `*_post` filter) but its happy path ends in an email the mailer-less
bench can't observe.

Real browser, real PoW solving (no mocks): the widget genuinely computes the
challenge, so this also catches timing regressions like the complexity-range
change that exposed the wpDiscuz race.

**Self-hosted transport.** `applyBaseline()` sets `openporte_selfhosted_transport`
from `OPENPORTE_E2E_TRANSPORT` (`rest` by default, or `ajax`; anything else
fails before the run). `npm run test:ajax` runs this matrix over Ajax. Each
acceptance test first checks that the widget's `challengeurl` points at the
expected endpoint, so a mistyped or ignored setting cannot pass as an Ajax run.
Only working cases are tested — REST works, and Ajax works the same; a blocked
transport is not simulated (issue #100).

## The replay-limit suite (`replay-limit.spec.js`)

A second spec file, on its own axis: not "does one solved token work" but
"what happens when the *same* token comes back" (issue #101). Five tests, each
restoring `openporte_replaylimit` afterwards:

- **spends the whole budget, then refuses the next replay** — limit 3 on core
  comments: the solved token is accepted three times across three separate
  requests, and refused on the fourth.
- **refuses the first replay when the limit is strict** — limit 1.
- **accepts unlimited replays when the limit is 0** — the documented escape
  hatch (pre-1.29 behaviour). Guards against the counter becoming
  unconditional.
- **accepts an AJAX resubmission of the same token** — Contact Form 7, limit 5.
  The page never navigates, so this is the real shape of a visitor correcting a
  field and resubmitting: same token, three times, all accepted.
- **still logs a user in when the limit is strict** — the highest-harm
  regression this feature could cause. Since 1.30.0 a wp-login.php login checks
  the token twice in one request — before the password check
  (`wp_authenticate_user`) and on the `authenticate` backstop — so at a limit of
  1 it succeeds only if the per-request memo answers the second check. The
  WordPress and WooCommerce checks are kept mutually exclusive by
  `openporte_is_woocommerce_form_submission()` (see AGENTS.md); WooCommerce is
  activated deliberately so both integrations' callbacks are registered. The
  login must succeed, and the token it consumed must then fail to replay.

Replays are posted the way a bot would: fresh page, widget detached, the
captured token re-injected as a bare hidden field (`captureWidgetToken()` /
`injectToken()` in `helpers.js`).

The **arithmetic** of the counter — limits, TTLs, memoisation, fail-open — is
covered far more cheaply by the PHPUnit unit suite (`tests/phpunit`, run with
`npm run test:unit` from the repo root). What only a browser can prove, and
what this file is for, is that one visitor's click costs exactly one use.

> **Not covered here: concurrency.** The counter is meant to be atomic because
> InnoDB row-locks the guarded UPDATE. Demonstrating that needs parallel workers
> against a real database, which neither this suite (one worker, by design) nor
> the unit suite (single process, fake `$wpdb`) can do. For v1.29.0 the property
> is **verified by design** — the acceptance record walks the argument link by
> link — and the empirical test is deferred to #102. Until that lands, treat
> atomicity as reasoned, not measured.

## Prerequisites

Read also tests/README.md to understand how to use the bench and
docs/maintenance-testing.md to have an overview of the testing approach.

1. A running bench: `./wp-env.sh start` (from the repo root; provisions
   fixtures via `tests/bin/wp-init.sh` — the CF7 driver relies on its
   "Contact Us" page, the WPForms driver on its "WPForms Test" page).
   **OpenPorte must be activated** on the bench
   (`./wp-env.sh run cli -- wp plugin activate openporte`) — the init script
   never activates it (a fresh bench starts with it deactivated, for the
   migration test).
2. Reachability: the suite talks to `WP_BASE_URL`
   (default `http://localhost:8888`). For the remote Docker host, either use
   `WP_BASE_URL=http://<remote-host>:8888` or an SSH tunnel
   (`ssh -f -N -L 8888:localhost:8888 user@remote`).
3. Fixtures from `tests/bin/wp-init.sh` — the core-comments post and Contact
   Form 7's "Contact Us" page. The replay-limit suite uses both, and creates
   its own `e2e-replay-user` (an editor, so a successful login lands somewhere
   assertable).
4. wp-cli bridge: settings are flipped between tests via
   `./wp-env.sh run cli -- wp option …` (default). Override with
   `WP_CLI_CMD="wp-env run cli -- wp"` for a local wp-env.
5. Node ≥ 18. Then:

```sh
cd tests/e2e
npm install
npx playwright install chromium
```

## Running

```sh
cd tests/e2e
npm test                                       # everything, all drivers
MATRIX_DRIVERS=wpdiscuz npm test               # one integration
npm run test:ajax                              # the matrix over the Ajax transport
npx playwright test --grep "onsubmit"          # one mode across drivers
npx playwright test replay-limit.spec.js       # just the replay-limit suite
npx playwright test shared-secret.spec.js      # just the shared secret controls
npm run test:headed                            # watch it
npm run report                                 # open the HTML report
```

Tests run **serially with one worker** — the settings under test are global
WordPress options.

## Adding an integration

Create `drivers/<name>.js` exporting
`{ key, option, ensure, open, fill, submit, expectAccepted, expectRejected }`
(see `drivers/contact-form-7.js` for the smallest example) and register it in
`drivers/index.js`. `ensure()` runs once per driver: activate the plugin,
create fixtures idempotently, return the context (page path) the other hooks
receive.

## Known caveats

- **wp-cli argument quoting**: `wp-env.sh` forwards commands through
  `ssh "… wp-env $*"`, so the remote shell strips one layer of quoting.
  Values passed to the `wp()` helper must contain no spaces or quotes
  (fixture values are deliberately space-free, e.g. `E2E-Comments`).
- **Comment flood throttle**: core rejects a comment posted within 15 s of
  the previous one from the same IP ("You are posting comments too
  quickly") — every submission here comes from one browser IP, so the
  comment drivers purge the fixture post's comments before each test
  (`driver.reset()`).
- **CF7 acceptance** treats `data-status="failed"` as captcha-accepted: on a
  bench with no mailer the mail send fails *after* the spam check. `spam` is
  the captcha-rejected status. All four visible fields must be filled — the
  default form's Subject is required, and CF7 6.x may block an invalid form
  client-side with the status silently stuck on `init`.
- **Floating UI combos** intentionally use the submit-triggered flow; the
  widget's floating popup positioning is not asserted (only that the
  submission ultimately succeeds). This is the path that caught the
  submit-swallowed-while-verifying bug fixed in `public/script.js`.
- **WooCommerce drivers** share one `[woocommerce_my_account]` fixture page:
  logged out it holds the login form and (once registration is enabled) the
  register form. Each driver zeroes the sibling WooCommerce integration
  options in `ensure()` so exactly one widget renders — the shared helpers
  target `.first()`. The register driver's `reset()` deletes the customers
  its tests created, sparing the login driver's fixture user.

## Shared secret controls

`shared-secret.spec.js` exercises the real settings page: keyboard Show/Hide,
Clipboard API success/failure, HTTP fallback success/failure/exception, repeated
copy feedback, regeneration without saving, and narrow LTR/RTL layouts.
OpenPorte must be activated on the bench. The suite uses the wp-env admin
credentials (`admin` / `password`), overridable through `WP_ADMIN_USER` and
`WP_ADMIN_PASSWORD`. It edits only the browser's field value and never saves
settings.

```sh
npx playwright test shared-secret.spec.js
```

Also check screen-reader announcements, 200% zoom, and admin colour schemes
manually; DOM assertions alone cannot establish those behaviours.
