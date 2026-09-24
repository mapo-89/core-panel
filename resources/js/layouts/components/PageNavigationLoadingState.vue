<script setup lang="ts">
import DataTableLoadingState from '@core-panel/components/TableBuilder/DataTableLoadingState.vue'
import type { PageNavigationLoadingState } from '@core-panel/composables/usePageNavigationPending'

withDefaults(
    defineProps<{
        variant?: PageNavigationLoadingState
    }>(),
    {
        variant: 'page',
    },
)
</script>

<template>
    <DataTableLoadingState v-if="variant === 'datatable'" :column-count="6" />

    <div v-else-if="variant === 'tabs-datatable'" class="cp-card grid gap-0">
        <div
            class="flex items-center gap-3 border-b border-[var(--cp-surface-border)] px-5 pt-3"
        >
            <Skeleton
                v-for="tab in 2"
                :key="tab"
                border-radius="var(--cp-radius-md) var(--cp-radius-md) 0 0"
                height="2.75rem"
                :width="tab === 1 ? '10rem' : '8rem'"
            />
        </div>
        <DataTableLoadingState :column-count="6" />
    </div>

    <div
        v-else-if="variant === 'side-tabs-cards'"
        class="grid items-start gap-4 lg:grid-cols-[15rem_minmax(0,1fr)]"
    >
        <aside class="cp-card grid gap-2 p-3">
            <div
                v-for="tab in 5"
                :key="tab"
                class="flex items-center gap-3 rounded-[var(--cp-radius-md)] px-3 py-2.5"
            >
                <Skeleton shape="circle" size="1.25rem" />
                <Skeleton height="0.85rem" :width="`${52 + tab * 6}%`" />
            </div>
        </aside>
        <div class="grid min-w-0 gap-4">
            <section
                v-for="card in 2"
                :key="card"
                class="cp-card grid gap-5 p-5"
            >
                <div class="grid gap-2">
                    <Skeleton height="1.1rem" width="38%" />
                    <Skeleton height="0.75rem" width="68%" />
                </div>
                <div class="grid gap-4 md:grid-cols-2">
                    <div v-for="field in 4" :key="field" class="grid gap-2">
                        <Skeleton height="0.75rem" width="42%" />
                        <Skeleton height="2.5rem" width="100%" />
                    </div>
                </div>
            </section>
        </div>
    </div>

    <div
        v-else-if="variant === 'side-tabs'"
        class="grid items-start gap-4 lg:grid-cols-[15rem_minmax(0,1fr)]"
    >
        <aside class="cp-card grid gap-2 p-3">
            <div
                v-for="tab in 5"
                :key="tab"
                class="flex items-center gap-3 rounded-[var(--cp-radius-md)] px-3 py-2.5"
            >
                <Skeleton shape="circle" size="1.25rem" />
                <Skeleton height="0.85rem" :width="`${52 + tab * 6}%`" />
            </div>
        </aside>
        <div class="min-w-0">
            <DataTableLoadingState :column-count="5" />
        </div>
    </div>

    <div v-else-if="variant === 'dashboard'" class="grid gap-6">
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <section
                v-for="card in 4"
                :key="card"
                class="cp-card grid gap-3 p-5"
            >
                <Skeleton height="0.8rem" width="55%" />
                <Skeleton height="2rem" width="35%" />
                <Skeleton height="0.7rem" width="72%" />
            </section>
        </div>
        <div class="grid gap-4 xl:grid-cols-2">
            <section
                v-for="panel in 2"
                :key="panel"
                class="cp-card grid min-h-64 gap-4 p-5"
            >
                <Skeleton height="1.1rem" width="38%" />
                <Skeleton
                    v-for="row in 4"
                    :key="row"
                    height="2.5rem"
                    width="100%"
                />
            </section>
        </div>
    </div>

    <div v-else-if="variant === 'files'" class="grid gap-6">
        <section class="cp-card grid gap-4 p-5">
            <Skeleton height="1.1rem" width="12rem" />
            <div class="flex flex-wrap gap-3">
                <Skeleton height="2.5rem" width="min(22rem, 100%)" />
                <Skeleton height="2.5rem" width="9rem" />
            </div>
        </section>
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <section
                v-for="file in 6"
                :key="file"
                class="cp-card grid gap-4 p-5"
            >
                <Skeleton height="7rem" width="100%" />
                <Skeleton height="0.9rem" width="72%" />
                <Skeleton height="0.7rem" width="45%" />
            </section>
        </div>
    </div>

    <div v-else aria-live="polite" class="grid gap-4" role="status">
        <section class="cp-card grid min-h-24 place-items-center gap-3 p-5">
            <Skeleton height="1rem" width="12rem" />
        </section>
        <section class="cp-card grid gap-3 p-5">
            <Skeleton height="1rem" width="42%" />
            <Skeleton height="0.75rem" width="68%" />
            <Skeleton height="0.75rem" width="58%" />
        </section>
    </div>
</template>
