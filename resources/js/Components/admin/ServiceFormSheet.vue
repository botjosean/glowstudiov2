<script setup>
import { ref, computed, watch } from 'vue';
import { X, Ban, Plus, RotateCcw } from '@lucide/vue';
import { useI18n } from 'vue-i18n';
import BottomSheet from '../ui/BottomSheet.vue';
import OutlinedInput from '../ui/OutlinedInput.vue';
import OutlinedSelect from '../ui/OutlinedSelect.vue';
import Chip from '../ui/Chip.vue';
import { useFormat } from '../../composables/useFormat';

const props = defineProps({
    mode: { type: String, default: 'create' }, // create | edit
    service: { type: Object, default: () => ({ name: '', durationMinutes: 45, price: 35, category: 'other', homeAvailable: false }) },
    errors: { type: Object, default: () => ({}) },
    processing: { type: Boolean, default: false },
});

const open = defineModel({ type: Boolean, default: false });
const emit = defineEmits(['save', 'delete', 'activate']);

const { t } = useI18n();
const { formatDuration } = useFormat();

const form = ref({ ...props.service });

/**
 * The durations that cover almost every booking. Anything else stays reachable
 * through the "other" chip, which reveals the full 5-minute list — a plain
 * <select> of all 36 steps was the only way in, and scrolling it to find
 * "1 h" was the slowest part of adding a service.
 */
const QUICK_DURATIONS = [30, 45, 60, 90, 120, 180];

// Kept open whenever the current value isn't one of the quick chips, so
// editing a 75-minute service shows its real duration instead of silently
// looking like none of the chips are selected.
const showCustomDuration = ref(false);

function syncDraft(service) {
    form.value = { homeAvailable: false, ...service };
    showCustomDuration.value = !QUICK_DURATIONS.includes(form.value.durationMinutes);
}

watch(open, (isOpen) => {
    if (isOpen) {
        syncDraft(props.service);
    }
});

// "Save and add another" leaves the sheet open and hands back a fresh draft
// through this prop — resyncing here is what actually clears the form.
watch(
    () => props.service,
    (service) => {
        if (open.value) {
            syncDraft(service);
        }
    },
);

function pickDuration(minutes) {
    form.value.durationMinutes = minutes;
    showCustomDuration.value = false;
}

// 5-minute steps up to the 6 h the database now allows (services_duration_chk).
const durationOptions = computed(() =>
    Array.from({ length: 72 }, (_, i) => (i + 1) * 5).map((minutes) => ({
        value: minutes,
        label: formatDuration(minutes),
    })),
);

/**
 * Grouped so twelve categories stay scannable in a native select, and beauty
 * comes first: the barbershop five are the legacy set, and the providers
 * signing up now are nail techs and stylists. Labels were hardcoded Spanish
 * strings before, which showed Spanish categories to the English UI.
 */
const categoryOptions = computed(() => {
    const option = (value) => ({ value, label: t(`admin.category_${value}`) });

    return [
        {
            label: t('admin.categoryGroupBeauty'),
            options: ['nails', 'hands', 'feet', 'lashes', 'facial', 'hair'].map(option),
        },
        {
            label: t('admin.categoryGroupBarber'),
            options: ['fade', 'classic', 'beard', 'kids', 'color'].map(option),
        },
        { label: '', options: [option('other')] },
    ];
});

function save(keepOpen = false) {
    emit('save', { ...form.value }, keepOpen);
}
</script>

