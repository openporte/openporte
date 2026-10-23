const { test } = require('@playwright/test');
const drivers = require('./drivers');
const {
  wpSetOption,
  applyBaseline,
  applyCombo,
  expectTransport,
  waitForVerified,
  clickWidgetCheckbox,
  tamperWidgetToken,
  removeWidgets,
  captureWidgetToken,
  injectToken,
} = require('./helpers');

// '' = the "Disabled" auto-verification mode (user ticks the checkbox).
const AUTO_MODES = ['', 'onload', 'onfocus', 'onsubmit'];
const FLOATING_MODES = [0, 1];

let counter = 0;

for (const driver of drivers) {
  test.describe(driver.key, () => {
    let ctx;

    test.beforeAll(async () => {
      applyBaseline();
      // ensure() may be async (a driver waiting for its plugin to be live).
      ctx = await driver.ensure();
      wpSetOption(driver.option, '1');
    });

    for (const auto of AUTO_MODES) {
      for (const floating of FLOATING_MODES) {
        const label = `auto=${auto || 'disabled'} floating=${floating ? 'on' : 'off'}`;

        test(`accepts a solved submission (${label})`, async ({ page }) => {
          applyCombo({ auto, floating });
          driver.reset?.(ctx);
          const marker = `e2e ${driver.key} ${label} #${++counter} ${Date.now()}`;

          await driver.open(page, ctx);
          await expectTransport(page);
          await driver.fill(page, ctx, marker);

          // Pre-solve where the mode supports it. The rows that don't:
          //   - auto=onsubmit: nothing to pre-solve, the widget starts the
          //     solve from the submit itself and replays it when done.
          //   - floating on: the widget is anchored to the submit button, so
          //     these rows submit straight away and land mid-solve. Floating
          //     UI only implies onsubmit when auto is unset — with
          //     auto=onload/onfocus the click hits a required, still-unticked
          //     checkbox and is swallowed by constraint validation unless the
          //     capture-phase glue in public/script.js holds and replays it.
          //     That race is exactly what these rows cover.
          if (!floating && auto !== 'onsubmit') {
            if (auto === '') {
              await clickWidgetCheckbox(page);
            }
            if (auto === 'onfocus') {
              // fill() already focused the fields; solving should be running.
            }
            await waitForVerified(page);
          }

          await driver.submit(page, ctx);
          await driver.expectAccepted(page, ctx, marker);
        });
      }
    }

    test('rejects a submission that bypasses the widget', async ({ page }) => {
      // One negative control per integration: without it, a combo could
      // "pass" simply because server-side verification is not wired up.
      applyCombo({ auto: '', floating: 0 });
      driver.reset?.(ctx);
      const marker = `e2e ${driver.key} bypass #${++counter} ${Date.now()}`;

      await driver.open(page, ctx);
      await driver.fill(page, ctx, marker);
      await removeWidgets(page);
      await driver.submit(page, ctx);
      await driver.expectRejected(page, ctx);
    });

    test('rejects a tampered widget token', async ({ page }) => {
      // Second negative control: the bypass test covers a *missing* token,
      // this one a *forged* token — a genuinely solved payload whose HMAC
      // signature is corrupted after the solve. Client-side everything looks
      // verified; only the server's signature check can catch it.
      applyCombo({ auto: '', floating: 0 });
      driver.reset?.(ctx);
      const marker = `e2e ${driver.key} tampered #${++counter} ${Date.now()}`;

      await driver.open(page, ctx);
      await driver.fill(page, ctx, marker);
      await clickWidgetCheckbox(page);
      await waitForVerified(page);
      await tamperWidgetToken(page);
      await driver.submit(page, ctx);
      await driver.expectRejected(page, ctx);
    });

    if (driver.key === 'wordpress-comments') {
      test('rejects an expired (stale, replayed) token', async ({ page }) => {
        // Expiry is enforced in OpenPortePlugin::verify_solution() from the
        // expires timestamp the server signed into the salt — the check is
        // integration-agnostic, so one driver covers it for the whole suite.
        applyCombo({ auto: '', floating: 0 });
        wpSetOption('openporte_expires', '2');
        try {
          driver.reset?.(ctx);
          const marker = `e2e ${driver.key} expired #${++counter} ${Date.now()}`;

          await driver.open(page, ctx);
          await driver.fill(page, ctx, marker);
          await clickWidgetCheckbox(page);
          await waitForVerified(page);
          // Capture the solved token and detach the widget: a live widget
          // auto-refetches expired challenges (refetchonexpire defaults on)
          // and would swap in a fresh token before we submit. Detaching turns
          // this into the real attack shape — replaying a stale token.
          const stale = await captureWidgetToken(page);
          await removeWidgets(page);
          // Outlive the 2 s validity window, then replay the stale token.
          await page.waitForTimeout(4000);
          await injectToken(page, stale);
          await driver.submit(page, ctx);
          await driver.expectRejected(page, ctx);
        } finally {
          wpSetOption('openporte_expires', '300'); // restore the baseline
        }
      });
    }
  });
}
