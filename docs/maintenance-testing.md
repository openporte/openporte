# Maintenance and Testing Guide

## Maintenance

### Server Requirements

The supported floor is **PHP 8.0 / WordPress 5.6**, matching the authoritative
`Requires PHP` and `Requires at least` fields in `readme.txt`; keep this section
in sync with them.

#### Support Status

**✅ Minimum supported (floor)**

- WordPress: 5.6
- PHP: 8.0

**✅ Recommended**

- WordPress: 7.0+
- PHP: 8.3–8.5

**✅ Latest tested**

- WordPress: 7.1 (the `Tested up to` header is `7.1` — major.minor only,
  a Plugin Check requirement)
- PHP: 8.5

#### Compatibility Notes

- The floor is **PHP 8.0**: `core.php`'s `generate_challenge()` originally used
  `str_ends_with()` (a PHP 8.0 / WP 5.9-polyfilled function). Acceptance test
  **(e.1)** caught this failing on PHP 7.3, which is why PHP 7.3 / WP 5.0 are
  **not** supported. The call was later rewritten to a plain `substr()` check so
  the code also runs on WP 5.6–5.8 (which predate the polyfill), but the
  declared PHP floor remains 8.0.
- WordPress 5.6 is the oldest core that ships with PHP 8.0 support.
- WordPress 7.0 no longer supports PHP 7.2 / 7.3.
- PHP 8.2 has limited remaining upstream security support; prefer 8.3–8.5.
- The latest integration plugins compatible with WP 5.6 & PHP 8.0 are:

| Plugin         | Version          | Notes             |
| -------------- | ---------------- | ----------------- |
| contact-form-7 | 5.4.2            | Verified, all tests passed |
| formidable     | 6.25.1           | Not tested but load alright with OpenPorte activated |
| forminator     | 1.33.0           | Not tested but load alright with OpenPorte activated |
| html-forms     | latest (1.6.4)   | Not tested but load alright with OpenPorte activated |
| woocommerce    | 6.2.3            | Verified, all tests passed |
| wpdiscuz       | none             | Tried several candidate, none work. Not testable with our minimum requirements #80 |
| wpforms-lite   | latest (2.0.0.2) | Verified, all tests passed |


## Testing

Three layers, in increasing cost and decreasing coverage-per-second. Run the
cheapest one that can answer the question.

| Layer | Where | Needs | Run it when |
| ----- | ----- | ----- | ----------- |
| **Unit** (PHPUnit) | `tests/phpunit/` | PHP + `composer install` — no WordPress, no Docker | Any change to verification, the replay counter, a sanitizer, or a health-check evaluator. Seconds. |
| **Browser E2E** (Playwright) | `tests/e2e/` | A running wp-env bench | Widget rendering, verification end-to-end, integration code, replay behaviour across real requests. Minutes. |
| **Manual bench** | wp-env.sh | A running bench | Settings UI, upgrade paths, uninstall, and the checks no harness covers (see the per-release acceptance record in `docs/acceptance/`). |

### Unit tests (PHPUnit)

```bash
composer install
npm run test:unit      # or: vendor/bin/phpunit
```

The suite runs against a small WordPress stand-in (`tests/phpunit/wp-shim.php`)
rather than a real WordPress install: an in-memory options table, transients,
object cache, hooks, and a fake `$wpdb` covering the statements the replay
counter issues. That is what makes it fast and dependency-free — and it is also
its limit. **A fake that agrees with itself proves nothing about MySQL.**
Anything that depends on real database or real WordPress semantics — the
counter's atomicity under concurrent workers above all — belongs on the bench,
not here. As of 1.29.0 that atomicity is verified by *design* rather than by
experiment (see the acceptance record); the parallel-replay test that would
measure it is tracked in #102.

Standing rule when changing logic-heavy code: extend the suite where a test
genuinely pins behaviour. Coverage where it helps, not as a metric.

> **Transient garbage collection.** The replay counter's rows are shaped as
> WordPress transients precisely so core's own GC reclaims them. On a site
> running `DISABLE_WP_CRON` with no system cron, `delete_expired_transients`
> never runs and expired counter rows accumulate in `wp_options`. They are
> harmless (each is tiny, `autoload='no'`, and an expired row is reclaimed
> lazily on the next read of the same key) but they do not self-clean. Such
> sites should run a persistent object cache — which sidesteps the option rows
> entirely — or a real system cron.

