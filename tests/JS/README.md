# JavaScript Tests

Currently there are no JS tests. To add them:

1. Install a test runner: `npm install --save-dev vitest`
2. Create `js/__tests__/` directory
3. Add test files: `js/__tests__/api.test.js`, `js/__tests__/data_store.test.js`
4. Run with: `npx vitest run`

See `scripts/ui-test.js` for the existing Puppeteer smoke test pattern.
