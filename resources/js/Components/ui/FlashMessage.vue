<script setup>
import { ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { CircleCheck, TriangleAlert, CircleX, X } from '@lucide/vue';
import { useI18n } from 'vue-i18n';

const page = usePage();
const { t } = useI18n();

const visible = ref(false);
const variant = ref('success');
const message = ref('');

const variants = {
    success: {
        classes: 'border-[var(--green-border)] bg-[var(--green-soft)] text-[var(--green-deep)]',
        icon: CircleCheck,
        role: 'status',
    },
    warning: {
        classes: 'border-[var(--amber-border)] bg-[var(--amber-soft)] text-[var(--amber-text)]',
        icon: TriangleAlert,
        role: 'status',
    },
    error: {
        classes: 'border-[var(--danger-border)] bg-[var(--danger-hover)] text-[var(--danger)]',
        icon: CircleX,
        role: 'alert',
    },
};

let dismissTimer = null;

// Watch the flash OBJECT, not e.g. page.props.flash.success — Inertia
// replaces props wholesale per response, so watching the leaf value would
// silently swallow two identical consecutive messages (same value, no
// change fires).
watch(
    () => page.props.flash,
    (flash) => {
        const [key, value] = ['error', 'warning', 'success']
            .map((k) => [k, flash?.[k]])
            .find(([, v]) => v) ?? [];

        if (!key) return;

        variant.value = key;
        message.value = typeof value === 'string' ? t(value) : t(value.key, value);
        visible.value = true;

        clearTimeout(dismissTimer);
        dismissTimer = setTimeout(() => {
            visible.value = false;
        }, 4000);
    },
    { immediate: true },
);

function dismiss() {
    clearTimeout(dismissTimer);
    visible.value = false;
}
</script>

<template>
    <Transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="opacity-0 -translate-y-2"
        leave-active-class="transition duration-150 ease-in motion-reduce:duration-0"
        leave-to-class="opacity-0"
    >
        <div
            v-if="visible"
            :role="variants[variant].role"
            aria-live="polite"
            class="fixed left-1/2 top-3 z-[60] w-[calc(100%-2rem)] max-w-[448px] -translate-x-1/2"
        >
            <div
                class="flex items-start gap-2.5 rounded-2xl border p-4 text-[13px] font-normal leading-relaxed shadow-[0_12px_32px_rgba(15,23,42,0.18)]"
                :class="variants[variant].classes"
            >
                <component :is="variants[variant].icon" :size="16" class="mt-0.5 shrink-0" />
                <span class="flex-1">{{ message }}</span>
                <button type="button" class="shrink-0 opacity-70 hover:opacity-100" @click="dismiss">
                    <X :size="14" />
                </button>
            </div>
        </div>
    </Transition>
</template>
