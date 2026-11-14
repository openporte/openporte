const { test, expect } = require('@playwright/test');

const demoURL = process.env.OPENPORTE_PLAYGROUND_URL;

/** Post directly to CF7 so browser validation cannot mask a verification bypass. */
async function postProof(page, proof) {
  return page.evaluate(async (token) => {
    const form = document.querySelector('form.wpcf7-form');
    const data = new FormData(form);
    data.set('demo-message', 'Direct verification check');
    data.delete('altcha');
    if (token !== null) data.set('altcha', token);
    const api = window.wpcf7.api;
    const response = await fetch(`${api.root}${api.namespace}/contact-forms/${data.get('_wpcf7')}/feedback`, {
      method: 'POST', body: data,
    });
    return response.json();
  }, proof);
}

test.describe('WordPress.org Playground demo', () => {
  test.skip(!demoURL, 'Set OPENPORTE_PLAYGROUND_URL to a fresh generated blueprint instance.');

  test('real verification, navigation, and visitor experience', async ({ page }) => {
    const errors = [];
    page.on('pageerror', (error) => {
      // Chromium can cancel WordPress's cross-document transition when the
      // journey navigates between admin and front end. This native cancellation
      // is unrelated to application JS; keep every other error observable.
      if (error.message === 'Transition was aborted because of invalid state. ViewTransition opt-in disabled') return;
      errors.push(error.message);
    });
    await page.goto(demoURL);
    await expect(page.getByRole('heading', { name: 'Try OpenPorte', exact: true })).toBeVisible();
    await expect(page.locator('altcha-widget')).toHaveCount(1);
    await expect(page.locator('form.wpcf7-form textarea')).toHaveCount(1);
    await expect(page.locator('form.wpcf7-form input[type="email"], form.wpcf7-form input[type="text"]')).toHaveCount(0);
    await expect(page.getByText('Demo only—no email is sent.')).toBeVisible();
    await expect(page.locator('#wpadminbar')).toBeVisible();

    // The authenticated session must still enforce server-side proof checks.
    expect((await postProof(page, null)).status).toBe('spam');
    expect((await postProof(page, 'invalid-proof')).status).toBe('spam');

    for (let attempt = 0; attempt < 2; attempt++) {
      await page.getByLabel('Message', { exact: true }).fill(`Hello from test ${attempt}`);
      await page.locator('altcha-widget input[type="checkbox"]').click();
      await expect(page.locator('altcha-widget .altcha')).toHaveAttribute('data-state', 'verified');
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

    // Use the real logout link, including its nonce, rather than assuming that
    // a new browser context defeats Playground's initial automatic login.
    const logout = await page.locator('#wp-admin-bar-logout a').getAttribute('href');
    await page.goto(logout);
    await page.goto(demoURL);
    await expect(page.locator('#wpadminbar')).toHaveCount(0);
    expect((await postProof(page, null)).status).toBe('spam');
    expect((await postProof(page, 'invalid-proof')).status).toBe('spam');
    await page.getByLabel('Message', { exact: true }).fill('Visitor submission');
    await page.locator('altcha-widget input[type="checkbox"]').click();
    await expect(page.locator('altcha-widget .altcha')).toHaveAttribute('data-state', 'verified');
    await page.getByRole('button', { name: 'Submit message' }).click();
    await expect(page.locator('form.wpcf7-form')).toHaveAttribute('data-status', 'sent');
    expect(errors).toEqual([]);
  });
});
