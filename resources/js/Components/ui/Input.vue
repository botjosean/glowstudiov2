<script setup>
defineProps({
    label: { type: String, default: '' },
    type: { type: String, default: 'text' },
    placeholder: { type: String, default: '' },
    disabled: { type: Boolean, default: false },
});

const model = defineModel({ type: [String, Number], default: '' });
</script>

<template>
    <label class="flex flex-col gap-2">
        <span v-if="label" class="text-[13px] font-medium text-[var(--text-mute)]">{{
            label
        }}</span>
        <input
            v-model="model"
            :type="type"
            :placeholder="placeholder"
            :disabled="disabled"
            class="w-full rounded-xl border border-[var(--border-strong)] bg-[var(--surface)] px-4 py-3.5 text-[15px] font-normal text-[var(--text-strong)] placeholder:text-[var(--text-faint)] focus:border-[var(--text-strong)] focus:outline-none disabled:cursor-not-allowed disabled:opacity-60"
        />
    </label>
</template>
