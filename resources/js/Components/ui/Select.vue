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
        <span v-if="label" class="text-[13px] font-medium text-[var(--text-mute)]">{{
            label
        }}</span>
        <div class="relative">
            <select
                v-model="model"
                class="w-full appearance-none rounded-xl border border-[var(--border-strong)] bg-[var(--surface)] px-4 py-3.5 pr-10 text-[15px] font-normal text-[var(--text-strong)] focus:border-[var(--text-strong)] focus:outline-none"
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
