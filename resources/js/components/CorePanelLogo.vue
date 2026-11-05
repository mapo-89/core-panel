<script setup lang="ts">
defineProps<{
    title?: string
}>()
</script>

<template>
    <span
        :aria-label="title || undefined"
        :aria-hidden="title ? undefined : true"
        :role="title ? 'img' : undefined"
        class="core-panel-logo"
    >
        <svg
            class="core-panel-logo__icon"
            viewBox="0 0 304 264"
            fill="currentColor"
            aria-hidden="true"
            focusable="false"
        >
            <path
                d="M174 20H104C57.6 20 20 57.6 20 104S57.6 188 104 188H116Q124 188 124 180V148H104C79.7 148 60 128.3 60 104S79.7 60 104 60H174Q182 60 182 52V28Q182 20 174 20Z"
            />
            <path
                d="M156 76H222C255.1 76 282 102.9 282 136S255.1 196 222 196H184V230Q184 240 174 240H146Q136 240 136 230V166Q136 156 146 156H222C233 156 242 147 242 136S233 116 222 116H136V96Q136 76 156 76Z"
            />
        </svg>
    </span>
</template>
