import { playwrightLauncher } from '@web/test-runner-playwright';
import { esbuildPlugin } from '@web/dev-server-esbuild';
import { readFileSync } from 'fs';
import { compile } from 'sass';
import { resolve, dirname } from 'path';
import { fileURLToPath } from 'url';

const __dirname = dirname(fileURLToPath(import.meta.url));

function typo3MockPlugin() {
    return {
        name: 'typo3-mock',
        resolveImport({ source }) {
            if (source.startsWith('@typo3/backend/modal')) {
                return '/src/__mocks__/typo3-backend.ts';
            }
            if (source.startsWith('@typo3/backend/severity')) {
                return '/src/__mocks__/typo3-backend.ts?severity';
            }
        },
        serve(context) {
            if (context.path.includes('typo3-backend.ts?severity')) {
                return {
                    body: `export default { notice: 0, info: 1, ok: 2, warning: 3, error: 4 };`,
                    type: 'js',
                };
            }
        },
    };
}

function scssPlugin() {
    return {
        name: 'scss-inline',
        resolveImport({ source }) {
            if (source.endsWith('.scss?inline')) {
                return source;
            }
        },
        serve(context) {
            if (context.path.endsWith('.scss?inline') || context.path.endsWith('.scss')) {
                const scssPath = context.path.replace('?inline', '');
                const fullPath = resolve(__dirname, scssPath.replace(/^\//, ''));
                try {
                    const result = compile(fullPath);
                    return {
                        body: `export default ${JSON.stringify(result.css)};`,
                        type: 'js',
                    };
                } catch (e) {
                    console.error('SCSS compile error:', e);
                    return { body: 'export default "";', type: 'js' };
                }
            }
        },
    };
}

export default {
    files: 'src/**/*.test.ts',
    nodeResolve: true,
    plugins: [
        typo3MockPlugin(),
        scssPlugin(),
        esbuildPlugin({
            ts: true,
            tsconfig: './tsconfig.json',
        }),
    ],
    browsers: [
        playwrightLauncher({ product: 'chromium' }),
    ],
    testFramework: {
        config: {
            timeout: 5000,
        },
    },
    coverageConfig: {
        include: ['src/**/*.ts'],
        exclude: ['src/**/*.test.ts'],
    },
};
