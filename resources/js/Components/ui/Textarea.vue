<script setup>
defineProps({
    label: { type: String, default: '' },
    placeholder: { type: String, default: '' },
    rows: { type: Number, default: 4 },
});

const model = defineModel({ type: String, default: '' });
</script>

<template>
    <label class="flex flex-col gap-2">
        <span v-if="label" class="text-[11px] font-bold uppercase tracking-wider text-[var(--text-faint)]">{{
            label
        }}</span>
        <textarea
            v-model="model"
            :rows="rows"
            :placeholder="placeholder"
            class="w-full resize-none rounded-xl border-[1.5px] border-[var(--border-strong)] bg-[var(--surface-alt)] px-4 py-3.5 text-sm font-medium text-[var(--text-strong)] placeholder:text-[var(--text-faint)] focus:border-[var(--green-border)] focus:outline-none"
        />
    </label>
</template>
