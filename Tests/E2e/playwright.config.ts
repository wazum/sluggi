import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
  testDir: '.',
  fullyParallel: false,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 0,
  workers: 1,
  reporter: 'list',

  use: {
    baseURL: process.env.TYPO3_BASE_URL || 'http://web',
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
    ignoreHTTPSErrors: true,
  },

  projects: [
    {
      name: 'setup',
      testMatch: 'fixtures/auth.setup.ts',
    },
    {
      name: 'setup-editor',
      testMatch: 'fixtures/editor-auth.setup.ts',
    },
    {
      name: 'setup-restricted-editor',
      testMatch: 'fixtures/restricted-editor-auth.setup.ts',
    },
    {
      name: 'chromium',
      testDir: './tests',
      testIgnore: ['**/last-segment-only.spec.ts', '**/hierarchy-permission.spec.ts', '**/field-access-restriction.spec.ts', '**/full-path-editing.spec.ts'],
      use: {
        ...devices['Desktop Chrome'],
        storageState: '.auth/user.json',
      },
      dependencies: ['setup'],
    },
    {
      name: 'editor',
      testDir: './tests',
      testMatch: ['**/last-segment-only.spec.ts', '**/hierarchy-permission.spec.ts', '**/full-path-editing.spec.ts'],
      use: {
        ...devices['Desktop Chrome'],
        storageState: '.auth/editor.json',
      },
      dependencies: ['setup-editor'],
    },
    {
      name: 'restricted-editor',
      testDir: './tests',
      testMatch: ['**/field-access-restriction.spec.ts'],
      use: {
        ...devices['Desktop Chrome'],
        storageState: '.auth/restricted-editor.json',
      },
      dependencies: ['setup-restricted-editor'],
    },
  ],

  outputDir: 'test-results/',
});
