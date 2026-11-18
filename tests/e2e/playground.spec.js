const { test, expect } = require('@playwright/test');

const { solveWidgetWithMouse } = require('./helpers');

const demoURL = process.env.OPENPORTE_PLAYGROUND_URL;

/** Post directly to CF7 so browser validation cannot mask a verification bypass. */
async function postProof(page, proof, nonce = null) {
  return page.evaluate(async ({ token, restNonce }) => {
    const form = document.querySelector('form.wpcf7-form');
    const data = new FormData(form);
    data.set('demo-message', 'Direct verification check');
    data.delete('altcha');
    if (token !== null) data.set('altcha', token);
    const api = window.wpcf7.api;
    const headers = restNonce ? { 'X-WP-Nonce': restNonce } : {};
    // Check the REST identity with exactly the credentials used for feedback.
    const identityResponse = await fetch(`${api.root}wp/v2/users/me`, { headers });
    const identity = await identityResponse.json();
    if (restNonce ? !(identityResponse.ok && identity.id > 0) : identity.code !== 'rest_not_logged_in') {
      throw new Error('Unexpected REST authentication state');
    }
    const response = await fetch(`${api.root}${api.namespace}/contact-forms/${data.get('_wpcf7')}/feedback`, {
      method: 'POST', body: data, headers,
    });
    if (!response.ok) throw new Error(`CF7 request failed: ${response.status}`);
    return response.json();
  }, { token: proof, restNonce: nonce });
}

