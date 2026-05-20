/* eslint-disable vue/multi-word-component-names, vue/no-reserved-component-names */

import type { App, DefineComponent, DirectiveBinding } from 'vue'

import PrimeVue from 'primevue/config'
import ConfirmationService from 'primevue/confirmationservice'
import DialogService from 'primevue/dialogservice'
import ToastService from 'primevue/toastservice'
import Tooltip from 'primevue/tooltip'

import Avatar from 'primevue/avatar'
import Badge from 'primevue/badge'
import Button from 'primevue/button'
import Checkbox from 'primevue/checkbox'
import Column from 'primevue/column'
import ConfirmDialog from 'primevue/confirmdialog'
import DataTable from 'primevue/datatable'
import DatePicker from 'primevue/datepicker'
import Dialog from 'primevue/dialog'
import Drawer from 'primevue/drawer'
import DynamicDialog from 'primevue/dynamicdialog'
import FileUpload from 'primevue/fileupload'
import IconField from 'primevue/iconfield'
import InputIcon from 'primevue/inputicon'
import InputNumber from 'primevue/inputnumber'
import InputOtp from 'primevue/inputotp'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import Menu from 'primevue/menu'
import MultiSelect from 'primevue/multiselect'
import Password from 'primevue/password'
import Paginator from 'primevue/paginator'
import RadioButton from 'primevue/radiobutton'
import Select from 'primevue/select'
import Skeleton from 'primevue/skeleton'
import Tab from 'primevue/tab'
import TabList from 'primevue/tablist'
import TabPanel from 'primevue/tabpanel'
import TabPanels from 'primevue/tabpanels'
import Tabs from 'primevue/tabs'
import Tag from 'primevue/tag'
import Textarea from 'primevue/textarea'
import Toast from 'primevue/toast'
import ToggleSwitch from 'primevue/toggleswitch'

import {
    applyCorePanelLayoutDensity,
    applyCorePanelRadiusToken,
    applyCorePanelThemeAccent,
    applyCorePanelThemePalette,
    applyCorePanelRuntimeThemeVariables,
    normalizeCorePanelLayoutDensity,
    normalizeCorePanelRadiusToken,
    normalizeCorePanelColorModePreference,
    normalizeCorePanelThemeAccent,
    normalizeCorePanelThemePalette,
    readStoredCorePanelColorMode,
    resolveCorePanelColorMode,
    resolveCorePanelRuntimeTheme,
} from '@core-panel/theme/core-panel'
import { canAnyInAuth, canInAuth, hasRoleInAuth } from '@/composables/useCan'

export type CorePanelUiConfig = {
    darkMode?: boolean | 'system'
    layoutDensity?: string
    radiusToken?: string
    themeAccent?: string
    themePalette?: string
    theme?: string
}

export type CorePanelPageModule = {
    default: DefineComponent
}

type SharedAuthPayload = {
    permissions?: string[]
    role?: string | null
    roles?: string[]
}

function resolveDirectiveAuth(binding: DirectiveBinding): SharedAuthPayload {
    return ((
        binding.instance as {
            $page?: { props?: { auth?: SharedAuthPayload } }
        } | null
    )?.$page?.props?.auth ?? {}) as SharedAuthPayload
}

function setDirectiveVisibility(el: HTMLElement, allowed: boolean): void {
    if (allowed) {
        const display = el.dataset.corePanelDisplay

        if (display !== undefined) {
            el.style.display = display
        } else {
            el.style.removeProperty('display')
        }

        el.hidden = false

        return
    }

    if (el.dataset.corePanelDisplay === undefined) {
        el.dataset.corePanelDisplay = el.style.display
    }

    el.style.display = 'none'
    el.hidden = true
}

function applyCanDirective(el: HTMLElement, binding: DirectiveBinding): void {
    const auth = resolveDirectiveAuth(binding)
    const value = binding.value
    const permissions = Array.isArray(value)
        ? value.map((permission) => String(permission))
        : [String(value ?? '')]
    const allowed = binding.modifiers.any
        ? canAnyInAuth(auth, permissions)
        : canInAuth(auth, permissions[0] ?? '')

    setDirectiveVisibility(el, allowed)
}

function applyRoleDirective(el: HTMLElement, binding: DirectiveBinding): void {
    const auth = resolveDirectiveAuth(binding)
    const value = binding.value
    const roles = Array.isArray(value)
        ? value.map((role) => String(role))
        : [String(value ?? '')]

    setDirectiveVisibility(el, hasRoleInAuth(auth, roles))
}

export function installCorePanelUi(
    app: App,
    config: CorePanelUiConfig = {},
): void {
    const themeName = config.theme ?? 'core-panel'
    const storedMode = readStoredCorePanelColorMode()
    const modePreference =
        storedMode ??
        normalizeCorePanelColorModePreference(
            config.darkMode === false ? 'light' : 'system',
        )
    const mode = resolveCorePanelColorMode(modePreference)
    const layoutDensity = normalizeCorePanelLayoutDensity(config.layoutDensity)
    const radiusToken = normalizeCorePanelRadiusToken(config.radiusToken)
    const themePalette = normalizeCorePanelThemePalette(config.themePalette)
    const themeAccent = normalizeCorePanelThemeAccent(config.themeAccent)

    applyCorePanelRuntimeThemeVariables(mode)
    applyCorePanelLayoutDensity(layoutDensity)
    applyCorePanelRadiusToken(radiusToken)
    applyCorePanelThemePalette(themePalette)
    applyCorePanelThemeAccent(themeAccent)

    app.use(PrimeVue, {
        theme: {
            preset: resolveCorePanelRuntimeTheme(themeName),
            options: {
                cssLayer: {
                    name: 'primevue',
                    order: 'tailwind-base, primevue, tailwind-utilities',
                },
                darkModeSelector: '.core-panel-dark',
            },
        },
    })

    app.use(ToastService)
    app.use(ConfirmationService)
    app.use(DialogService)
    app.directive('tooltip', Tooltip)
    app.directive('can', {
        mounted: applyCanDirective,
        updated: applyCanDirective,
    })
    app.directive('role', {
        mounted: applyRoleDirective,
        updated: applyRoleDirective,
    })

    app.component('Avatar', Avatar)
    app.component('Badge', Badge)
    app.component('Button', Button)
    app.component('Checkbox', Checkbox)
    app.component('Column', Column)
    app.component('ConfirmDialog', ConfirmDialog)
    app.component('DataTable', DataTable)
    app.component('DatePicker', DatePicker)
    app.component('Dialog', Dialog)
    app.component('Drawer', Drawer)
    app.component('DynamicDialog', DynamicDialog)
    app.component('FileUpload', FileUpload)
    app.component('IconField', IconField)
    app.component('InputNumber', InputNumber)
    app.component('InputIcon', InputIcon)
    app.component('InputOtp', InputOtp)
    app.component('InputText', InputText)
    app.component('Message', Message)
    app.component('Menu', Menu)
    app.component('MultiSelect', MultiSelect)
    app.component('Password', Password)
    app.component('Paginator', Paginator)
    app.component('RadioButton', RadioButton)
    app.component('Select', Select)
    app.component('Skeleton', Skeleton)
    app.component('Tab', Tab)
    app.component('TabList', TabList)
    app.component('TabPanel', TabPanel)
    app.component('TabPanels', TabPanels)
    app.component('Tabs', Tabs)
    app.component('Tag', Tag)
    app.component('Textarea', Textarea)
    app.component('Toast', Toast)
    app.component('ToggleSwitch', ToggleSwitch)
}
