<script setup>
defineProps({
    value: { type: [String, Number], required: true },
    label: { type: String, required: true },
});
</script>

<template>
    <div class="rounded-2xl border border-[var(--surface-mute)] bg-[var(--surface-alt)] p-3 text-center">
        <div class="text-lg font-bold text-[var(--text-strong)]">{{ value }}</div>
        <div class="mt-0.5 text-[11px] font-medium uppercase tracking-wider text-[var(--text-mute)]">{{ label }}</div>
    </div>
</template>
