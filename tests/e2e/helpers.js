const { execSync } = require('node:child_process');
const path = require('node:path');

const REPO_ROOT = path.resolve(__dirname, '..', '..');

// How to reach wp-cli. Default goes through the remote wp-env wrapper
// (./wp-env.sh run cli -- wp ...); rsync only happens on `start`, so these
// calls don't race local edits. Override for a local wp-env with:
//   WP_CLI_CMD="wp-env run cli -- wp"
const WP_CLI = process.env.WP_CLI_CMD || './wp-env.sh run cli -- wp';

/**
 * Run a wp-cli command and return trimmed stdout. `args` is a plain string —
 * values must contain NO spaces or quotes: wp-env.sh forwards the command
 * through `ssh "… wp-env $*"`, so the remote shell re-parses it and strips
 * one layer of quoting (same boundary tests/bin/wp-init.sh works around by
 * passing content through a file).
 */
function wp(args) {
  return execSync(`${WP_CLI} ${args}`, {
    cwd: REPO_ROOT,
    encoding: 'utf8',
    stdio: ['ignore', 'pipe', 'pipe'],
  }).trim();
}

/** Set an option, using delete for empty values (safer than passing '' through ssh+docker layers). */
function wpSetOption(name, value) {
  if (value === '' || value === null || value === undefined) {
    try {
      wp(`option delete ${name}`);
    } catch (e) {
      // already absent — fine
    }
  } else {
    wp(`option update ${name} ${value}`);
  }
}

/**
 * Fixture post for the comment-based drivers (core comments, wpDiscuz).
 * Values are deliberately space-free — see the wp() quoting caveat.
 */
function ensureCommentsPost() {
  let postId = wp('post list --post_type=post --name=e2e-comments --field=ID');
  if (!postId) {
    postId = wp(
      'post create --post_title=E2E-Comments --post_name=e2e-comments --post_status=publish --post_content=e2e-fixture --porcelain'
    );
  }
  wp(`post update ${postId} --comment_status=open`);
  // Approve anonymous comments immediately so acceptance is assertable.
  wpSetOption('comment_moderation', '0');
  wpSetOption('comment_previously_approved', '0');
  return postId;
}

/**
 * Delete every comment on a post. Core throttles comments from the same
 * IP/email posted within 15 s of the previous one ("You are posting comments
 * too quickly") — the whole matrix submits from one browser IP, so each test
 * must start with no earlier comment to be measured against.
 */
function purgeComments(postId) {
  const ids = wp(`comment list --post_id=${postId} --format=ids`);
  if (ids) {
    wp(`comment delete ${ids} --force`);
  }
}

const BASE_URL = process.env.WP_BASE_URL || 'http://localhost:8888';

// Self-hosted transport every combo runs over (issue #100): `rest`, the shipped
// default, or `ajax` (`npm run test:ajax`). The matrix is the same for both, so
// a pass on each shows OpenPorte works the same whichever endpoint serves the
// challenge. Anything else is a typo: fail before running the matrix over the
// wrong transport.
const TRANSPORT = process.env.OPENPORTE_E2E_TRANSPORT || 'rest';
if (!['rest', 'ajax'].includes(TRANSPORT)) {
  throw new Error(`OPENPORTE_E2E_TRANSPORT must be "rest" or "ajax", got "${TRANSPORT}".`);
}

/**
 * Poll a front-end URL until its HTML contains `needle`.
 *
 * Some plugins are not fully wired up the moment wp-cli reports them active
 * (see the wpDiscuz driver), and the first test then loads a page rendered as
 * if the plugin were off. Warm up before the first navigation instead of
 * letting one combo fail for a reason that has nothing to do with OpenPorte.
 *
 * The default MUST stay below playwright.config.js' `timeout` (which also caps
 * beforeAll hooks). At parity, Playwright kills the hook on the same tick this
 * would have thrown, so you get a bare "beforeAll hook timeout exceeded" and
 * never the message below naming the needle that never showed up.
 */
async function waitForFrontEnd(path, needle, timeout = 90000) {
  const deadline = Date.now() + timeout;
  let last = '';
  for (;;) {
    try {
      const res = await fetch(`${BASE_URL}${path}`);
      last = `HTTP ${res.status}`;
      if ((await res.text()).includes(needle)) {
        return;
      }
    } catch (e) {
      last = e.message;
    }
    if (Date.now() > deadline) {
      throw new Error(
        `Timed out waiting for "${needle}" at ${BASE_URL}${path} (last attempt: ${last}).`
      );
    }
    await new Promise((resolve) => setTimeout(resolve, 2000));
  }
}

/** Baseline OpenPorte configuration every combo starts from. */
function applyBaseline() {
  wpSetOption('openporte_api', 'selfhosted');
  wpSetOption('openporte_complexity', 'low');
  wpSetOption('openporte_expires', '300');
  wpSetOption('openporte_delay', '');
  // The shipped default. Pinned here because replay-limit.spec.js changes it
  // and a matrix combo must never inherit a strict limit: a single stray
  // replaylimit=1 would make every later acceptance test look like a
  // verification regression.
  wpSetOption('openporte_replaylimit', '5');
  wpSetOption('openporte_selfhosted_transport', TRANSPORT);
}

/**
 * Fail unless the page's widget fetches its challenge over TRANSPORT.
 *
 * Guards against false confidence: an ignored or misspelt option would let the
 * matrix pass over REST while claiming to test Ajax.
 */
