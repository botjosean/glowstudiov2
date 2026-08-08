<script setup>
import { computed } from 'vue';
import { ChevronDown } from '@lucide/vue';

/**
 * Outlined select matching OutlinedInput. A select always has a value here,
 * so the label lives permanently in the border notch instead of floating.
 * Options accept the same shapes as ui/Select.vue: a flat [{ value, label }]
 * list or a grouped [{ label, options }] one, where a group with an empty
 * label renders its options outside any <optgroup>.
 */
defineOptions({ inheritAttrs: false });

const props = defineProps({
    label: { type: String, required: true },
    id: { type: String, required: true },
    options: { type: Array, default: () => [] },
    error: { type: String, default: '' },
});

const model = defineModel({ type: [String, Number], default: '' });

const groups = computed(() => (props.options.some((option) => Array.isArray(option.options))
    ? props.options
    : [{ label: '', options: props.options }]));
</script>

<template>
    <div>
        <div class="relative">
            <select
                :id="id"
                v-model="model"
                v-bind="$attrs"
                :aria-invalid="error ? 'true' : undefined"
                :aria-describedby="error ? `${id}-error` : undefined"
                class="peer w-full appearance-none rounded-xl border bg-[var(--surface)] px-4 py-[15px] pr-10 text-[15px] font-semibold text-[var(--text-strong)] transition-[border-color,box-shadow] duration-150 focus:border-[var(--text-strong)] focus:shadow-[inset_0_0_0_1px_var(--text-strong)] focus-visible:outline-none"
                :class="error ? 'border-[var(--danger)]' : 'border-[var(--border-strong)]'"
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
            <label
                :for="id"
                class="pointer-events-none absolute left-3 top-0 -translate-y-1/2 bg-[var(--surface)] px-1 text-[12px] font-medium text-[var(--text-mute)] transition-colors duration-150 peer-focus:text-[var(--text-strong)]"
                :class="error && 'text-[var(--danger)] peer-focus:text-[var(--danger)]'"
            >
                {{ label }}
            </label>
            <ChevronDown
                :size="16"
                class="pointer-events-none absolute right-3.5 top-1/2 -translate-y-1/2 text-[var(--text-faint)]"
            />
        </div>
        <p v-if="error" :id="`${id}-error`" class="mt-1.5 text-[13px] font-normal text-[var(--danger)]">{{ error }}</p>
    </div>
</template>
