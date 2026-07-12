<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

import AvatarUploadDropzone from '@core-panel/components/AvatarUploadDropzone.vue'
import socialite from '@/routes/socialite'

const props = defineProps<{
    currentAvatarUrl: string | null
    provider: string
    providerAvatarUrl: string | null
    providerLabel: string
}>()

const processing = ref(false)
const visible = ref(true)

const providerAvatarPreview = computed(() => props.providerAvatarUrl ?? null)

function submit(decision: 'keep' | 'replace'): void {
    processing.value = true

    router.post(
        socialite.resolveAvatarSync.url(props.provider),
        { decision },
        {
            onFinish: () => {
                processing.value = false
                visible.value = false
            },
            preserveScroll: true,
        },
    )
}
</script>

<template>
    <Dialog
        v-model:visible="visible"
        :closable="false"
        :draggable="false"
        modal
        :style="{ width: 'min(840px, calc(100vw - 2rem))' }"
    >
        <template #header>
            <div class="grid gap-1">
                <h2 class="text-lg font-semibold text-[var(--cp-text-primary)]">
                    {{
                        $t('page-settings.social_avatar_sync_title', {
                            provider: providerLabel,
                        })
                    }}
                </h2>
                <p class="text-sm text-[var(--cp-text-muted)]">
                    {{
                        $t('page-settings.social_avatar_sync_subtitle', {
                            provider: providerLabel,
                        })
                    }}
                </p>
            </div>
        </template>

        <div class="grid gap-5">
            <div class="grid gap-4 md:grid-cols-2">
                <section
                    class="rounded-[var(--cp-radius-lg)] border border-[var(--cp-surface-border)] bg-[var(--cp-surface-panel)] p-4"
                >
                    <div class="grid gap-3">
                        <div class="grid gap-1">
                            <p
                                class="text-xs font-semibold uppercase tracking-[0.18em] text-[var(--cp-text-muted)]"
                            >
                                {{
                                    $t(
                                        'page-settings.social_avatar_sync_current_label',
                                    )
                                }}
                            </p>
                            <p class="text-sm text-[var(--cp-text-muted)]">
                                {{
                                    $t(
                                        currentAvatarUrl
                                            ? 'page-settings.social_avatar_sync_keep_hint'
                                            : 'page-settings.social_avatar_sync_missing',
                                    )
                                }}
                            </p>
                        </div>

                        <div
                            class="flex min-h-56 items-center justify-center rounded-[var(--cp-radius-lg)] border border-dashed border-[var(--cp-surface-border)] bg-[var(--cp-surface-muted)]/60 p-6"
                        >
                            <AvatarUploadDropzone
                                :current-avatar-url="currentAvatarUrl"
                                initials="CP"
                                layout="stacked"
                                :model-value="null"
                                overlay-icon="camera"
                                :presence-status="'offline'"
                                :show-badges="false"
                                :show-hint="false"
                                size="xl"
                                variant="regular"
                                :show-presence="false"
                                disabled
                            />
                        </div>
                    </div>
                </section>

                <section
                    class="rounded-[var(--cp-radius-lg)] border border-[var(--cp-surface-border)] bg-[var(--cp-surface-panel)] p-4"
                >
                    <div class="grid gap-3">
                        <div class="grid gap-1">
                            <p
                                class="text-xs font-semibold uppercase tracking-[0.18em] text-[var(--cp-text-muted)]"
                            >
                                {{
                                    $t(
                                        'page-settings.social_avatar_sync_provider_label',
                                        { provider: providerLabel },
                                    )
                                }}
                            </p>
                            <p class="text-sm text-[var(--cp-text-muted)]">
                                {{
                                    $t(
                                        'page-settings.social_avatar_sync_replace_hint',
                                        { provider: providerLabel },
                                    )
                                }}
                            </p>
                        </div>

                        <div
                            class="flex min-h-56 items-center justify-center rounded-[var(--cp-radius-lg)] border border-dashed border-[var(--cp-surface-border)] bg-[var(--cp-surface-muted)]/60 p-6"
                        >
                            <div
                                class="flex h-40 w-40 items-center justify-center overflow-hidden rounded-full border border-[var(--cp-surface-border)] bg-[var(--cp-surface-panel)] p-3"
                            >
                                <img
                                    v-if="providerAvatarPreview"
                                    :src="providerAvatarPreview"
                                    :alt="providerLabel"
                                    class="h-full w-full rounded-full object-cover"
                                />
                                <span
                                    v-else
                                    class="text-sm text-[var(--cp-text-muted)]"
                                >
                                    {{
                                        $t(
                                            'page-settings.social_avatar_sync_missing',
                                        )
                                    }}
                                </span>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <div class="flex flex-wrap items-center justify-end gap-2">
                <Button outlined :disabled="processing" @click="submit('keep')">
                    {{ $t('page-settings.social_avatar_sync_keep') }}
                </Button>
                <Button :loading="processing" @click="submit('replace')">
                    {{
                        $t('page-settings.social_avatar_sync_replace', {
                            provider: providerLabel,
                        })
                    }}
                </Button>
            </div>
        </div>
    </Dialog>
</template>
