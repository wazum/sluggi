import { defineConfig } from 'vite';
import { resolve } from 'path';

export default defineConfig({
    build: {
        outDir: 'Resources/Public',
        emptyOutDir: false,
        copyPublicDir: false,
        sourcemap: true,
        minify: true,
        rollupOptions: {
            preserveEntrySignatures: 'exports-only',
            input: {
                'JavaScript/sluggi-element': resolve(__dirname, 'src/index.ts'),
                'JavaScript/redirect-notification-handler': resolve(__dirname, 'src/redirect-notification-handler.ts'),
                'JavaScript/context-menu-actions': resolve(__dirname, 'src/context-menu-actions.ts'),
                'Css/sluggi-source-badge': resolve(__dirname, 'src/styles/sluggi-source-badge.scss'),
            },
            external: [/^@typo3\/.*/],
            output: {
                entryFileNames: '[name].js',
                assetFileNames: '[name][extname]',
            },
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
