const { test, expect } = require('@playwright/test');

// Uses the same wp-env bench as the other specs, with OpenPorte activated.
// All values edited here stay in the browser; this suite never saves settings.
test.beforeEach(async ({ page }) => {
  await page.addInitScript(() => {
    window.copiedSecret = null;
    Object.defineProperty(navigator, 'clipboard', {
      configurable: true,
      value: { writeText: async (value) => { window.copiedSecret = value; } },
    });
  });
  await page.goto('/wp-login.php');
  await page.locator('#user_login').fill(process.env.WP_ADMIN_USER || 'admin');
  await page.locator('#user_pass').fill(process.env.WP_ADMIN_PASSWORD || 'password');
  await Promise.all([
    page.waitForURL('**/wp-admin/**'),
    page.locator('#wp-submit').click(),
  ]);
  await page.goto('/wp-admin/options-general.php?page=openporte_admin');
  await page.getByLabel('Shared secret', { exact: true }).fill('e2e-secret-not-saved');
});

/** Controls for the actual settings renderer, rather than a duplicate HTML fixture. */
function controls(page) {
  return {
    input: page.getByLabel('Shared secret', { exact: true }),
    show: page.locator('.openporte-toggle-password'),
    copy: page.getByRole('button', { name: 'Copy shared secret', exact: true }),
    regenerate: page.getByRole('button', { name: 'Generate new shared secret', exact: true }),
    status: page.locator('.openporte-password-status'),
  };
}

test('keyboard show/hide preserves the secret, icons, and action names', async ({ page }) => {
  const { input, show } = controls(page);
  await expect(input).toHaveAttribute('type', 'password');
  await input.focus();
  await page.keyboard.press('Tab');
  await expect(show).toBeFocused();
  await page.keyboard.press('Space');
  await expect(input).toHaveAttribute('type', 'text');
  await expect(show).toHaveAccessibleName('Hide shared secret');
  await expect(show.locator('[data-icon="unseen"] svg')).toBeVisible();
  await expect(show.locator('[data-icon="seen"]')).toBeHidden();
  await page.keyboard.press('Enter');
  await expect(input).toHaveAttribute('type', 'password');
  await expect(input).toHaveValue('e2e-secret-not-saved');
  await expect(show).toHaveAccessibleName('Show shared secret');
  await expect(show.locator('[data-icon="seen"] svg')).toBeVisible();
  await expect(show).toBeFocused();
});

test('copy announces repeated success and resets its icon without losing focus', async ({ page }) => {
  const { input, copy, status } = controls(page);
  await page.clock.install();
  await copy.focus();
  await page.keyboard.press('Enter');
  await page.clock.fastForward(100);
  await expect(status).toHaveText('Copied!');
  await expect(status).toHaveAttribute('aria-live', 'polite');
  await expect(status).toHaveAttribute('aria-atomic', 'true');
  expect(await page.evaluate(() => window.copiedSecret)).toBe('e2e-secret-not-saved');
  await expect(copy.locator('[data-icon="check"] svg')).toBeVisible();
  await expect(input).toHaveAttribute('type', 'password');
  await page.clock.fastForward(1000);
  await copy.press('Enter');
  await expect(status).toBeEmpty();
  await page.clock.fastForward(100);
  await expect(status).toHaveText('Copied!');
  await page.clock.fastForward(1000);
  await expect(copy.locator('[data-icon="check"] svg')).toBeVisible();
  await page.clock.fastForward(1000);
  await expect(copy.locator('[data-icon="copy"] svg')).toBeVisible();
  await expect(copy).toHaveAccessibleName('Copy shared secret');
  await expect(copy).toBeFocused();
});

test('clipboard rejection reports failure while keeping the copy action usable', async ({ page }) => {
  const { copy, status } = controls(page);
  await page.evaluate(() => {
    navigator.clipboard.writeText = async () => { throw new Error('Permission denied'); };
  });
  await copy.click();
  await expect(status).toHaveText('Copy failed');
  await expect(copy.locator('[data-icon="copy"] svg')).toBeVisible();
  await expect(copy).toHaveAccessibleName('Copy shared secret');
  await page.evaluate(() => { navigator.clipboard.writeText = async () => {}; });
  await copy.click();
  await expect(status).toHaveText('Copied!');
});

