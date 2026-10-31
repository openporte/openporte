# OpenPorte

<div align="center">
    <picture>
      <source media="(prefers-color-scheme: dark)" srcset="share/branding/png/openporte-grayscale-dark-256px.png">
      <img alt="Shows an illustrated sun in light color mode and a moon with
      stars in dark color mode."
      src="share/branding/png/openporte-grayscale-light-256px.png" width="128">
    </picture>
</div>

**Stop spam without CAPTCHAs. Proof-of-Work protection for your site.
Open-source, self-hosted, privacy-first.**

OpenPorte protects WordPress forms, logins, registrations and comments from
spam and bots using a lightweight Proof-of-Work challenge. The goal is to make
spam expensive for bots while keeping the experience almost invisible for
legitimate visitors and respecting their privacy.

No cookies, no tracking, GDPR-friendly by design.

Website: <https://github.com/openporte/openporte>

WordPress Plugin Directory: <https://wordpress.org/plugins/openporte/>

> [!TIP]
> Need help? See <a href="SUPPORT.md" title="Support Information"
alt="Link to Support Information">SUPPORT.md</a>.

Disclosure: This project is (using AI)[^1].

[^1]: **AI-assisted project.** Architecture, security decisions, and final review
are mine; AI tools (Claude, Mistral, and others) help with drafting code,
tests, translations, and documentation — without them, one person couldn't
keep this fork alive.

## Description

OpenPorte is a community-driven open-source project dedicated to making modern
spam protection accessible, transparent, and under the website owner's control.

For site owners, it is easy to configure and deploy. For site users, it is an
accessible, transparent and privacy-friendly alternative to CAPTCHAs.

