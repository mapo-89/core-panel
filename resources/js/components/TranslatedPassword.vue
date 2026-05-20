<script setup lang="ts">
import { computed, useAttrs } from 'vue'
import { trans } from 'laravel-vue-i18n'

import PasswordRequirementsList from '@core-panel/components/FormBuilder/fields/PasswordRequirementsList.vue'

const model = defineModel<string | null>({ default: '' })
const attrs = useAttrs()
const props = withDefaults(
    defineProps<{
        matchPassword?: string | null
        minLength?: number | null
        showRequirements?: boolean
    }>(),
    {
        minLength: null,
        showRequirements: true,
    },
)

const showsStrengthFeedback = computed(() => props.minLength !== null)
const showsConfirmationFeedback = computed(
    () => props.matchPassword !== undefined && props.matchPassword !== null,
)
const showsRequirementFooter = computed(
    () =>
        props.showRequirements &&
        (props.minLength !== null || showsConfirmationFeedback.value),
)
const showsOverlay = computed(
    () => showsStrengthFeedback.value || showsRequirementFooter.value,
)
const forwardedAttrs = computed<Record<string, unknown>>(() => {
    const rest = { ...(attrs as Record<string, unknown>) }

    delete rest.pt

    return rest
})
const passThrough = computed<Record<string, unknown> | undefined>(() => {
    const existing = ((attrs as Record<string, unknown>).pt ?? {}) as Record<
        string,
        unknown
    >

    if (showsStrengthFeedback.value || !showsRequirementFooter.value) {
        return existing
    }

    const content = (existing.content ?? {}) as Record<string, unknown>
    const existingStyle =
        typeof content.style === 'string' ? `${content.style}; ` : ''

    return {
        ...existing,
        content: {
            ...content,
            style: `${existingStyle}display: none;`,
        },
    }
})
const labels = computed(() => ({
    medium: trans('common.auth.password_strength_medium'),
    prompt: trans('common.auth.password_strength_prompt'),
    strong: trans('common.auth.password_strength_strong'),
    weak: trans('common.auth.password_strength_weak'),
}))
</script>

<template>
    <Password
        v-model="model"
        :feedback="showsOverlay"
        :medium-label="labels.medium"
        :pt="passThrough"
        :prompt-label="labels.prompt"
        :strong-label="labels.strong"
        :weak-label="labels.weak"
        v-bind="forwardedAttrs"
    >
        <template v-if="$slots.footer || showsRequirementFooter" #footer>
            <slot name="footer">
                <PasswordRequirementsList
                    v-if="showsRequirementFooter"
                    :confirmation="
                        showsConfirmationFeedback ? matchPassword : undefined
                    "
                    :min-length="minLength"
                    :password="model"
                />
            </slot>
        </template>
    </Password>
</template>
