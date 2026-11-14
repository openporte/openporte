import { readFileSync, writeFileSync, mkdirSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { resolve } from 'node:path';

// Keep the committed blueprint self-contained and deterministically generated.
const root = fileURLToPath(new URL('../../', import.meta.url));
const source = resolve(root, 'demos/cf7');
const target = resolve(root, '.wordpress-org/blueprints/blueprint.json');
const mu = '/wordpress/wp-content/mu-plugins';
// One English scenario for now. Future locales must also ship a demo catalogue.
const locale = 'en_US';
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
    { step: 'mkdir', path: `${mu}/openporte-demo` },
    ...[
      ['content.php', `${mu}/openporte-demo/content.php`],
      ['demo.css', `${mu}/openporte-demo/demo.css`],
      ['demo.php', `${mu}/openporte-demo.php`],
      ['setup.php', '/tmp/openporte-demo-setup.php'],
    ].map(([file, path]) => ({ step: 'writeFile', path, data: readFileSync(resolve(source, file), 'utf8') })),
    { step: 'runPHP', code: "<?php require '/wordpress/wp-load.php'; require '/tmp/openporte-demo-setup.php'; openporte_demo_setup();" },
    { step: 'rm', path: '/tmp/openporte-demo-setup.php' },
  ],
};
const output = JSON.stringify(blueprint, null, 2) + '\n';
if (process.argv.includes('--check')) {
  if (readFileSync(target, 'utf8') !== output) {
    throw new Error('Blueprint is stale. Run npm run demo:build.');
  }
  console.log('Blueprint matches its sources.');
} else {
  mkdirSync(resolve(root, '.wordpress-org/blueprints'), { recursive: true });
  writeFileSync(target, output);
  console.log('Generated .wordpress-org/blueprints/blueprint.json');
}
