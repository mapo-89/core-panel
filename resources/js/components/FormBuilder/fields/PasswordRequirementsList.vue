<script setup lang="ts">
import { computed } from 'vue'
import { trans } from 'laravel-vue-i18n'

import AppIcon from '@core-panel/components/AppIcon.vue'

type PasswordRequirementState = 'idle' | 'invalid' | 'valid'

const props = withDefaults(
    defineProps<{
        confirmation?: string | null
        minLength?: number | null
        password: string | null
    }>(),
    {
        confirmation: null,
        minLength: null,
    },
)

type PasswordRequirementItem = {
    key: string
    label: string
    state: PasswordRequirementState
}

const passwordValue = computed(() => props.password ?? '')
const confirmationValue = computed(() => props.confirmation ?? '')
const minLengthValue = computed(() =>
    typeof props.minLength === 'number' && props.minLength > 0
        ? props.minLength
        : null,
)
const hasConfirmationRule = computed(
    () => props.confirmation !== undefined && props.confirmation !== null,
)

const items = computed<PasswordRequirementItem[]>(() => {
    const requirementItems: PasswordRequirementItem[] = []

    if (minLengthValue.value !== null) {
        const passwordLength = passwordValue.value.length
        const isSatisfied = passwordLength >= minLengthValue.value
        const state: PasswordRequirementState =
            passwordLength === 0 ? 'idle' : isSatisfied ? 'valid' : 'invalid'

        requirementItems.push({
            key: 'min-length',
            label: `${trans('common.auth.password_rule_min_length', {
                count: String(minLengthValue.value),
            })}${passwordLength > 0 ? ` (${passwordLength}/${minLengthValue.value})` : ''}`,
            state,
        })
    }

    if (hasConfirmationRule.value) {
        const passwordTouched = passwordValue.value.length > 0
        const confirmationTouched = confirmationValue.value.length > 0
        const isSatisfied =
            passwordTouched &&
            confirmationTouched &&
            passwordValue.value === confirmationValue.value
        const state: PasswordRequirementState =
            !passwordTouched && !confirmationTouched
                ? 'idle'
                : isSatisfied
                  ? 'valid'
                  : 'invalid'

        requirementItems.push({
            key: 'confirmation',
            label: trans('common.auth.password_rule_confirmation'),
            state,
        })
    }

    return requirementItems
})

function iconNameFor(state: PasswordRequirementState): string {
    if (state === 'valid') {
        return 'check'
    }

    if (state === 'invalid') {
        return 'x'
    }

    return 'info'
}
</script>

<template>
    <ul v-if="items.length > 0" class="cp-password-rules">
        <li
            v-for="item in items"
            :key="item.key"
            :class="`cp-password-rules__item cp-password-rules__item--${item.state}`"
        >
            <span class="cp-password-rules__icon" aria-hidden="true">
                <AppIcon :name="iconNameFor(item.state)" />
            </span>
            <span class="cp-password-rules__label">{{ item.label }}</span>
        </li>
    </ul>
</template>