OpenPorte is built around the
[open-source ALTCHA widget](https://github.com/altcha-org/altcha).

Some highlights:

- CAPTCHA-free spam protection
- Fully open source (GPL)
- Self-hosted – no mandatory external service
- Designed to be accessible and privacy-friendly
- Compatible with existing ALTCHA integrations
- Easy migration for users of ALTCHA Spam Protection v1 (≤ 1.26.3)

## Background

The original ALTCHA Spam Protection WordPress plugin (v1) was open source
(GPLv2). Its authors have since released a version 2/3 which is no longer
open source. They no longer maintain the open-source project and recommend
migrating to [v2/3](https://altcha.org).

OpenPorte continues the v1 line as free software (GPLv2 or later), for people
who want to stay on a fully open-source, self-hosted solution. It is based on
the last GPL release of ALTCHA v1 (1.26.1).

OpenPorte uses the upstream [ALTCHA widget](https://github.com/altcha-org/altcha)
(MIT-licensed) as a bundled dependency.

## Compatibility

Backward-compatible with ALTCHA v1: settings migrate automatically (the
original `altcha_*` options are left in place, so you can roll back), and the
`[altcha]` shortcode, the `altcha/v1` REST namespace and the `altcha_*` hooks
keep working as **deprecated aliases** of their `openporte` equivalents.
Integrations for paid-only plugins (e.g. Enfold) are also **deprecated** and
will be removed.

OpenPorte supports Custom API Mode. This mode has been verified against
[GateCHA](https://gatecha.org), an open-source implementation of the ALTCHA
server protocol and an alternative to the proprietary ALTCHA Sentinel. Sites
using a Sentinel-compatible backend are expected to work as well, since both
implement the same server-signature verification, though this has not been
directly tested.

## Supported Integrations

* CoBlocks
* Contact Form 7
* Elementor Pro Forms (deprecated — paid plugin)
* Enfold Theme (deprecated — paid plugin)
* Formidable Forms
* Forminator
* GravityForms
* HTML Forms
* wpDiscuz
* WPForms
* WordPress Login, Register, Password reset
* WordPress Comments
* WooCommerce
* Many other plugins and your own content (via the `[openporte]`
  shortcode, or the deprecated `[altcha]` alias)

### What login protection does not cover

The WordPress and WooCommerce login protection covers the login forms:
`wp-login.php`, WooCommerce's My Account login, and any form that signs in
through `wp_signon()`.

Two other ways of logging in are deliberately left alone, because the apps and
services that use them cannot solve a challenge:

* XML-RPC (`xmlrpc.php`);
* REST API authentication with application passwords.

An attacker can try passwords there without meeting the widget. If your site
does not need them, disable them:

```php
// Disable XML-RPC authentication.
add_filter( 'xmlrpc_enabled', '__return_false' );
// Disable application passwords.
add_filter( 'wp_is_application_passwords_available', '__return_false' );
```

OpenPorte does not limit login attempts or add two-factor authentication. Use a
dedicated plugin for those; it also protects XML-RPC and the REST API if you
keep them.

## Floating UI

The plugin supports the [Floating UI](https://altcha.org/docs/v2/floating-ui/),
enabled by setting **Display Mode** to *Floating* under Widget Customisation,
but with known limitations:

Currently the Floating UI does not work with:

* Forminator with multi-step forms

## Installation

### WordPress.org Plugin Directory

OpenPorte is listed in the WordPress.org plugin directory. So you can install
it directly from the admin UI on your site.

1. Open the WordPress admin UI
2. Under Plugins → Add Plugin, search for openporte
3. Install it and activate it.
4. Review the settings and enable your integrations

### GitHub release

You can also download the GitHub release and install it via the WordPress
admin UI.

1. Download the `.zip` from the [Releases](https://github.com/openporte/openporte/releases).
2. Under Plugins → Add Plugin, click **Upload Plugin** and select the downloaded
   `.zip` file.
3. Activate the plugin through the 'Plugins' menu in WordPress  
4. Review the settings and enable your integrations

### Modes of Operation

OpenPorte verifies submissions in one of two modes, selected in the settings
(API Mode):

* **Self-hosted** (default) — a proof-of-work challenge is issued and verified by
  your own WordPress site, served through the REST API (default) or
  admin-ajax.php (see [Self-hosted transport](#self-hosted-transport)). Fully
  self-contained, no external service, no account.
* **Custom** — point the Challenge URL at your own ALTCHA-compatible backend
  (e.g. a self-hosted ALTCHA Sentinel, or [GateCHA](https://gatecha.org));
  submissions are verified with your site's shared secret. The Algorithm
  setting must match the hash algorithm your backend uses (most
  ALTCHA-compatible backends default to SHA-256).

The paid altcha.org regional SaaS classifier offered by earlier versions has been
removed; both remaining modes are free and self-hostable.

#### Which settings apply in which mode

Verification always happens **locally** in WordPress, in both modes: OpenPorte
recomputes the challenge and its HMAC signature with your shared secret. It
never calls a backend verification API (such as GateCHA's `/api/v1/verify` or
ALTCHA Sentinel's verify endpoint) — so in Custom mode the backend only
*issues* challenges, and its dashboard will show challenges as issued but
never verified. A consequence: backend-side features that live in the verify
call (replay tracking, verification statistics) are not used.

| Setting | Self-hosted | Custom |
| ------- | ----------- | ------ |
| Challenge URL | ignored (the built-in endpoint of the selected transport is used) | fetched by the widget — include any required query parameters (e.g. an `apiKey`) |
| Self-hosted transport | selects REST API or Ajax (admin-ajax.php) | ignored — the widget fetches the Challenge URL |
| Shared secret | signs and verifies challenges | must **equal the backend's HMAC secret**; used to verify locally |
| Algorithm | generates and verifies challenges | must **match the backend's algorithm**; used at verification only |
| Complexity | sets the PoW difficulty | ignored — the backend decides |
| Expiration | sets the challenge life-span | ignored — the backend's expiry (embedded in the salt) is enforced instead |
| Replay limit | enforced locally | enforced locally — the backend's replay tracking is not used |
| Widget customization (auto-verify, floating, delay, logo/footer) | applies | applies |
| Integrations | apply | apply |

Two things worth knowing in Custom mode: a mismatched Algorithm or shared
secret is invisible while the widget solves (the widget reads the algorithm
from the challenge itself) and only shows up when a form submission is
verified server-side; and both self-hosted challenge endpoints (REST and
Ajax) remain registered, so challenges signed with the same secret can also be
obtained from WordPress itself.

### Self-hosted transport

In Self-hosted mode, the widget fetches each challenge from your own site.
The **Self-hosted transport** setting (Settings > OpenPorte Anti-spam, below
API Mode)
chooses which WordPress endpoint serves it:

| Transport | Challenge URL (logged-out visitors fetch it) |
| --------- | -------------------------------------------- |
| **REST API** (default) | `/wp-json/openporte/v1/challenge`, or `/?rest_route=/openporte/v1/challenge` with plain permalinks |
| **Ajax** | `/wp-admin/admin-ajax.php?action=openporte_challenge` |

Both endpoints serve the same challenge and are verified the same way; both
stay registered whichever one is selected. The deprecated
`/wp-json/altcha/v1/challenge` alias is still served too. In Custom API mode
all of them refuse with an error (HTTP 403) instead: your backend issues the
challenges there, and a challenge from your site would bypass its rules.

**When to switch.** Keep REST API unless the widget cannot load a challenge
for logged-out visitors. A common cause is a security or optimization plugin
that disables the REST API for visitors (for example "Disable WP REST API" or
Clearfy): the browser console then shows an error such as `Server responded
with 401` or `Unexpected token '<'`. Either allow `/openporte/v1/challenge` in
that plugin, or switch the transport to Ajax. Conversely, if a plugin or host
rule blocks `admin-ajax.php` for visitors, stay on REST API. Ajax is not a
universal fix: plugins that keep visitors out of wp-admin ("hide" or "lock
down" wp-admin, membership plugins) often block `admin-ajax.php` too, sometimes
only for logged-in subscribers and customers.

**How to check, by hand.** OpenPorte does not test this automatically: a check
run from the server cannot see firewall, CDN or browser-side rules, so only a
real visitor request is conclusive.

1. Open a private browser window, so you are logged out.
2. Open the challenge URL of the selected transport (table above) on your site.
   A working endpoint shows a short JSON text with `algorithm`, `challenge`,
   `maxnumber`, `salt` and `signature`. An error message, a login page, a
   redirect or an HTML page means it is blocked for visitors.
3. If it is blocked, open the other transport's URL the same way. If that one
   works, select it in the settings and save.
4. Still logged out, open a page with a protected form, solve the widget and
   submit the form. If your forms are also used by logged-in visitors, repeat
   once as a subscriber or customer: they reach admin-ajax.php through a
   different hook than logged-out visitors.

**After switching.** Pages already cached by a page-cache plugin, the host or a
CDN still contain the old challenge URL: purge those caches so visitors get the
new one. Both endpoints stay available, so a cached page keeps working as long
as its endpoint is reachable.

**What switching does not fix.** The transport only changes how the widget gets
its challenge. A form plugin that submits forms through the REST API itself
(Contact Form 7, for example) still needs the REST API for its own submissions,
whichever transport OpenPorte uses.

**Upgrading and downgrading.** Upgrading to 1.30.0 changes nothing: every site
keeps the REST API until Ajax is selected. Before downgrading to a release
older than 1.30.0, switch back to REST API and purge page and CDN caches.  
If you have already **downgraded, purge those caches now**: older release have
no Ajax endpoint, so a page cached with the Ajax challenge URL cannot load a
challenge.

**Multisite.** Each site has its own transport setting; a site where it was
never saved uses the REST API.

**Custom routing.** To serve challenges from another URL entirely (a proxy, a
different host), use the `openporte_challenge_url` filter (see
[Filters](#filters)); it overrides the selected transport's URL.

### Hooks

The plugin provides several hooks to customize or extend its functionality.
Each hook below is also fired under its old `altcha_*` name as a **deprecated
alias** (via WordPress' deprecated-hook mechanism); use the `openporte_*` names.

#### Filters

* `apply_filters('openporte_challenge_url', $challenge_url)`  
  Override the challenge URL. In Self-hosted mode it receives the URL of the
  selected transport; in Custom mode, the configured Challenge URL.  
  **Returns:** `string`

* `apply_filters('openporte_integrations', $integrations)`  
  Modify the list of integration states. Since 1.28 each entry is the raw
  checkbox option value — truthy (`1`) when the integration is enabled, falsy
  (`0` or `''`) otherwise. The legacy mode strings (`captcha`,
  `captcha_spamfilter`, `shortcode`) are no longer used.  
  **Returns:** `array<int|string>`

* `apply_filters('openporte_plugin_active', false, $name)`  
  Check if an integration by `$name` is active.  
  **Returns:** `bool`

* `apply_filters('openporte_replay_limit', $limit, $context, $surface, $hook)`  
  Override how many times one solved token may be accepted, so a site can be
  stricter on login than on comments. `$context` keeps the hook/context name
  supplied in 1.29.0. On the new pre-password login gate it remains
  `authenticate`, so existing strict-login callbacks keep working; otherwise
  it defaults to the current hook, or `''` outside hooks. A direct caller of
  `get_replaylimit($context)` keeps its explicit context unchanged.

  Since 1.30.0 the additive third argument, `$surface`, names the form:
  the suffix of the integration setting that turned the check on
  (`openporte_integration_<surface>`) — `wordpress_login`,
  `wordpress_register`, `wordpress_reset_password`, `wordpress_comments` (also
  wpDiscuz comments), `woocommerce_login`, `woocommerce_register`,
  `woocommerce_reset_password`, `contact_form_7`, `html_forms`, `wpforms`,
  `forminator`, `formidable`, `gravityforms`, `elementor`, `coblocks`,
  `enfold_theme`, and `wpmembers`, the one exception to the suffix rule:
  WP-Members registration is checked when `wordpress_register` is on, but
  under its own name. Code that calls `verify()` without naming a surface
  supplies `''` as `$surface`. The fourth argument, `$hook`, is the actual
  running hook (including `wp_authenticate_user` at the login gate), or `''`
  outside hooks. The counter is per token and shared across surfaces; the
  limit in force is that of the surface the token is submitted on. The return
  value is re-clamped to the 0–100 range, so a filter cannot switch protection
  off with an out-of-range value. Since 1.29.0; `$surface` and `$hook` since
  1.30.0. Note the spelling: the filter is `openporte_replay_limit`, the option
  `openporte_replaylimit`.  
  **Returns:** `int`

  ```php
  add_filter('openporte_replay_limit', function ($limit, $context, $surface) {
    return $surface === 'wordpress_login' ? 1 : $limit;
  }, 10, 3);
  ```

* `apply_filters('openporte_widget_attrs', $attrs, $mode, $language, $name)`  
  Override widget attributes.  
  **Returns:** `array<string, mixed>`

* `apply_filters('openporte_widget_html', $html, $mode, $language, $name)`  
  Override the entire widget HTML.  
  **Returns:** `string`

* `apply_filters('openporte_translations', $translations, $language)`  
  Override translation strings.  
  **Returns:** `array<string, string>`

#### Actions

* `do_action('openporte_verify_result', $result)`  
  Triggered after payload verification.

  * `$result`: `bool` verification result.

  The former `OpenPortePlugin::$instance->spamfilter_result` property was
  removed together with the spam-filter feature in 1.28 (see issue #6); the
  action now only carries the boolean result.

* `do_action('openporte_replay_store_unavailable', $key, $limit, $ttl)`  
  Triggered when the replay counter cannot be read or written (object cache or
  database unreachable). The submission is still accepted — replay protection
  fails open rather than locking visitors out — but this fires so a site can
  observe the degradation.

  * `$key`: `string` the replay counter's storage key.
  * `$limit`: `int` the Replay limit that could not be enforced.
  * `$ttl`: `int` the counter lifetime, in seconds, that would have been used.

  Since 1.29.0.

## Acknowledgements

The settings-page integration registration loop is adapted from the
GPL-licensed [GateCHA for WordPress](https://github.com/Upellift99/GateCHA-WordPress)
plugin by Upellift99 — thank you!

## License

GPLv2 - see [LICENSE](LICENSE)
