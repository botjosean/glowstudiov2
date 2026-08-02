<script setup>
import { ChevronDown } from '@lucide/vue';

defineProps({
    label: { type: String, default: '' },
    options: { type: Array, default: () => [] }, // [{ value, label }]
});

const model = defineModel({ type: [String, Number], default: '' });
</script>

<template>
    <label class="flex flex-col gap-2">
        <span v-if="label" class="text-[11px] font-bold uppercase tracking-wider text-[var(--text-faint)]">{{
            label
        }}</span>
        <div class="relative">
            <select
                v-model="model"
                class="w-full appearance-none rounded-xl border-[1.5px] border-[var(--border-strong)] bg-[var(--surface-alt)] px-4 py-3.5 pr-10 text-sm font-bold text-[var(--text-strong)] focus:border-[var(--green-border)] focus:outline-none"
            >
                <option v-for="opt in options" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
            </select>
            <ChevronDown
                :size="16"
                class="pointer-events-none absolute right-3.5 top-1/2 -translate-y-1/2 text-[var(--text-faint)]"
            />
        </div>
    </label>
</template>
