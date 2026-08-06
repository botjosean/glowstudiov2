<script setup>
defineProps({
    text: { type: String, required: true },
});
</script>

<template>
    <div class="flex items-center justify-between">
        <span class="text-[13px] font-medium text-[var(--text-mute)]">{{ text }}</span>
        <slot name="action" />
    </div>
</template>
