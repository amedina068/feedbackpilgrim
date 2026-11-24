import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/js/pilgrim.js'
            ],
            refresh: true,
        }),
    ],
    build: {
      emptyOutDir: false,
      outDir: 'public',
      rolldownOptions: {
        output: {
          entryFileNames: '[name].js',
        },
      },
    },
});