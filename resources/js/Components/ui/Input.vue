<script setup>
defineProps({
    label: { type: String, default: '' },
    type: { type: String, default: 'text' },
    placeholder: { type: String, default: '' },
});

const model = defineModel({ type: [String, Number], default: '' });
</script>

<template>
    <label class="flex flex-col gap-2">
        <span v-if="label" class="text-[11px] font-bold uppercase tracking-wider text-[var(--text-faint)]">{{
            label
        }}</span>
        <input
            v-model="model"
            :type="type"
            :placeholder="placeholder"
            class="w-full rounded-xl border-[1.5px] border-[var(--border-strong)] bg-[var(--surface-alt)] px-4 py-3.5 text-sm font-semibold text-[var(--text-strong)] placeholder:font-medium placeholder:text-[var(--text-faint)] focus:border-[var(--green-border)] focus:outline-none"
        />
    </label>
</template>
