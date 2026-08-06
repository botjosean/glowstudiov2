<script setup>
import { computed, ref } from 'vue';
import { Phone, X } from '@lucide/vue';
import BottomSheet from '../ui/BottomSheet.vue';
import { usePreferences } from '../../composables/usePreferences';

const props = defineProps({
    clientName: { type: String, default: '' },
    clientPhone: { type: String, default: '' },
    phoneDigits: { type: String, default: '' },
    message: { type: String, default: '' },
});

const open = defineModel({ type: Boolean, default: false });

const { setWhatsappPrompt } = usePreferences();

// The app is US end to end (Format::usPhone, America/New_York default,
// the +1 prefix on the booking phone field) — hardcoded, not derived.
const COUNTRY_CODE = '1';

const waHref = computed(
    () => `https://wa.me/${COUNTRY_CODE}${props.phoneDigits}?text=${encodeURIComponent(props.message)}`,
);

const dontAskAgain = ref(false);

function dismiss() {
    if (dontAskAgain.value) setWhatsappPrompt('never');
    open.value = false;
}
</script>

<template>
    <BottomSheet v-model="open">
        <div class="mb-4 flex items-center justify-between">
            <div class="text-lg font-bold text-[var(--text-strong)]">{{ $t('admin.waPromptTitle') }}</div>
            <button
                type="button"
                class="flex h-8 w-8 items-center justify-center rounded-full bg-[var(--surface-mute)]"
                @click="dismiss"
            >
                <X :size="16" class="text-[var(--text-mute)]" />
            </button>
        </div>

        <p class="mb-4 text-[13px] font-medium leading-relaxed text-[var(--text-body)]">
            {{ $t('admin.waPromptBody', { name: clientName }) }}
        </p>

        <div class="mb-3 flex items-center gap-1.5 text-[13px] font-normal text-[var(--text-mute)]">
            <Phone :size="13" class="text-[var(--text-faint)]" />
            {{ clientPhone }}
        </div>

        <div class="mb-5 rounded-2xl border border-[var(--surface-mute)] bg-[var(--surface-alt)] p-4">
            <div class="mb-1.5 text-[13px] font-medium text-[var(--text-mute)]">
                {{ $t('admin.waPromptPreviewLabel') }}
            </div>
            <p class="text-[13px] leading-relaxed text-[var(--text-body)]">{{ message }}</p>
        </div>

        <label class="mb-5 flex items-center gap-2">
            <input
                v-model="dontAskAgain"
                type="checkbox"
                class="h-[18px] w-[18px] rounded-[5px] border-[1.5px] border-[var(--border-strong)] bg-[var(--surface-alt)] accent-[var(--btn-green)]"
            />
            <span class="text-[13px] font-normal text-[var(--text-body)]">{{ $t('admin.waDontAskAgain') }}</span>
        </label>

        <div class="flex gap-3">
            <button
                type="button"
                class="w-2/5 rounded-xl bg-[var(--surface-mute)] py-3.5 text-[15px] font-semibold text-[var(--text-body)] hover:bg-[var(--border-strong)]"
                @click="dismiss"
            >
                {{ $t('admin.waSkip') }}
            </button>
            <a
                :href="waHref"
                target="_blank"
                rel="noopener noreferrer"
                class="flex w-3/5 items-center justify-center gap-1.5 rounded-xl bg-[#25D366] py-3.5 text-[15px] font-semibold text-white hover:brightness-95"
                @click="dismiss"
            >
                <svg width="15" height="15" viewBox="0 0 24 24" fill="#fff">
                    <path
                        d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38a9.9 9.9 0 0 0 4.74 1.2h.01c5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.86 9.86 0 0 0 12.05 2m0 1.67a8.2 8.2 0 0 1 5.83 2.42 8.19 8.19 0 0 1 2.41 5.83c0 4.55-3.7 8.24-8.25 8.24a8.3 8.3 0 0 1-4.2-1.15l-.3-.18-3.12.82.83-3.04-.2-.31a8.22 8.22 0 0 1-1.26-4.4c0-4.55 3.7-8.23 8.26-8.23m-4.57 4.7c-.16 0-.42.06-.64.3-.22.24-.85.83-.85 2.02s.87 2.35.99 2.51c.12.16 1.7 2.69 4.19 3.63 2.07.79 2.49.63 2.94.59.45-.04 1.45-.59 1.65-1.16.2-.57.2-1.06.14-1.16-.06-.1-.22-.16-.46-.28-.24-.12-1.45-.72-1.68-.8-.22-.08-.39-.12-.55.12-.16.24-.63.8-.77.96-.14.16-.28.18-.52.06-.24-.12-1.02-.38-1.94-1.2-.72-.64-1.2-1.44-1.34-1.68-.14-.24-.02-.37.1-.49.11-.11.24-.28.36-.42.12-.14.16-.24.24-.4.08-.16.04-.3-.02-.42-.06-.12-.55-1.36-.77-1.86-.2-.48-.4-.42-.55-.42h-.47Z"
                    />
                </svg>
                {{ $t('admin.waSend') }}
            </a>
        </div>
    </BottomSheet>
</template>
