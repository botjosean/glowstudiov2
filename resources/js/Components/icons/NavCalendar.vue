<script setup>
/**
 * The bottom bar's own icon set: drawn by hand after Booksy's, rounded and
 * a touch warmer than a stock library. All four share the same 24-grid,
 * 1.8 stroke, round caps, and inherit currentColor from the bar.
 */
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
        <rect x="3.2" y="5" width="17.6" height="16" rx="4" />
        <path d="M8 3v3.5M16 3v3.5" />
        <circle cx="8.2" cy="12" r="0.4" fill="currentColor" />
        <circle cx="12" cy="12" r="0.4" fill="currentColor" />
        <circle cx="15.8" cy="12" r="0.4" fill="currentColor" />
        <circle cx="8.2" cy="16" r="0.4" fill="currentColor" />
        <circle cx="12" cy="16" r="0.4" fill="currentColor" />
        <circle cx="15.8" cy="16" r="0.4" fill="currentColor" />
    </svg>
</template>
