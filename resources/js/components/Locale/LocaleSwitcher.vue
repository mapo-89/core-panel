<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3'
import {
    currentLocale as activeLocale,
    loadLanguageAsync,
} from 'laravel-vue-i18n'
import { computed } from 'vue'

import locale from '@/routes/locale'
import type { LocaleOption } from '@core-panel/types/core-panel'

const props = withDefaults(
    defineProps<{
        compact?: boolean
    }>(),
    {
        compact: false,
    },
)

const page = usePage<{
    locale: {
        current: string
        supported: string[]
        labels?: Record<string, string>
    }
}>()

const options = computed<LocaleOption[]>(() => {
    return (page.props.locale?.supported ?? []).map((locale) => ({
        code: locale,
        label: page.props.locale?.labels?.[locale] ?? locale.toUpperCase(),
    }))
})

const currentLocale = computed(
    () => activeLocale.value || page.props.locale?.current || 'de',
)

async function switchLocale(localeCode: string): Promise<void> {
    if (localeCode === currentLocale.value) {
        return
    }

    router.post(
        locale.set.url(),
        {
            locale: localeCode,
        },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: async () => {
                await loadLanguageAsync(localeCode)
                document.documentElement.lang = localeCode

                router.reload()
            },
        },
    )
}
</script>

<template>
    <div :class="props.compact ? 'grid min-w-[6.5rem] gap-2' : 'grid gap-2'">
        <label
            v-if="!props.compact"
            class="text-xs font-semibold text-[var(--cp-text-muted)]"
            for="core-panel-locale-switcher"
            >{{ $t('locale.switch') }}</label
        >

        <Select
            id="core-panel-locale-switcher"
            :model-value="currentLocale"
            :options="options"
            :class="props.compact ? 'min-w-[6.5rem]' : 'w-full'"
            fluid
            option-label="label"
            option-value="code"
            @update:model-value="(value) => switchLocale(String(value))"
        />
    </div>
</template>
