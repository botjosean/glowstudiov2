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
        <span v-if="label" class="text-[13px] font-medium text-[var(--text-mute)]">{{
            label
        }}</span>
        <textarea
            v-model="model"
            :rows="rows"
            :placeholder="placeholder"
            class="w-full resize-none rounded-xl border border-[var(--border-strong)] bg-[var(--surface)] px-4 py-3.5 text-[15px] font-normal text-[var(--text-strong)] placeholder:text-[var(--text-faint)] focus:border-[var(--text-strong)] focus:outline-none"
        />
    </label>
</template>
