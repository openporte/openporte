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

## Running against the OpenLiteSpeed bench (issue #133)

The suite also runs against the optional OpenLiteSpeed bench (see
[`tests/README.md`](../README.md#optional-openlitespeed-bench-issue-133)),
through its tunnel on `localhost:8888`. Point the wp-cli bridge at the
`litespeed` container instead of wp-env:

```sh
export WP_BASE_URL=http://localhost:8888
export WP_CLI_CMD="ssh YOUR_PI_LOGIN@YOUR_PI_HOST docker exec litespeed wp --allow-root --path=/var/www/vhosts/localhost/html"
```

`ssh` joins its arguments into one remote command line, so the
[wp-cli quoting caveat](#known-caveats) applies unchanged.

That bench has no third-party plugins and no `tests/bin/wp-init.sh`
fixtures, so run only the specs that WordPress core alone can serve:

```sh
npx playwright test login-gate.spec.js --grep-invert "WooCommerce"
npx playwright test replay-limit.spec.js --grep-invert "AJAX resubmission|still logs a user in"
MATRIX_DRIVERS=wordpress-comments npx playwright test settings-matrix.spec.js
MATRIX_DRIVERS=wordpress-comments npm run test:ajax
```

These specs create their own fixtures there (the `e2e-gate-user` editor and
the `E2E-Comments` post). `admin-settings.spec.js` and `shared-secret.spec.js`
additionally need `WP_ADMIN_USER` and `WP_ADMIN_PASSWORD` set to an
administrator on this bench.

On 2026-10-29, with the #133 fix deployed and LiteSpeed Cache 7.9.2 on its
default REST caching, all four commands passed: 4, 3, 11 and 11 tests.

### Running the full suite there

The whole suite runs on this bench too, once it has the third-party plugins
and fixtures, and once the cache is purged after every settings change.
Without that purge, each matrix driver passes its first combo and fails every
later one: the page is still the one cached under the first combo's settings
(no auto-verification, no floating widget, no WooCommerce register form).

1. **Install the plugins** the drivers activate (Contact Form 7, WPForms Lite,
   WooCommerce, wpDiscuz), leaving them inactive. The drivers create the
   comments post and the WooCommerce account page themselves.
2. **Create the fixtures `tests/bin/wp-init.sh` would**: a `contact-us` page
   holding a Contact Form 7 form, and the WPForms form plus its `wpforms-test`
   page. Pass content containing spaces on stdin (`docker exec -i … wp post
   create - …`), never as an argument: see the [wp-cli quoting caveat](#known-caveats).
3. **Switch LiteSpeed Cache's Object Cache off** (`wp litespeed-option set
   object false`), as `tests/README.md` asks. With it on and no Redis running,
   every wp-cli call prints a Redis warning on stdout, and the suite reads
   that warning back as part of each post ID.
4. **Sync the code under test** with `./wp-env.sh start` from the checkout
   you want to test, then `docker restart litespeed` to clear the opcode
   cache. Both benches mount the same remote directory, so the LiteSpeed
   bench serves whichever checkout last ran `start` or `plugin-check`. Check
   with `md5sum` on a changed file inside the container. Do not let another
   checkout sync while a run is in progress.
5. **Point `WP_CLI_CMD` at a wrapper that purges after every write**, and
   set an administrator for the admin specs:

   ```sh
   #!/bin/sh
   # lw-purge: wp-cli in the litespeed container, purging after each write.
   W() { ssh YOUR_PI_LOGIN@YOUR_PI_HOST docker exec litespeed wp --allow-root --path=/var/www/vhosts/localhost/html "$@"; }
   W "$@"; rc=$?
   case "$1 $2" in
     "option update"|"option delete"|"plugin activate"|"plugin deactivate"|"post update"|"post create"|"user create"|"user delete")
       W litespeed-purge all >/dev/null 2>&1
       curl -sS -o /dev/null 'http://localhost:8888/wp-login.php?purge=1' ;;
   esac
   exit $rc
   ```

   ```sh
   export WP_BASE_URL=http://localhost:8888
   export WP_CLI_CMD=/path/to/lw-purge
   export WP_ADMIN_USER=e2e-admin WP_ADMIN_PASSWORD=…
   npx playwright test
   npm run test:ajax
   ```

   Purge once before each of the two commands as well.

On 2026-10-30, with 1.30.1 (I-09 included), LiteSpeed Cache 7.9.2 on its
default page and REST caching, and the wrapper above, the full suite passed
85 of 86 tests and the Ajax matrix 60 of 61. Both failures were the same
combo, `contact-form-7 › auto=onload floating=on`: the test submits without
waiting, and on this fast bench the form went out about 70 ms after the
challenge request, before a token existed, and was refused as spam. The
same code passes that combo on wp-env. It is a timing race in the held-submit
glue in `public/script.js`, not a cache effect.

LiteSpeed-specific pitfalls:

- **Purge before every run.** The suite changes settings through wp-cli,
  which LiteSpeed Cache never sees, so a page cached under the previous
  run's settings is still served. Running the Ajax matrix straight after the
  REST one failed 6 acceptance tests on the suite's own `challengeurl` check:
  the comments post still carried the REST URL. Within a run this does not
  happen, because each submitted or purged comment makes LiteSpeed Cache
  purge the post.
- **A wp-cli purge only takes effect on the next request that reaches PHP.**
  `wp litespeed-purge all` reports "Could not notify the web server to
  purge", and cache hits bypass PHP, so they keep serving the old response.
  Follow it with one request that misses the cache:

  ```sh
  sh -c "$WP_CLI_CMD litespeed-purge all"
  curl -sS -o /dev/null 'http://localhost:8888/wp-login.php?purge=1'
  ```

  `sh -c` because zsh does not word-split `$WP_CLI_CMD` on its own.
- **The suite leaves its baseline settings behind.** After the runs above,
  WordPress login protection was off (`openporte_integration_wordpress_login`
  `0`) and the transport was `ajax`. A full run also changes the complexity,
  expiry, delay and replay limit, sets per-integration options, and leaves
  the drivers' plugins active. Record `wp option list --search='openporte_*'`
  and the active plugins before a run, restore them afterwards, then purge,
  before using the bench for manual LiteSpeed testing.

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

## Debugging techniques

Three tricks for investigating timing or `public/script.js` behaviour on the
bench, without changing what the bench serves.

**Widen the solve window.** A submit that lands mid-solve is hard to hit by
hand: even at `complexity=high` a desktop solves in well under 2 s. Hold the
challenge response instead:

```js
await page.route('**/openporte/v1/challenge**', async (route) => {
  await new Promise((r) => setTimeout(r, 4000));
  await route.continue();
});
```

The widget stays in `data-state="verifying"` for the whole delay and still
gets the server's own signed challenge, so verification passes for real (a
faked challenge would fail the HMAC). Over the Ajax transport, match
`**/admin-ajax.php?action=openporte_challenge**` instead.

**A/B a `public/script.js` change without a bench sync.** Only `start` syncs
the tree (see `tests/README.md`), so serve another build per page:

```js
const body = fs.readFileSync(oldScript, 'utf8'); // git show <sha>^:public/script.js > oldScript
await page.route('**/openporte/public/script.js*', (route) =>
  route.fulfill({ status: 200, contentType: 'application/javascript', body })
);
```

Use it with a historical version (does the test actually catch the bug the
fix is for?) or a local variant with one guard removed (which guard does the
work?).

**Scratch specs outside `tests/e2e`.** `playwright.config.js` sets
`testDir: __dirname`, so a spec elsewhere is "not found". Give the scratch
directory its own config with `testDir: __dirname`, and symlink
`tests/e2e/node_modules` next to it so `@playwright/test` resolves.

Timing results vary between runs: sample more than twice before calling a
race settled.
