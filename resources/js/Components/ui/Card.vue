<script setup>
defineProps({
    padded: { type: Boolean, default: true },
});
</script>

<template>
    <div
        class="rounded-2xl border border-[var(--surface-mute)] bg-[var(--surface)] shadow-[0_1px_2px_rgba(0,0,0,0.04)]"
        :class="padded && 'p-4'"
    >
        <slot />
    </div>
</template>
