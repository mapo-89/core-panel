<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'
import Message from 'primevue/message'

import AppIcon from '@/components/AppIcon.vue'
import AuthLayout from '@/layouts/AuthLayout.vue'
import verification from '@/routes/verification'

const page = usePage<{
    flash?: {
        status?: string | null
    }
}>()
const form = useForm({})
const statusMessage = computed(() => {
    const status = page.props.flash?.status

    if (status === 'verification-link-sent') {
        return 'page-auth.verification_link_sent_status'
    }

    return status ?? null
})

function submit(): void {
    form.post(verification.send.url())
}
</script>

<template>
    <AuthLayout
        :heading="$t('page-auth.verify_heading')"
        :subheading="$t('page-auth.verify_subheading')"
    >
        <Head :title="$t('common.auth.verify_email')" />

        <div class="grid gap-5">
            <Message
                v-if="statusMessage"
                class="auth-status"
                severity="success"
                >{{
                    statusMessage === 'page-auth.verification_link_sent_status'
                        ? $t(statusMessage)
                        : statusMessage
                }}</Message
            >

            <p class="auth-card__subtitle">
                {{ $t('page-auth.verify_help') }}
            </p>

            <form class="auth-form" @submit.prevent="submit">
                <Button
                    class="auth-form__submit"
                    :disabled="form.processing"
                    :loading="form.processing"
                    type="submit"
                >
                    <AppIcon name="envelope" />
                    <span>{{ $t('page-auth.resend_verification') }}</span>
                </Button>
            </form>
        </div>
    </AuthLayout>
</template>
