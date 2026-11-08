const { test, expect } = require('@playwright/test');
const { ensureWooCommerce } = require('./drivers/_woocommerce');
const { wp, wpSetOption, applyBaseline } = require('./helpers');

/**
 * WooCommerce accounts created outside the My Account form.
 *
 * WooCommerce fires `woocommerce_register_post` from wc_create_new_customer(),
 * so for every account it creates: the checkout's "Create an account?" box,
 * the block checkout, the order confirmation page, the REST API and any
 * WC_Customer::save(). The widget only exists on the My Account form, so a
 * check on that hook refused all the others.
 *
 * Rejection on the My Account form itself stays covered by the settings matrix
 * (woocommerce-register driver: missing and forged token).
 */

/** Passes WooCommerce's strength meter; never crosses the shell boundary. */
const ACCOUNT_PASSWORD = 'Kv9#mQx2!pTz7@Wf';
const CAPTCHA_ERROR = /could not verify you are not a robot/i;

/** Create the fixture page if absent; returns its ID. */
function ensurePage(name, content) {
  let id = wp(`post list --post_type=page --name=${name} --field=ID`);
  if (!id) {
    id = wp(
      `post create --post_type=page --post_title=${name} --post_name=${name} --post_status=publish --post_content=${content} --porcelain`
    );
  }
  return id;
}

/** Create the virtual fixture product if absent; returns its ID. */
function ensureProduct() {
  const id = wp('post list --post_type=product --name=e2e-checkout-product --field=ID');
  if (id) {
    return id;
  }
  return wp(
    'wc product create --name=e2e-checkout-product --slug=e2e-checkout-product --type=simple --regular_price=10 --virtual=true --user=1 --porcelain'
  );
}

/** Delete the customers these tests created. */
function deleteCustomers() {
  const ids = wp('user list --role=customer --search=e2e-checkout-* --field=ID')
    .split(/\s+/)
    .filter(Boolean);
  if (ids.length) {
    wp(`user delete ${ids.join(' ')} --yes`);
  }
}

test.describe('WooCommerce account creation outside My Account', () => {
  let productId;
  let checkoutPageId;

  test.beforeAll(() => {
    ensureWooCommerce();
    applyBaseline();
    // The settings from the forum report (every WooCommerce surface on).
    wpSetOption('openporte_integration_woocommerce_register', '1');
    wpSetOption('openporte_integration_woocommerce_login', '1');
    wpSetOption('openporte_integration_woocommerce_reset_password', '1');
    wpSetOption('woocommerce_coming_soon', 'no');
    wpSetOption('woocommerce_default_country', 'FR');
    wpSetOption('woocommerce_enable_guest_checkout', 'yes');
    wpSetOption('woocommerce_enable_signup_and_login_from_checkout', 'yes');
    wpSetOption('woocommerce_registration_generate_username', 'yes');
    wpSetOption('woocommerce_registration_generate_password', 'no');
    wp('wc payment_gateway update cod --enabled=true --user=1');
    productId = ensureProduct();
    checkoutPageId = ensurePage('e2e-classic-checkout', '[woocommerce_checkout]');
  });

  test.beforeEach(() => {
    deleteCustomers();
  });

  test('classic checkout: "Create an account?" places the order and creates the account', async ({ page }) => {
    const email = `e2e-checkout-${Date.now()}@example.com`;
    wpSetOption('woocommerce_checkout_page_id', checkoutPageId);

    await page.goto(`/?add-to-cart=${productId}`);
    await page.goto(`/?page_id=${checkoutPageId}`);
    await page.locator('form.checkout').waitFor();

    await page.locator('#billing_first_name').fill('Ada');
    await page.locator('#billing_last_name').fill('Lovelace');
    await page.locator('#billing_address_1').fill('1 rue de Rivoli');
    await page.locator('#billing_postcode').fill('75001');
    await page.locator('#billing_city').fill('Paris');
    await page.locator('#billing_phone').fill('0102030405');
    await page.locator('#billing_email').fill(email);
    await page.locator('#createaccount').check();
    await page.locator('#account_password').fill(ACCOUNT_PASSWORD);
    await page.locator('#account_password').blur();
    await expect(page.locator('form.checkout .blockUI')).toHaveCount(0);

    await page.locator('#place_order').click();

    await expect(page).toHaveURL(/order-received/, { timeout: 30000 });
    await expect(page.locator('body')).not.toContainText(CAPTCHA_ERROR);
    expect(wp(`user get ${email} --field=roles`)).toContain('customer');
  });

  test('block checkout: "Create an account" places the order and creates the account', async ({ page }) => {
    const email = `e2e-checkout-block-${Date.now()}@example.com`;
    // The Checkout page WooCommerce creates on install holds the checkout block.
    const blockPageId = wp('post list --post_type=page --name=checkout --field=ID');
    wpSetOption('woocommerce_checkout_page_id', blockPageId);
    wpSetOption('woocommerce_enable_delayed_account_creation', 'no');

    await page.goto(`/?add-to-cart=${productId}`);
    await page.goto(`/?page_id=${blockPageId}`);
    await page.locator('#email').waitFor();

    await page.locator('#email').fill(email);
    await page.getByRole('checkbox', { name: /create an account/i }).check();
    await page.getByRole('textbox', { name: 'Create a password' }).fill(ACCOUNT_PASSWORD);
    await page.locator('#billing-first_name').fill('Ada');
    await page.locator('#billing-last_name').fill('Lovelace');
    await page.locator('#billing-address_1').fill('1 rue de Rivoli');
    await page.locator('#billing-postcode').fill('75001');
    await page.locator('#billing-city').fill('Paris');
    await page.locator('#billing-city').blur();

    await page.locator('.wc-block-components-checkout-place-order-button').click();

    await expect(page).toHaveURL(/order-received/, { timeout: 30000 });
    await expect(page.locator('body')).not.toContainText(CAPTCHA_ERROR);
    expect(wp(`user get ${email} --field=roles`)).toContain('customer');
  });

  test('programmatic creation: wc customer create is not asked for a challenge', async () => {
    const email = `e2e-checkout-cli-${Date.now()}@example.com`;
    const id = wp(`wc customer create --email=${email} --password=E2e-Cli-Passw0rd --user=1 --porcelain`);
    expect(id).toMatch(/^\d+$/);
  });
});