> **Successful proofs create state before later form checks.** `verify()`
> consumes a replay slot immediately after cryptographic success, before a form
> plugin can reject the submission for flood control, duplication, or another
> validation rule. An attacker willing to pay the proof-of-work can therefore
> create one row pair per solved token. The work requirement bounds this write
> surface; the rows are small, `autoload='no'`, and expire as described above.

### Verify OpenPorte

The OpenPorte plugin can be tested remotely using the `wp-env.sh` helper script. See
[Testing tools](#testing-tools) below for technical details about the script.

**Note**: The script no longer requires OpenSSH >= 7.8. The previous implementation
used `ssh -o SetEnv` which required specific sshd configuration on the remote host.
The current implementation exports variables inline in the remote shell command,
which works on any SSH version.

#### CLI Reference

The `wp-env.sh` script accepts at least the following options (more
information can be found in `tests/README.md`):

| Option | Short | Description | Environment Variable |
|--------|-------|-------------|---------------------|
| `--php-version` | `-p` | Override PHP version | `WP_ENV_PHP_VERSION` |
| `--wp-version` | `-w` | Override WordPress Core version | `WP_ENV_CORE` |
| `--verbose` | `-v` | Display detailed environment information after start | N/A |

##### Supported Version Formats

**PHP Version**:

- Simple version: `7.3`, `7.4`, `8.0`, `8.1`, `8.2`
- Any value accepted by wp-env's `phpVersion` config

**WordPress Core Version**:

- Simple version: `6.5` (auto-expanded to `WordPress/WordPress#6.5`), `7.0`, `7.1`
- Branch reference: `WordPress/WordPress#7.0`
- Tag reference: `WordPress/WordPress#6.5.3`
- Latest trunk: `WordPress/WordPress#trunk`
- Any value accepted by wp-env's `core` config

##### Usage Examples (see also `tests/README.md`)

```bash
# Start environment with default versions
./wp-env.sh start

# Start with verbose output (shows environment details)
./wp-env.sh -v start
./wp-env.sh --verbose start

# Override PHP version only
./wp-env.sh --php-version 7.3 start
./wp-env.sh -p 7.3 start

# Override WordPress version only (both formats supported)
./wp-env.sh -w 6.5 start
./wp-env.sh --wp-version WordPress/WordPress#6.5 start

# Override both versions
./wp-env.sh -p 7.3 -w 7.0 start
./wp-env.sh --php-version 8.1 --wp-version WordPress/WordPress#trunk start

# Verbose start with overrides
./wp-env.sh -v -p 7.3 -w 7.0 start

# Options can be mixed with wp-env arguments
./wp-env.sh start --php-version 7.4 --no-cache
./wp-env.sh -p 8.0 -w 6.5 start --no-cache

# Stop the environment
./wp-env.sh stop
./wp-env.sh -p 7.3 stop
```

##### Verbose Output Example

When using `-v` or `--verbose` with `start`, the script displays:

```
✅ WordPress environment started successfully!

Environment Details:
  Admin URL:        http://localhost:8888/wp-admin
  WordPress:        6.9
  PHP:              8.4.21
  Database:         MariaDB 11.4.10-MariaDB
  Runtime:          docker
  Multisite:        no
  Xdebug:           off
  Install Path:     /home/user/.wp-env/wp-env-openporte-xxx

Service Status:    ✅ Running
```

#### Configuration

Create a `.wp-env.conf` file in the repository root with your remote connection details:

```bash
REMOTE_USER='your-ssh-username'
REMOTE_HOST='your-ssh-host'
REMOTE_PATH='path/to/remote/directory'
```

This file is **sourced** by the script, so it must contain valid shell syntax.

#### Requirements

**Local Machine**:

- **Bash**: The script requires bash (uses `[[ ]]`, arrays, etc.)
- **OpenSSH**: Any version (the script uses inline export, not `-o SetEnv`)
- **rsync**: For code synchronization
- **Git**: For repository validation

You will need access to an SSH Agent or the right keys to use the tool.

#### Accessing the Remote Web Server

By default, wp-env on the remote host binds to `localhost:8888`. If you cannot
access the remote web server directly at `http://REMOTE_HOST:8888/` due to
network restrictions, firewalls, or security requirements (e.g., HTTPS-only contexts),
you can create an SSH tunnel to forward the port locally.

##### SSH Tunnel Command

```bash
ssh -f -N -L 8888:localhost:8888 ${REMOTE_USER}@${REMOTE_HOST}
```

**Options explained**:

- `-f`: Fork into background after authentication
- `-N`: Do not execute a remote command (just forward ports)
- `-L 8888:localhost:8888`: Forward local port 8888 to remote's localhost:8888

##### Use Cases

**1. Network Firewall Blocks Port 8888**
If the remote host's firewall or network configuration blocks external access
to port 8888, the SSH tunnel creates a secure connection that bypasses these
restrictions.

**2. HTTP Blocked as Untrusted**
If your local browser or security policy blocks HTTP connections to the remote
host, the tunnel makes the remote server appear as if it's running locally.

**3. Secure Context Required**
Some browser APIs (Service Workers, geolocation, etc.) require a secure context
(HTTPS). While the tunnel itself doesn't provide HTTPS, you can combine it with
a local HTTPS proxy if needed.

**4. Multiple Remote Environments**
You can run multiple tunnels on different local ports to access multiple remote environments simultaneously:

```bash
# First remote environment
ssh -f -N -L 8888:localhost:8888 user1@host1
# Second remote environment
ssh -f -N -L 8889:localhost:8888 user2@host2
```

##### Accessing via Tunnel

After creating the tunnel, access the remote WordPress instance at:

```
http://localhost:8888/
```

To stop the tunnel:

```bash
# Find the SSH process
ps aux | grep "ssh -f -N -L"
# Kill the process
kill <PID>
```

Or use a specific SSH config with a control socket for easier management.

#### Testing Matrix

For comprehensive plugin testing, consider this matrix:

| PHP Version | WordPress Version | Notes |
|-------------|-------------------|-------|
| 8.0 | 5.6 | Minimum supported floor |
| 8.3 | 6.8 | Recommended baseline |
| 8.5 | 7.1 | Latest tested (PHP + WP ceiling) |
| 8.4 | trunk | Bleeding-edge WordPress |

Example test commands:

```bash
# Test the supported floor: PHP 8.0 with WP 5.6
./wp-env.sh -p 8.0 -w 5.6 start

# Test the ceiling: PHP 8.5 with WP 7.1
./wp-env.sh -p 8.5 -w 7.1 start

# Test against bleeding-edge WordPress
./wp-env.sh -p 8.4 -w WordPress/WordPress#trunk start
```

> Running the **PHP 8.0 / WP 5.6** floor exercises the no-`str_ends_with`
> compatibility path in `generate_challenge()` — a regression there would only
> surface on this oldest bench, so keep it in the rotation.

#### Older WordPress versions

When provisioning the minimum-supported stack (e.g. **PHP 8.0 / WP 5.6**), two
steps in [`tests/bin/wp-init.sh`](../tests/bin/wp-init.sh) have minimum-WordPress
requirements and will abort the `afterStart` hook unless adjusted:

- **Plugin Check** requires **WordPress 6.3+**. Comment out its install line
  (`wp plugin install plugin-check`) on older benches. Static analysis is not
  available on these versions anyway — run Plugin Check on a recent-WordPress
  bench (e.g. WP 7.0) and use the older bench for runtime verification only.
  Two `readme.txt` rules it enforces are easy to trip on release day: the
  `Tested up to:` header must be **major.minor only** (`7.0`, never `7.0.1`),
  and each `== Upgrade Notice ==` entry is capped at **300 characters** (the
  changelog itself has no such limit). See `docs/release-preparation.md`
  Phases 1 and 3.
- **Contact Form 7** installs the latest release by default, which fails the
  WordPress-version requirement on older cores. Pin a compatible version with
  `--version=`, e.g. `--version=5.3.2` for WP 5.6. (`wp-cli`'s
  `--ignore-requirements` flag is too recent to rely on in the bundled wp-env
  CLI, so an explicit `--version` is the dependable approach.)

On a minimum-version bench, run the **fresh-install verification only** — skip
the ALTCHA → OpenPorte upgrade scenario.

##### Why the bench hides PHP notices

`.wp-env.json` sets `WP_DEBUG_DISPLAY: false` and `WP_DEBUG_LOG: true` — notices
are recorded, never printed into the response. This is load-bearing, not tidiness.

With `WP_DEBUG` on and display enabled, a single notice from *any* third-party
plugin is echoed before headers go out, so the next `wp_redirect()` fails with
"Cannot modify header information — headers already sent" and the request dies
part-rendered. Every page on the site becomes an ~800-byte error stub, so every
E2E locator times out at 120 s and the suite looks like a mass OpenPorte
regression.

Observed on the WP 5.6 / PHP 8.0 bench: wpDiscuz 7.6.54 registers a block type
without a namespace prefix, which WP 5.6 rejects via `_doing_it_wrong()`. That
one notice took out 20 of the 21 failures across three drivers — wpDiscuz,
WPForms and both WooCommerce surfaces — none of which had anything wrong with
them. Read notices from `wp-content/debug.log` (or `./wp-env.sh logs`) instead.

#### Troubleshooting

**"Environment variables not applied"**

**Error**: wp-env ignores the PHP/WP version overrides

**Check**:

1. Verify the remote command includes the export statements by adding `-v` to SSH:
  `ssh -v ${REMOTE_USER}@${REMOTE_HOST} "export WP_ENV_PHP_VERSION=7.3 && echo test"`
2. Ensure wp-env respects `WP_ENV_PHP_VERSION` and `WP_ENV_CORE` environment variables
3. Test directly on the remote:
  `ssh ${REMOTE_USER}@${REMOTE_HOST} "export WP_ENV_PHP_VERSION=7.3 && echo \$WP_ENV_PHP_VERSION"`

**"Permission denied"**

**Error**: `Permission denied (publickey)`

**Solution**: Set up SSH key-based authentication:

```bash
ssh-copy-id ${REMOTE_USER}@${REMOTE_HOST}
```

**"rsync errors"**

**Error**: Various rsync permission or path issues

**Solution**: Ensure the remote directory exists and is writable:

```bash
ssh ${REMOTE_USER}@${REMOTE_HOST} "mkdir -p ~/path/to/remote/directory"
```

---

### Automated E2E suite (`tests/e2e`)

A Playwright **settings-matrix** suite drives a real browser against a running
wp-env bench, looping integrations × auto-verification mode × Floating UI — the
combinations too numerous to click through by hand. It solves the proof-of-work
for real (no mocks), so it also catches timing regressions such as the wpDiscuz
`auto="onsubmit"` race that prompted it.

```bash
./wp-env.sh start          # from the repo root — provisions the bench first
./wp-env.sh run cli -- wp plugin activate openporte # activate the plugin
cd tests/e2e && npm install && npm test
```

Per driver: 8 acceptance combos (auto ∈ {disabled, onload, onfocus, onsubmit} ×
floating ∈ {off, on}) plus two negative controls the server must reject —
**missing token** (widget stripped from the DOM, which also proves verification
is actually wired up) and **forged token** (real solve, then the payload's HMAC
corrupted). One suite-wide extra on the core-comments driver covers **expired
token replay**.

Drivers: WordPress core comments, Contact Form 7, wpDiscuz, WPForms Lite,
WooCommerce login and registration. The suite is **not shipped** (`tests/` is
excluded by `.distignore`). Full details, prerequisites and the rationale for
what is *not* driven are in [`tests/e2e/README.md`](../tests/e2e/README.md).

### Regression focus after verification changes

The security-hardening pass changed the token-verification path in
`includes/core.php` (see `docs/security-audit.md`). The E2E suite above covers
the happy path and the missing/forged/expired-token negatives; the following
still need a manual pass whenever `verify()`, `verify_solution()`,
`verify_server_signature()` or `decode_payload()` are touched, since they cover
cases the bench cannot drive:

- **Happy path unchanged:** a normally solved widget submission still verifies
  in self-hosted (proof-of-work) mode — acceptance test **(a)**.
- **Malformed input fails *quietly*:** submit a form with the `altcha` field
  missing or set to a non-base64 / non-JSON value. It must be rejected **and**
  produce **no** PHP warnings in `wp-env logs` (the point of the `decode_payload`
  hardening — previously these emitted "Attempt to read property on null").
- **Custom mode** (`custom` API mode with a signed
  `verificationData` backend): an **expired** (`expire` in the past) or
  not-**verified** payload is rejected; a normal unexpired/verified one is
  accepted; a **minimal** backend payload that omits `expire`/`verified`
  altogether still verifies (the checks are defensive, only-when-present).
- **Secret continuity:** a fresh install gets a 256-bit signing secret; an
  upgraded install keeps its existing secret unchanged (covered by the
  ALTCHA → OpenPorte migration test in `tests/README.md` — a changed secret
  would break previously issued challenges).

### The `altcha.min.js` widget dependency

`public/altcha.min.js` is the upstream ALTCHA web component, **vendored as-is**
and never edited (its version is tracked by `OPENPORTE_WIDGET_VERSION` in
`openporte.php`). We deliberately do **not** maintain or test the widget itself —
its proof-of-work algorithm, Svelte internals and own test suite are upstream's
responsibility. Our responsibility is strictly the **integration**: that the
widget loads, renders, solves a challenge from our endpoint, writes the solution
into the form's `altcha` field, and that our PHP `verify()` accepts it.

**Maintenance.** The upgrade procedure and the licensing-risk contingency live
in [`docs/agents/altcha-upstream.md`](agents/altcha-upstream.md): confirm the new
upstream release is still under an OSI-approved licence, replace the file, bump
`OPENPORTE_WIDGET_VERSION`, add a `readme.txt` changelog entry, and record the
"Last verified MIT upstream" SHA. Do **not** patch the vendored file; if a
security issue is found in the widget, the policy is to upgrade to a fixed
upstream release.

**Integration testing after a re-vendor.** Run these (browser + `wp-env logs`),
at least on the floor (PHP 8.0 / WP 5.6) and ceiling (PHP 8.5 / WP 7.1) benches:

1. The widget renders on a protected form (the `[openporte]` shortcode on
   **Test Page**, plus at least one form integration such as Contact Form 7).
2. In **self-hosted** mode it fetches the challenge from
   `/wp-json/openporte/v1/challenge` — confirm in the Network tab, and that there
   are **no** requests to `*.altcha.org`.
3. Solving succeeds, the form submits, the hidden `altcha` field is populated,
   and our PHP verification accepts it (entry created, no error).
4. **Negative:** omitting or tampering with the solution is rejected.
5. The companion `public/script.js` behaviour still holds — no duplicate widgets
   (the `MutationObserver`), and the checkbox `name` fix still applies — since it
   manipulates the widget DOM and is sensitive to upstream markup changes.
6. **Attribute-API compatibility (the main upgrade risk):** the eight attributes
   we emit in `get_widget_attrs()` (`challengeurl`, `strings`, `auto`,
   `floating`, `delay`, `hidelogo`, `hidefooter`, `name`) are still honoured by
   the new widget. If upstream renames or drops one, update **both**
   `get_widget_attrs()` and the `wp_kses` whitelist
   `OpenPortePlugin::$html_espace_allowed_tags`, or the attribute will be
   silently stripped on render. (The whitelist also permits `debug`, which the
   plugin never emits.)
7. The browser console is clean and `wp-env logs` shows no PHP notices.

New in widget 2.3.0, accepted but **unused** by OpenPorte (for the next
attribute audit): `disablerefetchonexpire`, `sentinel`, `plugins`,
`credentials`, `customfetch`, `overlay`/`overlaycontent`, `disableautofocus`,
`floatingpersist`, `language`. Upstream also deprecated `spamfilter` and
`blockspam` in 2.3.0 (documentation-only — no runtime warning in the bundle);
OpenPorte stopped emitting both in 1.28.0 when the spam-filter plumbing was
removed (#6), so that deprecation no longer concerns us.

**Don't trust the version string in the bundle.** Upstream commits a prebuilt
`dist/` and stamps the version at build time, so a release that doesn't rebuild
ships an older string: the 2.3.0 bundle still reports `2.2.4` (its `dist/` is
byte-identical to 2.2.4 — 2.3.0 only repackaged the unused
obfuscation/analytics/upload plugins into `@altcha/plugins` for a disputed
CVE). Verify a re-vendor
against `OPENPORTE_WIDGET_VERSION` and the SHA-256 in the provenance ledger, not
the embedded string — details in
[`docs/agents/altcha-upstream.md`](agents/altcha-upstream.md).

#### Bench provisioning notes (`wp-init.sh` tweaks per leg)

`tests/bin/wp-init.sh` targets the ceiling; the floor leg needs adjustments
(the script is tweaked often — record what and why here):

- **Floor (WP 5.6): pin Contact Form 7 to 5.4.2.** Current CF7 6.x calls
  `wp_is_serving_rest_request()` (added in WP 6.5) and fatals on every
  front-end request. CF7 5.4.2 (requires WP 5.5) works; on PHP 8.5 it logs
  `ArrayAccess` return-type deprecations — CF7's, not ours.
- **Plugin Check requires WP ≥ 6.3** — install it on the ceiling leg only
  (static plugin checks don't need to run on the floor).
- **Enable the form integration**: fresh installs default all integrations to
  off — `wp option update openporte_integration_contact_form_7 captcha` or the
  widget is never injected into the CF7 form.
- **Turn off `WP_DEBUG_DISPLAY`** (`wp config set WP_DEBUG_DISPLAY false
  --raw`, keep `WP_DEBUG_LOG`): with display on, notices are prepended to REST
  bodies, which corrupts the challenge JSON and breaks the widget itself, not
  just tooling.
- **Run `wp-env cleanup` between WP-version switches** — the database volume
  persists, and a newer install's active theme (e.g. `twentytwentyfive`)
  renders blank pages on an older core. The `cleanup`/`destroy` confirmation
  prompt needs a real TTY (`ssh -t …`) or use `--force`; it cannot be answered
  through `wp-env.sh`.
- For expiry testing, `wp option update openporte_expires 60` makes both the
  widget's `refetchonexpire` and the server-side expired-token rejection
  observable within a minute.

---

### Testing tools

#### wp-env.sh

The `wp-env.sh` script is a wrapper around
[`@wordpress/env`](https://github.com/WordPress/wordpress-develop/tree/trunk/tools/wp-env)
(wp-env) that facilitates remote testing of the OpenPorte plugin across
different PHP and WordPress versions.

##### Why This Exists

The official wp-env tool runs Docker containers locally. However, for this project we:

1. Need to test on a remote server with specific configurations
2. Want to quickly switch between PHP versions (7.3, 7.4, 8.0, 8.1, 8.2, etc.)
3. Want to test against different WordPress Core versions
4. Need to synchronize the local plugin code to the remote test environment

This script wp-env.sh automates the rsync-and-ssh workflow while providing a clean
CLI interface for version overrides. This is used by the maintainer because the
Docker host is on a remote test machine and not local.

##### Architecture

```
Local Machine                Remote Host
     │                           │
     │  1. Parse CLI args        │
     │  2. rsync code            │
     │───────────────────────────▶│
     │                           │
     │  3. SSH with inline       │
     │    variable export        │
     │───────────────────────────▶│
     │                           │
     │  4. (Optional) SSH tunnel │
     │◀───────────────────────────│  <- Local:8888 → Remote:8888
     │                           │
     └────────────────────────────┘
           ↓
     Remote runs: export WP_ENV_*=
                 source ~/.wpenvrc
                 cd ~/REMOTE_PATH
                 wp-env [args]
```

##### Environment Variable Injection

The script exports environment variables inline in the remote shell command
using the `${var:+value}` bash parameter expansion pattern. This approach was
chosen because the previous `ssh -o SetEnv` method required specific `AcceptEnv`
configuration in the remote sshd server, which cannot be assumed across different
hosting environments.

**Example**: If `--php-version 7.3` is specified, the remote command becomes:

```bash
ssh user@host "export WP_ENV_PHP_VERSION=7.3 && source ~/.wpenvrc && cd ~/path && wp-env start"
```

If no overrides are specified, the export statement is omitted entirely.

**Advantages**:

- Works on any SSH version (no OpenSSH ≥ 7.8 requirement)
- No dependency on remote sshd configuration
- Automatic cleanup (variables are session-scoped)
- Simple and direct implementation

##### Implementation Details

**Option Parsing**

The script processes all arguments to extract version overrides and flags before passing the remainder to wp-env:

```bash
VERBOSE_MODE=false

while [[ $# -gt 0 ]]; do
  case "$1" in
    --php-version|-p)
      PHP_VERSION_OVERRIDE="$2"
      shift 2
      ;;
    --wp-version|-w)
      # Auto-prepend WordPress/WordPress# if not already present
      if [[ "$2" != *"#"* ]]; then
        WP_VERSION_OVERRIDE="WordPress/WordPress#$2"
      else
        WP_VERSION_OVERRIDE="$2"
      fi
      shift 2
      ;;
    --verbose|-v)
      VERBOSE_MODE=true
      shift
      ;;
    *)
      REMAINING_ARGS+=("$1")
      shift
      ;;
  esac
done
set -- "${REMAINING_ARGS[@]}"
```

**Key design decisions**:

1. Version options can appear **anywhere** in the command line (not just at the beginning)
2. If an option appears multiple times, the **last occurrence wins** (standard POSIX behavior)
3. Options are **consumed** and not passed to wp-env
4. WordPress version accepts both short format (`6.5`) and full format (`WordPress/WordPress#6.5`)
5. Verbose mode is a flag (no argument) that enables detailed output after start

**Remote Command Construction**

The environment variables are set inline in the remote shell command using the
`${var:+value}` bash parameter expansion pattern, which expands to `value` only
if `var` is non-empty:

```bash
cd ~/${REMOTE_PATH} && \
 source ./.wpenvrc && \
 ${PHP_VERSION_OVERRIDE:+WP_ENV_PHP_VERSION=$PHP_VERSION_OVERRIDE} \
 ${WP_VERSION_OVERRIDE:+WP_ENV_CORE=$WP_VERSION_OVERRIDE} \
 wp-env $*
```

This approach:

- Omits the variable assignments if no overrides are specified
- Sets only the variables that have been set
- Works on any SSH version without requiring sshd configuration
- Variables are set after sourcing `.wpenvrc` but before running `wp-env`, allowing overrides

**Remote Command Execution**

```bash
ssh ${REMOTE_USER}@${REMOTE_HOST} \
  "export ${PHP_VERSION_OVERRIDE:+WP_ENV_PHP_VERSION=$PHP_VERSION_OVERRIDE} \
          ${WP_VERSION_OVERRIDE:+WP_ENV_CORE=$WP_VERSION_OVERRIDE} \
   && source ~/.wpenvrc && cd ~/${REMOTE_PATH} && wp-env $*"
```

Note: The remote command:

1. Exports the override variables (if any)
2. Sources `~/.wpenvrc` (which may contain default environment variables)
3. Changes to the project directory
4. Runs wp-env with all remaining arguments

The inline export variables take precedence over variables defined in `~/.wpenvrc`.

##### Configuration (Remote Host)

The remote host must have `~/.wpenvrc` which is automatically sourced before running
wp-env. It can contain default environment variables:

```bash
# Example ~/.wpenvrc on remote
export WP_ENV_PHP_VERSION="8.1"
export WP_ENV_CORE="WordPress/WordPress#7.0"
export WP_ENV_PORT="8888"
```

Variables set via `--php-version` or `--wp-version` will **override** these defaults.

Additionally, the remote host requires:

- **wp-env**: Must be installed and available in PATH
- **Docker**: wp-env requires Docker to be running
- **SSH access**: The local machine must have password-less SSH access
- **Bash**: Remote shell must be bash (for `~/.wpenvrc` sourcing)

##### Implementation Notes

The current implementation using inline `export` with `${var:+value}` parameter
expansion was chosen because it:

- Works on any SSH version (no OpenSSH ≥ 7.8 requirement)
- Does not require any special sshd configuration on the remote host
- Is simpler and more direct than the previous `ssh -o SetEnv` approach
- Automatically handles the case where no overrides are specified

The `${var:+value}` syntax expands to `value` only if `var` is non-empty, allowing
conditional inclusion of export statements without complex control flow.

If you need to support older OpenSSH versions, this can be added as a fallback.

##### Future Enhancements

Potential improvements for the script:

1. **More options**: Add `--mysql-version`, `--port`, etc.
2. **Dry-run mode**: Add `--dry-run` to show what would be executed without running
3. **Verbose mode**: Add `-v` for detailed output
4. **Configuration validation**: Validate PHP/WP version formats before execution
5. **Parallel testing**: Support running multiple test environments simultaneously

##### See Also

- [wp-env Documentation](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/)
- [Bash Parameter Expansion Guide](https://www.gnu.org/software/bash/manual/html_node/Shell-Parameter-Expansion.html)
- [OpenPorte Plugin](../readme.txt)
