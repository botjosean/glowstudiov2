<script setup>
defineProps({
    active: { type: Boolean, default: false },
});

defineEmits(['click']);
</script>

<template>
    <button
        type="button"
        class="inline-flex shrink-0 items-center gap-1.5 rounded-full border px-4 py-2 text-xs font-bold transition-colors"
        :class="
            active
                ? 'border-transparent bg-[var(--chip-bg)] text-[var(--chip-fg)]'
                : 'border-[var(--border-strong)] bg-[var(--surface)] text-[var(--text-body)] hover:border-[var(--text-faint)]'
        "
        @click="$emit('click')"
    >
        <slot />
    </button>
</template>