for (const result of ['success', 'failure', 'exception']) {
  test(`HTTP fallback handles ${result} and restores keyboard focus`, async ({ page }) => {
    const { copy, status } = controls(page);
    await page.evaluate((outcome) => {
      Object.defineProperty(window, 'isSecureContext', { value: false, configurable: true });
      document.execCommand = (command) => {
        if (command !== 'copy') throw new Error('Unexpected command');
        if (outcome === 'exception') throw new Error('Copy unavailable');
        window.copiedSecret = document.activeElement.value;
        return outcome === 'success';
      };
    }, result);
    await copy.focus();
    await copy.press('Space');
    await expect(status).toHaveText(result === 'success' ? 'Copied!' : 'Copy failed');
    await expect(copy).toBeFocused();
    await expect(page.locator('textarea')).toHaveCount(0);
    if (result === 'success') {
      expect(await page.evaluate(() => window.copiedSecret)).toBe('e2e-secret-not-saved');
    }
  });
}

test('regenerate fills a fresh secret without submitting or saving', async ({ page }) => {
  const { input, regenerate } = controls(page);
  let submissions = 0;
  page.on('request', (request) => {
    if (request.method() === 'POST' && request.url().includes('options.php')) submissions++;
  });
  await regenerate.click();
  const generated = await input.inputValue();
  expect(generated).toMatch(/^[a-f0-9]{64}$/);
  await regenerate.click();
  expect(await input.inputValue()).not.toBe(generated);
  expect(submissions).toBe(0);
  await expect(input).toHaveAttribute('type', 'password');
  await expect(regenerate.locator('svg')).toBeVisible();
});

test('long secrets stay clear of controls on narrow LTR and RTL layouts', async ({ page }) => {
  const { input, show } = controls(page);
  await page.setViewportSize({ width: 375, height: 812 });
  await input.fill('0123456789abcdef'.repeat(12));
  for (const direction of ['ltr', 'rtl']) {
    await page.evaluate((dir) => { document.documentElement.dir = dir; }, direction);
    const geometry = await input.evaluate((element) => {
      const field = element.getBoundingClientRect();
      const actions = element.parentElement.querySelector('.openporte-password-actions').getBoundingClientRect();
      return {
        padding: parseFloat(getComputedStyle(element).paddingInlineEnd),
        actionsWidth: actions.width,
        left: field.left, right: field.right,
        actionsLeft: actions.left, actionsRight: actions.right,
      };
    });
    expect(geometry.padding).toBeGreaterThan(geometry.actionsWidth);
    expect(geometry.actionsLeft).toBeGreaterThanOrEqual(geometry.left);
    expect(geometry.actionsRight).toBeLessThanOrEqual(geometry.right);
    await show.focus();
    expect(await show.evaluate((element) => getComputedStyle(element).outlineStyle)).not.toBe('none');
  }
  await page.emulateMedia({ forcedColors: 'active' });
  await show.focus();
  expect(await show.evaluate((element) => getComputedStyle(element).outlineStyle)).not.toBe('none');
});

test('the deprecated renderer still updates ordinary Show/Hide button text', async ({ page }) => {
  await page.addInitScript(() => {
    document.addEventListener('DOMContentLoaded', () => {
      // The deprecated callback's public class/data-target contract, added before
      // admin.js binds its DOMContentLoaded handlers.
      const input = document.createElement('input');
      input.id = 'legacy-secret';
      input.type = 'password';
      const button = document.createElement('button');
      button.id = 'legacy-toggle';
      button.type = 'button';
      button.className = 'openporte-toggle-password';
      button.dataset.target = input.id;
      button.dataset.labelShow = 'Show';
      button.dataset.labelHide = 'Hide';
      button.textContent = 'Show';
      document.body.append(input, button);
    });
  });
  await page.reload();
  const toggle = page.locator('#legacy-toggle');
  await toggle.click();
  await expect(toggle).toHaveText('Hide');
  await expect(page.locator('#legacy-secret')).toHaveAttribute('type', 'text');
  await toggle.click();
  await expect(toggle).toHaveText('Show');
});
