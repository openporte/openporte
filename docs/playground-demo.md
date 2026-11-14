# WordPress.org Playground demo

The preview gives site owners a working Contact Form 7 form before they install
OpenPorte. It starts in English with one message field, an inline verification
checkbox, and a submit button. Verification is real; email delivery and message
storage are disabled. Settings exploration is optional.

## Sources and generation

- `demos/cf7/content.php`: demo copy, using the separate `openporte-demo` domain.
- `demos/cf7/setup.php`: creates the form, landing page, and minimal front-page
  template using WordPress/CF7 APIs. IDs are stored in demo-specific options so
  repeating setup updates the same objects. Repeating setup resets demo settings.
- `demos/cf7/demo.php`: Playground-only MU plugin for dynamic settings links,
  the return link, translations, and the page stylesheet.
- `bin/demo/build.mjs`: embeds those files into
  `.wordpress-org/blueprints/blueprint.json`. Do not hand-edit the output.

```sh
npm run demo:build
npm run demo:check
```

Generation uses Node.js built-ins and requires no npm installation. All demo
setup files are embedded; the preview downloads WordPress, released OpenPorte,
CF7, and Twenty Twenty-Five from WordPress.org. PHP is set to 8.3, WordPress to
`latest`, and the locale to `en_US`. Each fresh launch follows the public plugin
releases; it does not install the current Git branch. For release-candidate
testing, use a temporary copy of the blueprint pointing at the candidate zip.

The plugin's normal CF7 integration inserts the widget and checks the proof.
The sample form uses `skip_mail: on` and `do_not_store: true`. Do not enable
CF7's subscribers-only mode: it skips spam verification. No storage plugin is
installed. The demo helper does not log or save submitted messages.

An administrator session makes the settings links immediately usable. Both
authenticated and logged-out submissions must still pass proof verification.
Changing settings affects the running demo; opening a new Preview from the
listing creates a fresh instance. Reloading the page is not a reset.

## Validate before publication

First run `npm run demo:check`, PHP syntax checks on `demos/cf7/*.php`, and the
repository's PHP analysers. Validate the JSON against
<https://playground.wordpress.net/blueprint-schema.json>.

Launch the generated JSON in Playground (import it through Playground's
Blueprint interface), or serve it locally with the Playground CLI:

```sh
npx @wp-playground/cli server \
  --blueprint=.wordpress-org/blueprints/blueprint.json --port=9400
```

Use a fresh instance for each browser test run; the test deliberately logs out.
The existing browser-test dependencies are under `tests/e2e`:

```sh
cd tests/e2e
npm install
npx playwright install chromium
OPENPORTE_PLAYGROUND_URL=http://127.0.0.1:9400 npm run test:playground
```

`PLAYWRIGHT_CHROMIUM_EXECUTABLE` optionally selects an installed Chromium binary.
The demo test skips during the ordinary wp-env matrix unless its URL is supplied.

The automated journey covers the message-only form, real proof solving, repeated
successful submissions, direct missing/invalid-proof rejection, settings/editor
navigation, keyboard interaction, a narrow viewport, and logged-out submission.
It also checks for uncaught browser errors. Manually check readable status
messages, empty-message validation, configuration changes followed by retrying
the form, and recovery through a new Preview. Inspect PHP/browser logs and
confirm no outgoing mail or message storage. Record the WordPress, PHP,
OpenPorte, CF7, and Playground versions used in the PR/release checks.

## Translation later

For English copy changes, run `npm run demo:i18n` (WP-CLI required), then rebuild
the blueprint. This extracts `demos/cf7/languages/openporte-demo.pot` without
changing the plugin's existing catalogue. Follow `docs/agents/i18n.md` for
gettext conventions and placeholder comments.

For a future language, translate the demo POT into
`openporte-demo-<locale>.po`, compile its MO file, and extend the generator to
embed the selected catalogue under
`wp-content/mu-plugins/openporte-demo/languages/`. Set the generator's locale
and ensure WordPress/CF7 language packs and OpenPorte's widget translation are
available. The MU plugin loads the demo domain on `init`, before setup reads
the strings. Setup then creates page content and CF7 messages in that locale.
Changing WordPress's language after setup does not translate already-created
content; regenerate a fresh demo instead.

Stable IDs, full strings, and logical CSS properties keep translations independent
of navigation and layout. No language picker or additional locale ships yet.

## Publication and recovery

The pinned 10up deployment action defaults `ASSETS_DIR` to `.wordpress-org`,
recursively copies it to SVN `assets/`, and runs `svn add --force`. Nested
`blueprints/blueprint.json` is therefore included. The release workflow checks
that the generated file matches its sources before deploying. The existing
branding sync script copies named images and leaves the blueprint directory alone.

1. Publish through the normal release process. Demo sources and helpers are
   excluded from the installable plugin zip by `.distignore`; the JSON is a
   directory asset, not runtime plugin code.
2. Confirm the deployed file at
   <https://plugins.svn.wordpress.org/openporte/assets/blueprints/blueprint.json>.
3. As a plugin committer, use **Test Preview** on WordPress.org and run the
   acceptance journey against the deployed asset and released plugins.
4. Enable public Preview from the plugin's **Advanced** view only after that
   check. Creating/merging the PR alone does not enable the directory button.
5. If the preview breaks, disable public Preview, repair and redeploy the asset,
   and repeat the committer check before enabling it again.

Other integrations, migration walkthroughs, multilingual navigation, and broader
directory marketing changes remain outside this first demo.
