<script setup>
defineProps({
    text: { type: String, required: true },
});
</script>

<template>
    <div class="flex items-center justify-between">
        <span class="text-[11px] font-bold uppercase tracking-widest text-[var(--text-faint)]">{{ text }}</span>
        <slot name="action" />
    </div>
</template>
