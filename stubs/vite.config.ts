import path from 'node:path'
import fs from 'node:fs'

import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import laravel from 'laravel-vite-plugin'
import { wayfinder } from '@laravel/vite-plugin-wayfinder'
import tailwindcss from '@tailwindcss/vite'
import i18n from 'laravel-vue-i18n/vite'

const hostJsPath = path.resolve(__dirname, 'resources/js')
const packageJsPath = path.resolve(
    __dirname,
    'vendor/mapo-89/core-panel/resources/js',
)
const hostThemePath = path.resolve(hostJsPath, 'theme/core-panel')
const packageThemePath = path.resolve(packageJsPath, 'theme/core-panel')
const additionalLangPaths = [
    path.resolve(__dirname, 'lang/vendor/core-panel'),
    path.resolve(__dirname, '../resources/lang'),
].filter((candidate) => fs.existsSync(candidate))

function resolveImportTarget(targetPath: string): string | null {
    if (fs.existsSync(targetPath)) {
        if (fs.statSync(targetPath).isDirectory()) {
            const directoryCandidates = [
                path.resolve(targetPath, 'index.ts'),
                path.resolve(targetPath, 'index.js'),
                path.resolve(targetPath, 'index.vue'),
                path.resolve(targetPath, 'index.css'),
            ]

            return (
                directoryCandidates.find((candidate) =>
                    fs.existsSync(candidate),
                ) ?? null
            )
        }

        return targetPath
    }

    const candidates = [
        `${targetPath}.ts`,
        `${targetPath}.js`,
        `${targetPath}.vue`,
        `${targetPath}.css`,
    ]

    return candidates.find((candidate) => fs.existsSync(candidate)) ?? null
}

function resolveCorePanelImport(importee: string): string | null {
    if (!importee.startsWith('@core-panel/')) {
        return null
    }

    const relativePath = importee.replace('@core-panel/', '')
    const hostCandidate = path.resolve(hostJsPath, relativePath)
    const resolvedHostImport = resolveImportTarget(hostCandidate)

    if (resolvedHostImport !== null) {
        return resolvedHostImport
    }

    return resolveImportTarget(path.resolve(packageJsPath, relativePath))
}

function corePanelVendorFirst() {
    return {
        name: 'core-panel-vendor-first',
        enforce: 'pre' as const,
        resolveId(importee: string) {
            return resolveCorePanelImport(importee)
        },
    }
}

function hasCorePanelThemeOverride(): boolean {
    return (
        fs.existsSync(path.resolve(hostThemePath, 'index.ts')) &&
        fs.existsSync(path.resolve(hostThemePath, 'index.css'))
    )
}

export default defineConfig({
    resolve: {
        alias: [
            {
                find: '@',
                replacement: path.resolve(__dirname, 'resources/js'),
            },
            {
                find: '@core-panel/theme/core-panel',
                replacement: hasCorePanelThemeOverride()
                    ? hostThemePath
                    : packageThemePath,
            },
            {
                find: /^@primeuix\/themes$/,
                replacement: path.resolve(
                    __dirname,
                    'node_modules/@primeuix/themes/dist/index.mjs',
                ),
            },
            {
                find: /^@primeuix\/themes\/aura$/,
                replacement: path.resolve(
                    __dirname,
                    'node_modules/@primeuix/themes/dist/aura/index.mjs',
                ),
            },
            {
                find: /^@primeuix\/themes\/types$/,
                replacement: path.resolve(
                    __dirname,
                    'node_modules/@primeuix/themes/types/index.d.ts',
                ),
            },
            {
                find: /^@inertiajs\/core$/,
                replacement: path.resolve(
                    __dirname,
                    'node_modules/@inertiajs/core',
                ),
            },
            {
                find: /^@inertiajs\/vue3$/,
                replacement: path.resolve(
                    __dirname,
                    'node_modules/@inertiajs/vue3',
                ),
            },
            {
                find: /^@blade-flags\/core\/flags\/flat$/,
                replacement: path.resolve(
                    __dirname,
                    'node_modules/@blade-flags/core/dist/flags/flat.js',
                ),
            },
            {
                find: /^@vueuse\/core$/,
                replacement: path.resolve(
                    __dirname,
                    'node_modules/@vueuse/core',
                ),
            },
            {
                find: /^laravel-vue-i18n$/,
                replacement: path.resolve(
                    __dirname,
                    'node_modules/laravel-vue-i18n',
                ),
            },
            {
                find: /^lucide-vue-next$/,
                replacement: path.resolve(
                    __dirname,
                    'node_modules/lucide-vue-next',
                ),
            },
            {
                find: /^primevue$/,
                replacement: path.resolve(__dirname, 'node_modules/primevue'),
            },
            {
                find: /^primevue\/(.*)$/,
                replacement:
                    path.resolve(__dirname, 'node_modules/primevue') + '/$1',
            },
            {
                find: /^vue$/,
                replacement: path.resolve(__dirname, 'node_modules/vue'),
            },
        ],
    },
    build: {
        chunkSizeWarningLimit: 750,
        rollupOptions: {
            onwarn(warning, warn) {
                const message = warning.message ?? ''

                if (
                    message.includes('Sourcemap is likely to be incorrect') &&
                    (message.includes('tailwindcss') ||
                        message.includes('inertiajs'))
                ) {
                    return
                }

                warn(warning)
            },
            output: {
                manualChunks(id) {
                    if (!id.includes('node_modules')) {
                        return
                    }

                    if (
                        /[\\/]node_modules[\\/](primevue|@primevue)[\\/]/.test(
                            id,
                        )
                    ) {
                        return 'vendor-primevue'
                    }

                    if (/[\\/]node_modules[\\/](vue|@vue)[\\/]/.test(id)) {
                        return 'vendor-vue'
                    }

                    return 'vendor'
                },
            },
        },
    },
    server: {
        host: '0.0.0.0',
        port: 5173,
        origin: 'http://localhost:5173',
        cors: {
            origin: ['http://localhost:8000', 'http://127.0.0.1:8000'],
        },
        hmr: {
            host: 'localhost',
        },
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
    plugins: [
        corePanelVendorFirst(),
        tailwindcss(),
        ...(fs.existsSync(path.resolve(__dirname, 'artisan'))
            ? [wayfinder()]
            : []),
        i18n({
            additionalLangPaths,
        }),
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.ts'],
            refresh: true,
        }),
        vue(),
    ],
})
