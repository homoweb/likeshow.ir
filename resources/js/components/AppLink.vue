<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{ href: string }>();

/**
 * Non same-origin targets (e.g. external links) must use plain browser
 * navigation; Inertia's <Link> can only perform same-origin XHR visits
 * and fails with a network error otherwise.
 */
const isSameOrigin = computed((): boolean => {
    try {
        return (
            new URL(props.href, window.location.href).origin ===
            window.location.origin
        );
    } catch {
        return false;
    }
});
</script>

<template>
    <Link v-if="isSameOrigin" :href="href">
        <slot />
    </Link>
    <a v-else :href="href">
        <slot />
    </a>
</template>
