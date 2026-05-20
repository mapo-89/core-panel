<script setup lang="ts">
import { computed } from 'vue'
import { Deferred, Head } from '@inertiajs/vue3'
import { trans } from 'laravel-vue-i18n'

import AppLayout from '@/layouts/AppLayout.vue'

type DashboardSummary = {
    activeUsers: number
    failedJobs: number
    pendingJobs: number
    totalUsers: number
}

type SystemHealth = {
    appVersion: string
    databaseStatus: string
    laravelVersion: string
    octaneStatus: string
    phpVersion: string
    queueStatus: string
    redisStatus: string
    storageStatus: string
}

type ActivityItem = {
    createdAt: string | null
    description: string
    event: string
    id: string
}

const props = defineProps<{
    dashboard: DashboardSummary
    labels: Record<string, string>
    recentActivities?: ActivityItem[]
    systemHealth?: SystemHealth
}>()

const statCards = computed(() => [
    {
        key: 'totalUsers',
        label: props.labels.totalUsers,
        value: props.dashboard.totalUsers,
    },
    {
        key: 'activeUsers',
        label: props.labels.activeUsers,
        value: props.dashboard.activeUsers,
    },
    {
        key: 'pendingJobs',
        label: props.labels.pendingJobs,
        value: props.dashboard.pendingJobs,
    },
    {
        key: 'failedJobs',
        label: props.labels.failedJobs,
        value: props.dashboard.failedJobs,
    },
])

const healthCards = computed(() => {
    if (!props.systemHealth) {
        return []
    }

    return [
        {
            key: 'queue',
            label: trans('dashboard.health_queue'),
            value: props.systemHealth.queueStatus,
        },
        {
            key: 'redis',
            label: trans('dashboard.health_redis'),
            value: props.systemHealth.redisStatus,
        },
        {
            key: 'database',
            label: trans('dashboard.health_database'),
            value: props.systemHealth.databaseStatus,
        },
        {
            key: 'storage',
            label: trans('dashboard.health_storage'),
            value: props.systemHealth.storageStatus,
        },
        {
            key: 'octane',
            label: trans('dashboard.health_octane'),
            value: props.systemHealth.octaneStatus,
        },
    ]
})

const guidanceCards = computed(() => [
    {
        key: 'users',
        label: trans('dashboard.users'),
        summary: trans('dashboard.guidance_users_summary'),
        tone: 'secondary' as const,
        value: props.dashboard.totalUsers,
    },
    {
        key: 'queue',
        label: trans('dashboard.health_queue'),
        summary: trans('dashboard.guidance_queue_summary'),
        tone:
            props.dashboard.failedJobs > 0
                ? ('danger' as const)
                : props.dashboard.pendingJobs > 0
                  ? ('warn' as const)
                  : ('success' as const),
        value: props.dashboard.pendingJobs,
    },
    {
        key: 'api',
        label: trans('dashboard.show_api_tokens'),
        summary: trans('dashboard.guidance_api_summary'),
        tone: 'info' as const,
        value: 0,
    },
])

function statusTone(
    status: string,
): 'contrast' | 'danger' | 'info' | 'success' | 'warn' {
    switch (status) {
        case 'enabled':
        case 'ok':
            return 'success'
        case 'degraded':
            return 'warn'
        case 'disabled':
            return 'contrast'
        default:
            return 'danger'
    }
}

function translatedStatus(status: string): string {
    return trans(`dashboard.status_${status}`)
}
</script>

