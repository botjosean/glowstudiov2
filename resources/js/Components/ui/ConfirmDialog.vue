<script setup>
import { Check, Ban } from '@lucide/vue';
import BottomSheet from './BottomSheet.vue';

const props = defineProps({
    title: { type: String, required: true },
    body: { type: String, default: '' },
    detail: { type: String, default: '' },
    confirmLabel: { type: String, default: '' },
    cancelLabel: { type: String, default: '' },
    variant: { type: String, default: 'danger' }, // danger | primary
    processing: { type: Boolean, default: false },
});

const open = defineModel({ type: Boolean, default: false });
const emit = defineEmits(['confirm']);
</script>

<template>
    <BottomSheet v-model="open">
        <div class="mb-1.5 text-lg font-extrabold text-[var(--text-strong)]">{{ title }}</div>
        <p v-if="body" class="mb-1.5 text-[13px] font-medium leading-relaxed text-[var(--text-body)]">{{ body }}</p>
        <p v-if="detail" class="mb-5 text-xs font-semibold text-[var(--text-faint)]">{{ detail }}</p>
        <div v-else class="mb-5" />

        <div class="flex gap-3">
            <button
                type="button"
                :disabled="processing"
                class="w-2/5 rounded-xl bg-[var(--surface-mute)] py-3.5 text-sm font-bold text-[var(--text-body)] hover:bg-[var(--border-strong)] disabled:cursor-not-allowed disabled:opacity-60"
                @click="open = false"
            >
                {{ cancelLabel || $t('common.cancel') }}
            </button>
            <button
                type="button"
                :disabled="processing"
                class="flex w-3/5 items-center justify-center gap-1.5 rounded-xl py-3.5 text-sm font-bold disabled:cursor-not-allowed disabled:opacity-60"
                :class="
                    variant === 'danger'
                        ? 'border border-[var(--danger-border)] text-[var(--danger)] hover:bg-[var(--danger-hover)]'
                        : 'bg-[var(--btn-green)] text-white hover:bg-[var(--btn-green-hover)]'
                "
                @click="emit('confirm')"
            >
                <Ban v-if="variant === 'danger'" :size="15" />
                <Check v-else :size="15" />
                {{ confirmLabel || $t('common.confirm') }}
            </button>
        </div>
    </BottomSheet>
</template>
