# OpenPorte

<div align="right">
    <a href="https://plugintracker.io/plugins/openporte" rel="sponsored">
        <img src="https://plugintracker.io/badge/openporte/installs.svg?style=flat-square&color=gray&claim=88482bbc0c1442a9"
            alt="OpenPorte Spam Protection Active installs on PluginTracker.io" /></a>&nbsp;
    <a href="https://plugintracker.io/plugins/openporte" rel="sponsored">
        <img src="https://plugintracker.io/badge/openporte/rating.svg?style=flat-square&color=gray&claim=88482bbc0c1442a9"
            alt="OpenPorte Spam Protection Star rating on PluginTracker.io" /></a>&nbsp;
    <a href="https://plugintracker.io/plugins/openporte" rel="sponsored">
        <img src="https://plugintracker.io/badge/openporte/top-in-tag.svg?style=flat-square&color=gray&claim=88482bbc0c1442a9"
            alt="OpenPorte Spam Protection Top in tag on PluginTracker.io" /></a>
</div>
<div align="center">
    <picture>
      <source media="(prefers-color-scheme: dark)" srcset="share/branding/png/openporte-grayscale-dark-256px.png">
      <img alt="Shows an illustrated sun in light color mode and a moon with
      stars in dark color mode."
      src="share/branding/png/openporte-grayscale-light-256px.png" width="128">
    </picture>
</div>
<div>&nbsp;</div>

**Stop spam without CAPTCHAs. Proof-of-Work protection for your forms and logins.
Open-source, self-hosted, privacy-first.**

OpenPorte is a free and open-source WordPress plugin that protects forms, comments, logins, registrations, and WooCommerce from spam and bots using lightweight **Proof-of-Work challenges instead of CAPTCHAs**.

The goal is simple: make spam attempts computationally costly for bots while keeping the experience unobtrusive for legitimate visitors and respecting their privacy.

No cookies, no tracking, privacy-first by design.

Website: <https://github.com/openporte/openporte>

WordPress Plugin Directory: <https://wordpress.org/plugins/openporte/>

> [!TIP]
> Need help? See <a href="SUPPORT.md" title="Support Information"
alt="Link to Support Information">SUPPORT.md</a>.

Disclosure: This project uses AI assistance.[^1]

[^1]: **AI-assisted project.** Architecture, security decisions, and final review
    are mine; AI tools (Claude, Mistral, local LLMs and others) help with drafting code,
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

- **CAPTCHA-free spam protection:** No image-recognition puzzles or intrusive challenges.
- **Open source (GPL):** Free software, developed openly and welcoming community contributions.
- **Self-hosted by default:** Challenges are generated and verified directly by your WordPress site, without requiring an account or external service.
- **Privacy-first:** No tracking, fingerprinting, or cookies introduced by OpenPorte, helping simplify GDPR compliance.
- **Accessible by design:** Built around the open-source ALTCHA widget, with an emphasis on accessibility and minimal user interaction.
- **Easy configuration:** Enable the integrations you need and configure the widget to suit your site.
- **Flexible deployment:** Choose between WordPress REST API and Ajax for self-hosted challenge delivery, or use a compatible external challenge provider through Custom API Mode.
- **ALTCHA v1 migration:** Supported settings from ALTCHA Spam Protection v1 (≤ 1.26.3) can be imported automatically, with deprecated compatibility aliases for existing integrations.

## Background

