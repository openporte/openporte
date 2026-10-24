const { test, expect } = require('@playwright/test');
const { wp, wpSetOption } = require('./helpers');

/**
 * The settings page's live behaviour (issue #102): public/admin.js switches
 * the mode-specific fields as API Mode changes, and reveals the number input
 * of a "Custom" preset. The PHP renderer and sanitizers are unit-tested
 * (tests/phpunit/SettingsRendererTest.php, SettingsSanitizerTest.php); this
 * is the browser half — the toggles themselves, and a real save in Custom
 * mode, where the disabled fields are not submitted and the sanitizers must
 * keep their stored values.
 *
 * The suite saves settings, so it snapshots the options it touches and
 * restores them afterwards. Values are space-free: see the wp() quoting
 * caveat in helpers.js.
 */

/** Options this suite changes, restored in afterAll. */
const TOUCHED = [
  'openporte_api',
  'openporte_api_custom_url',
  'openporte_complexity',
  'openporte_expires',
  'openporte_selfhosted_transport',
  'openporte_replaylimit',
  'openporte_integration_wordpress_login',
];

/** A Challenge URL nothing listens on: the endpoint health check fails fast. */
const DEAD_CHALLENGE_URL = 'http://127.0.0.1:9/challenge';

/** Settings controlled by API Mode, as rendered by admin/options.php. */
function fields(page) {
  return {
    api: page.locator('#openporte_api'),
    challengeUrl: page.locator('#openporte_api_custom_url'),
    complexity: page.locator('#openporte_complexity'),
    expires: page.locator('#openporte_expires'),
    transport: page.locator('#openporte_selfhosted_transport'),
    notes: page.locator('[data-selfhosted-note]'),
  };
}

/** Assert the fields match a mode: Custom disables what the backend owns. */
async function expectMode(page, custom) {
  const f = fields(page);
  for (const owned of [f.complexity, f.expires, f.transport]) {
    if (custom) {
      await expect(owned).toBeDisabled();
    } else {
      await expect(owned).toBeEnabled();
    }
  }
  if (custom) {
    await expect(f.challengeUrl).toBeEnabled();
  } else {
    await expect(f.challengeUrl).toBeDisabled();
  }
  const notes = await f.notes.count();
  expect(notes).toBeGreaterThanOrEqual(3);
  for (let i = 0; i < notes; i++) {
    if (custom) {
      await expect(f.notes.nth(i)).toBeVisible();
    } else {
      await expect(f.notes.nth(i)).toBeHidden();
    }
  }
}

let snapshot = {};

test.beforeAll(() => {
  for (const name of TOUCHED) {
    try {
      snapshot[name] = wp(`option get ${name}`);
    } catch (e) {
      snapshot[name] = '';
    }
  }
});

test.afterAll(() => {
  for (const name of TOUCHED) {
    wpSetOption(name, snapshot[name]);
  }
});

test.beforeEach(async ({ page }) => {
  // A known Self-hosted state, with non-default values so a save that
  // dropped them would show.
  wpSetOption('openporte_api', 'selfhosted');
  wpSetOption('openporte_api_custom_url', DEAD_CHALLENGE_URL);
  wpSetOption('openporte_complexity', 'medium');
  wpSetOption('openporte_expires', '1800');
  wpSetOption('openporte_selfhosted_transport', 'ajax');
  wpSetOption('openporte_replaylimit', '5');
  // The admin login below must not need a solved challenge.
  wpSetOption('openporte_integration_wordpress_login', '0');

  await page.goto('/wp-login.php');
  // Same login as shared-secret.spec.js: both values in one task, so core's
  // delayed focus() cannot redirect the typing.
  await page.locator('#loginform').evaluate((form, credentials) => {
    form.elements.log.value = credentials.username;
    form.elements.pwd.value = credentials.password;
  }, {
    username: process.env.WP_ADMIN_USER || 'admin',
    password: process.env.WP_ADMIN_PASSWORD || 'password',
  });
  await Promise.all([
    page.waitForURL('**/wp-admin/**'),
    page.locator('#wp-submit').click(),
  ]);
  await page.goto('/wp-admin/options-general.php?page=openporte_admin');
});

test('API Mode switches the mode-specific fields live', async ({ page }) => {
  await expectMode(page, false);

  await fields(page).api.selectOption('custom');
  await expectMode(page, true);

  await fields(page).api.selectOption('selfhosted');
  await expectMode(page, false);
});

test('a "Custom" preset reveals its number input', async ({ page }) => {
  for (const [select, input, preset] of [
    ['#openporte_expires', '#openporte_expires_custom', '300'],
    ['#openporte_replaylimit', '#openporte_replaylimit_custom', '5'],
  ]) {
    await expect(page.locator(input)).toBeHidden();
    await page.locator(select).selectOption('custom');
    await expect(page.locator(input)).toBeVisible();
    await page.locator(select).selectOption(preset);
    await expect(page.locator(input)).toBeHidden();
  }
});

test('saving in Custom mode keeps the stored values of the disabled fields', async ({ page }) => {
  await fields(page).api.selectOption('custom');
  await expectMode(page, true);

  await page.locator('#submit').click();
  await expect(page.locator('#setting-error-settings_updated')).toBeVisible();

  // The browser omitted the disabled fields; their sanitizers must have kept
  // what was stored rather than resetting it.
  expect(wp('option get openporte_api')).toBe('custom');
  expect(wp('option get openporte_complexity')).toBe('medium');
  expect(wp('option get openporte_expires')).toBe('1800');
  expect(wp('option get openporte_selfhosted_transport')).toBe('ajax');

  // And the server renders the saved mode on the next load.
  await page.reload();
  await expectMode(page, true);
});
