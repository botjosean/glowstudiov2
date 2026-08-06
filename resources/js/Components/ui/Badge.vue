<script setup>
const props = defineProps({
    variant: { type: String, default: 'confirmed' }, // confirmed | pending | cancelled | closed
    uppercase: { type: Boolean, default: false },
});

const variants = {
    confirmed: 'bg-[var(--green-soft)] border-[var(--green-border)] text-[var(--green-text)]',
    pending: 'bg-[var(--amber-soft)] border-[var(--amber-border)] text-[var(--amber-text)]',
    cancelled: 'bg-[var(--danger-hover)] border-[var(--danger-border)] text-[var(--danger)]',
    closed: 'bg-[var(--surface-mute)] border-[var(--border-strong)] text-[var(--text-mute)]',
};
</script>

<template>
    <span
        class="inline-flex items-center gap-1 rounded-full border px-2.5 py-1 text-[11px] font-medium"
        :class="[variants[props.variant], uppercase && 'uppercase tracking-wider']"
    >
        <slot />
    </span>
</template>
