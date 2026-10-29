# OpenPorte test bench (wp-env)

Manual, browser-driven test environment for the plugin. This harness
provisions a reproducible WordPress instance with fixtures so changes can be
exercised by hand. For the browser suites that run against this bench — the
settings matrix (integrations × auto-mode × Floating UI) and the replay-limit
suite — see [`e2e/README.md`](e2e/README.md). For the unit suite, which needs
no bench at all, see [`phpunit/`](phpunit/) and `npm run test:unit`.

For server-side LiteSpeed Cache testing, use the separate
[OpenLiteSpeed bench](#optional-openlitespeed-bench-issue-133) below. Installing
the LiteSpeed Cache plugin in the Apache-based wp-env bench does not provide
the LiteSpeed server cache.

## What it sets up

Driven by [`.wp-env.json`](../.wp-env.json) (`afterStart` →
[`bin/wp-init.sh`](bin/wp-init.sh)):

- **WordPress** (version pinned in `.wp-env.json`) on `http://localhost:8888`.
- **A suite of third-party form plugins** (`PLUGINS_SUITE` in
  [`bin/wp-init.sh`](bin/wp-init.sh)) — installed; only **Contact Form 7** is
  **activated** (used by the integration page). The E2E drivers activate the
  others themselves.
- **ALTCHA Spam Protection v1.26.3** (the upstream plugin OpenPorte forks) and
  **OpenPorte** (this repo, mapped into `wp-content/plugins/openporte`) — both
  **installed**; their activation state is never touched by the script (a
  fresh bench starts with both deactivated).
- Three pages:
  - **Contact Us** — a Contact Form 7 form; the form id is discovered at
    provisioning time (not hard-coded, since Contact Form 7 assigns it on install).
  - **WPForms Test** — a minimal WPForms fixture form (one textarea, message
    confirmation, WPForms' own anti-spam token and honeypot disabled so
    OpenPorte is the only spam gate) created as a `wpforms` CPT post.
  - **Test Page** — the `[altcha]` and `[openporte]` shortcodes, to check the
    primary shortcode and its deprecated alias render.

`wp-init.sh` is idempotent: re-running leaves existing fixture pages and
plugin activation states untouched.

## Running it

The repo is exercised on a remote Docker host via [`wp-env.sh`](../wp-env.sh)
(host/path in `.wp-env.conf`). Typical loop:

```sh
./wp-env.sh start        # boots wp-env and runs the afterStart hook
./wp-env.sh -v start     # same, with environment details printed
./wp-env.sh stop
```

To target other versions: `./wp-env.sh -p 8.0 -w 6.5 start`.

> **Older WordPress versions:** provisioning may need small tweaks — Plugin Check
> and Contact Form 7 have minimum-WordPress requirements that can abort the
> `afterStart` hook. See [Older WordPress versions](../docs/maintenance-testing.md#older-wordpress-versions)
> in the maintenance & testing guide before running a minimum-version bench.

### Command reference

`wp-env.sh` is a thin wrapper: `-p`/`-w`/`-v` are its own flags (see the
script's header comment), everything else is forwarded verbatim to the
native `wp-env` CLI running on the remote host — so `wp-env <cmd> --help`
(over SSH, from `REMOTE_PATH`, after `source ./.wpenvrc`) is always the
ground truth for what a subcommand accepts. **Sync (`rsync --delete` of the
working tree to the remote) only happens on `start` and `plugin-check`** —
every other subcommand (`logs`, `stop`, `run`, `status`, …) skips it, so
`run cli -- wp ...` never races an in-progress edit.

| Goal | Command |
|---|---|
| Clean baseline / switching between floor and ceiling versions | `./wp-env.sh cleanup` |
| Deploy or update the environment to specific versions | `./wp-env.sh -p "8.5" -w "7.1" -v start --update` |
| Upgrade `@wordpress/env` on the remote (as `REMOTE_USER`, not your own SSH account) | `./wp-env.sh update` |
| Check the plugin against Plugin Check | `./wp-env.sh plugin-check openporte` |
| Tail logs, filtered to the interesting ones | `./wp-env.sh logs --watch false \| egrep -iwv "200\|302\|304\|403"` |
| One-shot environment/version snapshot | `./wp-env.sh status && ./wp-env.sh run cli wp --info && ./wp-env.sh run cli wp core version` |
| One wp-cli command | `./wp-env.sh run cli -- wp <command>` (`--` only needed when the wrapped command has flags that could be mistaken for wp-env's own — plain `wp <subcommand> args...` usually doesn't need it, matching wp-env's own `wp-env run cli wp user list` example) |
| Bench URL from a remote Docker host | `ssh -f -N -L 8888:localhost:8888 ${REMOTE_USER}@${REMOTE_HOST}` (per `.wp-env.conf`), then `http://localhost:8888/` |

**`cleanup` + `start --update`, not just `start`:** `cleanup` removes
containers, volumes, networks, and local files but **keeps Docker images**,
so the following `start` still comes up quickly. `--update` on `start` means
"download source updates and apply WordPress configuration" per `wp-env
start --help` — the flag that makes a version change actually take, rather
than trusting a bare `start` to notice `-p`/`-w` differ from what is already
running. `cleanup` then `-p ... -w ... start --update` is the combination
that reliably lands on the versions asked for.

**Gotchas worth remembering:**
- `wp-env cleanup` prompts for confirmation unless you pass `--force` —
  needed for any non-interactive invocation.
- `wp-env logs`'s flag is `--watch` (boolean, default `true`); `--watch false`
  and `--no-watch` both turn it off.
- An SSH agent holding the keys named in `.wp-env.conf`
  (`REMOTE_RPC_KEY`/`REMOTE_RSYNC_KEY`) must be reachable via `SSH_AUTH_SOCK`
  for every `wp-env.sh` call — there is no interactive fallback.
- **A bare `./wp-env.sh start` falls back to `.wp-env.json`'s versions**, even
  on a bench brought up with other ones: `-p`/`-w` are environment variables
  for that one call, not stored anywhere. To sync code onto a PHP 8.5 / WP
  7.1.3 bench, repeat the flags (`./wp-env.sh -p 8.5 -w 7.1.3 start`), and
  check `wp core version` afterwards. The database survives either way.
- **Quoting does not survive `wp-env.sh`.** It joins its arguments into one
  string for `ssh`, so the remote shell strips a layer of quotes: an argument
  with spaces or quotes (SQL above all) arrives split, and a flag value such
  as `wp plugin install … --version=5.4.2` is lost. SSH directly instead:

  ```sh
  source ./.wp-env.conf
  ssh -i "$REMOTE_RPC_KEY" "$REMOTE_USER@$REMOTE_HOST" \
    "cd ~/$REMOTE_PATH && source ./.wpenvrc && wp-env run cli -- wp db query 'SHOW INDEX FROM wp_options'"
  ```

  For a specific plugin version, install its versioned zip URL instead
  (`./wp-env.sh run cli wp plugin install https://downloads.wordpress.org/plugin/contact-form-7.5.4.2.zip --force`).
- **Arbitrary PHP on the bench**, without a `start`: copy the file into the
  remote tree over the rsync key (that tree is what the container maps), run
  it by its container path, then delete it. This is the way to read plugin
  state as PHP sees it, or to set an option to an empty string, which
  `wp option update x ''` cannot do through the quoting above.

  ```sh
  source ./.wp-env.conf
  ssh -i "$REMOTE_RSYNC_KEY" "$REMOTE_USER@$REMOTE_HOST" "cat > ~/$REMOTE_PATH/.tmp-snippet.php" < snippet.php
  ssh -i "$REMOTE_RPC_KEY" "$REMOTE_USER@$REMOTE_HOST" \
    "cd ~/$REMOTE_PATH && source ./.wpenvrc && wp-env run cli -- wp eval-file wp-content/plugins/openporte/.tmp-snippet.php"
  ssh -i "$REMOTE_RSYNC_KEY" "$REMOTE_USER@$REMOTE_HOST" "rm ~/$REMOTE_PATH/.tmp-snippet.php"
  ```

  The next `start` would delete a leftover anyway (`rsync --delete`).

### Simulating a plugin update from an older release

The working tree is mapped live onto `wp-content/plugins/openporte`, so starting
the bench at an older tag does not give you an older OpenPorte to upgrade from.
Install the published older build **next to** the mapping instead, in its own
folder, with a `wp eval-file` snippet (see above):

1. Deactivate `openporte` and, for a clean start, delete its `openporte_*`
   options.
2. `download_url('https://downloads.wordpress.org/plugin/openporte.1.29.1.zip')`,
   `unzip_file()` it into a temporary directory, and move its `openporte/`
   folder to `wp-content/plugins/openporte-1291/`. Never install it into
   `openporte/`: that folder is the working tree.
3. Activate `openporte-1291/openporte.php` and set up the scenario (settings,
   options) on the old version.
4. Simulate the update: rewrite `active_plugins`, replacing
   `openporte-1291/openporte.php` with `openporte/openporte.php`, through
   `update_option()`. No activation hook runs, exactly as with a real update;
   the next request runs `openporte_upgrade()`.
5. Check the result, then delete `wp-content/plugins/openporte-1291/`.

The WordPress.org zip is the same build as the one attached to the GitHub
release, so neither a checkout of the old tag nor a local `wp dist-archive` is
needed.

## The ALTCHA → OpenPorte migration test

> **Activate ALTCHA (v1, v2, or v3) and OpenPorte one at a time, never together** — both
> register the `[altcha]` shortcode and the `altcha/v1` REST route and will clash.

The migration runs on **OpenPorte activation** (`register_activation_hook`), not
as a silent in-place update. Because the entry file was renamed
`altcha.php → openporte.php`, WordPress treats OpenPorte as a distinct plugin.

1. Activate **ALTCHA Spam Protection** (v1.26.3, v2.x, or v3.x). Set some **non-default** config
   (e.g. API mode `custom` + a Challenge URL, change the complexity, toggle the
   Contact Form 7 integration). Note the signing secret
   (`wp option get altcha_secret`).
2. Deactivate ALTCHA.
3. Activate **OpenPorte**. On activation it **copies** every `altcha_*` option
   into its `openporte_*` counterpart (guarded: it never overwrites an existing
   `openporte_*` value).
4. Verify:
   - `wp option list | grep -E 'altcha_|openporte_'` — the `openporte_*` options
     mirror the `altcha_*` ones, **and the `altcha_*` options are still present**
     (so the user can roll back to ALTCHA v1 without data loss).
   - The signing secret is **unchanged** (`wp option get openporte_secret` equals
     the value noted in step 1) — otherwise previously issued challenges break.
   - On **Test Page**, both `[altcha]` and `[openporte]` render the widget.
   - `/wp-json/altcha/v1/challenge`, `/wp-json/openporte/v1/challenge` and
     `/wp-admin/admin-ajax.php?action=openporte_challenge` all return a
     challenge in Self-hosted mode. If the imported configuration is in Custom
     API mode, all three answer **403** instead (since 1.30.0 the site issues
     no challenges of its own in Custom mode): switch `openporte_api` to
     `selfhosted` to check them.
   - `wp option get openporte_selfhosted_transport` is `rest`: an imported
     configuration keeps the historical REST transport.
   - `./wp-env.sh logs` (or `wp-env logs`) is clean — no PHP warnings/notices.
5. Roll back (optional): deactivate OpenPorte, reactivate ALTCHA — its config is
   intact.

## Open point: the legacy ALTCHA zip

`bin/wp-init.sh` installs ALTCHA v1.26.3 from `local/altcha-spam-protection.1.26.3.zip`
when present, otherwise from wordpress.org
(`https://downloads.wordpress.org/plugin/altcha-spam-protection.1.26.3.zip`).

As of this writing wordpress.org still serves the **byte-identical** 1.26.3 build,
so committing the zip to git is unnecessary — the URL fallback keeps the harness
reproducible for other contributors. **Decision pending (JC):** delete the local
zip and rely on the URL, or keep a committed copy as an offline/archival pin in
case the upstream listing is ever removed. The `local/` zip is git-ignored today.

## Conflict guard test (ALTCHA v2/v3)

The conflict guard prevents OpenPorte from activating when ALTCHA v2 or v3 is active.

**Test ALTCHA v2/v3 detection:**

1. Install ALTCHA v2.x or v3.x (from `altcha-wordpress-next`)
2. Activate ALTCHA
3. Try to activate OpenPorte
4. **Expected:** Activation fails with a clear error message:
   "OpenPorte is a fork of ALTCHA and cannot run while any ALTCHA plugin is active"
5. **Verify:** OpenPorte does NOT activate, no PHP errors in logs

The conflict guard checks for:
- `ALTCHA_VERSION` constant (ALTCHA v1)
- `altcha_plugin_active()` function (ALTCHA v1)
- `ALTCHA_PLUGIN_VERSION` constant (ALTCHA v2/v3)
- `AltchaPlugin` class (ALTCHA v2/v3)

## Optional OpenLiteSpeed bench (issue #133)

This is a separate WordPress installation and database on the remote Docker
host. It shares the synchronized OpenPorte source directory with wp-env, but
does not inherit wp-env's settings, users, fixture pages, or version overrides.
Manage it with Docker Compose; `wp-env.sh` still manages only the original
bench. No paid LiteSpeed license or QUIC.cloud connection is needed for this
OpenLiteSpeed REST-cache test.

### Initial setup on the remote host

Use a normal SSH login with Docker access, rather than the wrapper's restricted
RPC key. Keep the Compose project outside `REMOTE_PATH`: the wrapper syncs that
directory with `rsync --delete`.

1. Clone the [official LiteSpeed Docker environment](https://github.com/litespeedtech/ols-docker-env)
   into a separate directory:

   ```sh
   mkdir -p ~/projects/wordpress/benches
   cd ~/projects/wordpress/benches
   git clone https://github.com/litespeedtech/ols-docker-env.git openporte-litespeed
   cd openporte-litespeed
   ```

2. Edit its `.env`: set `DOMAIN=localhost`, `TimeZone=Europe/Berlin`, and
   distinct random `MYSQL_ROOT_PASSWORD` and `MYSQL_PASSWORD` values. Keep
   `MYSQL_DATABASE=wordpress` and `MYSQL_USER=wordpress`. Generate each password
   with `openssl rand -hex 24`, then protect the file with `chmod 600 .env`.
   Keep the supplied image version fields, checking the selected tag supports
   the host architecture before pulling. On an ARM64 Pi, `uname -m` should
   report `aarch64`; the following manifest must include `linux/arm64`:

   ```sh
   . ./.env
   docker manifest inspect "litespeedtech/openlitespeed:${OLS_VERSION}-${PHP_VERSION}"
   ```

   Keep `DOMAIN` as a hostname without a port; the provisioning scripts
   validate it as such.

3. In `docker-compose.yml`, replace the `litespeed` service's entire `ports`
   list with:

   ```yaml
   ports:
     - "127.0.0.1:8889:80"
     - "127.0.0.1:7081:7080"
   ```

4. Start only the database and web server. Check the logs and wait for MariaDB
   to report that it is ready before provisioning WordPress:

   ```sh
   docker compose pull mysql litespeed
   docker compose up -d mysql litespeed
   docker compose logs --tail=50 mysql litespeed
   bash bin/demosite.sh
   bash bin/webadmin.sh YOUR_NEW_WEBADMIN_PASSWORD
   ```

   `demosite.sh` prepares WordPress and installs LiteSpeed Cache; it is a
   first-install command, not a routine startup command.

5. **After provisioning**, add `OPENPORTE_SOURCE` to `.env`, pointing to the
   absolute remote directory containing the synchronized `openporte.php`
   (`REMOTE_PATH` in the repository's `.wp-env.conf`). Add this entry to the
   existing `litespeed` service's `volumes` list:

   ```yaml
   - "${OPENPORTE_SOURCE}:/var/www/vhosts/localhost/html/wp-content/plugins/openporte:ro"
   ```

   Apply it with `docker compose up -d mysql litespeed`. Mount the source only
   after provisioning because the installer recursively changes site-file
   ownership. The read-only mount protects the synchronized plugin files.

### Browser access and WordPress settings

On the local machine, close any existing tunnel using local port 8888, then
open this tunnel with the normal SSH login and host from your configuration:

```sh
ssh -N -o ExitOnForwardFailure=yes \
  -L 8888:127.0.0.1:8889 \
  -L 7081:127.0.0.1:7081 \
  YOUR_PI_LOGIN@YOUR_PI_HOST
```

Keep the tunnel open and finish WordPress installation at
`http://localhost:8888`. OpenLiteSpeed WebAdmin is at `https://localhost:7081`
(self-signed certificate), using `admin` and the separate WebAdmin password.

- Activate OpenPorte and LiteSpeed Cache. Leave upstream ALTCHA deactivated.
- Set OpenPorte to **Self-hosted**, with **REST** transport.
- In **LiteSpeed Cache → Cache**, enable **Enable Cache** and **Cache REST API**.
- Under its **Object** tab, explicitly switch **Object Cache OFF**: the
  provisioner configures Redis defaults, but this procedure does not start Redis.
- Create a published page containing `[openporte]` for a manual widget test.

For later sessions, run `docker compose up -d mysql litespeed` in the Compose
directory; stop it with `docker compose stop`. To refresh the shared plugin
source, run `./wp-env.sh start` from the local repository, preserving any
desired wp-env version overrides as described above. This also starts the
original bench; it does not configure the OLS installation.

### Cache verification and stale-cache cleanup

Before retesting a deployed fix, verify the mounted source contains it:

```sh
docker compose exec litespeed grep -n openporte_prevent_page_caching \
  /var/www/vhosts/localhost/html/wp-content/plugins/openporte/includes/core.php
```

Expect the helper definition and calls from both challenge handlers. Restart
the container with `docker compose restart litespeed` to clear stale PHP opcode
cache, then use **LiteSpeed Cache → Toolbox → Purge → Purge All** in WordPress.
**An existing cached response bypasses PHP: deploying the opt-out does not
remove that cached response.** Purge after deploying the fix and between
before/after comparisons.

Run these anonymous GET requests from the local machine through the tunnel.
Quote URLs containing `?` or `&`, especially in zsh.

WordPress may advertise `index.php?rest_route=/` in its `Link` header. Use that
form if `/wp-json/` returns 404; the pretty URL requires working permalink
rewrites. First inspect an ordinary REST response (replace `2` with an
existing published page ID):

```sh
for attempt in 1 2 3; do
  curl -sS -D - -o /dev/null \
    'http://localhost:8888/index.php?rest_route=/wp/v2/pages/2'
done
```

Do not assume this is cacheable: the observed core page response carried
`X-LiteSpeed-Cache-Control: no-cache`. For a page-cache positive control, request
the published HTML page three times and look for `miss` followed by `hit`:

```sh
for attempt in 1 2 3; do
  curl -sS -D - -o /dev/null 'http://localhost:8888/?page_id=2'
done
```

Then inspect headers and bodies for the challenge:

```sh
for attempt in 1 2 3; do
  curl -sS -i \
    'http://localhost:8888/index.php?rest_route=/openporte/v1/challenge'
  printf '\n'
done
```

With the fix, expect HTTP 200, `Cache-Control: no-cache, no-store, max-age=0`,
`X-LiteSpeed-Cache-Control: no-cache`, no cache hits, and different challenge,
salt, and signature values on successive requests. A cached response can still
carry `Cache-Control: no-store`; check LiteSpeed's cache headers and the bodies.
See [LiteSpeed's cache verification guide](https://docs.litespeedtech.com/lscache/start/).

### Maintainer-supplied acceptance evidence

The maintainer supplied the following live OpenLiteSpeed results in the
2026-10-29 session. These are supplied HTTP captures, not agent-rerun tests;
the exact WordPress, PHP, OLS, LiteSpeed Cache versions and deployed commit
were not recorded with the captures. Times below are the captured HTTP `Date`
values in UTC.

| Request / state | Observed result |
|---|---|
| HTML `/?page_id=2`, 2026-10-28 23:48:32 UTC | HTTP 200; `miss → hit → hit`. |
| Core REST `/wp/v2/pages/2`, 2026-10-28 23:51:21 UTC | Three HTTP 200 JSON responses with LiteSpeed `no-cache` and HTTP `no-store, private`; no cache hits. This was not a REST-cache positive control. |
| OpenPorte REST challenge before deploying the opt-out, 2026-10-28 23:52:46 UTC | HTTP 200; LiteSpeed `public,max-age=604800`, then `miss → hit → hit`, with identical challenge, salt and signature despite HTTP `no-store`. This directly demonstrated REST caching of the affected route. |
| OpenPorte REST challenge after deploying the opt-out, 2026-10-29 00:10:20 UTC | Three HTTP 200 responses with LiteSpeed `no-cache`, HTTP `no-cache, no-store, max-age=0`, no cache hits, and distinct challenges, salts and signatures. |

This establishes the anonymous Self-hosted REST success path for that setup.
Ajax, the legacy REST alias, Custom-mode 403 responses, and a complete browser
form submission were not established by these captures; the agent-run
evidence below covers them. Record the component versions and deployed
revision with future acceptance runs.

### Agent-run acceptance evidence

Run by the agent on the same bench on 2026-10-29, between about 00:00 and
00:30 UTC, against OpenLiteSpeed 1.9.4, PHP 8.3.36 (lsphp83), WordPress
7.1.4, LiteSpeed Cache 7.9.2 (default REST caching) and OpenPorte 1.30.0.
The deployed `includes/core.php` was the uncommitted fix on branch
`fix/133-litespeed-caches-challenge`, SHA-256
`8ea0512558f9f788df76bd84edc887867cca93f8e900ea42aeb2b29a3d50ef65`; the
original 1.30.0 file's SHA-256 starts `c411c5585dec8011`. Each run below was
preceded by a purge, applied through a request that misses the cache (see
[`tests/e2e/README.md`](e2e/README.md#running-against-the-openlitespeed-bench-issue-133)).

| Request / state | Observed result |
|---|---|
| Ajax challenge, LiteSpeed **AJAX cache TTL** forced on for `openporte_challenge 3600`, original 1.30.0 code | LiteSpeed `public,max-age=3600`, then `miss → hit → hit`, identical signatures. Positive control: an admin setting can make the Ajax transport cache too. |
| Same, with the fix | LiteSpeed `no-cache` on all three, distinct signatures, `cc5_AJAX.openporte_challenge` tag present (setting active). |
| Ajax challenge, default settings, with the fix | LiteSpeed `no-cache`, distinct signatures. |
| Legacy REST alias `/altcha/v1/challenge`, with the fix | HTTP 200, LiteSpeed `no-cache`, distinct signatures. |
| Custom API mode, REST, legacy alias and Ajax, with the fix | HTTP 403 `openporte_challenge_unavailable`, LiteSpeed `no-cache` on all three requests per endpoint. No before-the-fix control was run for these. |
| 7 scripted `wp-login.php` logins, a fresh REST challenge solved for each, original 1.30.0 code | Every challenge `miss → hit…` with one shared signature; logins 1–5 succeed, 6 and 7 are refused with "Could not verify you are not a robot." (replay limit 5). Reproduces the reported lockout. |
| Same 7 logins, with the fix | No cache hits, a distinct challenge per login; 7/7 logged in. |
| Playwright, with the fix: `login-gate.spec.js` (core tests), `replay-limit.spec.js` (comments tests), settings matrix on core comments over REST and over Ajax | 4, 3, 11 and 11 tests passed. Real browser solves and form submissions. |

The scripted logins used a temporary subscriber, deleted afterwards. The bench
was returned to Self-hosted mode, REST transport, login protection on and an
empty AJAX cache TTL after these runs. Not established on this bench: the
WooCommerce, Contact Form 7, WPForms and wpDiscuz integrations, and the
settings-page specs.
