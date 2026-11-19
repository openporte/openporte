# Contributing to OpenPorte

OpenPorte is a community reconstruction of the retired official ALTCHA WordPress
plugin. There is no live upstream — **we are the canonical source**. License:
GPLv2 or later.

This document covers **how releases flow** (branching, versioning, lifecycle) and
the **commit/PR conventions**. For the mechanical step-by-step of cutting a
release, see [`docs/release-preparation.md`](docs/release-preparation.md); for
coding rules and the manual verification protocol, see [`AGENTS.md`](AGENTS.md).

## Branching model — single trunk, linear

Development is **single-track**. There is exactly one long-lived branch, `main`.
We do **not** keep parallel version branches (`v1.27`, `v1.28`, …) — those names
refer to *version phases*, not branches. This keeps history linear
(`v1.27.0 → … → v1.27.x → v1.28.0 → …`) and avoids parallel maintenance we don't
have the resources to carry.

- All work happens on **short-lived branches off `main`**, merged back via PR.
- Use descriptive prefixes: `feature/…`, `fix/…`, `docs/…`, and `release/x.y.z`
  for release-prep branches (see the runbook).
- `main` is protected — never commit a release directly to it.

## Release lifecycle — two phases

A version line moves through two phases. The boundary is **WordPress.org
acceptance** of the current line.

**Phase A — patch line (the current `x.y` while it is in / awaiting review).**
`main` accepts **patch-level work only**: bug fixes, security fixes, and
WordPress.org review-requested changes. Each ships as `x.y.Z`.

- Keep each release **small** while a submission is under review, so the reviewer
  has little to re-check. **Review-requested fixes take priority** and ship ahead
  of other queued patches.
- Larger queued patch work (e.g. a security-hardening branch) **waits** until the
  review queue is settled, then merges as a later `x.y.Z`.

**Phase B — next minor line (after acceptance).** The first feature merge bumps
`main` to `x.(y+1).0`. From that point, **no further work happens on the previous
line** — that is the freeze. Feature branches that were waiting now land here.

### Hotfix escape hatch (rare)

If a fix is ever needed for an already-frozen line *after* the next minor has
shipped, cut a one-off `hotfix/x.y.z` branch **from the `vx.y.z` tag**, release
it, and delete the branch. This is the only sanctioned departure from linear
history — there is no standing parallel branch.

## Versioning — semantic versioning

| Bump | When |
| ---- | ---- |
| **patch** `x.y.Z` | bug/security fix, **no** user-visible behaviour change |
| **minor** `x.Y.0` | new integration or opt-in feature (backward compatible); also additive translations and a backward-compatible widget re-vendor |
| **major** `X.0.0` | breaking change: dropping a PHP/WP floor, or **removing a public hook/integration past its deprecation window** |

Removing an integration is breaking for its users: **deprecate it in a minor**
(use the `Deprecate` commit verb) and **remove it in the next major**.

The bundled ALTCHA widget has its own version (`OPENPORTE_WIDGET_VERSION` in
[`openporte.php`](openporte.php)) that moves **independently** of the plugin
version — bump it only when `public/altcha.min.js` is re-vendored (see
[`docs/agents/altcha-upstream.md`](docs/agents/altcha-upstream.md)).

The five version-string locations must change atomically — see the version-bump
checklist in [`AGENTS.md`](AGENTS.md) and Phase 1 of the release runbook.

### Tag naming and the pre-push hook

Release tags are `vMAJOR.MINOR.PATCH` (e.g. `v1.27.2`). The publish workflow
derives the WordPress.org version by stripping the leading `v`, and checks it
against the readme `Stable tag`, so a tag that doesn't follow the convention
would break (or silently skip) the deploy.

The repository ships a `pre-push` hook ([`.githooks/pre-push`](.githooks/pre-push))
that rejects non-conforming tag pushes locally. Git hooks are **not** installed
automatically — enable them once per clone:

```bash
git config core.hooksPath .githooks
```

This is a client-side guard and can be bypassed with `git push --no-verify`;
the publish workflow re-validates the tag shape server-side regardless.

The hook also recognises a 4-part `vMAJOR.MINOR.PATCH.N` tag — the GitHub-only
re-release convention for shipping a corrected release asset *without*
redeploying to WordPress.org. It blocks these by default (so they can't be
pushed by accident) and tells you to use `--no-verify` when you mean it. See
[`docs/release-preparation.md`](docs/release-preparation.md) →
"Recovering from a bad GitHub Release asset".

## Tracking the plan

Open work is slotted onto **GitHub milestones** — one per planned release
(e.g. `v1.28.0`), plus a `future` bucket for accepted-but-unscheduled ideas.
These serve as the living release plan. An issue gets a release milestone once its
scope and target version are decided; issues still under discussion stay on
`future` (or unmilestoned).

## Code quality checks

Three PHP analysers cover the plugin. Two of them run in CI and all three run
locally — same ruleset, same scope, same versions. Install the dev tooling once
per clone:

```bash
composer install
```

That is the only prerequisite; the `npm run` wrappers below are thin shell
scripts and need no `npm install`.

