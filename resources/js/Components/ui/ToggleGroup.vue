<script setup>
defineProps({
    options: { type: Array, required: true }, // [{ value, label, hint }]
    disabled: { type: Boolean, default: false },
});

const model = defineModel({ type: [String, Number], required: true });
</script>

<template>
    <div
        class="grid gap-1 rounded-xl bg-[var(--surface-mute)] p-1"
        :style="{ gridTemplateColumns: `repeat(${options.length}, 1fr)` }"
    >
        <button
            v-for="opt in options"
            :key="opt.value"
            type="button"
            :disabled="disabled"
            class="flex flex-col items-center gap-0.5 rounded-lg px-0 py-2.5 disabled:cursor-not-allowed disabled:opacity-60"
            :class="
                model === opt.value
                    ? 'bg-[var(--surface)] shadow-[0_1px_2px_rgba(0,0,0,0.06)]'
                    : ''
            "
            @click="model = opt.value"
        >
            <span
                class="text-xs font-extrabold"
                :class="model === opt.value ? 'text-[var(--text-strong)]' : 'text-[var(--text-mute)] font-bold'"
                >{{ opt.label }}</span
            >
            <span v-if="opt.hint" class="text-[10px] font-semibold text-[var(--text-faint)]">{{ opt.hint }}</span>
        </button>
    </div>
</template>