<template>
    <AppLayout :subtitle="labels.centralContext" :title="labels.title">
        <Head :title="labels.title" />

        <div class="grid gap-6">
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <div
                    v-for="card in statCards"
                    :key="card.key"
                    class="cp-dashboard-card"
                >
                    <div class="grid gap-2">
                        <span
                            class="text-sm font-medium text-[var(--cp-text-muted)]"
                            >{{ card.label }}</span
                        >
                        <span
                            class="text-3xl font-semibold text-[var(--cp-text-primary)]"
                            >{{ card.value }}</span
                        >
                    </div>
                </div>
            </div>

            <div
                class="grid gap-6 xl:grid-cols-[minmax(0,1.75fr)_minmax(20rem,1fr)]"
            >
                <section class="grid gap-6">
                    <div class="cp-dashboard-card">
                        <div class="mb-4 grid gap-1">
                            <h2
                                class="text-lg font-semibold text-[var(--cp-text-primary)]"
                            >
                                {{ trans('dashboard.guidance_title') }}
                            </h2>
                            <p class="text-sm text-[var(--cp-text-muted)]">
                                {{ trans('dashboard.guidance_description') }}
                            </p>
                        </div>

                        <div class="grid gap-3 md:grid-cols-3">
                            <div
                                v-for="card in guidanceCards"
                                :key="card.key"
                                class="cp-dashboard-row cp-dashboard-row--split"
                            >
                                <div class="grid gap-1">
                                    <span
                                        class="text-sm font-medium text-[var(--cp-text-primary)]"
                                    >
                                        {{ card.label }}
                                    </span>
                                    <span
                                        class="text-xs text-[var(--cp-text-muted)]"
                                    >
                                        {{ card.summary }}
                                    </span>
                                </div>

                                <Tag
                                    :severity="card.tone"
                                    :value="String(card.value)"
                                />
                            </div>
                        </div>
                    </div>

                    <div class="cp-dashboard-card">
                        <div
                            class="mb-4 flex items-center justify-between gap-3"
                        >
                            <div class="grid gap-1">
                                <h2
                                    class="text-lg font-semibold text-[var(--cp-text-primary)]"
                                >
                                    {{ labels.recentActivities }}
                                </h2>
                                <p class="text-sm text-[var(--cp-text-muted)]">
                                    {{ labels.quickActions }}
                                </p>
                            </div>
                        </div>

                        <Deferred data="recentActivities">
                            <template #fallback>
                                <div class="grid gap-3">
                                    <Skeleton
                                        v-for="index in 4"
                                        :key="index"
                                        height="4.25rem"
                                    />
                                </div>
                            </template>

                            <div
                                v-if="(recentActivities ?? []).length === 0"
                                class="cp-dashboard-card cp-dashboard-card--subtle cp-dashboard-card--empty"
                            >
                                {{ trans('dashboard.empty_activity') }}
                            </div>

                            <div v-else class="grid gap-3">
                                <div
                                    v-for="activity in recentActivities"
                                    :key="activity.id"
                                    class="cp-dashboard-row"
                                >
                                    <div
                                        class="flex flex-wrap items-center justify-between gap-3"
                                    >
                                        <span
                                            class="text-sm font-medium text-[var(--cp-text-primary)]"
                                            >{{ activity.description }}</span
                                        >
                                        <Tag
                                            :severity="statusTone('ok')"
                                            :value="activity.event"
                                        />
                                    </div>
                                    <div
                                        class="flex flex-wrap gap-3 text-xs text-[var(--cp-text-muted)]"
                                    >
                                        <span v-if="activity.createdAt">{{
                                            activity.createdAt
                                        }}</span>
                                    </div>
                                </div>
                            </div>
                        </Deferred>
                    </div>
                </section>

                <aside class="grid gap-6">
                    <div class="cp-dashboard-card">
                        <div class="grid gap-4">
                            <div
                                class="flex items-center justify-between gap-3"
                            >
                                <h2
                                    class="text-lg font-semibold text-[var(--cp-text-primary)]"
                                >
                                    {{ labels.systemHealth }}
                                </h2>
                                <div class="flex gap-2">
                                    <Tag
                                        :value="labels.pendingJobs"
                                        severity="info"
                                    />
                                    <Badge :value="dashboard.pendingJobs" />
                                </div>
                            </div>

                            <div class="grid gap-3">
                                <div
                                    class="cp-dashboard-row cp-dashboard-row--split"
                                >
                                    <span
                                        class="text-sm text-[var(--cp-text-muted)]"
                                        >{{ labels.pendingJobs }}</span
                                    >
                                    <span
                                        class="text-sm font-semibold text-[var(--cp-text-primary)]"
                                        >{{ dashboard.pendingJobs }}</span
                                    >
                                </div>
                                <div
                                    class="cp-dashboard-row cp-dashboard-row--split"
                                >
                                    <span
                                        class="text-sm text-[var(--cp-text-muted)]"
                                        >{{ labels.failedJobs }}</span
                                    >
                                    <span
                                        class="text-sm font-semibold text-[var(--cp-text-primary)]"
                                        >{{ dashboard.failedJobs }}</span
                                    >
                                </div>
                            </div>

                            <Deferred data="systemHealth">
                                <template #fallback>
                                    <div class="grid gap-3">
                                        <Skeleton
                                            v-for="index in 5"
                                            :key="index"
                                            height="3.5rem"
                                        />
                                    </div>
                                </template>

                                <div class="grid gap-3">
                                    <div
                                        v-for="card in healthCards"
                                        :key="card.key"
                                        class="cp-dashboard-row cp-dashboard-row--split"
                                    >
                                        <span
                                            class="text-sm text-[var(--cp-text-muted)]"
                                            >{{ card.label }}</span
                                        >
                                        <Tag
                                            :severity="statusTone(card.value)"
                                            :value="
                                                translatedStatus(card.value)
                                            "
                                        />
                                    </div>
                                </div>
                            </Deferred>

                            <div
                                v-if="systemHealth"
                                class="cp-dashboard-row text-sm text-[var(--cp-text-muted)]"
                            >
                                <div
                                    class="flex items-center justify-between gap-3"
                                >
                                    <span>{{
                                        trans('dashboard.health_php_version')
                                    }}</span>
                                    <span
                                        class="font-medium text-[var(--cp-text-primary)]"
                                        >{{ systemHealth.phpVersion }}</span
                                    >
                                </div>
                                <div
                                    class="flex items-center justify-between gap-3"
                                >
                                    <span>{{
                                        trans(
                                            'dashboard.health_laravel_version',
                                        )
                                    }}</span>
                                    <span
                                        class="font-medium text-[var(--cp-text-primary)]"
                                        >{{ systemHealth.laravelVersion }}</span
                                    >
                                </div>
                                <div
                                    class="flex items-center justify-between gap-3"
                                >
                                    <span>{{
                                        trans('dashboard.health_app')
                                    }}</span>
                                    <span
                                        class="font-medium text-[var(--cp-text-primary)]"
                                        >{{ systemHealth.appVersion }}</span
                                    >
                                </div>
                            </div>
                        </div>
                    </div>
                </aside>
            </div>
        </div>
    </AppLayout>
</template>
