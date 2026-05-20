<script setup lang="ts">
import { computed } from 'vue'

import type { DataTablePagination } from './types'

const props = defineProps<{
    pagination: DataTablePagination
}>()

const emit = defineEmits<{
    page: [event: { page: number; rows: number }]
}>()

const resultsLabel = computed(() => ({
    from: String(props.pagination.from ?? 0),
    to: String(props.pagination.to ?? 0),
    total: String(props.pagination.total),
}))
</script>

<template>
    <div class="cp-datatable-pagination">
        <p class="cp-datatable-pagination__summary">
            {{
                $t('table-builder.labels.results', {
                    from: resultsLabel.from,
                    to: resultsLabel.to,
                    total: resultsLabel.total,
                })
            }}
        </p>
        <Paginator
            :first="Math.max(0, (pagination.page - 1) * pagination.perPage)"
            :rows="pagination.perPage"
            :total-records="pagination.total"
            template="PrevPageLink PageLinks NextPageLink RowsPerPageDropdown"
            :rows-per-page-options="[10, 20, 50, 100]"
            @page="emit('page', $event)"
        />
    </div>
</template>
