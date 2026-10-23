const { test, expect } = require('@playwright/test');
const comments = require('./drivers/comments');
const contactForm7 = require('./drivers/contact-form-7');
const { ensureWooCommerce } = require('./drivers/_woocommerce');
const {
  wp,
  wpSetOption,
  applyBaseline,
  applyCombo,
  purgeComments,
  waitForVerified,
  clickWidgetCheckbox,
  removeWidgets,
  captureWidgetToken,
  injectToken,
  tagWidgetForm,
} = require('./helpers');

/**
 * Replay-limit behaviour (issue #101).
 *
 * The settings matrix proves one solved token is accepted once. This suite
 * proves what happens when the *same* token comes back: bounded reuse, keyed
 * on the token's signature and counted server-side.
 *
 * Two directions matter equally and pull against each other:
 *
 *   R2 — a captured token must stop being accepted once its budget is spent.
 *   R3 — a legitimate visitor whose form came back with an unrelated error
 *        resubmits the very same token, and that must keep working.
 *
 * Both are exercised here against real submissions, because the memo/counter
 * split only makes sense across real request boundaries: the unit suite
 * (tests/phpunit) can prove the arithmetic, but only a browser can prove that
 * one visitor's click costs exactly one use.
 *
 * Every test restores openporte_replaylimit in a finally block. The matrix
 * suite's applyBaseline() also pins it back to the default of 5, so a crash
 * mid-test cannot leave a strict limit behind to poison later runs.
 */

const DEFAULT_LIMIT = '5';

/** Fixture user for the login test. Space-free — see the wp() quoting caveat. */
const LOGIN_USER = 'e2e-replay-user';
const LOGIN_PASS = 'E2e-Replay-Passw0rd-2026';

/** Create the login fixture user if absent; returns its ID. */
function ensureLoginUser() {
  try {
    return wp(`user get ${LOGIN_USER} --field=ID`);
  } catch (e) {
    // Editor, not subscriber or customer: WooCommerce redirects users without
    // shop/edit capabilities away from wp-admin, and wp-admin is what makes
    // "the login actually succeeded" assertable.
    return wp(
      `user create ${LOGIN_USER} ${LOGIN_USER}@example.com --role=editor --user_pass=${LOGIN_PASS} --porcelain`
    );
  }
}

