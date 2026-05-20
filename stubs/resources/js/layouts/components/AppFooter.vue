<script setup lang="ts">
const currentYear = new Date().getFullYear()
</script>

<template>
    <footer class="app-footer px-5 md:px-8 lg:px-10">
        <div class="app-footer__surface">
            <div class="app-footer__brand-group">
                <div class="app-footer__main"></div>
            </div>

            <div class="app-footer__meta">
                <span class="app-footer__copy"> &copy; {{ currentYear }} </span>
            </div>
        </div>
    </footer>
</template>
