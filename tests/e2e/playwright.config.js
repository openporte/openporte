const { defineConfig } = require('@playwright/test');

module.exports = defineConfig({
  testDir: __dirname,
  // PoW solving is genuine browser work (see openporte_complexity); generous
  // timeouts keep slow combos (high complexity / slow bench) from flaking.
  timeout: 120000,
  expect: { timeout: 30000 },
  // Settings are global WordPress options — tests MUST run serially, one
  // worker, or combos would trample each other's configuration.
  workers: 1,
  fullyParallel: false,
  retries: 0,
  use: {
    launchOptions: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE
      ? { executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE }
      : {},
    baseURL: process.env.WP_BASE_URL || 'http://localhost:8888',
    trace: 'retain-on-failure',
    video: 'retain-on-failure',
  },
  reporter: [['list'], ['html', { open: 'never' }]],
});
