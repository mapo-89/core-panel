import '../css/app.css'

import { createInertiaApp } from '@inertiajs/vue3'
import { I18n, i18nVue } from 'laravel-vue-i18n'
import { createApp, h, type DefineComponent } from 'vue'

import { installCorePanelUi } from './plugins/core-panel'

const lazyLanguageModules = import.meta.glob<{
    default: Record<string, string>
}>('../../lang/*.json')
let currentAppName = 'CorePanel'

function resolveAppName(props: Record<string, unknown>): string {
    const value = props.appName

    return typeof value === 'string' && value.trim() !== ''
        ? value.trim()
        : 'CorePanel'
}

createInertiaApp({
    title: (title) => {
        const activeAppName =
            document.documentElement.dataset.appName?.trim() || currentAppName

        return title ? `${title} - ${activeAppName}` : activeAppName
    },
    resolve: (name) => {
        const pages = import.meta.glob<{ default: DefineComponent }>(
            './pages/**/*.vue',
            { eager: true },
        )

        return (
            pages[`./pages/${name}.vue`] ?? pages[`./pages/Admin/${name}.vue`]
        )?.default
    },
    async setup({ el, App, props, plugin }) {
        const app = createApp({ render: () => h(App, props) })
        const initialPageProps = props.initialPage.props as Record<
            string,
            unknown
        >
        currentAppName = resolveAppName(initialPageProps)
        const locale = (props.initialPage.props as Record<string, unknown>)
            .locale as
            | {
                  current?: string
                  fallback?: string
              }
            | undefined
        const corePanelSettings = ((
            props.initialPage.props as Record<string, unknown>
        ).corePanel ?? {}) as {
            settings?: {
                appearance?: {
                    theme?: string
                    theme_palette?: string
                }
                ui?: {
                    layout_density?: string
                    primary_color_token?: string
                    radius_token?: string
                }
            }
        }
        const activeLocale = locale?.current ?? document.documentElement.lang
        const i18nOptions = {
            fallbackLang: locale?.fallback ?? 'en',
            lang: activeLocale,
            resolve: async (lang: string) => {
                const loader =
                    lazyLanguageModules[`../../lang/php_${lang}.json`]

                return loader ? await loader() : { default: {} }
            },
        }

        await I18n.getSharedInstance(i18nOptions).loadLanguageAsync(
            activeLocale,
            false,
            true,
        )

        document.documentElement.lang = activeLocale
        document.documentElement.dataset.appName = currentAppName

        app.use(plugin)
        app.use(i18nVue, i18nOptions)
        installCorePanelUi(app, {
            layoutDensity:
                corePanelSettings.settings?.ui?.layout_density ?? 'comfortable',
            radiusToken: corePanelSettings.settings?.ui?.radius_token ?? 'md',
            themeAccent:
                corePanelSettings.settings?.ui?.primary_color_token ??
                '#1ab88f',
            themePalette:
                corePanelSettings.settings?.appearance?.theme_palette ??
                'paper',
            theme: 'core-panel',
        })
        app.mount(el)
    },
    progress: {
        color: 'var(--p-primary-600)',
    },
})
