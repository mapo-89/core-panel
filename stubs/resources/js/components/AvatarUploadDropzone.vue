<script setup lang="ts">
import { usePage } from '@inertiajs/vue3'
import { computed, onBeforeUnmount, ref, watch } from 'vue'

import AppIcon from '@/components/AppIcon.vue'
import UserAvatar from '@/components/ui/UserAvatar.vue'

const props = withDefaults(
    defineProps<{
        currentAvatarUrl?: string | null
        disabled?: boolean
        hint?: string
        initials: string
        layout?: 'inline' | 'stacked'
        modelValue: File | null
        overlayIcon?: string
        overlaySpinning?: boolean
        presenceStatus?: 'online' | 'away' | 'offline' | null
        showPresence?: boolean
        showBadges?: boolean
        showHint?: boolean
        size?: 'sm' | 'md' | 'lg' | 'xl'
        variant?: 'compact' | 'regular'
    }>(),
    {
        currentAvatarUrl: null,
        disabled: false,
        hint: '',
        layout: 'stacked',
        overlayIcon: 'camera',
        overlaySpinning: false,
        presenceStatus: null,
        showBadges: true,
        showHint: true,
        showPresence: false,
        size: 'lg',
        variant: 'compact',
    },
)

const emit = defineEmits<{
    invalidFile: []
    'update:modelValue': [value: File | null]
}>()

const page = usePage<{
    corePanel?: {
        uploads?: {
            avatar?: {
                accept?: string
                badges?: string[]
                mimeTypes?: string[]
            }
        }
    }
}>()

const dragActive = ref(false)
const fileInput = ref<HTMLInputElement | null>(null)
const previewUrl = ref<string | null>(props.currentAvatarUrl ?? null)
let objectUrl: string | null = null

const allowedMimeTypes = computed<string[]>(() => {
    return (
        page.props.corePanel?.uploads?.avatar?.mimeTypes ?? [
            'image/jpeg',
            'image/png',
            'image/webp',
        ]
    )
})

const acceptValue = computed(() => {
    return (
        page.props.corePanel?.uploads?.avatar?.accept ??
        allowedMimeTypes.value.join(',')
    )
})

const badges = computed(() => {
    return (
        page.props.corePanel?.uploads?.avatar?.badges ?? [
            'JPG',
            'PNG',
            'WEBP',
            '10 MB',
        ]
    )
})

watch(
    () => props.currentAvatarUrl,
    (value) => {
        if (props.modelValue === null) {
            previewUrl.value = value ?? null
        }
    },
)

watch(
    () => props.modelValue,
    (value) => {
        if (objectUrl !== null) {
            URL.revokeObjectURL(objectUrl)
            objectUrl = null
        }

        if (value === null) {
            previewUrl.value = props.currentAvatarUrl ?? null

            return
        }

        objectUrl = URL.createObjectURL(value)
        previewUrl.value = objectUrl
    },
    { immediate: true },
)

onBeforeUnmount(() => {
    if (objectUrl !== null) {
        URL.revokeObjectURL(objectUrl)
    }
})

function isAllowedFile(file: File): boolean {
    return allowedMimeTypes.value.includes(file.type)
}

function openPicker(): void {
    if (!props.disabled) {
        fileInput.value?.click()
    }
}

function clearSelectedFile(): void {
    emit('update:modelValue', null)

    if (fileInput.value !== null) {
        fileInput.value.value = ''
    }
}

function updateFile(file: File | null): void {
    if (file === null) {
        clearSelectedFile()

        return
    }

    if (!isAllowedFile(file)) {
        emit('invalidFile')

        if (fileInput.value !== null) {
            fileInput.value.value = ''
        }

        return
    }

    emit('update:modelValue', file)
}

function handleInputChange(event: Event): void {
    const input = event.target as HTMLInputElement | null
    updateFile(input?.files?.[0] ?? null)
}

function handleDragOver(): void {
    if (!props.disabled) {
        dragActive.value = true
    }
}

function handleDragLeave(): void {
    dragActive.value = false
}

function handleDrop(event: DragEvent): void {
    dragActive.value = false

    if (props.disabled) {
        return
    }

    updateFile(event.dataTransfer?.files?.[0] ?? null)
}
</script>

<template>
    <div
        class="cp-avatar-dropzone"
        :class="[
            `cp-avatar-dropzone--${layout}`,
            `cp-avatar-dropzone--${variant}`,
        ]"
    >
        <input
            ref="fileInput"
            class="hidden"
            :accept="acceptValue"
            type="file"
            @change="handleInputChange"
        />

        <div
            class="cp-avatar-dropzone__media"
            role="button"
            tabindex="0"
            :class="{ 'is-drag-active': dragActive }"
            @click="openPicker"
            @dragenter.prevent="handleDragOver"
            @dragleave.prevent="handleDragLeave"
            @dragover.prevent="handleDragOver"
            @drop.prevent="handleDrop"
            @keydown.enter.prevent="openPicker"
            @keydown.space.prevent="openPicker"
        >
            <div class="cp-avatar-dropzone__avatar-frame">
                <UserAvatar
                    :avatar-url="previewUrl"
                    :initials="initials"
                    :presence-status="presenceStatus ?? 'offline'"
                    :show-presence="showPresence"
                    :size="size"
                />

                <div class="cp-avatar-dropzone__overlay-badge">
                    <AppIcon
                        :name="overlayIcon"
                        class="cp-avatar-dropzone__overlay-icon"
                        :class="{ 'animate-spin': overlaySpinning }"
                    />
                </div>
            </div>
        </div>

        <div class="cp-avatar-dropzone__body">
            <p v-if="showHint && hint" class="cp-avatar-dropzone__hint">
                {{ hint }}
            </p>

            <div v-if="showBadges" class="cp-avatar-dropzone__badges">
                <span
                    v-for="badge in badges"
                    :key="badge"
                    class="cp-avatar-dropzone__badge"
                >
                    {{ badge }}
                </span>
            </div>

            <slot
                name="actions"
                :clear-selected-file="clearSelectedFile"
                :has-selection="modelValue !== null"
                :open-picker="openPicker"
            />
        </div>
    </div>
</template>
