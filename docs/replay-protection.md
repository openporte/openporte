# Replay protection (the Replay limit)

> Maintainer / contributor reference for the replay work that shipped in
> **1.29.0**. It collects, in one public place, the design reasoning that was
> spread across a long private planning cycle for
> [issue #99](https://github.com/openporte/openporte/issues/99): why solved
> tokens were replayable, why the fix looks the way it does, what it deliberately
> does *not* do, and how much of it has actually been proven.
>
> Two shorter treatments already exist and stay authoritative for their own
> purpose: [`docs/architecture.md`](architecture.md) describes where the
> mechanism sits in the codebase ("Stateless primitives, stateful wrapper"), and
> [`docs/security-audit.md`](security-audit.md) carries finding #1 with its
> grade and residual-risk arithmetic. This document is the long form behind both.

## The weakness

An ALTCHA proof-of-work token is a base64 JSON envelope carrying the challenge,
the salt, the solved number, the algorithm and an HMAC signature. Through
1.28.1, `verify_solution()` checked the algorithm, recomputed the hash, checked
the signature and — when the salt carried an `?expires=` parameter — checked the
expiry, and then returned `true`. It kept **no record of which solutions it had
already accepted**.

So one solved proof-of-work was a reusable credential. A bot could solve a
challenge once and replay that exact payload across every protected form until
the token expired. With Expiration set to "None" (`0`), it never expired. The
proof-of-work cost, which is the entire anti-automation argument, was paid once
and amortised over unlimited submissions.

The 1.26.3 changelog entry "Fixed possible replay attacks via salt splicing"
refers to a narrower salt-parsing bug and never addressed one-time use.

## The two axes any fix has to get right

The planning cycle reviewed four candidate designs. Two properties separated the
ones that work from the ones that only look like they work.

**Atomicity.** The obvious implementation — read a counter from a transient,
compare it to the limit, write it back — is a read-modify-write with a gap in
the middle. Under a parallel burst, which is precisely the shape of a replay
attack, every worker reads the same stale count and every worker passes. The
bound breaks exactly when it is needed. Only a store that can check and
increment in one indivisible step holds.

**Key identity.** The HMAC signs the `challenge` string only. Verification
decodes the JSON envelope before it looks at any field, so re-encoding the same
solved challenge into different bytes — reordered keys, different whitespace —
produces a payload that still verifies. A counter keyed on the raw payload is
therefore trivially defeated by re-encoding. The key has to be a field the HMAC
has already vouched for.

## What was asked for, and what 1.29.0 delivers

Issue #99 set three requirements:

| | Requirement | 1.29.0 |
| --- | --- | --- |
| **R1** | `0`/None and very short expiry values not selectable | **Advisory only.** Both still save; both raise a notice. Enforcement is [#103](https://github.com/openporte/openporte/issues/103). |
| **R2** | Bounded reuse, independent of expiry | **Met** for every token that carries an expiry. A token without one gets a repeating 4-hour window instead of a lifetime bound. |
| **R3** | Legitimate resubmission of a still-valid token keeps working | **Met** at the default limit of 5. At limit 1 an inter-request resubmit is refused; see the trade-off below. |

R1 became advisory *because* R2 landed. Once reuse is bounded independently of
expiry, a short expiry is no longer the primary replay control, so a breaking
config change could wait for its own release rather than ride on this one.

## Custom mode: why protection has to be local

This was the least obvious result of the design cycle, and it corrected an
earlier analysis that had it backwards.

In Custom mode the widget fetches a standard proof-of-work challenge from the
operator's backend, solves it in the browser, and OpenPorte verifies that
solution **itself** with `verify_solution()` and the shared secret. There is no
server-to-server call at verify time — the plugin's only outbound request
anywhere is the settings-page health check. A backend's own verify-and-consume
endpoint (GateCHA's `/api/v1/verify`, for instance) is never called.

The consequence: a custom backend's replay protection, however good, has never
applied to OpenPorte's forms. Even a backend that single-uses each *challenge*
cannot stop replay of an already-solved *token* to a WordPress form endpoint,
because the party that accepts the proof is the only party that can consume it,
and that party is `verify()`. Custom-mode replay was therefore the same replay
as self-hosted, minus OpenPorte's control over the expiry.

That is why enforcement sits *after* the dispatch in `verify()`, keyed on the
signature both payload shapes carry. Self-hosted, custom and the dormant
server-signature path are all bounded by OpenPorte-owned state, with no
dependency on the backend being stateful and no protocol change. It works with a
minimal backend.

## The mechanism

`verify()` became a stateful policy wrapper around two primitives that stay pure
cryptography. It runs, in order:

1. **Payload memo** — a per-request cache keyed on the submitted bytes *and* the
   HMAC key, so a caller verifying one payload against two secrets in a request
   gets two real answers.
2. **Decode and dispatch** to `verify_server_signature()` (payload carries
   `verificationData`) or `verify_solution()` (everything else).
3. **Signature memo** — a second per-request cache keyed on the verified
   signature, so the same solved challenge re-encoded into different bytes still
   counts as one use.
4. **`enforce_replay_limit()`** — reached only on full cryptographic success.
5. **One `openporte_verify_result` action** per call.

The memo makes verification idempotent within a request. That is what lets the
counter work at strict limit 1: WordPress's login flow fires `authenticate`
callbacks that could each verify the same token, and they must not spend two
slots. It is cleared on `init` by `reset_request_state()`, so persistent-worker
SAPIs (FrankenPHP, RoadRunner, Swoole) cannot leak one visitor's accepted token
into the next visitor's request.

### The counter

**Key.** The first 32 hex characters of `sha256($data->signature)`, prefixed
`openporte_replay_`. The signature is HMAC-verified before the counter is
touched, so the key is neither forgeable nor sensitive to JSON encoding. Nothing
about the visitor is stored — no IP address, no `$_SERVER` value.

**Lifetime.** The token's own remaining validity, read through the shared
`payload_expires()` helper that also feeds the crypto gate — one reader for the
proof-of-work salt's `expires` and the server-signature payload's `expire`.
Because both numbers come from the same source and are compared against
OpenPorte's own clock, the counter can never die while the token it tracks is
still acceptable, even when a custom backend's clock differs. A 60-second floor
can only make the counter *outlive* a nearly-expired token, which is harmless.
There is no ceiling on the database backend, so an expiring token is bounded to
N uses over its whole life rather than N uses per window. A token carrying no
expiry falls back to a 4-hour window.

**Storage — an atomic consume on infrastructure that already exists.** No custom
table, no cron.

- *With a persistent object cache*: seed with `wp_cache_add()` and increment with
  `wp_cache_incr()`, which is a real `INCR` on Redis or Memcached. The seed value
  is the **string** `'0'`, not the integer: several drop-ins serialize non-string
  values, which turns `INCR` into a permanent, invisible failure. Cache TTLs are
  capped at 30 days, because Memcached reads anything larger as an absolute Unix
  timestamp and would expire the counter immediately.
- *Otherwise*: a transient-shaped pair of `wp_options` rows, so WordPress's own
  garbage collection reclaims them. The expiry marker is written first — a value
  row without one would never be collected, quietly turning the lifetime into
  "forever". The value row is claimed with a direct `INSERT IGNORE`, leaving
  uniqueness to the `option_name` index. Subsequent uses are spent by a single
  guarded statement, `UPDATE … SET option_value = option_value + 1 WHERE
  option_name = %s AND CAST(option_value AS UNSIGNED) < %d`, which InnoDB
  row-locks: the check and the increment cannot be separated by another worker,
  and "rows changed" is the verdict.

`add_option()` is deliberately **not** used for the value row. The original
design assumed it fails when the row exists, making it a create-at-1 mutex. Core
actually implements it as `INSERT … ON DUPLICATE KEY UPDATE` behind a cached
existence check, so two concurrent workers can both believe they created the row
— the exact lost update the design set out to avoid. This is the single most
important implementation correction in the release.

**Fail-open, but observable.** A store that cannot count returns "unknown" and
the submission is **accepted**. A broken Redis must not lock a site's visitors
out of its forms. The degradation fires
`openporte_replay_store_unavailable` and is recorded in `openporte_replay_health`
for the settings page. That record is deliberately approximate: at most one
sample a minute (the path is submitter-driven, and one sample a minute already
says "the store is failing now"), and the count restarts after a quiet day so
the page reports a store failing *now*, not one that hiccuped months ago.

**State only after crypto success.** Junk, forged and expired tokens never create
a row, so the open REST challenge endpoint stays stateless and cannot be turned
into a write amplifier.

## Configuration

**Replay limit** (`openporte_replaylimit`, default **5**, range 0–100). Presets
are Unlimited (`0`), Single use (`1`), 5 uses (recommended) and 10 uses, plus a
Custom number field so a value set over WP-CLI renders honestly instead of
snapping to the nearest preset. It is active in **both** API modes.

For developers:

- **`openporte_replay_limit`** filter — receives the limit and the current hook
  name as context, so a site can run strict on login and lenient on comments
  without touching a call site. The return value is re-clamped, so a filter
  returning nonsense cannot silently switch protection off. Called outside any
  hook the context is an empty string, which filters should read as "no
  context", never as a hook name.
- **`openporte_replay_store_unavailable`** action — fires on every fail-open,
  with the counter key, the limit and the TTL.

### Expiry guidance that shipped alongside it

| Value | Surface |
| --- | --- |
| `0` ("None") | Red error-style admin notice, plus a `_doing_it_wrong` at sanitize time. The save still goes through. |
| under 60 s | Warning notice — the challenge may expire before a slow device finishes solving it. |
| over 300 s | Field hint text only, not a notice. A wider window is bounded by the replay limit. |

In Custom mode the Expiration field is rendered disabled, because the backend
owns the expiry there. That change introduced, and then closed, a genuine
footgun: a disabled field is not submitted, WordPress passes `null` to the
sanitize callback, and `absint(null)` is `0` — so every Custom-mode save would
have silently written the worst possible replay configuration. Both sanitizers
now return the stored value on `null`, the same guard the challenge-URL
sanitizer has always used.

### Health check

The settings page reports the configured limit, whether the counter is backed by
the object cache or the database, and any fail-open episode in the last day. In
Custom mode the endpoint probe additionally parses the served salt and warns when
it carries no future `expires` (challenges that never time out) or one under a
minute. That probe is the only place OpenPorte can see a misconfigured backend
before visitors are affected.

## Operating notes

- **An object cache makes this cheaper, not more expensive.** On a busy site the
  local `INCR` is sub-millisecond with no network hop. Delegating replay checks
  to a backend's verify API would add a round trip to every submission and a hard
  runtime dependency; the honest reason to want that is centralised reporting
  across many properties, not speed.
- **Without an object cache** the counter costs one or two indexed writes per
  *verified* submission on a plain database. Rows are non-autoloaded and small.
- **Transient garbage collection.** The rows rely on WordPress's own sweep. A
  site running `DISABLE_WP_CRON` without a system cron should run an object cache
  or wire up cron, or expired counter rows will accumulate.
- **Uninstall** now sweeps `_transient_openporte_%` and its timeout companions,
  which also closes a pre-existing gap for the health-check transient.
- **Per-node caches do not share a counter.** APCu, or a node-local Redis, gives
  each node its own budget.

## What it deliberately does not do

**Amplification equals the limit.** One solved proof-of-work buys five
submissions at the default, not one. That is the point of the trade-off below,
not an oversight.

**Bounded reuse, not strict single-use — on purpose.** Strict single-use carries
a real false-rejection risk. When a submission fails for an unrelated reason (a
username already taken, a missing field) and the visitor resubmits, the
still-valid token would be refused as a replay unless the widget re-solves on
re-render, which it usually does but not in every configuration. A default of 5
keeps those resubmissions working while cutting amplification from unbounded to
five. This is a deliberate deviation from upstream ALTCHA, whose closed-source v2
plugin keeps a strict used-challenge registry. Lowering the default toward strict
is gated on visitor-recovery UX — a distinct replay-reject signal the widget can
re-solve on — tracked in
[#103](https://github.com/openporte/openporte/issues/103).

**At limit 1, inter-request resubmits are refused.** The memo covers the
same-request case, including the dual `authenticate` callbacks on login. It
cannot cover a second HTTP request. Strict mode is a documented trade-off until
the recovery UX lands.

**Tokens with no expiry get a window, not a lifetime.** A self-hosted expiry of
`0`, or a custom backend that omits `expires` from its salt, leaves nothing to
track, so the fallback window resets: N uses per 4 hours. A slow drip rather than
an unbounded burst. Closing this properly requires either rejecting such tokens
or keeping a permanent consumed-token store; both are deferred.

**Direct calls to the primitives bypass everything.** `verify_solution()` and
`verify_server_signature()` remain public and stateless. Both are now deprecated
for direct use (`_deprecated_function`, guarded so it never fires on internal
dispatch), with removal scheduled for 2.0 in
[#105](https://github.com/openporte/openporte/issues/105). `verify()` is the sole
supported entry point.

**`replaylimit = 0` switches the counter off** and writes no state at all. It is
the documented escape hatch for a site that genuinely needs pre-1.29 behaviour,
and the settings page warns for as long as it is set. There is deliberately no
separate "off" toggle: an operator who disabled local protection believing a
custom backend covered it would get *zero* protection, since OpenPorte never
calls that backend.

**A solved token creates state before downstream form checks run.** An attacker
willing to pay the proof-of-work can create one row pair per solved token even
when a later validation rejects the submission. The cost is bounded by the
proof-of-work and each row is small and self-expiring.

**Verification Delay is not a replay control and never was.** The
`openporte_delay` setting is emitted only as a client-side widget attribute; the
widget applies it as a browser `setTimeout` *before* it fetches and solves the
challenge, and no PHP path sleeps. A replayed token is a bare HTTP POST — the
widget's JavaScript never runs, so there is nothing to skip — and a bot solving
the proof-of-work itself bypasses it just as completely. It is a perception knob
and must never be counted toward this finding. Complexity is the setting that
actually raises a bot's cost.

## State of verification

**Unit suite** (`npm run test:unit`, `tests/phpunit/`). Stood up for this
release — it is the plugin's first PHPUnit suite. It runs against a hand-written
WordPress shim covering options, transients, the object cache, hooks, escaping
and a fake `$wpdb` that models the three statements the database backend issues,
with failure switches for a broken cache and a broken database. `ReplayLimitTest`
and `ReplayStorageTest` cover enforcement and both storage backends;
`VerifyMemoTest` the memo layers; `VerifyPrimitivesTest` the crypto gate,
including a salt-splicing regression test that pins the CVE-2025-68113 invariant;
`SettingsSanitizerTest`, `SettingsRendererTest` and `HealthcheckTest` the admin
surfaces.

**Mutation-tested, not just green.** Fourteen deliberate breaks were introduced
into `core.php` and `settings.php` one at a time and the suite re-run: the
counter disabled, the signature memo bypassed, the cache seeded with an integer,
the paired timeout row skipped, the TTL floor removed, the fallback TTL changed,
the deprecation guard removed, fail-open turned into fail-closed, the counter
written before crypto success, the filter re-clamp removed, the null guard
removed, `absint()` swapped in for `intval()`, the expiry advisory removed.
Thirteen were caught. The survivor — removing the *payload* memo read — is an
equivalent mutant: the signature memo already short-circuits a successful second
verification in the same request, and re-running the decode on a failed token
reproduces the same answer. No test was added for it, because pinning "work
avoided" would be a brittle white-box assertion.

**Browser suite** (`tests/e2e/replay-limit.spec.js`, five tests): the budget is
spent and then refused, the first replay is refused at limit 1, replays are
accepted at limit 0, an AJAX resubmission is accepted, and a login still succeeds
at limit 1 with both `authenticate` callbacks registered while the token it
consumed then fails to replay. The full browser suite, including the settings
matrix, was run on the bench with no regressions.

**What is not proven.** Atomicity is a **reasoned argument checked link by link,
not a measured result.** Neither suite can produce genuine concurrency: the unit
suite is one process against a fake `$wpdb`, and the browser suite runs a single
worker by design. One environmental assumption *was* verified on the bench —
`wp_options` is InnoDB and carries `UNIQUE KEY option_name`, which is what makes
the guarded `INSERT` an atomic create and the guarded `UPDATE` row-locked. What
remains open is the behaviour of the whole under load. A design argument catches
a wrong shape; only parallel workers catch a wrong assumption. The parallel-replay
stress test is [#102](https://github.com/openporte/openporte/issues/102). Until
it lands, nothing here should be read as evidence that atomicity has been
demonstrated under concurrency. The manual steps, including the object-cache
path that no harness in this repository exercises, are tracked in the release's
acceptance record
([`docs/acceptance/openporte-v1.29.0.md`](acceptance/openporte-v1.29.0.md)).

## Invariants

Breaking any of these silently removes the protection rather than failing loudly.

- **Enforcement lives in `verify()` only.** The primitives stay stateless and
  pure. Move enforcement into one and the other path loses it.
- **The counter must stay atomic.** Cache `INCR`, or the single row-locked
  `UPDATE`. Never a read-check-write, and never `add_option()` as a create-only
  mutex.
- **State is written only after cryptographic success**, so unauthenticated junk
  cannot create rows.
- **Key on verified fields only** — the signature, never the raw payload, and
  never an IP address or any `$_SERVER` value.
- **The counter must outlive the token it tracks.** Its TTL comes from the same
  `payload_expires()` the crypto gate uses. The only ceiling is the object
  cache's portable 30-day maximum.
- **`expires` must stay covered by the signature** (CVE-2025-68113). The
  signature covers the challenge, the challenge covers the salt, and `expires`
  lives in the salt. Keep the trailing `&` that `generate_challenge()` appends,
  and never sign anything the challenge does not hash.
- **The memo counts one use per request**, so a strict limit still lets the dual
  `authenticate` login through.
- **Fail-open, but observable.** A broken store degrades to pre-1.29 behaviour
  and says so.

## Alternatives considered and rejected

Recorded so they are not re-proposed without new information.

| Alternative | Why not |
| --- | --- |
| Non-atomic transient counter, atomic later | Loses updates under exactly the parallel burst a replay produces. Shipping it first would have shipped a bound that breaks when tested. |
| Custom table with `ON DUPLICATE KEY UPDATE` | Robust, but adds a schema, a `dbDelta`, a cron sweep and an uninstall drop for a counter the options table already holds atomically. Held as a fallback if the counter ever grows first-class needs such as per-form analytics. |
| Pruning on a fixed reuse window | Re-budgets long-expiry tokens: a 4-hour token would get N uses per window instead of N uses total. Tying the lifetime to the token's own validity is strictly better. |
| Overriding a stored `0` expiry at generation time | Would make a saved `0` cosmetic — the settings page reading "never expires" while the plugin quietly minted expiring challenges. `get_expires()` returns the stored value unmodified; the 4-hour fallback covers the case instead. |
| Auto-strict for tokens lacking an expiry | Couples a backend quirk to the counter: a legitimate resubmit would be refused purely because the backend omitted a field. It should be an operator's choice, and it becomes one in #103. |
| Synthetic first-use expiry for expiry-less tokens | Changes when the uses land but not the steady-state drip rate, since a replay after the record is collected starts a fresh window. Complexity without the benefit. |
| Stamping an upgrade time and hard-rejecting legacy no-expiry tokens after a grace period | The 4-hour fallback bounds the same case per token with no stored timestamp and no migration. |
| Delegating replay checks to the backend's verify API | Introduces the one thing OpenPorte does not do today — calling a backend at submit time — with a network round trip per submission and a hard runtime dependency. Deferred to its own release, [#104](https://github.com/openporte/openporte/issues/104), and never as a bare "off". |

## Roadmap

[#99](https://github.com/openporte/openporte/issues/99) is the umbrella issue and
closes when #105 closes.

| Issue | Scope |
| --- | --- |
| [#101](https://github.com/openporte/openporte/issues/101) | The atomic counter and the advisories — shipped in 1.29.0. |
| [#102](https://github.com/openporte/openporte/issues/102) | Per-context limits wired through the filter, threshold tuning from real feedback, and the standalone parallel-replay stress script. |
| [#103](https://github.com/openporte/openporte/issues/103) | Enforced expiry bounds (removing "None", closing the `0` residual for good), the operator policy for tokens with no expiry, and visitor-recovery UX. Breaking config. |
| [#104](https://github.com/openporte/openporte/issues/104) | The optional "delegate to backend" verification path. Blocked by #103. |
| [#105](https://github.com/openporte/openporte/issues/105) | Removal of the deprecated primitives and expiry shims. Breaking API. |

The widget upgrade from ALTCHA v2 to v3 is tracked separately. It does not affect
this design: the counter keys on a verified, unique-per-solve identifier, and
both the classic and the new challenge formats produce a challenge and an HMAC
signature. Whether a v3 widget still solves OpenPorte's classic challenge is a
real open question, but it belongs to that upgrade, not here.
