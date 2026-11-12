const { test, expect } = require('@playwright/test');
const contactForm7 = require('./drivers/contact-form-7');
const {
  wp,
  wpEval,
  wpSetOption,
  applyBaseline,
  applyCombo,
  waitForVerified,
  clickWidgetCheckbox,
  removeWidgets,
} = require('./helpers');

/**
 * Shortcode injection through the form integrations (GHSA-hvr5-q69x-jp62).
 *
 * Before 1.30.3, OpenPorte ran do_shortcode on the *rendered* Contact Form 7
 * and HTML Forms HTML, which contains visitor input, so any visitor could run
 * any registered shortcode. Now only [openporte]/[altcha] are rendered, and
 * only from the admin's template. This suite proves both halves on real
 * plugins:
 *
 *   - the attack vectors from the advisory are inert: a shortcode a visitor
 *     puts in the URL (CF7 default:get, HTML Forms {{url_params}}) or in a
 *     refused submission (CF7 re-display) comes back as text;
 *   - the widget an admin places by hand still renders, keeps its language,
 *     and is still verified with the integration switched off (#76).
 *
 * The probe is core's [audio]: its output carries the class
 * "wp-audio-shortcode", so its presence means the shortcode ran.
 */

const PROBE = '[audio src=https://example.com/e2e-probe.mp3]';
const EXECUTED = 'wp-audio-shortcode';

const CF7_FORM = [
  '<label>Name [text* your-name]</label>',
  '<label>Email [email* your-email]</label>',
  '<label>Subject [text* your-subject]</label>',
  '<label>Message [textarea your-message default:get]</label>',
  '[openporte]',
  '[submit "Send"]',
].join('\n');

// Rendering only: three widgets, one per way of writing the language.
const CF7_LANGUAGE_FORM = [
  '[openporte language:de_DE]',
  '[altcha language="de_DE"]',
  '[openporte]',
].join('\n');

const HF_MARKUP = [
  '<p>Ref: {{url_params.ref}}</p>',
  '<p><label>Message</label><textarea name="message" required></textarea></p>',
  '[openporte]',
  '<p><input type="submit" value="Send"></p>',
].join('\n');

/**
 * Create or refresh the fixture forms and pages; returns their paths.
 * Idempotent: looks each one up by title or slug and overwrites its content.
 */
function ensureFixtures() {
  const out = wpEval(`
    $page = function ($slug, $content) {
      $post = array('post_type' => 'page', 'post_status' => 'publish', 'post_name' => $slug, 'post_title' => $slug, 'post_content' => $content);
      $existing = get_page_by_path($slug, OBJECT, 'page');
      if ($existing) {
        $post['ID'] = $existing->ID;
        return wp_update_post($post);
      }
      return wp_insert_post($post);
    };
    $cf7 = function ($title, $template) {
      $found = get_posts(array('post_type' => 'wpcf7_contact_form', 'title' => $title, 'post_status' => 'any', 'posts_per_page' => 1));
      $form = $found ? wpcf7_contact_form($found[0]->ID) : WPCF7_ContactForm::get_template(array('title' => $title));
      $form->set_properties(array('form' => $template));
      $form->save();
      return $form->shortcode();
    };
    $hf = get_page_by_path('e2e-injection', OBJECT, 'html-form');
    $hf_post = array('post_type' => 'html-form', 'post_status' => 'publish', 'post_name' => 'e2e-injection', 'post_title' => 'E2E Injection', 'post_content' => ${JSON.stringify(HF_MARKUP)});
    if ($hf) {
      $hf_post['ID'] = $hf->ID;
      wp_update_post($hf_post);
    } else {
      wp_insert_post($hf_post);
    }
    echo 'E2E-FIXTURES' . wp_json_encode(array(
      'cf7' => '/?page_id=' . $page('e2e-injection-cf7', $cf7('E2E Injection', ${JSON.stringify(CF7_FORM)})),
      'cf7Language' => '/?page_id=' . $page('e2e-injection-cf7-language', $cf7('E2E Injection Language', ${JSON.stringify(CF7_LANGUAGE_FORM)})),
      'hf' => '/?page_id=' . $page('e2e-injection-hf', '[hf_form slug="e2e-injection"]'),
    ));
  `);
  const match = out.match(/E2E-FIXTURES(\{.*\})/);
  if (!match) {
    throw new Error(`Fixture creation printed no paths:\n${out}`);
  }
  return JSON.parse(match[1]);
}

/** GET a page with one query argument and return the raw HTML. */
async function fetchWith(request, path, name, value) {
  const res = await request.get(`${path}&${name}=${encodeURIComponent(value)}`);
  expect(res.ok()).toBeTruthy();
  return res.text();
}

/** How many widgets the HTML contains. */
function countWidgets(html) {
  return (html.match(/<altcha-widget /g) || []).length;
}