<template>
    <BottomSheet v-model="open">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <div class="text-[20px] font-bold leading-tight tracking-tight text-[var(--text-strong)]">
                    {{ mode === 'create' ? $t('admin.newService') : $t('admin.editService') }}
                </div>
                <div v-if="mode === 'edit'" class="mt-0.5 text-[13px] font-normal text-[var(--text-mute)]">{{ service.name }}</div>
            </div>
            <button
                type="button"
                :aria-label="$t('common.close')"
                class="flex h-8 w-8 items-center justify-center rounded-full bg-[var(--surface-mute)] hover:bg-[var(--border-strong)]"
                @click="open = false"
            >
                <X :size="16" class="text-[var(--text-mute)]" />
            </button>
        </div>

        <OutlinedInput
            id="service-name"
            v-model="form.name"
            class="mb-5"
            :label="$t('admin.serviceName')"
            :placeholder="$t('admin.serviceNamePlaceholder')"
            :error="errors.name"
            clearable
        />

        <div class="mb-5">
            <span class="mb-2 block text-[13px] font-medium text-[var(--text-mute)]">{{ $t('admin.duration') }}</span>
            <div class="flex flex-wrap gap-2">
                <Chip
                    v-for="minutes in QUICK_DURATIONS"
                    :key="minutes"
                    :active="!showCustomDuration && form.durationMinutes === minutes"
                    @click="pickDuration(minutes)"
                >
                    {{ formatDuration(minutes) }}
                </Chip>
                <Chip :active="showCustomDuration" @click="showCustomDuration = true">
                    {{ $t('admin.durationOther') }}
                </Chip>
            </div>
            <div v-if="showCustomDuration" class="mt-3">
                <OutlinedSelect
                    id="service-duration"
                    v-model="form.durationMinutes"
                    :label="$t('admin.duration')"
                    :options="durationOptions"
                />
                <div class="mt-1.5 text-[12px] font-normal text-[var(--text-faint)]">{{ $t('admin.durationHint') }}</div>
            </div>
            <p v-if="errors.durationMinutes" class="mt-1.5 text-[13px] font-normal text-[var(--danger)]">{{ errors.durationMinutes }}</p>
        </div>

        <div class="mb-5 grid grid-cols-2 items-start gap-3">
            <OutlinedInput
                id="service-price"
                v-model.number="form.price"
                type="number"
                inputmode="numeric"
                min="0"
                step="1"
                prefix="$"
                :label="$t('admin.price')"
                :error="errors.price"
            />
            <OutlinedSelect
                id="service-category"
                v-model="form.category"
                :label="$t('admin.category')"
                :options="categoryOptions"
                :error="errors.category"
            />
        </div>

        <!-- Her per-service safety switch: unchecked services are never
             offered at home by any channel, no matter the profile mode. -->
        <label class="mb-2 flex items-start gap-2.5">
            <input
                v-model="form.homeAvailable"
                type="checkbox"
                class="mt-0.5 h-[18px] w-[18px] shrink-0 rounded-[5px] accent-[var(--text-strong)]"
            />
            <span>
                <span class="block text-[14px] font-medium text-[var(--text-strong)]">{{ $t('admin.serviceHomeAvailable') }}</span>
                <span class="block text-[12px] font-normal text-[var(--text-faint)]">{{ $t('admin.serviceHomeAvailableHint') }}</span>
            </span>
        </label>

        <div class="mt-6 flex flex-col gap-2.5">
            <button
                type="button"
                :disabled="processing"
                class="w-full rounded-xl bg-[var(--btn-bg)] py-3.5 text-[15px] font-semibold text-white hover:bg-[var(--btn-hover)] disabled:cursor-not-allowed disabled:opacity-60"
                @click="save()"
            >
                {{ processing ? $t('common.saving') : mode === 'create' ? $t('admin.saveService') : $t('admin.saveChanges') }}
            </button>

            <button
                v-if="mode === 'create'"
                type="button"
                :disabled="processing"
                class="flex w-full items-center justify-center gap-1.5 rounded-xl border border-[var(--border-strong)] py-3.5 text-[14px] font-semibold text-[var(--text-strong)] hover:bg-[var(--surface-mute)] disabled:cursor-not-allowed disabled:opacity-60"
                @click="save(true)"
            >
                <Plus :size="15" />
                {{ $t('admin.saveAndAddAnother') }}
            </button>

            <button
                v-if="mode === 'edit' && service.isActive !== false"
                type="button"
                :disabled="processing"
                class="flex w-full items-center justify-center gap-1.5 rounded-xl border border-[var(--danger-border)] py-3.5 text-[14px] font-semibold text-[var(--danger)] hover:bg-[var(--danger-hover)] disabled:cursor-not-allowed disabled:opacity-60"
                @click="emit('delete')"
            >
                <Ban :size="15" />
                {{ $t('admin.deactivateService') }}
            </button>

            <button
                v-if="mode === 'edit' && service.isActive === false"
                type="button"
                :disabled="processing"
                class="flex w-full items-center justify-center gap-1.5 rounded-xl border border-[var(--border-strong)] py-3.5 text-[14px] font-semibold text-[var(--text-strong)] hover:bg-[var(--surface-mute)] disabled:cursor-not-allowed disabled:opacity-60"
                @click="emit('activate')"
            >
                <RotateCcw :size="15" />
                {{ $t('admin.activateService') }}
            </button>
        </div>
    </BottomSheet>
</template>
