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
        <path d="M5.5 3.5h13V19l-2.2-1.6L14.2 19 12 17.4 9.8 19l-2.1-1.6L5.5 19V3.5Z" />
        <path d="M9 8.2h6" />
        <path d="M9 11.8h4" />
    </svg>
</template>
