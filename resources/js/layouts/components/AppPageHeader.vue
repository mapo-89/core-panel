<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import { ArrowLeft } from 'lucide-vue-next'
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'

const props = withDefaults(
    defineProps<{
        backUrl?: boolean | string
        keepTitleVisible?: boolean
        subtitle?: string
        title?: string
    }>(),
    {
        backUrl: false,
        keepTitleVisible: false,
        subtitle: '',
        title: '',
    },
)

const resolvedBackUrl = computed(() => {
    if (props.backUrl === true) {
        return typeof window !== 'undefined' && window.history.length > 1
            ? 'back'
            : null
    }

    return props.backUrl || null
})

const rootElement = ref<HTMLElement | null>(null)
const isCompact = ref(false)

let scrollParent: HTMLElement | null = null
let cleanupScrollListener: (() => void) | null = null
let resizeObserver: ResizeObserver | null = null

const COMPACT_ENTER_THRESHOLD = 32
const COMPACT_LEAVE_THRESHOLD = 0

function findScrollParent(element: HTMLElement | null): HTMLElement | null {
    let current = element?.parentElement ?? null

    while (current !== null) {
        const styles = window.getComputedStyle(current)
        const overflowY = styles.overflowY

        if (overflowY === 'auto' || overflowY === 'scroll') {
            return current
        }

        current = current.parentElement
    }

    return null
}

function updatePageHeaderHeightVariable(): void {
    if (typeof document === 'undefined') {
        return
    }

    document.documentElement.style.setProperty(
        '--cp-page-header-height',
        `${rootElement.value?.offsetHeight ?? 0}px`,
    )
}

function updateCompactState(): void {
    if (scrollParent === null) {
        isCompact.value = false

        return
    }

    const scrollTop = scrollParent.scrollTop

    if (isCompact.value) {
        isCompact.value = scrollTop > COMPACT_LEAVE_THRESHOLD

        return
    }

    isCompact.value = scrollTop > COMPACT_ENTER_THRESHOLD
}

function goBack(): void {
    if (props.backUrl === true) {
        window.history.back()
        return
    }

    if (typeof props.backUrl === 'string') {
        router.visit(props.backUrl)
    }
}

onMounted(() => {
    scrollParent = findScrollParent(rootElement.value)

    updatePageHeaderHeightVariable()

    if (typeof ResizeObserver !== 'undefined' && rootElement.value !== null) {
        resizeObserver = new ResizeObserver(() => {
            updatePageHeaderHeightVariable()
        })

        resizeObserver.observe(rootElement.value)
    }

    if (scrollParent === null) {
        return
    }

    const onScroll = () => {
        updateCompactState()
    }

    scrollParent.addEventListener('scroll', onScroll, { passive: true })
    cleanupScrollListener = () => {
        scrollParent?.removeEventListener('scroll', onScroll)
    }

    updateCompactState()
})

onBeforeUnmount(() => {
    cleanupScrollListener?.()
    cleanupScrollListener = null
    resizeObserver?.disconnect()
    resizeObserver = null
    scrollParent = null
    if (typeof document !== 'undefined') {
        document.documentElement.style.setProperty(
            '--cp-page-header-height',
            '0px',
        )
    }
})
</script>

<template>
    <div
        v-if="title || resolvedBackUrl || $slots.actions"
        ref="rootElement"
        :class="[
            'cp-page-header relative sticky top-0 z-30 mb-6 flex shrink-0 flex-col items-start justify-between gap-4 px-1 transition-all duration-200 ease-out before:pointer-events-none before:absolute before:inset-x-0 before:bottom-full before:h-6 before:bg-[var(--cp-surface-canvas)] before:shadow-[0_-10px_18px_var(--cp-surface-canvas)] after:pointer-events-none after:absolute after:inset-x-0 after:top-full after:h-3 after:bg-[var(--cp-surface-canvas)] after:shadow-[0_8px_14px_var(--cp-surface-canvas)] md:flex-row md:items-center',
            isCompact
                ? 'bg-[var(--cp-surface-canvas)] py-0.5'
                : 'bg-[var(--cp-surface-canvas)] py-3.5',
        ]"
    >
        <div
            v-if="title"
            :class="[
                'min-w-0 flex-1 transition-all duration-200 ease-out',
                isCompact && !keepTitleVisible
                    ? 'max-h-0 translate-y-[-0.25rem] overflow-hidden opacity-0'
                    : 'max-h-32 opacity-100',
            ]"
        >
            <div class="flex min-w-0 items-center gap-3">
                <slot name="title-leading" />
                <h1
                    :class="[
                        'min-w-0 font-bold leading-tight text-[var(--cp-text-primary)] transition-all duration-200 ease-out',
                        isCompact && keepTitleVisible ? 'text-lg' : 'text-2xl',
                    ]"
                >
                    {{ title }}
                </h1>
            </div>
            <p
                v-if="subtitle && (!isCompact || !keepTitleVisible)"
                class="mt-[0.35rem] text-sm text-[var(--cp-text-muted)]"
            >
                {{ subtitle }}
            </p>
            <div
                v-if="$slots.meta"
                :class="[
                    'flex flex-wrap items-center gap-2 overflow-hidden transition-all duration-200 ease-out',
                    isCompact
                        ? keepTitleVisible
                            ? 'mt-1.5 max-h-20 opacity-100'
                            : 'mt-2 max-h-20 opacity-100'
                        : 'mt-0 max-h-0 opacity-0',
                ]"
            >
                <slot name="meta" />
            </div>
        </div>

        <div
            v-if="resolvedBackUrl || $slots.actions"
            :class="[
                'cp-page-header__actions flex w-full shrink-0 items-center gap-2 transition-all duration-200 ease-out md:w-auto',
                isCompact ? 'cp-page-header__actions--compact' : '',
            ]"
        >
            <Button
                v-if="resolvedBackUrl"
                class="gap-2"
                outlined
                severity="secondary"
                @click="goBack"
            >
                <ArrowLeft class="cp-icon" />
                <span>{{ $t('common.ui.back') }}</span>
            </Button>
            <slot name="actions" />
        </div>
    </div>
</template>

<style scoped>
.cp-page-header__actions :deep(.p-button) {
    transition:
        transform 0.2s ease,
        padding 0.2s ease,
        font-size 0.2s ease,
        min-height 0.2s ease;
}

.cp-page-header__actions--compact :deep(.p-button) {
    transform: scale(0.92);
    transform-origin: center right;
}
</style>