async function expectTransport(page) {
  const url = await page.locator('altcha-widget').first().getAttribute('challengeurl');
  const expected = TRANSPORT === 'ajax'
    ? '/wp-admin/admin-ajax.php?action=openporte_challenge'
    : '/openporte/v1/challenge';
  if (!url || !url.includes(expected)) {
    throw new Error(`Widget challengeurl is "${url}", expected the ${TRANSPORT} endpoint (${expected}).`);
  }
}

/** Per-combo widget settings. auto: ''|onload|onfocus|onsubmit; floating: 0|1. */
function applyCombo({ auto, floating }) {
  wpSetOption('openporte_auto', auto);
  wpSetOption('openporte_floating', floating ? '1' : '');
}

/** Wait until any widget on the page reports the solved state. */
async function waitForVerified(page, timeout = 90000) {
  await page
    .locator('.altcha[data-state="verified"]')
    .first()
    .waitFor({ state: 'attached', timeout });
}

/** Start solving manually (the auto=disabled flow): tick the widget checkbox. */
async function clickWidgetCheckbox(page) {
  await page.locator('.altcha input[type="checkbox"]').first().click();
}

/**
 * Negative-control helper: corrupt the solved widget's token so the payload
 * decodes fine but its HMAC signature no longer matches — the forged-token
 * counterpart to removeWidgets()' missing-token control. Call only after
 * waitForVerified(); the hidden field is empty before the solve completes.
 * (The field's name varies per integration — e.g. WooCommerce registration
 * uses "openporte_register" — so locate it by its non-empty value instead.)
 */
async function tamperWidgetToken(page) {
  await page.evaluate(() => {
    const input = [...document.querySelectorAll('altcha-widget input[type="hidden"]')]
      .find((el) => el.value);
    if (!input) {
      throw new Error('No solved widget token found to tamper with.');
    }
    const payload = JSON.parse(atob(input.value));
    // Flip the signature's first byte (hex), guaranteeing a mismatch while
    // keeping every field structurally valid.
    payload.signature = (payload.signature.startsWith('00') ? '11' : '00')
      + payload.signature.slice(2);
    input.value = btoa(JSON.stringify(payload));
  });
}

/**
 * Negative-control helper: strip every widget from the DOM so the form posts
 * with no altcha field, which the server must reject. (Bypasses client-side
 * `required` validation the same way a bot skipping the widget would.)
 */
async function removeWidgets(page) {
  await page.evaluate(() => {
    document.querySelectorAll('altcha-widget').forEach((el) => {
      // public/script.js holds a closure over this checkbox in its submit
      // guard; a detached required+unchecked checkbox fails reportValidity()
      // and the guard would block the very submission this control needs to
      // reach the server. Drop the constraint before detaching.
      el.querySelectorAll('input[type="checkbox"]').forEach((cb) => {
        cb.required = false;
      });
      el.remove();
    });
  });
}

// Marks the form a captured token will be replayed into. Survives
// removeWidgets(), which is the point: the widget goes, the form stays.
const REPLAY_FORM_TAG = 'openporte-replay-target';

/**
 * Tag the form owning the page's widget, so injectToken() can find it again
 * after the widget itself has been detached.
 */
async function tagWidgetForm(page) {
  await page.evaluate((tag) => {
    const widget = document.querySelector('altcha-widget');
    const form = widget && widget.closest('form');
    if (!form) {
      throw new Error('No widget-bearing form on the page to tag.');
    }
    form.dataset.openporteE2e = tag;
  }, REPLAY_FORM_TAG);
}

/**
 * Capture the solved widget's token, the way an attacker sniffing one
 * submission would. Call only after waitForVerified(): the hidden field is
 * empty until the solve completes. Tags the owning form as a side effect.
 */
async function captureWidgetToken(page) {
  await tagWidgetForm(page);
  return page.evaluate(() => {
    const input = [...document.querySelectorAll('altcha-widget input[type="hidden"]')]
      .find((el) => el.value);
    if (!input) {
      throw new Error('No solved widget token found to capture.');
    }
    return { name: input.name, value: input.value };
  });
}

/**
 * Replay a captured token into the tagged form, replacing any token already
 * there. This is the attack shape the replay counter exists to bound: no
 * widget, no solve, just the same string posted again.
 */
async function injectToken(page, token) {
  await page.evaluate(({ tag, name, value }) => {
    const form = document.querySelector(`form[data-openporte-e2e="${tag}"]`);
    if (!form) {
      throw new Error('No tagged form to replay the token into — call tagWidgetForm() first.');
    }
    form.querySelectorAll(`input[name="${name}"]`).forEach((el) => el.remove());
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = name;
    input.value = value;
    form.appendChild(input);
  }, { tag: REPLAY_FORM_TAG, ...token });
}

module.exports = {
  BASE_URL,
  TRANSPORT,
  REPLAY_FORM_TAG,
  tagWidgetForm,
  captureWidgetToken,
  injectToken,
  wp,
  wpSetOption,
  waitForFrontEnd,
  ensureCommentsPost,
  purgeComments,
  applyBaseline,
  applyCombo,
  expectTransport,
  waitForVerified,
  clickWidgetCheckbox,
  tamperWidgetToken,
  removeWidgets,
};
