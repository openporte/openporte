# OpenPorte test bench (wp-env)

Manual, browser-driven test environment for the plugin. This harness
provisions a reproducible WordPress instance with fixtures so changes can be
exercised by hand. For the browser suites that run against this bench — the
settings matrix (integrations × auto-mode × Floating UI) and the replay-limit
suite — see [`e2e/README.md`](e2e/README.md). For the unit suite, which needs
no bench at all, see [`phpunit/`](phpunit/) and `npm run test:unit`.

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
     challenge.
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
