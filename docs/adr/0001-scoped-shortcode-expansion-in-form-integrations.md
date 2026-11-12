# Render the widget in Contact Form 7 and HTML Forms by scoped tag expansion, not `do_shortcode`

**Status:** Accepted, 1.30.3 (2026-11-13).

Admins place the widget in a Contact Form 7 or HTML Forms form by writing
`[openporte]` (or the legacy `[altcha]`) in the form. To render it, OpenPorte
used to add `do_shortcode` as a filter on each plugin's rendered-form hook
(`wpcf7_form_elements`, `hf_form_html`). By then the HTML contains visitor
input: the values of a refused submission, `default:get` URL parameters,
HTML Forms' `{{url_params.*}}`. So any visitor could run any registered
shortcode (GHSA-hvr5-q69x-jp62, CWE-94, `docs/security-audit.md` #22).

We now render only our own two tags, and only from the form as the admin
wrote it:

- **Contact Form 7:** `[openporte]` and `[altcha]` are display-only CF7
  form-tags (`wpcf7_add_form_tag()` on `wpcf7_init`). CF7 replaces form-tags
  while it parses the form template, before any visitor value is filled in.
- **HTML Forms:** a scoped expander on `hf_form_markup` at priority 5, before
  `hf_template()` fills in `{{…}}` at priority 10. It matches only our two tags,
  with core's own grammar (`get_shortcode_regex( array( 'openporte', 'altcha' ) )`,
  `shortcode_parse_atts()`, `[[openporte]]` escaping); every other shortcode
  stays text.
- Both call one renderer, `openporte_render_shortcode()` in
  `includes/helpers.php`, which is also the `[openporte]`/`[altcha]` shortcode
  callback everywhere else.

## Considered options

- **Remove the filters outright.** Rejected: a widget placed by hand would
  print as `[openporte]`, and visitors could not solve the CAPTCHA.
- **An allow-list through `pre_do_shortcode_tag`.** Rejected: it is
  site-wide, it puts the burden on the admin, and it does not close the hole,
  because the shortcodes that disclose data (`[gallery]`, `[playlist]`) are
  the ones a typical allow-list keeps.
- **`do_shortcode( $html, true )`.** Rejected: it only skips shortcodes inside
  HTML attributes. The vectors whose output is visible (a textarea's content,
  text in a `<p>`) still run.

## Consequences

- **Breaking, on purpose:** other plugins' shortcodes no longer render inside
  Contact Form 7 or HTML Forms forms. Neither plugin renders shortcodes in its
  forms itself, so this was a side effect OpenPorte introduced.
- **No opt-back-in.** The readme offers no setting or snippet that restores
  the old behaviour: it would re-open the vulnerability. A pasted
  `do_shortcode` filter sits on a site unnoticed, while a pending plugin update
  at least shows in the admin. The FAQ explains the change and points affected
  admins to support.
- **Contact Form 7 attribute syntax:** a form-tag takes `language:de_DE`. The
  legacy `[openporte language="de_DE"]` keeps working, recovered from CF7's
  parse of the quoted value. A tag mixing another quoted attribute with it
  (`[openporte mode="x" language="de_DE"]`) does not match CF7's tag grammar
  and loses its language. `mode` is ignored in CF7 form-tags.
- **HTML Forms** keeps WordPress shortcode attribute syntax.
- **Pinned by** `tests/phpunit/RenderShortcodeTest.php`, `Cf7FormTagTest.php`
  and `HtmlFormsTagsTest.php`, and on real plugins by
  `tests/e2e/shortcode-injection.spec.js`, which fails if either old filter is
  put back.
- **Rule for new integrations:** never run `do_shortcode` on a host plugin's
  rendered output. Render OpenPorte's own tags from the admin-authored source,
  before the host fills in anything a visitor controls (`AGENTS.md`, "Known
  gotchas").