| Command | What it checks | CI workflow | Blocking? |
| ------- | -------------- | ----------- | --------- |
| `npm run lint:phpcs` | WordPress security/correctness sniffs and PHP cross-version compatibility, per [`phpcs.xml.dist`](phpcs.xml.dist) and [`phpcs-demo.xml.dist`](phpcs-demo.xml.dist) | `.github/workflows/phpcs.yml` | **Yes** — a finding fails the PR |
| `npm run lint:phpmd` | Complexity, dead code and design smells, per [`phpmd.xml.dist`](phpmd.xml.dist) | `.github/workflows/phpmd.yml` | No — advisory, uploaded to the repo's Security tab |
| `npm run lint:phpstan` | Type correctness — wrong argument types, impossible conditions, unknown symbols — per [`phpstan.neon.dist`](phpstan.neon.dist) | `.github/workflows/phpstan.yml` | **Yes** — a *new* finding fails the PR |

PHPCS runs the plugin and demo separately so each accepts only its own text
domain. Both configurations share `bin/lint/phpcs-common.xml`.

All three workflows invoke exactly what you run locally (PHPMD via
[`bin/lint/phpmd.sh`](bin/lint/phpmd.sh), PHPStan via
[`bin/lint/phpstan.sh`](bin/lint/phpstan.sh)), and all three analysers are pinned by
`composer.lock`, so a finding on your machine is a finding in CI and vice
versa. Don't run `phpmd` or `phpstan` directly — neither config file can carry
what its invocation needs. A bare `phpmd .` also walks `vendor/` (the ruleset
cannot express path exclusions) and produces hundreds of findings CI never sees;
a bare `phpstan analyse` runs out of memory parsing the WordPress stubs and dies
with a misleading "Child process error (exit code 255)". The wrapper scripts
under [`bin/lint/`](bin/lint/) are the single source of truth for both.

PHPStan sees WordPress core through `php-stubs/wordpress-stubs`, wired in via
`scanFiles` in `phpstan.neon.dist`. It is deliberately *not* using the usual
`szepeviktor/phpstan-wordpress` extension: that package's current release caps
the stubs at `^6.6.2` while this project tracks `^7.0`, so Composer cannot
resolve the two together. Worth revisiting when it tags a stubs-7 release.

PHPStan runs at level 5 and **gates the PR**, which works because
[`phpstan-baseline.neon`](phpstan-baseline.neon) absorbs the two findings that
predate its introduction (deferred to the `future` milestone, tracked in
[#77](https://github.com/openporte/openporte/issues/77)). Only a *new* finding
turns the check red. Unlike the PHPMD backlog below, the list is short enough to
hold at zero-new.

When the check goes red, there are exactly two right moves — the same choice a
`phpcs` finding gives you:

1. **Fix it.** The default. PHPStan findings are usually a real type error.
2. **Justify it**, if it is a genuine false positive: add an `ignoreErrors` entry
   to `phpstan.neon.dist` with a comment saying why, as narrowly scoped as you
   can manage (by `identifier` and `path`, not a bare message match). This is the
   same convention as a documented `phpcs:ignore`, and it is reviewable — the
   justification sits next to the suppression in the diff.

What is *not* a right move is regenerating the baseline. That silently promotes a
new finding to permanent debt, with no note of what it was or why it was
accepted. The baseline is a one-off record of where the analyser started, not an
inbox; it shrinks as #77 is worked through and disappears with the last finding.
If a finding is real but genuinely not for now, file an issue and fix it there —
don't bury it.

Two things to know before reading PHPMD output:

- **It exits `2` when it reports violations.** That is normal, not a crash.
- **There is a standing backlog of pre-existing findings** — the long
  `openporte_settings_init()`, the deprecated `openporte_settings_field_callback()`,
  the size of the `OpenPortePlugin` singleton. When reviewing your own change,
  compare against `main` rather than expecting a clean run. Complexity findings
  are judgement calls: the maintainer decides what gets refactored and what is
  justified, which is why this one is advisory.

`phpcs` also ships a fixer for the mechanical subset: `vendor/bin/phpcbf`.
Note that the ruleset is deliberately scoped to security and correctness, not
whole-file formatting — see the rationale at the top of `phpcs.xml.dist`, and
the touch-scoped style policy in
[`docs/agents/coding-style.md`](docs/agents/coding-style.md) before reformatting
anything you aren't otherwise changing.

## Commits and pull requests

Commit messages follow [`docs/agents/commit-conventions.md`](docs/agents/commit-conventions.md):
an imperative, capitalized verb prefix (`Add`, `Fix`, `Update`, `Remove`,
`Refactor`, `Docs`, `Bump`, `Deprecate`, `Revert`), ≤72 chars, no trailing
period. Reference issues in the body footer — `Fixes #123` to auto-close on merge,
`Refs #123` for context.

Before opening a PR, run the checks above and the verification protocol in
[`AGENTS.md`](AGENTS.md). There is no CI test suite, but two suites are run by
hand: `npm run test:unit` (PHPUnit, seconds, no WordPress or Docker needed) and
the browser E2E suite in [`tests/e2e/`](tests/e2e/) against the `wp-env` bench.
Neither replaces the bench — changes are still validated there (`php -l` on
changed PHP, a clean `wp-env logs`, and the relevant acceptance steps under
[`docs/acceptance/`](docs/acceptance/)).

The PR template (`.github/PULL_REQUEST_TEMPLATE.md`) asks for two things every
PR must settle **at review time**, not deferred to release prep: a "Docs
updated" checklist (so `docs/architecture.md`, `AGENTS.md`, etc. don't drift
out of sync with the change), and a "Changelog entry" — the exact bullet(s)
this change earns, in user-facing language. Reviewers should treat a missing
or wrong changelog entry as a blocking comment, the same as a missing test
plan. At release time, `docs/release-preparation.md` Phase 3 collects these
entries straight from the merged PRs' descriptions into `readme.txt` and the
GitHub release notes, instead of reconstructing them from `git log`.
