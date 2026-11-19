import { readFileSync, writeFileSync, mkdirSync } from 'node:fs';
import { createHash } from 'node:crypto';
import { validateBlueprint } from '@wp-playground/blueprints';
import { fileURLToPath } from 'node:url';
import { resolve } from 'node:path';

// Keep the committed blueprint self-contained and deterministically generated.
const root = fileURLToPath(new URL('../../', import.meta.url));
const source = resolve(root, 'demos/cf7');
const target = resolve(root, '.wordpress-org/blueprints/blueprint.json');
const muPluginsDir = '/wordpress/wp-content/mu-plugins';
// One English scenario for now. Future locales must also ship a demo catalogue.
const locale = 'en_US';
// Directory assets can change independently of the released plugin version.
const cssHash = createHash('sha256').update(readFileSync(resolve(source, 'demo.css'))).digest('hex').slice(0, 16);
const cssHashPlaceholder = 'OPENPORTE_DEMO_CSS_HASH';

/** Read one embedded file; only demo.php carries the stylesheet version. */
function embed(file) {
  const data = readFileSync(resolve(source, file), 'utf8');
  if (file !== 'demo.php') {
    return data;
  }
  // Without the placeholder, the stylesheet would keep a stale cache version.
  if (!data.includes(cssHashPlaceholder)) {
    throw new Error(`demos/cf7/demo.php must contain ${cssHashPlaceholder}.`);
  }
  return data.replaceAll(cssHashPlaceholder, cssHash);
}
const blueprint = {
  $schema: 'https://playground.wordpress.net/blueprint-schema.json',
  meta: { title: 'Try OpenPorte with Contact Form 7', author: 'openporte', description: 'Try a message-only form with real OpenPorte verification, then explore its settings.' },
  landingPage: '/',
  preferredVersions: { php: '8.3', wp: 'latest' },
  login: true,
  steps: [
    { step: 'setSiteLanguage', language: locale },
    { step: 'installPlugin', pluginData: { resource: 'wordpress.org/plugins', slug: 'contact-form-7' }, options: { activate: true } },
    { step: 'installPlugin', pluginData: { resource: 'wordpress.org/plugins', slug: 'openporte' }, options: { activate: true } },
    { step: 'installTheme', themeData: { resource: 'wordpress.org/themes', slug: 'twentytwentyfive' }, options: { activate: true } },
    { step: 'mkdir', path: `${muPluginsDir}/openporte-demo` },
    ...[
      ['content.php', `${muPluginsDir}/openporte-demo/content.php`],
      ['demo.css', `${muPluginsDir}/openporte-demo/demo.css`],
      ['demo.php', `${muPluginsDir}/openporte-demo.php`],
      ['setup.php', '/tmp/openporte-demo-setup.php'],
    ].map(([file, path]) => ({ step: 'writeFile', path, data: embed(file) })),
    { step: 'runPHP', code: "<?php require '/wordpress/wp-load.php'; require '/tmp/openporte-demo-setup.php'; openporte_demo_setup();" },
    { step: 'rm', path: '/tmp/openporte-demo-setup.php' },
  ],
};
const validation = validateBlueprint(blueprint);
if (!validation.valid) {
  throw new Error(`Invalid blueprint: ${JSON.stringify(validation.errors)}`);
}
const output = JSON.stringify(blueprint, null, 2) + '\n';
if (process.argv.includes('--check')) {
  if (readFileSync(target, 'utf8') !== output) {
    throw new Error('Blueprint is stale. Run npm run demo:build.');
  }
  console.log('Blueprint passes schema validation and matches its sources.');
} else {
  mkdirSync(resolve(root, '.wordpress-org/blueprints'), { recursive: true });
  writeFileSync(target, output);
  console.log('Generated .wordpress-org/blueprints/blueprint.json');
}
