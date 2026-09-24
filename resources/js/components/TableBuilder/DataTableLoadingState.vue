<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'

import AppIcon from '@core-panel/components/AppIcon.vue'

const props = withDefaults(
    defineProps<{
        columnCount?: number
    }>(),
    {
        columnCount: 5,
    },
)

const rootRef = ref<HTMLElement | null>(null)
const stickyHeadRef = ref<HTMLElement | null>(null)
const gridTemplateColumns = computed(
    () => `repeat(${Math.max(1, props.columnCount)}, minmax(0, 1fr))`,
)
let resizeObserver: ResizeObserver | null = null

function syncStickyHeadHeight(): void {
    rootRef.value?.style.setProperty(
        '--cp-datatable-sticky-head-height',
        `${stickyHeadRef.value?.offsetHeight ?? 0}px`,
    )
}

onMounted(() => {
    syncStickyHeadHeight()

    if (typeof ResizeObserver !== 'undefined' && stickyHeadRef.value !== null) {
        resizeObserver = new ResizeObserver(syncStickyHeadHeight)
        resizeObserver.observe(stickyHeadRef.value)
    }
})

onBeforeUnmount(() => resizeObserver?.disconnect())
</script>

<template>
    <section ref="rootRef" aria-busy="true" class="grid gap-0 cp-datatable">
        <div ref="stickyHeadRef" class="cp-datatable__sticky-head">
            <div class="grid gap-3 px-[1.125rem] pt-[1.125rem] pb-1">
                <div class="cp-datatable__toolbar">
                    <Skeleton height="2.5rem" width="min(22rem, 55vw)" />
                    <div class="cp-datatable__toolbar-actions">
                        <Skeleton height="2.5rem" width="7rem" />
                        <Skeleton height="2.5rem" width="7rem" />
                    </div>
                </div>
            </div>

            <div
                class="cp-datatable__sticky-header-row"
                :style="{ gridTemplateColumns }"
            >
                <div
                    v-for="column in columnCount"
                    :key="column"
                    class="cp-datatable__sticky-header-cell"
                >
                    <Skeleton height="0.9rem" width="65%" />
                </div>
            </div>
        </div>

        <div
            aria-live="polite"
            class="cp-card cp-datatable__surface min-h-[14rem]"
            role="status"
        >
            <div class="cp-datatable__body-overlay">
                <div class="cp-datatable__body-overlay-card">
                    <AppIcon
                        class="cp-datatable__body-overlay-icon"
                        name="refresh"
                    />
                    <span class="cp-datatable__body-overlay-text">
                        {{ $t('table-builder.states.loading') }}
                    </span>
                </div>
            </div>
        </div>
    </section>
</template>
