<script setup lang="ts">
import { Head } from '@inertiajs/vue3'

import AppLayout from '@core-panel/layouts/AppLayout.vue'
import ApiTokenManager from '@/pages/Admin/ApiTokens/components/ApiTokenManager.vue'
import type { ApiTokenRecord, FormOptionRecord } from '@core-panel/types/core-panel'

const props = defineProps<{
    abilities: string[] | FormOptionRecord[]
    tokens: ApiTokenRecord[]
}>()
</script>

<template>
    <AppLayout>
        <Head :title="$t('page-api-tokens.title')" />

        <div class="grid gap-6 px-4 py-8">
            <ApiTokenManager
                :abilities="
                    props.abilities.map((ability) =>
                        typeof ability === 'string'
                            ? { label: ability, value: ability }
                            : ability,
                    )
                "
                can-create
                can-delete
                :tokens="props.tokens"
            />
        </div>
    </AppLayout>
</template>
