<script setup>
import { computed } from 'vue';
import { ChevronDown } from '@lucide/vue';

const props = defineProps({
    label: { type: String, default: '' },
    // Either a flat [{ value, label }] list or a grouped
    // [{ label, options: [{ value, label }] }] one. A group with an empty
    // label renders its options bare, outside any <optgroup>.
    options: { type: Array, default: () => [] },
});

const model = defineModel({ type: [String, Number], default: '' });

const groups = computed(() => (props.options.some((option) => Array.isArray(option.options))
    ? props.options
    : [{ label: '', options: props.options }]));
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
                <template v-for="(group, index) in groups" :key="index">
                    <optgroup v-if="group.label" :label="group.label">
                        <option v-for="opt in group.options" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                    </optgroup>
                    <template v-else>
                        <option v-for="opt in group.options" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                    </template>
                </template>
            </select>
            <ChevronDown
                :size="16"
                class="pointer-events-none absolute right-3.5 top-1/2 -translate-y-1/2 text-[var(--text-faint)]"
            />
        </div>
    </label>
</template>
