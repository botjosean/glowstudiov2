<script setup>
const props = defineProps({
    variant: { type: String, default: 'primary' }, // primary | green | outline | ghost | danger
    block: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    type: { type: String, default: 'button' },
});

const variants = {
    primary: 'bg-[var(--btn-bg)] text-white hover:bg-[var(--btn-hover)]',
    green: 'bg-[var(--btn-green)] text-white hover:bg-[var(--btn-green-hover)]',
    outline:
        'bg-[var(--surface)] text-[var(--green-text-strong)] border-[1.5px] border-[var(--green-border)] hover:bg-[var(--green-soft)]',
    ghost: 'bg-transparent text-[var(--text-strong)] border border-transparent hover:border-[var(--border-strong)]',
    danger: 'bg-transparent text-[var(--danger)] border-[1.5px] border-[var(--danger-border)] hover:bg-[var(--danger-hover)]',
};
</script>

<template>
    <button
        :type="type"
        :disabled="disabled"
        class="inline-flex items-center justify-center gap-2 rounded-xl px-5 py-3 text-sm font-bold transition-colors disabled:cursor-not-allowed disabled:opacity-50"
        :class="[variants[props.variant], block && 'w-full']"
    >
        <slot />
    </button>
</template>
