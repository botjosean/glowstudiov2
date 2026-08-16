<script setup>
defineProps({
    size: { type: Number, default: 22 },
});
</script>

<template>
    <svg
        :width="size"
        :height="size"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="1.8"
        stroke-linecap="round"
        stroke-linejoin="round"
        aria-hidden="true"
    >
        <!-- La tiendita de Booksy: toldo festoneado y puerta al frente. -->
        <path d="M4 9.5 5.2 4h13.6L20 9.5" />
        <path d="M4 9.5a2.6 2.6 0 0 0 5.3 0 2.68 2.68 0 0 0 5.4 0 2.6 2.6 0 0 0 5.3 0" />
        <path d="M5.2 12.5V20h13.6v-7.5" />
        <path d="M10 20v-4.6a2 2 0 0 1 4 0V20" />
    </svg>
</template>
