import { defineConfig } from 'vite';
import { resolve } from 'path';

export default defineConfig({
    base: '/sluggi/',
    root: 'demo',
    build: {
        outDir: '../demo-dist',
        emptyOutDir: true,
    },
    resolve: {
        alias: {
            '@typo3/backend/modal.js': resolve(__dirname, 'demo/mocks/typo3-modal.ts'),
            '@typo3/backend/modal': resolve(__dirname, 'demo/mocks/typo3-modal.ts'),
            '@typo3/backend/severity.js': resolve(__dirname, 'demo/mocks/typo3-severity.ts'),
            '@typo3/backend/severity': resolve(__dirname, 'demo/mocks/typo3-severity.ts'),
            '@': resolve(__dirname, 'src'),
        },
    },
    css: {
        preprocessorOptions: {
            scss: {
                api: 'modern-compiler',
            },
        },
    },
});
