import path from 'node:path'
import fs from 'node:fs'

import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import laravel from 'laravel-vite-plugin'
import { wayfinder } from '@laravel/vite-plugin-wayfinder'
import tailwindcss from '@tailwindcss/vite'
import i18n from 'laravel-vue-i18n/vite'

const hostJsPath = path.resolve(__dirname, 'resources/js')
const packageManagedJsPath = fs.existsSync(
    path.resolve(hostJsPath, 'theme/core-panel'),
)
    ? hostJsPath
    : path.resolve(__dirname, '../resources/js')
const additionalLangPaths = [
    path.resolve(__dirname, 'lang/vendor/core-panel'),
    path.resolve(__dirname, '../resources/lang'),
].filter((candidate) => fs.existsSync(candidate))

export default defineConfig({
    resolve: {
        alias: [
            {
                find: '@',
                replacement: path.resolve(__dirname, 'resources/js'),
            },
            {
                find: '@core-panel',
                replacement: packageManagedJsPath,
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
