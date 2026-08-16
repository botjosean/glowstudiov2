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
        <circle cx="9.2" cy="8.2" r="3.4" />
        <path d="M3.5 20c.6-3.4 3-5.2 5.7-5.2s5.1 1.8 5.7 5.2" />
        <path d="M15.4 5.4a3.4 3.4 0 0 1 0 5.6" />
        <path d="M17.6 14.9c1.9.7 3.1 2.2 3.4 4.6" />
    </svg>
</template>
