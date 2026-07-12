<script setup lang="ts">
import { computed } from 'vue'

import UserAvatar from '@core-panel/components/ui/UserAvatar.vue'
import ProfileAvatarUpload from '@/pages/Admin/Settings/components/ProfileAvatarUpload.vue'
import type { UserCapabilities, UserRecord } from '@core-panel/types/core-panel'

const props = defineProps<{
    capabilities: UserCapabilities
    roleLabels: Record<string, string>
    user: UserRecord
}>()

const initials = computed(() => props.user.name.slice(0, 2).toUpperCase())
const localeLabel = computed(() => props.user.locale ?? '—')
const visibleRoleLabels = computed(() =>
    props.user.roles
        .map((role) => props.roleLabels[role] ?? role)
        .filter(
            (role, index, roles) =>
                role !== '' && roles.indexOf(role) === index,
        ),
)
const statusLabel = computed(() => {
    if (props.user.deletedAt) {
        return 'common.ui.deleted'
    }

    return props.user.status === 'blocked'
        ? 'common.ui.blocked'
        : props.user.status === 'inactive'
          ? 'common.ui.inactive'
          : 'common.ui.active'
})
const statusSeverity = computed<'danger' | 'secondary' | 'success' | 'warn'>(
    () => {
        if (props.user.deletedAt) {
            return 'danger'
        }

        return props.user.status === 'blocked'
            ? 'danger'
            : props.user.status === 'inactive'
              ? 'warn'
              : 'success'
    },
)
const createdAtLabel = computed(() => {
    if (!props.user.createdAt) {
        return null
    }

    const normalizedValue = props.user.createdAt.includes('T')
        ? props.user.createdAt
        : props.user.createdAt.replace(' ', 'T')
    const parsedDate = new Date(normalizedValue)

    if (Number.isNaN(parsedDate.getTime())) {
        return props.user.createdAt
    }

    const locale =
        props.user.locale || document.documentElement.lang || undefined

    return new Intl.DateTimeFormat(locale, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(parsedDate)
})
</script>

<template>
    <div class="cp-user-profile__workspace">
        <section class="cp-card grid gap-5 p-6">
            <div class="cp-user-profile__section-copy">
                <h2 class="text-lg font-semibold text-[var(--cp-text-primary)]">
                    {{ $t('page-users.tab_overview') }}
                </h2>
                <p class="text-sm text-[var(--cp-text-muted)]">
                    {{ $t('page-users.show_description') }}
                </p>
            </div>

            <section class="cp-user-profile__hero">
                <div class="cp-user-profile__identity">
                    <div class="flex items-center gap-4 min-w-0">
                        <UserAvatar
                            :avatar-url="user.avatarUrl"
                            :initials="initials"
                            :presence-last-seen-at="
                                user.presenceLastSeenAt ?? null
                            "
                            :presence-status="user.presenceStatus ?? 'offline'"
                            :user-id="user.id"
                            size="lg"
                        />

                        <div class="cp-user-profile__copy">
                            <strong class="cp-user-profile__name">
                                {{ user.name }}
                            </strong>
                            <span class="cp-user-profile__email">
                                {{ user.email }}
                            </span>
                        </div>
                    </div>

                    <div class="cp-user-profile__summary-status">
                        <Tag
                            :severity="statusSeverity"
                            :value="$t(statusLabel)"
                        />
                    </div>
                </div>

                <div class="cp-user-profile__tags">
                    <Tag
                        v-for="roleLabel in visibleRoleLabels"
                        :key="roleLabel"
                        severity="secondary"
                        :value="roleLabel"
                    />
                    <Tag v-if="user.locale" :value="localeLabel" />
                </div>

                <div class="cp-user-profile__hero-grid">
                    <div class="cp-user-profile__fact">
                        <span>{{ $t('page-users.email_verified') }}</span>
                        <strong>{{
                            user.emailVerifiedAt
                                ? $t('common.ui.yes')
                                : $t('common.ui.no')
                        }}</strong>
                    </div>
                    <div class="cp-user-profile__fact">
                        <span>{{ $t('common.auth.security') }}</span>
                        <strong>{{
                            user.twoFactorEnabled
                                ? $t('common.ui.two_factor_enabled')
                                : $t('common.ui.two_factor_disabled')
                        }}</strong>
                    </div>
                    <div class="cp-user-profile__fact">
                        <span>{{
                            $t('table-builder.columns.created_at')
                        }}</span>
                        <strong>{{
                            createdAtLabel ?? $t('common.ui.never')
                        }}</strong>
                    </div>
                </div>
            </section>
        </section>

        <section
            v-if="capabilities.supportsMedia"
            class="cp-card grid gap-5 p-6"
        >
            <ProfileAvatarUpload
                :avatar-url="user.avatarUrl"
                :initials="initials"
                :presence-status="user.presenceStatus"
                :reload-keys="['auth', 'flash', 'user']"
                :user-id="user.id"
            />
        </section>
    </div>
</template>
