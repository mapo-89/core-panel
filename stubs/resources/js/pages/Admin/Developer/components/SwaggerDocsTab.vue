<script setup lang="ts">
defineProps<{
    docsUrl: string
}>()
</script>

<template>
    <div class="cp-section cp-section--compact">
        <div class="cp-section__header">
            <div>
                <h2 class="cp-section__title">
                    {{ $t('page-developer.tabs.docs') }}
                </h2>
                <p class="cp-section__description">
                    {{ $t('page-developer.docs.description') }}
                </p>
            </div>

            <Button
                as="a"
                :href="docsUrl"
                icon="pi pi-external-link"
                outlined
                rel="noopener noreferrer"
                target="_blank"
                :label="$t('page-developer.actions.open_docs')"
            />
        </div>

        <div class="cp-section__body">
            <div class="cp-developer-docs">
                <iframe
                    class="cp-developer-docs__frame"
                    :src="docsUrl"
                    :title="$t('page-developer.tabs.docs')"
                />
            </div>
        </div>
    </div>
</template>
