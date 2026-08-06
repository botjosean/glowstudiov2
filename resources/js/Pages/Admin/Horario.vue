<script setup>
import { computed, onMounted } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Clock4, UtensilsCrossed, Timer, Info } from '@lucide/vue';
import AdminLayout from '../../Layouts/AdminLayout.vue';
import Select from '../../Components/ui/Select.vue';
import { useFormat } from '../../composables/useFormat';

const props = defineProps({
    providerName: { type: String, default: 'Pati' },
    schedule: {
        type: Object,
        default: () => ({
            workStart: 9 * 60,
            workEnd: 20 * 60,
            lunchStart: 13 * 60,
            lunchEnd: 14 * 60,
            bufferMinutes: 15,
        }),
    },
});

const { formatTime, formatDuration } = useFormat();

// The Inicio checklist's "review your schedule" step has no server-side
// signal (defaults always exist), so opening this page is what completes it.
onMounted(() => {
    localStorage.setItem('glow:scheduleReviewed', '1');
});

const form = useForm({ ...props.schedule });

function submit() {
    form.put('/admin/horario', { preserveScroll: true, preserveState: true });
}

const timeOptions = computed(() =>
    Array.from({ length: 96 }, (_, i) => i * 15).map((minutes) => ({
        value: minutes,
        label: formatTime(Math.floor(minutes / 60), minutes % 60),
    })),
);

const bufferOptions = computed(() =>
    Array.from({ length: 21 }, (_, i) => i * 15).map((minutes) => ({
        value: minutes,
        label: formatDuration(minutes),
    })),
);

const workStartLabel = computed(() => formatTime(Math.floor(form.workStart / 60), form.workStart % 60));
const workEndLabel = computed(() => formatTime(Math.floor(form.workEnd / 60), form.workEnd % 60));
const lunchStartLabel = computed(() => formatTime(Math.floor(form.lunchStart / 60), form.lunchStart % 60));
const lunchEndLabel = computed(() => formatTime(Math.floor(form.lunchEnd / 60), form.lunchEnd % 60));
</script>

<template>
    <AdminLayout :provider-name="providerName">
        <div class="flex flex-col gap-4 p-4 pb-6">
            <div class="rounded-2xl border border-[var(--surface-mute)] bg-[var(--surface-alt)] p-4">
                <div class="mb-3 flex items-center gap-2.5">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-[10px] bg-[var(--chip-bg)]">
                        <Clock4 :size="16" class="text-[var(--chip-fg)]" />
                    </div>
                    <div>
                        <div class="text-sm font-extrabold text-[var(--text-strong)]">{{ $t('admin.workday') }}</div>
                        <div class="text-[11px] font-semibold text-[var(--text-faint)]">{{ $t('admin.workdayHint') }}</div>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <Select v-model="form.workStart" :label="$t('admin.startTime')" :options="timeOptions" />
                    <Select v-model="form.workEnd" :label="$t('admin.endTime')" :options="timeOptions" />
                </div>
            </div>

            <div class="rounded-2xl border border-[var(--surface-mute)] bg-[var(--surface-alt)] p-4">
                <div class="mb-3 flex items-center gap-2.5">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-[10px] bg-[var(--loc-chip)]">
                        <UtensilsCrossed :size="16" class="text-[var(--loc-text)]" />
                    </div>
                    <div>
                        <div class="text-sm font-extrabold text-[var(--text-strong)]">{{ $t('admin.lunch') }}</div>
                        <div class="text-[11px] font-semibold text-[var(--text-faint)]">{{ $t('admin.lunchHint') }}</div>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <Select v-model="form.lunchStart" :label="$t('admin.startTime')" :options="timeOptions" />
                    <Select v-model="form.lunchEnd" :label="$t('admin.endTime')" :options="timeOptions" />
                </div>
            </div>

            <div class="rounded-2xl border border-[var(--surface-mute)] bg-[var(--surface-alt)] p-4">
                <div class="mb-3 flex items-center gap-2.5">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-[10px] bg-[var(--blue-soft)]">
                        <Timer :size="16" class="text-[var(--blue-text)]" />
                    </div>
                    <div>
                        <div class="text-sm font-extrabold text-[var(--text-strong)]">{{ $t('admin.buffer') }}</div>
                        <div class="text-[11px] font-semibold text-[var(--text-faint)]">{{ $t('admin.bufferHint') }}</div>
                    </div>
                </div>
                <Select v-model="form.bufferMinutes" :options="bufferOptions" />
            </div>

            <div class="flex items-start gap-2.5 rounded-2xl border border-[var(--green-border)] bg-[var(--green-soft)] p-4">
                <Info :size="16" class="mt-0.5 shrink-0 text-[var(--green-text)]" />
                <div class="text-xs font-semibold leading-relaxed text-[var(--green-deep)]">
                    {{ $t('admin.scheduleSummaryPrefix') }}
                    <span class="font-extrabold">{{ workStartLabel }} {{ $t('admin.to') }} {{ workEndLabel }}</span
                    >, {{ $t('admin.scheduleSummaryLunch') }}
                    <span class="font-extrabold">{{ lunchStartLabel }} {{ $t('admin.to') }} {{ lunchEndLabel }}</span>
                    {{ $t('admin.scheduleSummaryAnd') }}
                    <span class="font-extrabold">{{ formatDuration(form.bufferMinutes) }}</span>
                    {{ $t('admin.scheduleSummaryBetween') }}
                </div>
            </div>

            <p
                v-if="form.errors.workStart || form.errors.workEnd || form.errors.lunchStart || form.errors.lunchEnd || form.errors.bufferMinutes"
                class="text-xs font-semibold text-[var(--danger)]"
            >
                {{ form.errors.workStart || form.errors.workEnd || form.errors.lunchStart || form.errors.lunchEnd || form.errors.bufferMinutes }}
            </p>

            <button
                type="button"
                :disabled="form.processing"
                class="w-full rounded-xl bg-[var(--btn-bg)] py-3.5 text-sm font-bold text-white hover:bg-[var(--btn-hover)] disabled:cursor-not-allowed disabled:opacity-60"
                @click="submit"
            >
                {{ form.processing ? $t('common.saving') : $t('admin.saveChanges') }}
            </button>
        </div>
    </AdminLayout>
</template>
