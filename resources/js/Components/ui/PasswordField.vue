<script setup>
import { ref, computed } from 'vue';
import { Eye, EyeOff, Check } from '@lucide/vue';
import { useI18n } from 'vue-i18n';

defineProps({
    label: { type: String, default: '' },
    // Pass a boolean to turn this into a "confirm password" field: the eye
    // toggle is replaced by a green check once it matches the other field.
    // Leave unset for a plain password field (SignIn) — null is falsy, so
    // the toggle just stays a toggle.
    matched: { type: Boolean, default: null },
});

const model = defineModel({ type: String, default: '' });

const { t } = useI18n();
const visible = ref(false);
const type = computed(() => (visible.value ? 'text' : 'password'));
</script>

<template>
    <label class="flex flex-col gap-2">
        <span v-if="label" class="text-[13px] font-medium text-[var(--text-mute)]">{{ label }}</span>
        <div
            class="flex items-center justify-between rounded-xl border-[1.5px] px-4 py-3.5"
            :class="
                matched
                    ? 'border-[var(--green-border)] bg-[var(--green-soft)]'
                    : 'border-[var(--border-strong)] bg-[var(--surface-alt)]'
            "
        >
            <input
                v-model="model"
                :type="type"
                class="w-full bg-transparent text-base tracking-[3px] text-[var(--text-strong)] focus:outline-none"
            />
            <Check v-if="matched" :size="18" class="shrink-0 text-[var(--green-text)]" />
            <button
                v-else
                type="button"
                class="shrink-0 text-[var(--text-faint)]"
                :aria-label="visible ? t('common.hidePassword') : t('common.showPassword')"
                @click="visible = !visible"
            >
                <component :is="visible ? EyeOff : Eye" :size="18" />
            </button>
        </div>
    </label>
</template>
