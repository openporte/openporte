# WordPress SVG icons

Source: [`@wordpress/icons`](https://github.com/WordPress/gutenberg/tree/89415cce7bc59c1df622ebc3884ecc3af32b2f70/packages/icons),
commit `89415cce7bc59c1df622ebc3884ecc3af32b2f70`.

Copyright: The WordPress Contributors.
License: GPL-2.0-or-later; see the plugin's [LICENSE](../../../LICENSE).

The original files are `packages/icons/src/library/{seen,unseen,copy,update,check}.svg`
at that revision. On 2026-10-19, OpenPorte added `aria-hidden="true"` and
`focusable="false"` to each root SVG for use inside labelled buttons. Geometry,
viewboxes, and upstream colour/stroke attributes are unchanged.

These files are rendered inline from a fixed allowlist in `admin/options.php`.
They require no icon font, React, network request, or runtime build step.
