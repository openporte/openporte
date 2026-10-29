# AGENTS.md

> **v0 — starting point.** Refine this file as you learn the codebase. Delete anything
that turns out to be wrong.

## How to use this file

References like `@docs/agents/*.md` are **lazy-loaded**: read the linked file with
your Read tool only when the current task makes it relevant (e.g., load
commit-conventions.md when committing, not on every turn).

## About Skills

This repo uses the [Agent Skills Specification](https://agentskills.io/specification).
Suggest a new skill when a task is recurring, multi-step, domain-specific, or needs
consistency. Before creating one, verify it's project-specific, reusable, has clear
inputs/outputs/success criteria, and follows the spec. Skills live in
`.agents/skills/`, one directory per skill (see its README).

## Memory

Durable, cross-session facts about how to work with the maintainer live in
`.agents/memory/`: one self-contained Markdown file per memory, kebab-case
name. Discover with `glob .agents/memory/*.md`; read them all at session
start — they are short by design.

Each file: an H1 title, an `Added: YYYY-MM-DD` line, then the rule.
Write the rule in the imperative — authoritative, concise, unambiguous, no
room for interpretation. Plain Markdown only: agent- and tool-agnostic.

Scope: how the agent shall interact with the user (preferences, working
agreements, session decisions that must survive). Project conventions
(commit style, static analysis, coding rules) belong in `docs/agents/`,
repository facts in this file — not here.

Add a memory when you learn something about the user that any future session
will need; do not pad the store with trivia.

## What this repo is

Community reconstruction of the retired official ALTCHA WordPress plugin. The upstream
GPL project has been removed from GitHub by its original author — there is no live
upstream to reference or merge from. **We are the canonical source.** Style and
structure decisions are ours alone. License: GPLv2 or later.

Pure PHP WordPress plugin at runtime — no build step is required to run it. There is no
CI, but there are two automated suites you can run yourself: a PHPUnit unit suite
(`tests/phpunit/`, `npm run test:unit`) and a Playwright browser suite (`tests/e2e/`,
against the wp-env bench). Composer (dev-only: `phpcs`/`phpmd`/`phpstan`/`phpunit`) and
npm (`bin/release/*.sh` release-prep scripts, see `docs/release-preparation.md`) are dev
tooling, not shipped — both are excluded via `.distignore`.

Compat floor: PHP/WP minimums in `readme.txt` (see field `Requires at least` for the
minimum WordPress version; and field `Requires PHP` for the minimum PHP version). Don't
use syntax/APIs newer than the floor unless justified and acknowledged by the maintainer.

## Auditing upstream ALTCHA v1 (plugin history)

For "what did the original plugin actually do" questions, don't rely on
memory or docs — audit the source. Two ways:

- **In this repo:** branch `altcha-spam-protection-gpl-1.x` (local and on
  origin) imports the full WordPress.org release history — one commit per
  release, 0.3.2 → 1.26.3 (the last GPL v1) → 3.0.0. The final commit is the
  upstream "kill switch": 3.0.0 replaced the entire GPL plugin with a stub
  that downloads the non-free v3 from GitHub. Diff the two topmost commits to
  see it; diff against the 1.26.3 commit for v1-behavior questions.
- **WordPress.org SVN:** `https://plugins.svn.wordpress.org/altcha-spam-protection/`
  (e.g. `svn export …/tags/1.26.3`). Gotcha: the tags listing sorts
  lexicographically, so `1.26.x` appears *before* `1.6.x` — don't read the
  tail of the list as "the newest tags".

(The bundled *widget* has its own upstream doc: @docs/agents/altcha-upstream.md.)

## Scope: paid-plugin integrations being removed

   Integrations targeting paid-only plugins (Enfold; check others in
   `integrations/` against their authors' licensing) are scheduled for removal
   from this fork. Users should migrate to the official ALTCHA plugin (v2/v3).

   Until removed, commits modifying these files should use the `Deprecate` verb.

## Verification protocol

There is no CI. Two suites are run by hand, cheapest first:

- **PHPUnit** (`tests/phpunit/`, `npm run test:unit`) — seconds, no WordPress and
no Docker: it runs against a small WordPress stand-in (`tests/phpunit/wp-shim.php`).
Run it on any change to verification, the replay counter, a sanitizer or a
health-check evaluator. **Extend it when you change logic-heavy code** and a test
would genuinely pin the behaviour — coverage where it helps, not as a metric.
Its limit is exactly its speed: a fake `$wpdb` proves nothing about MySQL, so
anything depending on real database semantics (the replay counter's atomicity
under concurrency above all) belongs on the bench instead.
- **Playwright** (`tests/e2e/`, against the wp-env bench — see
`tests/e2e/README.md`) — a browser E2E matrix (integrations × auto-mode ×
Floating UI) plus a replay-limit suite. Run it when touching widget rendering,
verification, or integration code.

Beyond that, before and after changes:

1. Use [`wp-env`](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/)
(requires Docker — OrbStack or Docker Desktop on macOS) to spin up a local WordPress
instance and test manually.
2. Tail `wp-env`'s PHP error log (`wp-env logs`) after any change — PHP warnings and
notices surface here and are otherwise silent.
3. For integration changes, activate the relevant third-party plugin in `wp-env` and
exercise the affected form.

## Entry point and load order

`openporte.php` is the sole entry point. It `require`s files in this order, which matters:

1. `includes/helpers.php`
2. `includes/core.php` — instantiates `OpenPortePlugin` singleton immediately on load
3. `public/widget.php`
4. All 13 files under `integrations/` — each self-registers its hooks at `require` time

Each integration file registers hooks unconditionally at load; the callbacks themselves
check `OpenPortePlugin::$instance->get_integration_*()` to decide whether to act.

## Coding conventions

- **Singleton access:** always `OpenPortePlugin::$instance`. Never call `new OpenPortePlugin()`.
- **WP options keys:** all defined as `static` properties on `OpenPortePlugin` (e.g.,
`OpenPortePlugin::$option_api`). Never hardcode the raw option string `"openporte_*"`
anywhere — always reference the property. (The legacy `altcha_*` keys live only in the
activation-time migration map.)
- **i18n:** most user-facing strings use `__()` / `esc_html__()`. Exceptions exist (see
fix-mes below) — follow the existing pattern when adding new strings.
- **Static analysis**: only necessary for code changes, then read `@docs/agents/static-analysis.md`.

### i18n discipline (apply on every change)

- All user-facing strings must be wrapped in a translation function
  (`__()`, `esc_html__()`, `esc_attr__()`, …) with the text domain.
- Any string containing a placeholder (`%s`, `%d`, `%1$s`, …) MUST be preceded
  by a `/* translators: … */` comment describing each placeholder. See the
  `get_translations()` footer string for the existing pattern. Plain strings
  with no placeholder do NOT need such a comment.
- Adding or changing a user-facing string invalidates its existing translation
  and requires the `.pot` template to be regenerated. Note this in the commit
  so translations are refreshed.
- Producing/refreshing translations (the WP-CLI workflow, glossary, and
  LLM-assisted procedure) is documented in `@docs/agents/i18n.md`.

### WordPress Coding Standards & inline documentation

This project follows the [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/)
(PHP, JS, CSS) and its inline-documentation conventions — but **touch-scoped
only**: never a drive-by mass reformat of code a task doesn't otherwise
require touching. When you do touch a function/method/class/hook to
implement a change or fix, bring that unit into line with WPCS as part of the
change (behavior-preserving; indentation stays 2-space, matching this repo's
existing convention). New symbols always get a docblock; symbols you modify
get one added or updated; symbols you don't touch get left alone. New
symbols are tagged `@since <in-progress version>`; existing undocumented
symbols you touch get `@since` backfilled from git history, no further back
than 1.26.3. Full rules and rationale: @docs/agents/coding-style.md.

### Comment what you touch

When you modify a branch, fallback, or workaround whose intent is not
self-evident, add a short inline comment explaining the *why*, not the *what*.
Example: in `get_challengeurl()`, the final `else` is annotated as the
self-hosted default and graceful fallback for legacy `eu`/`us` DB values.
Prefer a one-line intent comment over leaving future readers (human or agent)
to re-derive the logic.

## Version bump checklist

Five locations must change atomically or the plugin breaks:

| File | Field |
| ---- | ----- |
| `openporte.php` | `* Version:` in header |
| `openporte.php` | `* Stable tag:` in header |
| `openporte.php` | `define('OPENPORTE_VERSION', ...)` |
| `readme.txt` | `Version:` in header |
| `readme.txt` | `Stable tag:` + new `= X.Y.Z =` changelog entry |

`OPENPORTE_VERSION` is also used as the cache-busting query string for all enqueued assets.

## `public/altcha.min.js` — vendored, do not edit

Vendored from [`altcha-org/altcha`](https://github.com/altcha-org/altcha) (MIT
at last upgrade). Version tracked by `OPENPORTE_WIDGET_VERSION` in `openporte.php`.

For upgrades and licensing-risk contingency (only load on a need-basis):
@docs/agents/altcha-upstream.md

## Known gotchas

**Login and lost password — dual registration, one owner per request.**
`integrations/wordpress.php` and `integrations/woocommerce.php` both check the login
challenge twice: on `wp_authenticate_user` (before core compares the password — the
check that matters, see `docs/security-audit.md` #14) and on `authenticate` at priority
20 (a backstop for providers that skip that filter; the verify memo makes the second
check free). Both also hook `lostpassword_post`. Which file owns a request is decided
only by `openporte_is_woocommerce_form_submission()` in `includes/helpers.php`: the
WooCommerce nonce field counts only when WooCommerce is active and the request is not
`wp-login.php` (`login_init` not fired). Never route on the bare field again (#15). If
WooCommerce renames its nonce fields, its forms fall back to the WordPress handlers.
Keep both files in sync when changing auth logic.

**`integrations/coblocks.php` — intentional reCAPTCHA spoof.**
CoBlocks has no extension API, so the integration fakes a reCAPTCHA token and intercepts
the outbound HTTP verification call via `pre_http_request`. This is deliberate. The
intercept matches on `CoBlocks_Form::GCAPTCHA_VERIFY_URL` — if that constant changes
in a CoBlocks update, all CoBlocks forms silently break.

**Challenge endpoints — `Cache-Control` alone does not stop page caches.**
LiteSpeed Cache caches REST responses by default (7 days) and decides through
its own `X-LiteSpeed-Cache-Control` header, ignoring ours. One cached challenge
then serves every visitor until long past its expiry, and no protected form
goes through (#133). Both challenge endpoints therefore call
`openporte_prevent_page_caching()` (`DONOTCACHEPAGE` plus LiteSpeed's
`litespeed_control_set_nocache` action) before anything else. Any new code
path that serves a challenge must call it too. wp-env runs Apache, where
LiteSpeed Cache never caches, so the bench cannot reproduce this.

**`has_active_integrations()` / `get_integrations()` are deprecated dead code.**
Both deprecated in 1.28.0, removal at the next major (#62). The "only enqueue scripts
on pages with active integrations" gate they served was removed upstream in 1.21.0,
so they have had no caller since. Do not wire new code to them, and do not "fix"
their incomplete integration list (Enfold Theme and WP-Members are missing) —
it is frozen with the deprecation. The "Custom HTML" integration
(`integrations/custom.php`, `get_integration_custom()`) is deprecated on the
same schedule.

**Fix on sight when touching adjacent code (call out in commit message):**

- The core.php defects previously listed here — the untranslated "requires
  JavaScript" string, the missing `is_wp_error()` guard, and the `===` HMAC
  comparison — were fixed in 1.27.0. When touching the HMAC path, preserve the
  `true` (raw binary) flag on `hash('sha256', …, true)`; removing it breaks all
  challenge verification.
- Read the file `local/Security_Analysis.md` for security issues that need
  correction (not published online).

## Release

Push a git tag. The `.github/workflows/publish.yml` workflow deploys straight to
WordPress.org SVN. No manual steps.

Branching model, versioning policy, and the patch/minor release lifecycle live in
@CONTRIBUTING.md. The step-by-step cut-a-release runbook is `docs/release-preparation.md`.

## Commit conventions

Imperative verb prefix (`Add`, `Fix`, `Update`, `Remove`, `Refactor`, `Docs`,
`Bump`, `Deprecate`, `Revert`), ≤72 chars, no trailing period. Issue refs in
body footer: `Fixes #123` to auto-close, `Refs #123` otherwise.

Full conventions and examples (load on a as-needed-basis): @docs/agents/commit-conventions.md