test.describe('shortcode injection (GHSA-hvr5-q69x-jp62)', () => {
  let paths;

  test.beforeAll(() => {
    applyBaseline();
    applyCombo({ auto: '', floating: 0 });
    wp('plugin activate contact-form-7 html-forms');
    // switch_to_locale() only switches to an installed locale; OpenPorte's own
    // German strings ship in languages/. Needs network access the first time.
    wp('language core install de_DE');
    paths = ensureFixtures();
  });

  test.beforeEach(() => {
    // Integrations off: every widget on these pages is the one placed by hand.
    wpSetOption(contactForm7.option, '');
    wpSetOption('openporte_integration_html_forms', '');
  });

  test.afterAll(() => {
    wpSetOption(contactForm7.option, '');
    wpSetOption('openporte_integration_html_forms', '');
  });

  test.describe('Contact Form 7', () => {
    test('the [openporte] form-tag renders exactly one widget', async ({ request }) => {
      const html = await (await request.get(paths.cf7)).text();
      expect(countWidgets(html)).toBe(1);
      expect(html).not.toContain('[openporte]');
    });

    test('with the integration on, the form-tag widget is not doubled', async ({ request }) => {
      wpSetOption(contactForm7.option, '1');
      const html = await (await request.get(paths.cf7)).text();
      expect(countWidgets(html)).toBe(1);
    });

    test('language:de_DE and the legacy language="de_DE" both reach the widget', async ({ request }) => {
      const html = await (await request.get(paths.cf7Language)).text();
      const strings = [...html.matchAll(/<altcha-widget [^>]*strings="([^"]*)"/g)].map((m) => m[1]);
      expect(strings).toHaveLength(3);
      expect(strings[0]).toContain('Ich bin kein Roboter');
      expect(strings[1]).toContain('Ich bin kein Roboter');
      expect(strings[2]).not.toContain('Ich bin kein Roboter');
    });

    test('vector B: a shortcode in a default:get URL parameter stays text', async ({ request }) => {
      const html = await fetchWith(request, paths.cf7, 'your-message', PROBE);
      expect(html).toContain(PROBE);
      expect(html).not.toContain(EXECUTED);
    });

    test('vector A: a shortcode in a refused submission stays text when re-displayed', async ({ browser }) => {
      // Integration on and no token: the submission is spam, and CF7 re-renders
      // the form with the visitor's values. The CAPTCHA did not stop this
      // before 1.30.3. Without JavaScript, CF7 posts the form natively, so the
      // response is the re-rendered page.
      wpSetOption(contactForm7.option, '1');
      const context = await browser.newContext({ javaScriptEnabled: false });
      try {
        const page = await context.newPage();
        await page.goto(paths.cf7);
        await page.locator('[name="your-name"]').fill('E2E Tester');
        await page.locator('[name="your-email"]').fill('e2e@example.com');
        await page.locator('[name="your-subject"]').fill('E2E injection');
        await page.locator('[name="your-message"]').fill(PROBE);
        await page.locator('form.wpcf7-form [type="submit"]').click();
        await page.waitForLoadState();
        await expect(page.locator('[name="your-message"]')).toHaveValue(PROBE);
        expect(await page.content()).not.toContain(EXECUTED);
      } finally {
        await context.close();
      }
    });

    test('#76: the hand-placed widget is verified with the integration off', async ({ page }) => {
      const ctx = { path: paths.cf7 };
      await contactForm7.open(page, ctx);
      await contactForm7.fill(page, ctx, 'E2E missing token');
      await removeWidgets(page);
      await contactForm7.submit(page);
      await contactForm7.expectRejected(page);

      await contactForm7.open(page, ctx);
      await contactForm7.fill(page, ctx, 'E2E solved');
      await clickWidgetCheckbox(page);
      await waitForVerified(page);
      await contactForm7.submit(page);
      await contactForm7.expectAccepted(page);
    });
  });

  test.describe('HTML Forms', () => {
    const REFUSED = 'Could not verify you are not a robot.';
    const ACCEPTED = 'Thank you! We will be in touch soon.';

    test('the [openporte] tag in the markup renders exactly one widget', async ({ request }) => {
      const html = await (await request.get(paths.hf)).text();
      expect(countWidgets(html)).toBe(1);
      expect(html).not.toContain('[openporte]');
    });

    test('vector D: a shortcode in a {{url_params}} value stays text', async ({ request }) => {
      const html = await fetchWith(request, paths.hf, 'ref', PROBE);
      expect(html).toContain(PROBE);
      expect(html).not.toContain(EXECUTED);
    });

    test('#76: the hand-placed widget is verified with the integration off', async ({ page }) => {
      await page.goto(paths.hf);
      await page.locator('[name="message"]').fill('E2E missing token');
      await removeWidgets(page);
      await page.locator('form.hf-form [type="submit"]').click();
      await expect(page.getByText(REFUSED)).toBeVisible();

      await page.goto(paths.hf);
      await page.locator('[name="message"]').fill('E2E solved');
      await clickWidgetCheckbox(page);
      await waitForVerified(page);
      await page.locator('form.hf-form [type="submit"]').click();
      await expect(page.getByText(ACCEPTED)).toBeVisible();
    });
  });
});