The original ALTCHA Spam Protection WordPress plugin (v1) was open source
(GPLv2). Its authors have since released a version 2/3 which is no longer
open source. They no longer maintain the open-source project and recommend
migrating to [v2/3](https://altcha.org).

OpenPorte continues the GPL-licensed ALTCHA v1 line as free software (GPLv2 or
later), for people who want to stay on a fully open-source, self-hosted solution.

OpenPorte uses the upstream [ALTCHA widget](https://github.com/altcha-org/altcha)
(MIT-licensed) as a bundled dependency.

## Compatibility & migration

OpenPorte can import supported settings from ALTCHA Spam Protection v1
(≤ 1.26.3) and retains deprecated aliases for several legacy interfaces.
Some features have changed or been removed; review your settings and test
protected forms after migration.

### Migrating from the original ALTCHA Spam Protection v1 plugin

Deactivate the original ALTCHA plugin, then install and activate OpenPorte.
On activation, supported v1 settings are copied where corresponding OpenPorte
settings do not already exist. The original ALTCHA settings are left untouched.
Do not run both plugins at the same time.

### Migrating from ALTCHA for WordPress v2/v3 plugin

If you previously upgraded or were automatically upgraded to ALTCHA v2/v3,
OpenPorte can still import your original v1 settings if they remain in the
database. Settings introduced in v2/v3 are not imported. The original ALTCHA
v1/v2/v3 settings are left untouched.  
Deactivate ALTCHA, then install and activate OpenPorte. Do not run both plugins
at the same time.

### Backward compatibility

The `[altcha]` shortcode, the `altcha/v1` REST namespace and the `altcha_*`
hooks remain available as **deprecated aliases** of their `openporte`
equivalents. These aliases do not imply full feature parity with ALTCHA v1.
Integrations for paid-only plugins (e.g. Enfold) are also **deprecated** and
will be removed.

### Custom API compatibility

OpenPorte supports Custom API Mode. This mode has been verified against
[GateCHA](https://gatecha.org), an open-source server implementing the ALTCHA
challenge protocol.

Challenge verification is performed locally by OpenPorte using the configured
shared secret, even when challenges are issued by an external backend.
Backend-side verification features are therefore not used.

OpenPorte does not support the former ALTCHA SaaS API Mode. Users who rely on
ALTCHA Sentinel's advanced features should consider the official ALTCHA Spam
Protection v2/v3 WordPress plugin instead.

## Supported integrations

- CoBlocks
- Contact Form 7
- Elementor Pro Forms (deprecated — paid plugin)
- Enfold Theme (deprecated — paid plugin)
- Formidable Forms
- Forminator
- GravityForms
- HTML Forms
- wpDiscuz
- WPForms
- WordPress Login, Register, Password reset
- WordPress Comments
- WooCommerce
- Many other plugins and your own content (via the `[openporte]`
  shortcode, or the deprecated `[altcha]` alias)

### What login protection does not cover

The WordPress and WooCommerce login protection covers the login forms:
`wp-login.php`, WooCommerce's My Account login, and any form that signs in
through `wp_signon()`.

Two other ways of logging in are deliberately left alone, because the apps and
services that use them cannot solve a challenge:

- XML-RPC (`xmlrpc.php`);
- REST API authentication with application passwords.

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

- Forminator with multi-step forms

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

### Downgrading OpenPorte

Downgrading to an earlier version of OpenPorte is **not officially supported**.

- **Major or minor version downgrade** (e.g. 1.30.1 → 1.29.0): You must remove the existing plugin settings before installing the older version. To do so, disable **Keep settings when the plugin is deleted** under Settings → OpenPorte → Data, save your changes, then deactivate and delete the plugin through WordPress's Plugins screen and install the older version.
- **Patch version downgrade** (e.g. 1.30.1 → 1.30.0): This may work without removing your settings, but compatibility is not guaranteed.

**Important:** Deactivating OpenPorte does not remove its settings. Deleting it through WordPress's Plugins screen removes them only if **Keep settings when the plugin is deleted** is disabled. This option is available since version 1.30.0.

Clearing OpenPorte settings leaves the original ALTCHA settings intact. Activating a version with the legacy migration routine can import supported ALTCHA v1 settings again, so this does not necessarily produce a default configuration.

**Multisite:** The current uninstall handler cleans only the current site's options table; it does not reset settings across the network. Before a major or minor downgrade, have your network administrator ensure that OpenPorte settings are removed on each affected site.

Before downgrading, back up your site and database. After installing the older version, clear your WordPress, hosting, and CDN caches to prevent cached pages from referencing features unavailable in that release. You may also need to reconfigure OpenPorte.

#### Need help?

If you're considering a downgrade or encounter difficulties, you're welcome to ask for advice in the [OpenPorte support forum on WordPress.org](https://wordpress.org/support/plugin/openporte/).

Support is provided on a voluntary, best-effort basis by the maintainer and community. Questions and contributions from other users are welcome and appreciated. Please understand that **responses and resolution times cannot be guaranteed**, as availability may vary.

### Modes of operation

OpenPorte verifies submissions in one of two modes, selected in the settings
(API Mode):

- **Self-hosted** (default) — a proof-of-work challenge is issued and verified by
  your own WordPress site, served through the REST API (default) or
  admin-ajax.php (see [Self-hosted transport](#self-hosted-transport)). Fully
  self-contained, no external service, no account.
- **Custom** — point the Challenge URL at your own ALTCHA-compatible backend
  (e.g. [GateCHA](https://gatecha.org));
  submissions are verified with your site's shared secret. The Algorithm
  setting must match the hash algorithm your backend uses (most
  ALTCHA-compatible backends default to SHA-256).

The paid altcha.org regional SaaS classifier offered by earlier versions has
been removed. Neither mode requires a paid service; a Custom backend may have
its own costs and terms.

#### Which settings apply in which mode

Verification always happens **locally** in WordPress, in both modes: OpenPorte
recomputes the challenge and its HMAC signature with your shared secret. It
never calls a backend verification API (such as GateCHA's `/api/v1/verify`) — so in Custom mode the backend only
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

In Custom mode, the configured Algorithm and shared secret must match your
backend. A mismatch may only become apparent when the form is submitted: the
widget can solve a challenge that OpenPorte subsequently rejects. WordPress's
built-in challenge endpoints remain registered but return HTTP 403 in this mode.

#### Self-hosted transport

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

A second cause is a page cache or CDN that stores REST API responses: every
visitor then gets the same challenge, refused once the Replay limit is spent
and for everyone once it expires (#133). Since 1.30.1 both challenge endpoints
opt out of LiteSpeed Cache and of caching plugins that honour
`DONOTCACHEPAGE`, besides sending `Cache-Control: no-store`. A cache that
ignores all three, such as a host- or CDN-level rule caching `/wp-json/`,
still needs `/openporte/v1/challenge` excluded from it, or the transport
switched to Ajax. To spot it, open the challenge URL twice, logged out: two
identical responses mean it is cached.

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

**Upgrading and downgrading.** The [downgrade policy](#downgrading-openporte)
above applies; the following is an additional transport caveat. Upgrading to
1.30.0 changes nothing: every site
keeps the REST API until Ajax is selected. Before downgrading to a release
older than 1.30.0, switch back to REST API and purge page and CDN caches.  
If you have already **downgraded, purge those caches now**: older releases have
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

- `apply_filters('openporte_challenge_url', $challenge_url)`  
  Override the challenge URL. In Self-hosted mode it receives the URL of the
  selected transport; in Custom mode, the configured Challenge URL.  
  **Returns:** `string`

- `apply_filters('openporte_integrations', $integrations)`  
  Modify the list of integration states. Since 1.28 each entry is the raw
  checkbox option value — truthy (`1`) when the integration is enabled, falsy
  (`0` or `''`) otherwise. The legacy mode strings (`captcha`,
  `captcha_spamfilter`, `shortcode`) are no longer used.  
  **Returns:** `array<int|string>`

- `apply_filters('openporte_plugin_active', false, $name)`  
  Check if an integration by `$name` is active.  
  **Returns:** `bool`

- `apply_filters('openporte_replay_limit', $limit, $context, $surface, $hook)`  
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

- `apply_filters('openporte_widget_attrs', $attrs, $mode, $language, $name)`  
  Override widget attributes.  
  **Returns:** `array<string, mixed>`

- `apply_filters('openporte_widget_html', $html, $mode, $language, $name)`  
  Override the entire widget HTML.  
  **Returns:** `string`

- `apply_filters('openporte_translations', $translations, $language)`  
  Override translation strings.  
  **Returns:** `array<string, string>`

#### Actions

- `do_action('openporte_verify_result', $result)`  
  Triggered after payload verification.

  - `$result`: `bool` verification result.

  The former `OpenPortePlugin::$instance->spamfilter_result` property was
  removed together with the spam-filter feature in 1.28 (see issue #6); the
  action now only carries the boolean result.

- `do_action('openporte_replay_store_unavailable', $key, $limit, $ttl)`  
  Triggered when the replay counter cannot be read or written (object cache or
  database unreachable). The submission is still accepted — replay protection
  fails open rather than locking visitors out — but this fires so a site can
  observe the degradation.

  - `$key`: `string` the replay counter's storage key.
  - `$limit`: `int` the Replay limit that could not be enforced.
  - `$ttl`: `int` the counter lifetime, in seconds, that would have been used.

  Since 1.29.0.

## Acknowledgements

The settings-page integration registration loop is adapted from the
GPL-licensed [GateCHA for WordPress](https://github.com/Upellift99/GateCHA-WordPress)
plugin by Upellift99 — thank you!

## License

GPLv2 - see [LICENSE](LICENSE)
