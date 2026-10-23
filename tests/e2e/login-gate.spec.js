const { test, expect } = require('@playwright/test');
const { ensureWooCommerce } = require('./drivers/_woocommerce');
const {
  wp,
  wpSetOption,
  applyBaseline,
  waitForVerified,
  clickWidgetCheckbox,
  removeWidgets,
} = require('./helpers');

/**
 * wp-login.php login and lost-password protection (docs/security-audit.md
 * findings #14 and #15).
 *
 * #14: the challenge is checked before the password (on wp_authenticate_user),
 * so a missing token is refused whatever the password, and the error no longer
 * tells a correct password from a wrong one. Only with a solved challenge does
 * core get to say "incorrect password".
 *
 * #15: a WooCommerce nonce field added to a wp-login.php request no longer
 * makes the WordPress and WooCommerce checks both stand down. WooCommerce is
 * activated for those tests, with only the WordPress protection on — the
 * configuration that used to be bypassable.
 *
 * Every test runs in a fresh browser context, so no auth cookie leaks between
 * them. The lost-password happy path ends in an email the mailer-less bench
 * cannot send, so it is only asserted not to fail on the challenge.
 */

/** Fixture user. Space-free — see the wp() quoting caveat in helpers.js. */
const LOGIN_USER = 'e2e-gate-user';
const LOGIN_PASS = 'E2e-Gate-Passw0rd-2026';
const WRONG_PASS = 'definitely-not-the-password';

const CAPTCHA_ERROR = /could not verify you are not a robot/i;
const WRONG_PASSWORD_ERROR = /password you entered .* is incorrect/i;

/** Create the fixture user if absent; returns its ID. */
function ensureLoginUser() {
  try {
    return wp(`user get ${LOGIN_USER} --field=ID`);
  } catch (e) {
    // Editor: WooCommerce sends users without edit capabilities away from
    // wp-admin, and reaching wp-admin is the "logged in" assertion.
    return wp(
      `user create ${LOGIN_USER} ${LOGIN_USER}@example.com --role=editor --user_pass=${LOGIN_PASS} --porcelain`
    );
  }
}

/** Add a hidden field to the page's login or lost-password form. */
async function addField(page, name, value) {
  await page.evaluate(({ name, value }) => {
    const form = document.querySelector('#loginform, #lostpasswordform');
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = name;
    input.value = value;
    form.appendChild(input);
  }, { name, value });
}

/** Fill the login form; solve the challenge, or strip the widget. */
async function fillLogin(page, password, { solve }) {
  await page.goto('/wp-login.php');
  await page.locator('#user_login').fill(LOGIN_USER);
  await page.locator('#user_pass').fill(password);
  if (solve) {
    await clickWidgetCheckbox(page);
    await waitForVerified(page);
  } else {
    await removeWidgets(page);
  }
}

test.describe('wp-login.php protection', () => {
  test.beforeAll(() => {
    applyBaseline();
    ensureLoginUser();
  });

  test.beforeEach(() => {
    wpSetOption('openporte_integration_wordpress_login', '1');
    wpSetOption('openporte_integration_wordpress_reset_password', '1');
    wpSetOption('openporte_integration_woocommerce_login', '0');
    wpSetOption('openporte_integration_woocommerce_reset_password', '0');
  });

  test.afterAll(() => {
    wpSetOption('openporte_integration_wordpress_login', '0');
    wpSetOption('openporte_integration_wordpress_reset_password', '0');
  });

  test('a wrong password without a token gets the CAPTCHA error, not "incorrect password"', async ({ page }) => {
    await fillLogin(page, WRONG_PASS, { solve: false });
    await page.locator('#wp-submit').click();

    await expect(page.locator('#login_error')).toContainText(CAPTCHA_ERROR);
    await expect(page.locator('#login_error')).not.toContainText(WRONG_PASSWORD_ERROR);
  });

  test('a correct password without a token is refused the same way', async ({ page }) => {
    await fillLogin(page, LOGIN_PASS, { solve: false });
    await page.locator('#wp-submit').click();

    await expect(page.locator('#login_error')).toContainText(CAPTCHA_ERROR);
    await expect(page).not.toHaveURL(/wp-admin/);
  });

  test('with a solved challenge, a wrong password gets core\'s "incorrect password"', async ({ page }) => {
    await fillLogin(page, WRONG_PASS, { solve: true });
    await page.locator('#wp-submit').click();

    await expect(page.locator('#login_error')).toContainText(WRONG_PASSWORD_ERROR);
  });

  test('with a solved challenge, a correct password logs in', async ({ page }) => {
    await fillLogin(page, LOGIN_PASS, { solve: true });
    await page.locator('#wp-submit').click();

    await expect(page).toHaveURL(/wp-admin/);
  });

  test.describe('with WooCommerce active and only WordPress protection on', () => {
    test.beforeAll(() => {
      ensureWooCommerce();
    });

    test('a forged WooCommerce login field does not skip the login check', async ({ page }) => {
      await fillLogin(page, LOGIN_PASS, { solve: false });
      await addField(page, 'woocommerce-login-nonce', 'forged');
      await page.locator('#wp-submit').click();

      await expect(page.locator('#login_error')).toContainText(CAPTCHA_ERROR);
      await expect(page).not.toHaveURL(/wp-admin/);
    });

    test('a forged WooCommerce lost-password field does not skip the reset check', async ({ page }) => {
      await page.goto('/wp-login.php?action=lostpassword');
      await page.locator('#user_login').fill(LOGIN_USER);
      await removeWidgets(page);
      await addField(page, 'woocommerce-lost-password-nonce', 'forged');
      await page.locator('#wp-submit').click();

      await expect(page.locator('#login_error')).toContainText(CAPTCHA_ERROR);
    });

    test('a lost-password request with a solved challenge passes the check', async ({ page }) => {
      await page.goto('/wp-login.php?action=lostpassword');
      await page.locator('#user_login').fill(LOGIN_USER);
      await clickWidgetCheckbox(page);
      await waitForVerified(page);
      await page.locator('#wp-submit').click();
      await page.waitForLoadState('load');

      // The bench cannot send the email, so core may report that instead;
      // what matters is that the challenge did not refuse the request.
      await expect(page.locator('body')).not.toContainText(CAPTCHA_ERROR);
    });
  });
});
