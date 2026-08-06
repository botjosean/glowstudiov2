<script setup>
import { Check, ChevronDown } from '@lucide/vue';

defineProps({
    title: { type: String, required: true },
    hint: { type: String, default: '' },
    done: { type: Boolean, default: false },
});

const open = defineModel({ type: Boolean, default: false });
</script>

<template>
    <section class="overflow-hidden rounded-2xl border border-[var(--border-strong)] bg-[var(--surface)]">
        <button
            type="button"
            class="flex w-full items-center gap-3 px-4 py-4 text-left"
            :aria-expanded="open"
            @click="open = !open"
        >
            <span
                class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full"
                :class="done ? 'bg-[var(--btn-green)]' : 'border-[1.5px] border-[var(--border-strong)]'"
            >
                <Check v-if="done" :size="13" class="text-white" />
            </span>
            <span class="min-w-0 flex-1">
                <span class="block text-[15px] font-semibold text-[var(--text-strong)]">{{ title }}</span>
                <span v-if="hint && !open" class="mt-0.5 block truncate text-[13px] font-normal text-[var(--text-mute)]">{{
                    hint
                }}</span>
            </span>
            <ChevronDown
                :size="18"
                class="shrink-0 text-[var(--text-faint)] transition-transform duration-200"
                :class="open && 'rotate-180'"
            />
        </button>
        <div v-show="open" class="border-t border-[var(--surface-mute)] px-4 pb-5 pt-4">
            <slot />
        </div>
    </section>
</template>
