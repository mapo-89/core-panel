<script setup lang="ts">
import { computed } from 'vue'
import {
    languageDe,
    languageEn,
    languageEs,
    languageFr,
    languageIt,
    languageNl,
    languagePl,
    languagePt,
    languageTr,
} from '@blade-flags/core/flags/flat'

const props = defineProps<{
    code: string
}>()

const normalizedCode = computed(() =>
    props.code.trim().toLowerCase().replace(/_/g, '-'),
)

const availableFlags = {
    de: languageDe,
    en: languageEn,
    es: languageEs,
    fr: languageFr,
    it: languageIt,
    nl: languageNl,
    pl: languagePl,
    pt: languagePt,
    tr: languageTr,
} satisfies Record<string, string>

const resolvedLanguageFlag = computed(
    () => availableFlags[normalizedCode.value as keyof typeof availableFlags],
)
</script>

<template>
    <span
        class="locale-flag"
        :class="`locale-flag--${normalizedCode}`"
        :title="code.toUpperCase()"
        aria-hidden="true"
    >
        <!-- eslint-disable vue/no-v-html -->
        <span
            v-if="resolvedLanguageFlag"
            class="locale-flag__svg"
            v-html="resolvedLanguageFlag"
        />
        <!-- eslint-enable vue/no-v-html -->

        <svg
            v-else
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 32 24"
            fill="none"
        >
            <rect width="32" height="24" rx="2" fill="#DDE7F0" />
            <path
                fill="#4A6175"
                d="M16 4.5a7.5 7.5 0 1 0 0 15 7.5 7.5 0 0 0 0-15Zm0 1.5c1.22 0 2.33.45 3.18 1.2h-1.44c-.32-.45-.68-.85-1.08-1.2H16Zm-1.88.28c-.4.27-.77.58-1.1.92H11.5c.78-.56 1.68-.95 2.62-1.2Zm-4 4.42h2.02c-.14.74-.2 1.52-.2 2.33 0 .8.06 1.58.2 2.32H10.1a5.96 5.96 0 0 1 0-4.65Zm.53 6.15h1.88c.33.58.73 1.11 1.2 1.57a6.05 6.05 0 0 1-3.08-1.57Zm2.94-1.5c-.18-.66-.27-1.4-.27-2.17 0-.78.1-1.51.27-2.18h4.82c.18.67.27 1.4.27 2.18 0 .77-.1 1.5-.27 2.17H13.6Zm4.7 3.07c.4-.27.77-.58 1.1-.92h1.52a6.02 6.02 0 0 1-2.62 1.2Zm1.6-2.57c.14-.74.2-1.51.2-2.32 0-.81-.06-1.59-.2-2.33h2.02a5.96 5.96 0 0 1 0 4.65h-2.02Zm-.56-6.15c-.33-.58-.73-1.1-1.2-1.57a6.05 6.05 0 0 1 3.08 1.57h-1.88Z"
            />
        </svg>
    </span>
</template>
