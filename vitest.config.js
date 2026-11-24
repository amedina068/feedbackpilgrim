import { configDefaults, defineConfig } from 'vitest/config';
import vue from '@vitejs/plugin-vue';

// Vitest runs the unit tests of the Vue components and composables in jsdom.
// tests/js has the node:test suite (npm run test:js) and tests/e2e has the
// Playwright specs (npm run test:e2e). Vitest must not run those files.
export default defineConfig({
    plugins: [vue()],
    test: {
        environment: 'jsdom',
        include: ['tests/vitest/**/*.test.js'],
        exclude: [...configDefaults.exclude, 'tests/e2e/**', 'tests/js/**'],
        restoreMocks: true,
        unstubGlobals: true,
    },
});
