const { expect } = require('@playwright/test');
const { wp, wpSetOption } = require('../helpers');
const { ensureWooCommerce, customerIdIfPresent } = require('./_woocommerce');

/**
 * WooCommerce registration form (native POST; server-side check hooks
 * `woocommerce_process_registration_errors`, and the widget posts under the
 * custom name "openporte_register" — see integrations/woocommerce.php).
 *
 * Accepted: WooCommerce logs the new customer in and redirects to the
 * dashboard ("Log out" navigation). Rejected: the validation error surfaces
 * as a WooCommerce notice and no account is created.
 *
 * The third WooCommerce surface (reset password) is deliberately not driven:
 * it shares the register code path (render hook + a *_post filter) but its
 * happy path ends in an email, which the mailer-less bench can't observe.
 */
module.exports = {
  key: 'woocommerce-register',
  option: 'openporte_integration_woocommerce_register',

  ensure() {
    const base = ensureWooCommerce();
    // Same page as the login form — keep the sibling widgets off (see the
    // login driver).
    wpSetOption('openporte_integration_woocommerce_login', '0');
    wpSetOption('openporte_integration_woocommerce_reset_password', '0');
    wpSetOption('woocommerce_enable_myaccount_registration', 'yes');
    // Explicit password field: the default emails a generated password, which
    // the mailer-less bench would silently drop.
    wpSetOption('woocommerce_registration_generate_password', 'no');
    // The login driver's fixture customer must survive reset()'s cleanup.
    return { ...base, keepId: customerIdIfPresent() };
  },

  // Registered accounts accumulate — delete them before each test, sparing
  // the login fixture customer.
  reset(ctx) {
    const ids = wp('user list --role=customer --field=ID')
      .split(/\s+/)
      .filter(Boolean)
      .filter((id) => id !== ctx.keepId);
    if (ids.length) {
      wp(`user delete ${ids.join(' ')} --yes`);
    }
  },

  async open(page, ctx) {
    await page.goto(ctx.path);
    await page.locator('form.woocommerce-form-register').waitFor();
  },

  // The marker is unused; uniqueness comes from the timestamped email. The
  // password must satisfy WooCommerce's client-side strength meter (it
  // disables the submit button below "strong") — it never crosses the shell
  // boundary, so any characters are fine here.
  async fill(page) {
    await page.locator('#reg_email').fill(`e2e-reg-${Date.now()}@example.com`);
    await page.locator('#reg_password').fill('Kv9#mQx2!pTz7@Wf');
    // Blur now: leaving the password field grows the strength-meter hint,
    // shifting the Register button ~30px down. If that happens between the
    // submit click's mousedown and mouseup, the click lands on the form
    // background and silently does nothing.
    await page.locator('#reg_password').blur();
  },

  async submit(page) {
    await page.locator('form.woocommerce-form-register button[name="register"]').click();
  },

  async expectAccepted(page) {
    await expect(page.locator('body')).toContainText(/log out/i);
  },

  async expectRejected(page) {
    await expect(page.locator('body')).toContainText(/could not verify/i);
  },
};