test.describe('WordPress.org Playground demo', () => {
  test.skip(!demoURL, 'Set OPENPORTE_PLAYGROUND_URL to a fresh generated blueprint instance.');
  // wp-admin opts into cross-document view transitions unless reduced motion
  // is preferred. In headless Chromium the transition after the settings
  // save redirect is skipped and the page stops rendering, so no later click
  // becomes actionable. Visitors still get the transition; the test opts out.
  // (Not a top-level `use` option in Playwright Test: it goes through contextOptions.)
  test.use({ contextOptions: { reducedMotion: 'reduce' } });

  test('real verification, navigation, and visitor experience', async ({ page }) => {
    const errors = [];
    page.on('pageerror', (error) => errors.push(error.message));
    await page.goto(demoURL);
    await expect(page.getByRole('heading', { name: 'Try OpenPorte', exact: true })).toBeVisible();
    await expect(page.locator('altcha-widget')).toHaveCount(1);
    await expect(page.locator('form.wpcf7-form textarea')).toHaveCount(1);
    await expect(page.locator('form.wpcf7-form input[type="email"], form.wpcf7-form input[type="text"]')).toHaveCount(0);
    await expect(page.getByText('Demo only—no email is sent.')).toBeVisible();
    await expect(page.locator('#wpadminbar')).toBeVisible();

    // WordPress's authenticated admin AJAX action issues the REST nonce.
    // Cookies alone are insufficient: REST otherwise resets the user to 0.
    const settingsURL = await page.locator('a[href*="page=openporte_admin"]').getAttribute('href');
    const nonceResponse = await page.request.get(new URL('admin-ajax.php?action=rest-nonce', settingsURL).href);
    expect(nonceResponse.ok()).toBe(true);
    const nonce = (await nonceResponse.text()).trim();
    expect(nonce).toMatch(/^[a-f0-9]{10}$/);
    expect((await postProof(page, null, nonce)).status).toBe('spam');
    expect((await postProof(page, 'invalid-proof', nonce)).status).toBe('spam');
    await page.getByLabel('Message', { exact: true }).fill('Authenticated submission');
    await solveWidgetWithMouse(page);
    const proof = await page.locator('input[name="altcha"]').inputValue();
    expect((await postProof(page, proof, nonce)).status).toBe('mail_sent');
    await page.reload();

    await page.getByRole('button', { name: 'Submit message' }).click();
    await expect(page.locator('form.wpcf7-form')).toHaveAttribute('data-status', 'invalid');
    await expect(page.locator('.wpcf7-not-valid-tip')).toHaveText('Please enter a sample message.');
    await page.reload();

    for (let attempt = 0; attempt < 2; attempt++) {
      await page.getByLabel('Message', { exact: true }).fill(`Hello from test ${attempt}`);
      await solveWidgetWithMouse(page);
      await page.getByRole('button', { name: 'Submit message' }).click();
      await expect(page.locator('form.wpcf7-form')).toHaveAttribute('data-status', 'sent');
      await expect(page.locator('.wpcf7-response-output')).toHaveText(
        'Verification successful. Your demo message was accepted. No email was sent.'
      );
      await page.reload();
    }

    await page.getByText('See how this is set up', { exact: true }).click();
    await page.getByRole('link', { name: 'Explore OpenPorte settings' }).click();
    await expect(page).toHaveURL(/page=openporte_admin/);
    await page.getByLabel('Self-hosted transport', { exact: true }).selectOption('ajax');
    await page.getByRole('button', { name: 'Save Changes', exact: true }).click();
    await expect(page.getByLabel('Self-hosted transport', { exact: true })).toHaveValue('ajax');
    await page.getByRole('link', { name: 'Back to demo', exact: true }).click();
    await expect(page.locator('altcha-widget')).toHaveAttribute('challengeurl', /admin-ajax\.php/);
    await page.getByLabel('Message', { exact: true }).fill('Retry after changing transport');
    await solveWidgetWithMouse(page);
    await page.getByRole('button', { name: 'Submit message' }).click();
    await expect(page.locator('form.wpcf7-form')).toHaveAttribute('data-status', 'sent');
    await page.getByText('See how this is set up', { exact: true }).click();
    await page.getByRole('link', { name: 'Explore OpenPorte settings' }).click();
    await page.getByLabel('Self-hosted transport', { exact: true }).selectOption('rest');
    await page.getByRole('button', { name: 'Save Changes', exact: true }).click();
    await expect(page.getByLabel('Self-hosted transport', { exact: true })).toHaveValue('rest');
    await page.getByRole('link', { name: 'Back to demo', exact: true }).click();
    await page.getByText('See how this is set up', { exact: true }).click();
    await page.getByRole('link', { name: 'View the Contact Form 7 form' }).click();
    await expect(page).toHaveURL(/page=wpcf7/);
    await expect(page.getByRole('link', { name: 'Back to demo', exact: true })).toBeVisible();
    await page.getByRole('link', { name: 'Back to demo', exact: true }).click();

    // A narrow viewport still exposes a labelled, keyboard-operable form.
    await page.setViewportSize({ width: 375, height: 812 });
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
    await page.getByLabel('Message', { exact: true }).focus();
    await page.keyboard.type('Keyboard submission');
    await page.keyboard.press('Tab');
    await expect(page.locator('altcha-widget input[type="checkbox"]')).toBeFocused();
    await page.keyboard.press('Space');
    await expect(page.locator('altcha-widget .altcha')).toHaveAttribute('data-state', 'verified');

    // The widget's attribution links are also keyboard focus stops.
    const submit = page.getByRole('button', { name: 'Submit message' });
    for (let tabs = 0; tabs < 4; tabs++) {
      await page.keyboard.press('Tab');
      if (await submit.evaluate((element) => element === document.activeElement)) break;
    }
    await expect(submit).toBeFocused();
    await page.keyboard.press('Enter');
    await expect(page.locator('form.wpcf7-form')).toHaveAttribute('data-status', 'sent');
    await expect(page.locator('.wpcf7-response-output')).toHaveText(
      'Verification successful. Your demo message was accepted. No email was sent.'
    );

    // Use the real logout link, including its nonce, rather than assuming that
    // a new browser context defeats Playground's initial automatic login.
    const logout = await page.locator('#wp-admin-bar-logout a').getAttribute('href');
    await page.goto(logout);
    await page.goto(demoURL);
    await expect(page.locator('#wpadminbar')).toHaveCount(0);
    expect((await postProof(page, null)).status).toBe('spam');
    expect((await postProof(page, 'invalid-proof')).status).toBe('spam');
    await page.getByLabel('Message', { exact: true }).fill('Visitor submission');
    await solveWidgetWithMouse(page);
    await page.getByRole('button', { name: 'Submit message' }).click();
    await expect(page.locator('form.wpcf7-form')).toHaveAttribute('data-status', 'sent');
    expect(errors).toEqual([]);
  });
});