test.describe('replay limit', () => {
  let ctx;

  test.beforeAll(async () => {
    applyBaseline();
    applyCombo({ auto: '', floating: 0 });
    ctx = await comments.ensure();
    wpSetOption(comments.option, '1');
  });

  test.afterAll(() => {
    wpSetOption('openporte_replaylimit', DEFAULT_LIMIT);
  });

  /**
   * Solve once on a freshly loaded form and hand back the token, leaving the
   * page ready to submit it.
   */
  async function solveAndCapture(page, marker) {
    await comments.open(page, ctx);
    await comments.fill(page, ctx, marker);
    await clickWidgetCheckbox(page);
    await waitForVerified(page);
    return captureWidgetToken(page);
  }

  /**
   * Post a previously captured token as a bare replay: fresh page, no widget,
   * no solve — just the same string again, which is all a bot has.
   */
  async function replay(page, token, marker) {
    purgeComments(ctx.postId); // core's 15 s same-IP comment throttle
    await comments.open(page, ctx);
    await comments.fill(page, ctx, marker);
    await tagWidgetForm(page);
    await removeWidgets(page);
    await injectToken(page, token);
    await comments.submit(page, ctx);
  }

  test('spends the whole budget, then refuses the next replay', async ({ page }) => {
    // Limit 3 rather than the default 5: same proof, two fewer page loads.
    wpSetOption('openporte_replaylimit', '3');
    try {
      comments.reset(ctx);
      const stamp = Date.now();
      const token = await solveAndCapture(page, `e2e replay budget 1 ${stamp}`);

      await comments.submit(page, ctx);
      await comments.expectAccepted(page, ctx, `e2e replay budget 1 ${stamp}`);

      // Uses 2 and 3: the same token, posted again as its own request. This is
      // R3 — the shape a visitor resubmitting after a form error produces.
      for (const use of [2, 3]) {
        const marker = `e2e replay budget ${use} ${stamp}`;
        await replay(page, token, marker);
        await comments.expectAccepted(page, ctx, marker);
      }

      // Use 4 is over the budget: R2.
      await replay(page, token, `e2e replay budget 4 ${stamp}`);
      await comments.expectRejected(page, ctx);
    } finally {
      wpSetOption('openporte_replaylimit', DEFAULT_LIMIT);
    }
  });

  test('refuses the first replay when the limit is strict', async ({ page }) => {
    wpSetOption('openporte_replaylimit', '1');
    try {
      comments.reset(ctx);
      const stamp = Date.now();
      const marker = `e2e replay strict 1 ${stamp}`;
      const token = await solveAndCapture(page, marker);

      await comments.submit(page, ctx);
      await comments.expectAccepted(page, ctx, marker);

      await replay(page, token, `e2e replay strict 2 ${stamp}`);
      await comments.expectRejected(page, ctx);
    } finally {
      wpSetOption('openporte_replaylimit', DEFAULT_LIMIT);
    }
  });

  test('accepts unlimited replays when the limit is 0', async ({ page }) => {
    // 0 is the documented escape hatch: pre-1.29 behaviour, no state written.
    // Without this, a bug that made the counter unconditional would go unseen.
    wpSetOption('openporte_replaylimit', '0');
    try {
      comments.reset(ctx);
      const stamp = Date.now();
      const marker = `e2e replay unlimited 1 ${stamp}`;
      const token = await solveAndCapture(page, marker);

      await comments.submit(page, ctx);
      await comments.expectAccepted(page, ctx, marker);

      for (const use of [2, 3, 4, 5, 6]) {
        const replayMarker = `e2e replay unlimited ${use} ${stamp}`;
        await replay(page, token, replayMarker);
        await comments.expectAccepted(page, ctx, replayMarker);
      }
    } finally {
      wpSetOption('openporte_replaylimit', DEFAULT_LIMIT);
    }
  });

  test('accepts an AJAX resubmission of the same token', async ({ page }) => {
    // The R3 case in its native habitat: Contact Form 7 never navigates, so a
    // visitor correcting a field resubmits from the same page with the token
    // already in hand. Re-injecting explicitly rather than relying on whether
    // CF7 resets the form keeps the test about OpenPorte, not about CF7.
    wpSetOption('openporte_replaylimit', '5');
    const cf7 = await contactForm7.ensure();
    wpSetOption(contactForm7.option, '1');
    try {
      const stamp = Date.now();
      await contactForm7.open(page, cf7);
      await contactForm7.fill(page, cf7, `e2e ajax resubmit 1 ${stamp}`);
      await clickWidgetCheckbox(page);
      await waitForVerified(page);
      const token = await captureWidgetToken(page);

      await contactForm7.submit(page, cf7);
      await contactForm7.expectAccepted(page, cf7);

      await removeWidgets(page);
      for (const use of [2, 3]) {
        await contactForm7.fill(page, cf7, `e2e ajax resubmit ${use} ${stamp}`);
        await injectToken(page, token);
        await contactForm7.submit(page, cf7);
        await contactForm7.expectAccepted(page, cf7);
      }
    } finally {
      wpSetOption(contactForm7.option, '0');
      wpSetOption('openporte_replaylimit', DEFAULT_LIMIT);
    }
  });

  test('still logs a user in when the limit is strict', async ({ page }) => {
    // The highest-harm regression this feature could cause: a strict limit of 1
    // that locks every user out of the site. WordPress and WooCommerce both
    // check the login, kept mutually exclusive by
    // openporte_is_woocommerce_form_submission() (see AGENTS.md), so
    // WooCommerce is activated deliberately here — both integrations' callbacks
    // are registered while the login runs.
    //
    // What this pins, precisely: that only the WordPress integration acts on
    // wp-login.php, that the login succeeds on the single use the limit allows,
    // and that the counter is really live on this path.
    //
    // Since 1.30.0 it also pins the per-request memo on a real login. The
    // WordPress integration verifies twice in this one request — on
    // wp_authenticate_user before the password check, then on the
    // `authenticate` backstop (docs/security-audit.md #14) — and at a limit of
    // 1 the second check succeeds only because the memo answers it without
    // consuming another use. VerifyMemoTest covers the memo in isolation.
    ensureWooCommerce();
    wpSetOption('openporte_integration_wordpress_login', '1');
    wpSetOption('openporte_integration_woocommerce_login', '0');
    wpSetOption('openporte_replaylimit', '1');
    ensureLoginUser();
    try {
      await page.goto('/wp-login.php');
      await page.locator('#user_login').fill(LOGIN_USER);
      await page.locator('#user_pass').fill(LOGIN_PASS);
      await clickWidgetCheckbox(page);
      await waitForVerified(page);
      const token = await captureWidgetToken(page);
      await page.locator('#wp-submit').click();

      await expect(page).toHaveURL(/wp-admin/);
      await expect(page.locator('body')).not.toContainText(/could not verify/i);

      // And the counter really is live on this path: the token that just
      // logged the user in is spent, so replaying it must fail. Without this
      // the test above would also pass if enforcement never ran at all.
      await page.context().clearCookies();
      await page.goto('/wp-login.php');
      await page.locator('#user_login').fill(LOGIN_USER);
      await page.locator('#user_pass').fill(LOGIN_PASS);
      await tagWidgetForm(page);
      await removeWidgets(page);
      await injectToken(page, token);
      await page.locator('#wp-submit').click();
      await expect(page.locator('body')).toContainText(/could not verify/i);
    } finally {
      wpSetOption('openporte_integration_wordpress_login', '0');
      wpSetOption('openporte_replaylimit', DEFAULT_LIMIT);
    }
  });
});
