=== OpenPorte Spam Protection ===
Tags: captcha, spam, anti-spam, anti-bot, altcha
Stable tag: 1.29.0
Requires at least: 5.6
Requires PHP: 8.0
Tested up to: 7.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Contributors: huygens-25

Stop spam without CAPTCHAs. Proof-of-Work protection for your site. Open-source, self-hosted, privacy-first.

== Description ==

OpenPorte is a free and open-source plugin that protects WordPress forms
from spam using a lightweight Proof-of-Work challenge instead of CAPTCHAs.
The goal is to make spam expensive for bots while keeping the experience almost
invisible for legitimate visitors and respecting their privacy.

For site owners, OpenPorte offers an easy to configure and deploy modern
spam protection under their control.
For the site users, it offers an accessible, transparent and privacy-friendly
alternative to CAPTCHAs.

OpenPorte is built around the [open-source ALTCHA widget](https://github.com/altcha-org/altcha).

Some highlights:

- CAPTCHA-free spam protection
- Fully open source (GPL)
- Self-hosted – no mandatory external service
- Designed to be accessible and privacy-friendly
- Compatible with existing ALTCHA integrations
- Easy migration for users of ALTCHA Spam Protection v1 (≤ 1.26.3)


For the list of contributors, refer to our GitHub project: [Contributors](https://github.com/openporte/openporte/graphs/contributors?from=1.6.2024).

= AI-assisted development =

AI-assisted project. Architecture, security decisions, and final review are
mine; AI tools (Claude, Mistral, and others) help with drafting code, tests,
translations, and documentation — without them, one person couldn't keep
this fork alive.

= Background =

The original ALTCHA Spam Protection WordPress plugin (v1) was open source
(GPLv2). Its authors have since released a version 2/3 which is no longer
open source. They no longer maintain the open source project and recommend
that users migrate to v2/v3. See the official project at https://altcha.org
for their offering.

OpenPorte started as a fork and continues the v1 line as free software
(GPLv2 or later) for users who want to stay on a fully open-source,
self-hosted solution. It is a faithful fork: existing v1 installations
can switch to OpenPorte and keep their settings (see Upgrading).

= Compatibility =

OpenPorte is backward-compatible with ALTCHA Spam Protection v1 plugin:

* Your existing settings are migrated automatically on activation.
* The `[altcha]` shortcode keeps working (alongside the new `[openporte]`).
* The `altcha_*` filters and actions keep firing as deprecated aliases.
* Custom API Mode has been verified against [GateCHA](https://gatecha.org),
an open source server implementing the creation and verification of ALTCHA challenges.

See the Deprecations section for the full list of compatibility aliases and
what they map to.

== Upgrade Notice ==

= 1.29.0 =
Solved challenges can no longer be replayed without limit. The new "Replay
limit" setting (default 5) bounds how often one is accepted; no action needed,
and resubmitting after a form error still works. Developers: call verify(), not
verify_solution() or verify_server_signature().

= 1.28.1 =
This update is only about the new GitHub location and descriptions. Please
view the preceding 1.28.0 upgrade notice if you upgraded from an earlier
release.

= 1.28.0 =
"Custom HTML" is deprecated and switched off by this upgrade. Sites using the
`<altcha-widget>` tags (in theme templates or custom HTML) are concerned. If
affected, re-enable the setting, or replace those tags with the `[openporte]`
shortcode.
The paid spam-filter classifier is removed.

== Upgrading ==

= From the original ALTCHA v1 plugin =

Deactivate the old ALTCHA plugin, then install and activate OpenPorte. Your
existing configuration is detected and copied into the OpenPorte settings on
first activation; the original ALTCHA settings are left untouched, so you can
roll back to ALTCHA v1 without losing anything. Do not run both plugins at the
same time.

= From ALTCHA v2/v3 =

If your site was already moved to ALTCHA v2/v3 (for example by the automatic
3.0.0 update), your original v1 settings are normally still in the database:
the v2/v3 upgrade neither migrates nor removes them. Deactivate ALTCHA, then
install and activate OpenPorte — it finds and imports the v1 settings, even
when the v1 plugin itself has long been deleted. Settings made in v2/v3 are
not read.

== Deprecations ==

The following ALTCHA-era identifiers are kept as aliases for backward
compatibility and are scheduled for removal in a future release:

* The `[altcha]` shortcode — use `[openporte]`.
* The `altcha/v1` REST namespace — use `openporte/v1`.
* The `altcha_*` filters and actions — now firing through WordPress' deprecated
  hook mechanism; use the `openporte_*` equivalents.
* The `AltchaPlugin` class and the `ALTCHA_VERSION` / `ALTCHA_WIDGET_VERSION`
  constants — use `OpenPortePlugin` and the `OPENPORTE_*` constants.
* Integrations targeting paid-only third-party plugins; affected users should
  migrate to the official ALTCHA v2/v3 plugin.
* The "Custom HTML" integration (auto-configuration of hand-written
  `<altcha-widget>` tags) — place the `[openporte]` shortcode instead. You can
  re-enable it for now under Settings → OpenPorte → Integrations.

== Privacy ==

= No cookies, no tracking =

OpenPorte prioritizes user privacy by avoiding the use of cookies and fingerprinting techniques.

= No external service =

This plugin remains fully contained within your WordPress installation, eliminating any reliance on external services.

== Modes of Operation ==

OpenPorte verifies submissions in one of two modes, selected in the settings
(API Mode):

* Self-hosted (default) — a proof-of-work challenge is issued and verified by
  your own WordPress site through the REST API. Fully self-contained, with no
  external service and no additional setup beyond enabling the integrations you
  need.
* Custom — point the Challenge URL at your own ALTCHA-compatible backend (for
  example a self-hosted ALTCHA Sentinel, or [GateCHA](https://gatecha.org)).
  Submissions are verified with your site's shared secret.

The paid altcha.org regional SaaS classifier offered by earlier versions has
been removed; both remaining modes are free and self-hostable.
 
== Installation ==

Download, install and activate `OpenPorte Spam Protection`.
 
Alternatively, install the plugin manually:

1. Download the `.zip` from the [Releases](https://github.com/openporte/openporte/releases).
2. Upload `openporte` folder to the `/wp-content/plugins/` directory  
3. Activate the plugin through the 'Plugins' menu in WordPress  
4. Review the settings and enable your integrations

== Frequently Asked Questions ==

= Is placing the `[openporte]` shortcode enough to protect a form? =

Not on its own. The shortcode displays the widget; the verification that
rejects unsolved submissions is performed by the integration for that form
plugin. For a supported plugin, enable its toggle under Settings → OpenPorte →
Integrations as well. Two integrations go further: with Contact Form 7 and
HTML Forms, a widget you placed in the form yourself (the shortcode or a
hand-written tag) is verified even while the toggle is off.

In a form plugin OpenPorte has no integration for, the shortcode still shows
the widget and visitors still have to tick it, but nothing checks the result on
the server — a bot posting directly to the form's endpoint is not stopped. See
**Supported Integrations** for the list.

== REST API ==

This plugin requires the WordPress REST API. If you are using any "Disable REST API" plugins, ensure that the endpoint `/altcha/v1/challenge` (marked for deprecation) and `/openporte/v1/challenge` are allowed.

== Supported Integrations ==

* CoBlocks
* Contact Form 7
* Elementor Pro Forms (deprecated — paid plugin, see Deprecations)
* Formidable Forms
* Forminator
* GravityForms
* HTML Forms
* wpDiscuz
* WPForms
* WordPress Login, Register, Password reset
* WordPress Comments
* WooCommerce
* Many other plugins and your own content (via the `[openporte]` shortcode, or the deprecated `[altcha]` alias)

== Source Code ==

All source code for the plugin, and the ALTCHA widget is available on GitHub. In the repository, you'll also find versions of non-minified JavaScript and CSS assets:

* Plugin: https://github.com/openporte/openporte
* ALTCHA Widget: https://github.com/altcha-org/altcha

== Screenshots ==

1. Friction-less Captcha without puzzles
2. Configuration
3. Protection on the login page
4. Protection with WPForms
5. Floating UI Captcha

== Changelog ==

= 1.29.0 =

* Security: a solved challenge can no longer be replayed without limit. Until now a valid token was accepted on every submission until it expired — and with Expiration set to "None", forever — so a bot could solve one proof-of-work and reuse it indefinitely. Each solved challenge is now accepted a bounded number of times, counted server-side.
* New "Replay limit" setting (General), default 5. Presets: Unlimited (pre-1.29 behaviour), Single use (strict), 5 uses (recommended), 10 uses, or a custom value from 0 to 100. It applies in both API Modes.
* Why 5 and not 1: when a form comes back with an unrelated error — a missing field, a mistyped password — the visitor resubmits the same challenge, and a strict limit would reject them. A small allowance keeps those submissions working. **Known trade-off at Single use (1):** such a resubmission *is* rejected, because only submissions within one request are exempt. Choose it only if your forms rarely bounce back; a future release will let the widget re-solve automatically after a rejection, at which point strict becomes comfortable.
* Custom API Mode is now covered too. OpenPorte verifies custom-backend challenges locally, so a backend's own replay protection never applied to your forms; the new counter closes that gap without any change to your backend.
* Replay protection never locks visitors out: if the counter cannot be stored, submissions are accepted as before and the settings page tells you so.
* The settings page now reports replay protection's status — the limit in force, whether the counter uses your object cache or the database, and any recent failure — alongside the existing endpoint check.
* Expiration guidance, advisory only in this release. "None" (0) now raises a red notice and values under 60 seconds a warning, but nothing is rejected or changed: the replay counter bounds reuse regardless of expiry, so there is no need to force a migration. **This means issue #99's original ask — making 0 and very short expiries unselectable — is only partly met in 1.29.0**; the hard limits, and the removal of "None", land in a later release that can change stored configuration.
* In Custom API Mode the Expiration and Complexity fields are now greyed out, because your backend sets the challenge expiry and its difficulty, not OpenPorte. The endpoint health check reports what the backend uses, and warns when it sets no expiry at all.
* For developers: `verify()` is now the only supported entry point. `verify_solution()` and `verify_server_signature()` still work but are deprecated for direct calls (they skip replay protection) and will be removed in the next major release. New `openporte_replay_limit` filter (receives the current hook as context) and `openporte_replay_store_unavailable` action.
* Uninstalling now also removes OpenPorte's transients, which the previous cleanup left behind.
* Fixed: a submission whose token was malformed in a specific way — a JSON field sent as a list or an object instead of text — could raise a PHP error instead of being rejected. Such tokens are now simply refused, like any other invalid one. This affects every release since the fork; no valid submission ever took that path.
* Behaviour change: Contact Form 7 forms that contain a manually placed widget (the `[openporte]`/`[altcha]` shortcode, or a hand-written `<altcha-widget>` tag) are now verified server-side even when the Contact Form 7 integration toggle is off, matching the long-standing HTML Forms behaviour. Sites that displayed a decorative widget this way will start rejecting submissions that do not solve it — remove the widget from the form if that is not what you want.

= 1.28.1 =

* New GitHub location and updated description of the project.
* No other changes.

= 1.28.0 =

* ALTCHA Widget 2.3.0. Note: the bundled widget file still reports "2.2.4" — upstream 2.3.0 only repackaged optional plugins OpenPorte does not use and did not rebuild its distributed file, which is byte-identical to 2.2.4. Nothing is missing.
* Removed the remaining spam-filter plumbing from the paid-SaaS classifier; verification relies on the proof-of-work challenge alone.
* WordPress and plugin integration settings are now simple on/off toggles rather than dropdowns. The removal of the spam filter reduced the dropdown choices to two values which justified their replacement with toggles. Existing settings automatically convert on upgrades; any legacy mode that displayed or verified the captcha is now "on". The two "shortcode" modes preserve their behaviour: HTML Forms’ shortcode-placed widget verification remains unaffected, while Contact Form 7’s verification is enforced even though the integration skips auto-injection for any form already displaying a widget.
* New Algorithm setting (SHA-256/384/512). New installs are seeded with SHA-512; upgraded sites keep SHA-256, so existing custom ALTCHA-compatible backends continue to verify.
* Expiration is now a preset list (5/30/60 minutes) plus a Custom input accepting 0-14400 seconds; new installs default to 5 minutes.
* Retuned the Low/Medium/High proof-of-work complexity for modern hardware; developers can adjust the ranges via the new `openporte_complexity_matrix` filter.
* The settings page now health-checks a custom Challenge URL and warns about misconfiguration (unreachable backend, algorithm or secret mismatch) before visitors are impacted.
* Settings screen refresh: per-field hints throughout, a Show/Hide toggle on the secret field (now called "Shared secret"), and clearer API-mode wording. The "Floating UI" checkbox is now a "Display Mode" dropdown menu for Inline or Floating; the setting itself is unchanged, so sites keep their current behaviour.
* "Hide logo" and "Hide footer" are now grouped as "Acknowledgement", each a Show/Hide toggle. Both remain independent settings, still off by default, so nothing changes for existing sites — the hints simply explain that the ALTCHA logo and "Protected by ALTCHA" text credit the open-source project OpenPorte is built on, and that hiding them affects nothing else.
* "Delay" is now "Verification Delay", and the pause it adds drops from 1.5 seconds to 0.5. This setting only ever changed how the check is *perceived* — it never made spam-blocking stronger, and the hint now says so and points at Complexity, which does.
* Deprecated: the "Custom HTML" integration (auto-configuring hand-written `<altcha-widget>` tags by loading the widget scripts on every front-end page) is deprecated and will be removed in the next major release — use the `[openporte]` shortcode instead. The setting is switched off on upgrade and no longer enabled on new installs; if your site relies on hand-written widget tags, re-enable the toggle under Integrations while you migrate. See **Upgrade Notice**.
* For developers and third-party integrators: the spam-filter API is removed — the `OpenPortePlugin::$spamfilter_result` property, the `get_blockspam()` method, the `$option_blockspam` option and the `blockspam` / `spamfilter` widget attributes are gone, and `verify_server_signature()` no longer parses classification data. The integration-listing methods `get_integrations()` and `has_active_integrations()` are deprecated with no replacement (they lost their only caller in 1.21.0) and will be removed in the next major release.
* Fix: Contact Form 7 showed the widget twice when a form contained an `[openporte]`/`[altcha]` shortcode and the CF7 integration was also enabled — two checkboxes to tick, two challenge requests per page. The integration no longer auto-injects a widget into a form that already renders one. This affected 1.27.x and earlier too; verification is unchanged.
* Fix: a form submitted while the widget was still verifying lost the click silently; the submission is now held and replayed once the challenge is solved.
* Fix: WooCommerce login and registration silently failed with auto-verification "On form submit" or Display Mode set to Floating (the widget's replayed submission dropped the submit button's name, which WooCommerce requires).
* Fix: wpDiscuz comments could be posted before the challenge was solved; the submission is now held until verification completes.
* Fix: the "OpenPorte cannot be activated" conflict screen now links to the Plugins page instead of a browser Back link, which failed with "the page has expired" when activating right from the plugin uploader.
* Tested on WordPress 7.0.2. The browser E2E suite (tests/e2e, not shipped) covers WordPress comments, Contact Form 7, wpDiscuz, WPForms and WooCommerce login/registration across every auto-verification and display-mode combination, with negative controls (missing, forged and expired tokens).
* 4 new translations. OpenPorte has been localised in 4 new languages: Czech, Polish, Romanian and Turkish.

= 1.27.3 =
This is a small bug-fix release.
* Branding: add a logo refresh and new banners
* Fix a conflict with ALTCHA v2+ by extending the plugin's guard at activation
* Doc: define support workflow and add it to the readmes
* Support up to WordPress 7.0.1

= 1.27.2 =
This is a security-hardening release. None of these are exploitable vulnerabilities; they are defence-in-depth improvements with no change to normal behaviour.
* Hardening: submitted verification tokens are now strictly validated before use, so malformed or junk submissions fail closed without emitting PHP warnings.
* Hardening: signed server (spam-filter) responses are now only accepted while unexpired and explicitly verified, mirroring the proof-of-work path. Minimal custom backends that omit those fields keep working.
* Hardening: new installs now generate a 256-bit HMAC signing key. Existing keys — and the challenges already signed with them — are left untouched.
* Hardening: the inline widget-configuration script now hex-escapes its JSON so attribute values cannot break out of the `<script>` context.
* Hardening: tightened the Formidable Forms autoloader class-name guard.
* Fixed a typo in the settings field markup (`autcomplete="none"` became `autocomplete="off"`).
* Removed dead code left over from the paid-SaaS removal.

= 1.27.1 =
* Renamed the Elementor form-field integration class to use the `OpenPorte_` prefix, as requested by the wordpress.org plugin review (avoids the reserved `Elementor` prefix). No behaviour change.
* Removed the wordpress.org directory icon files from the plugin package; they are deployed separately as directory assets.
* Removed the `load_plugin_textdomain()` call: since WordPress 4.6 (we require 5.6+) translations are loaded automatically by core. No behaviour change.

= 1.27.0 =
* Forked ALTCHA Spam Protection v1 as OpenPorte, a community-maintained, fully open-source (GPLv2 or later) continuation.
* Rebranded the plugin to OpenPorte: new `[openporte]` shortcode and `openporte/v1` REST namespace, with the `[altcha]` shortcode, `altcha/v1` endpoint, `altcha_*` hooks and the `ALTCHA_*` / `AltchaPlugin` symbols kept as deprecated aliases (see Deprecations).
* Existing ALTCHA v1 settings are copied into the OpenPorte namespace on activation; the original `altcha_*` options are left in place so you can roll back.
* Removed the paid altcha.org regional SaaS classifier; self-hosted proof-of-work and custom self-hostable backends are unchanged.
* Security: HMAC signatures are now compared with `hash_equals()` (timing-safe).
* Wrapped the "This form requires JavaScript!" message so it can be translated.
* Corrected the documented minimum requirements to match the plugin's existing PHP 8.0 / WordPress 5.6 floor.
Contributors (GitHub) for this release: jcberthon, ded-furby.
Co-contributors: Mistral (AI), Claude (AI), GPT-OSS (AI).

= 1.26.3 =
* Fixed possible replay attacks via salt splicing.

= 1.26.2 =
* Updated readme for the new version 2.

= 1.26.1 =
* Fix Elementor Pro Forms widget rendering

= 1.26.0 =
* Added Formidable Forms integration
* Fixed PHP warning in the verify function
* ALTCHA Widget 2.2.2

= 1.25.0 =
* Added hooks for improved customization and integration flexibility. [#45]

= 1.24.0 =
* Fix issue with duplicate widget rendering in Elementor popups and WPDiscuz replies

= 1.23.0 =
* Support for CoBlocks

= 1.22.1 =
* Fix Gravity Forms validation with custom server 

= 1.22.0 =
* Fix Forminator multi-page forms
* Fix Gravity Forms with Sentinel and fields classification

= 1.21.0 =
* ALTCHA Widget 2.0.2
* Widget scripts are now injected only on pages, which include the widget
* Support for custom Challenge URL and ALTCHA Sentinel

= 1.20.0 =
* Enfold Theme (contact and newsletter forms) integration

= 1.19.0 =
* Fix submit issues with Contact Form 7 + Conditional fields

= 1.18.0 =
* Fix language with Contact Form 7

= 1.17.0 =
* Update widget to 1.2.0
* Widget removes support for Expires header fixing potential auto-revalidation issues
* Widget script provided as a UMD module allowing for JS minification

= 1.16.0 =
* Fix reply to comments from the admin page [#36]

= 1.15.0 =
* Translations with gettext and automatic language detection [#33]

= 1.14.1 =
* Fix the "Settings" link [#32]

= 1.14.0 =
* Automatic language detection [#31]
* Change placement of the "Settings" link in the plugin list [#32]

= 1.13.1 =
* Ignore WooCommerce form submissions in WordPress integration [#30]

= 1.13.0 =
* WooCommerce integration [#26]
* Improved validation message [#27]
* Password lost error message [#28]

= 1.12.0 =
* HTML Forms - skip verification if the shortcode is not in the form markup [#23]

= 1.11.1 =
* Fix Forminator compatibility issue

= 1.11.0 =
* Added support for WP-Members

= 1.10.0 =
* Added support for WPDiscuz

= 1.9.3 =
* Fix REST API Cache-Control header

= 1.9.2 =
* Enable Custom HTML (shortcode) integration by default when activated

= 1.9.1 =
* PHP 7 support (replace str_contains by strpos) [#19]

= 1.9.0 =
* Widget updated to version 1.0.0
* CF7 - fix widget placement
* Fix page caching

= 1.8.0 =
* Shortcode (custom integration) - fix mode (SpamFilter) 

= 1.7.0 =
* HTML Forms - add Shortcode option

= 1.6.1 =
* Fix WordPress login integration

= 1.6.0 =
* Fix Elementor Pro Forms widget rendering
* Fix Contact Form 7 widget position and shortcode support

= 1.5.0 =
* Fix REST base URL (+ REST prefix removed from settings) [#13]

= 1.4.0 =
* Support for Elementor Pro Forms
* Widget updated to 0.6.7

= 1.3.1 =
* Fix site_url parsing issue [#11]

= 1.3.0 =
* Added support for custom REST API prefixes

= 1.2.0 =
* Forminator - fix widget rendering with file input
* Widget updated to 0.6.4

= 1.1.0 =
* Shortcode - support for `language` attribute

= 1.0.0 =
* Widget updated to 0.6.3

= 0.3.0 =
* Added nonce sanitization
* Removed server-side spam filter (required for Plugin Directory)

= 0.2.1 =
* Fixes requested by Plugin Directory review
* Fixed various Spam Filter issues

= 0.2.0 =
* Widget updated to 0.6.0
* Added support for Floating UI

= 0.1.7 =
* Fix Forminator multi-step forms

= 0.1.6 =
* Widget updated to 0.5.1

= 0.1.5 =
* Fixes requested by Plugin Directory review

= 0.1.4 =
* GravityForms - added label and description options
* Altcha widget updated to 0.4.3

= 0.1.3 =
* Fixed "lost password" verification bug
* Altcha widget updated to 0.4.1

= 0.1.2 =
* Fixed widgets footer link and log warnings

= 0.1.1 =
* Widget v0.4.0
* Challenge expiration

= 0.1.0 =
* First version
