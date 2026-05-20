<script setup lang="ts">
import type { ToastMessageOptions } from 'primevue/toast'

import AppIcon from '@/components/AppIcon.vue'

type AppToastSeverity = 'success' | 'info' | 'warn' | 'error'

interface ToastTone {
    accent: string
    icon: string
    progress: string
    root: string
}

const tones: Record<AppToastSeverity, ToastTone> = {
    error: {
        accent: 'app-toast__accent--error',
        icon: 'x',
        progress: 'app-toast__progress-bar--error',
        root: 'app-toast--error',
    },
    info: {
        accent: 'app-toast__accent--info',
        icon: 'info',
        progress: 'app-toast__progress-bar--info',
        root: 'app-toast--info',
    },
    success: {
        accent: 'app-toast__accent--success',
        icon: 'check',
        progress: 'app-toast__progress-bar--success',
        root: 'app-toast--success',
    },
    warn: {
        accent: 'app-toast__accent--warn',
        icon: 'triangle-alert',
        progress: 'app-toast__progress-bar--warn',
        root: 'app-toast--warn',
    },
}

const visibleMessages = new WeakMap<object, boolean>()
const recentSignatures = new Map<string, number>()
const duplicateWindowMs = 900

function resolveSeverity(message: ToastMessageOptions): AppToastSeverity {
    if (
        message.severity === 'success' ||
        message.severity === 'info' ||
        message.severity === 'warn' ||
        message.severity === 'error'
    ) {
        return message.severity as AppToastSeverity
    }

    return 'info'
}

function resolveTone(message: ToastMessageOptions): ToastTone {
    return tones[resolveSeverity(message)]
}

function resolveLife(message: ToastMessageOptions): number {
    return typeof message.life === 'number' && message.life > 0
        ? message.life
        : 4000
}

function messageSignature(message: ToastMessageOptions): string {
    return JSON.stringify([
        resolveSeverity(message),
        message.summary ?? '',
        message.detail ?? '',
    ])
}

function shouldDisplayMessage(message: ToastMessageOptions): boolean {
    const messageObject = message as ToastMessageOptions & object

    if (visibleMessages.has(messageObject)) {
        return visibleMessages.get(messageObject) ?? true
    }

    const now = Date.now()

    for (const [signature, timestamp] of recentSignatures.entries()) {
        if (now - timestamp > duplicateWindowMs) {
            recentSignatures.delete(signature)
        }
    }

    const signature = messageSignature(message)
    const lastSeenAt = recentSignatures.get(signature)
    const shouldDisplay =
        lastSeenAt === undefined || now - lastSeenAt > duplicateWindowMs

    recentSignatures.set(signature, now)
    visibleMessages.set(messageObject, shouldDisplay)

    return shouldDisplay
}

function pauseToastTimer(): void {}

function resumeToastTimer(): void {}
</script>

<template>
    <Toast
        position="bottom-center"
        :on-mouse-enter="pauseToastTimer"
        :on-mouse-leave="resumeToastTimer"
        :breakpoints="{
            '640px': {
                width: 'calc(100vw - 1.5rem)',
            },
        }"
    >
        <template #container="{ message, closeCallback }">
            <div
                v-if="shouldDisplayMessage(message)"
                class="app-toast"
                :class="resolveTone(message).root"
                :data-severity="resolveSeverity(message)"
            >
                <div class="app-toast__progress">
                    <span
                        class="app-toast__progress-bar"
                        :class="resolveTone(message).progress"
                        :style="{
                            animationDuration: `${resolveLife(message)}ms`,
                        }"
                    />
                </div>

                <div class="app-toast__body">
                    <div
                        class="app-toast__accent"
                        :class="resolveTone(message).accent"
                    >
                        <AppIcon
                            :name="resolveTone(message).icon"
                            class="app-toast__icon"
                        />
                    </div>

                    <div class="app-toast__content">
                        <div class="app-toast__header">
                            <p class="app-toast__summary">
                                {{ message.summary }}
                            </p>

                            <button
                                type="button"
                                class="app-toast__close"
                                :aria-label="
                                    $primevue.config.locale?.aria?.close ??
                                    'Close'
                                "
                                @click="closeCallback"
                            >
                                <AppIcon
                                    name="x"
                                    class="app-toast__close-icon"
                                />
                            </button>
                        </div>

                        <p v-if="message.detail" class="app-toast__detail">
                            {{ message.detail }}
                        </p>
                    </div>
                </div>
            </div>
        </template>
    </Toast>
</template>
