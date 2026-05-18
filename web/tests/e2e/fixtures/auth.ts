import { test as base, expect } from '@playwright/test';

/**
 * Single-import surface for specs.
 *
 * Slice 0 just re-exports the upstream `test` (typed via an empty
 * `extend<{}>({})` so future slices can swap in real fixtures
 * without changing the import path) and `expect`. Slices 1–8 extend
 * `test` here with login-as-different-permissions, network mocking,
 * worker-scoped DB resets, etc.
 *
 * Specs always `import { test, expect } from '../../fixtures/auth.ts'`
 * so a later slice can add fixtures without sweeping the whole suite.
 */
// eslint-disable-next-line @typescript-eslint/no-empty-object-type
export const test = base.extend<{}>({});

export { expect };
