<script setup>
import { ref, computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { X, ChevronLeft, ChevronRight, ChevronDown, Calendar, Check, Info } from '@lucide/vue';
import { useFormat } from '../../composables/useFormat';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    provider: { type: Object, required: true }, // { slug, name }
    service: { type: Object, required: true }, // { id, name, price }
});

const { t, locale } = useI18n();
const { formatTime } = useFormat();

const step = ref('datetime'); // datetime | details | confirmation

const today = new Date();
const viewMonth = ref(today.getMonth());
const viewYear = ref(today.getFullYear());
const selectedDay = ref(today.getDate());

const slots = [
    { h: 10, m: 0 },
    { h: 11, m: 15 },
    { h: 13, m: 30 },
    { h: 15, m: 0 },
    { h: 16, m: 15 },
    { h: 17, m: 30 },
    { h: 18, m: 45 },
    { h: 20, m: 0 },
];
const selectedSlotIndex = ref(2);

const monthLabel = computed(() =>
    new Date(viewYear.value, viewMonth.value, 1).toLocaleDateString(locale.value === 'es' ? 'es-ES' : 'en-US', {
        month: 'long',
        year: 'numeric',
    }),
);

const weekdayLabels = computed(() => {
    const base = new Date(2026, 0, 4); // a Sunday
    return Array.from({ length: 7 }, (_, i) =>
        new Date(base.getFullYear(), base.getMonth(), base.getDate() + i)
            .toLocaleDateString(locale.value === 'es' ? 'es-ES' : 'en-US', { weekday: 'narrow' })
            .toUpperCase(),
    );
});

const calendarCells = computed(() => {
    const firstOfMonth = new Date(viewYear.value, viewMonth.value, 1);
    const startOffset = firstOfMonth.getDay();
    const daysInMonth = new Date(viewYear.value, viewMonth.value + 1, 0).getDate();
    const cells = [];

    for (let i = 0; i < startOffset; i++) {
        cells.push({ day: null });
    }
    for (let day = 1; day <= daysInMonth; day++) {
        const isPast =
            viewYear.value === today.getFullYear() && viewMonth.value === today.getMonth() && day < today.getDate();
        const isToday =
            viewYear.value === today.getFullYear() && viewMonth.value === today.getMonth() && day === today.getDate();
        cells.push({ day, isPast, isToday });
    }
    return cells;
});

function previousMonth() {
    if (viewMonth.value === 0) {
        viewMonth.value = 11;
        viewYear.value -= 1;
    } else {
        viewMonth.value -= 1;
    }
}

function nextMonth() {
    if (viewMonth.value === 11) {
        viewMonth.value = 0;
        viewYear.value += 1;
    } else {
        viewMonth.value += 1;
    }
}

const selectedDateLabel = computed(() =>
    new Date(viewYear.value, viewMonth.value, selectedDay.value).toLocaleDateString(
        locale.value === 'es' ? 'es-ES' : 'en-US',
        { weekday: 'short', month: 'short', day: 'numeric' },
    ),
);

const selectedTimeLabel = computed(() => {
    const slot = slots[selectedSlotIndex.value];
    return formatTime(slot.h, slot.m);
});

const details = ref({ fullName: '', phone: '' });

function closeToProfile() {
    router.visit(`/p/${props.provider.slug}`);
}

function formatUsPhone(value) {
    const digits = value.replace(/\D/g, '').slice(0, 10);
    if (digits.length === 0) return '';
    if (digits.length < 4) return `(${digits}`;
    if (digits.length < 7) return `(${digits.slice(0, 3)}) ${digits.slice(3)}`;
    return `(${digits.slice(0, 3)}) ${digits.slice(3, 6)}-${digits.slice(6)}`;
}

function onPhoneInput(event) {
    details.value.phone = formatUsPhone(event.target.value);
}
</script>

<template>
    <div class="flex min-h-screen items-end justify-center bg-[var(--backdrop)] sm:items-center">
        <div class="w-full max-w-[480px] rounded-t-3xl bg-[var(--surface)] p-6 shadow-[0_-10px_40px_rgba(15,23,42,0.2)] sm:rounded-3xl">
            <div class="mx-auto mb-5 h-1.5 w-12 rounded-full bg-[var(--border-strong)]" />

            <!-- Step: date & time -->
            <template v-if="step === 'datetime'">
                <div class="mb-4 flex items-center justify-between">
                    <div>
                        <span class="block text-[10px] font-bold uppercase tracking-[2px] text-[var(--text-faint)]">{{
                            $t('booking.stepOf', { current: 1, total: 2 })
                        }}</span>
                        <div class="text-lg font-extrabold text-[var(--text-strong)]">{{ $t('booking.title') }}</div>
                    </div>
                    <button
                        type="button"
                        class="flex h-8 w-8 items-center justify-center rounded-full bg-[var(--surface-mute)]"
                        @click="closeToProfile"
                    >
                        <X :size="16" class="text-[var(--text-body)]" />
                    </button>
                </div>

                <div class="mb-5 flex items-center justify-between rounded-2xl border border-[var(--surface-mute)] bg-[var(--surface-alt)] p-4">
                    <div>
                        <span class="block text-[10px] font-bold uppercase tracking-wider text-[var(--text-faint)]">{{
                            provider.name
                        }}</span>
                        <span class="mt-0.5 block text-sm font-extrabold text-[var(--text-strong)]">{{
                            service.name
                        }}</span>
                    </div>
                    <div class="text-right">
                        <span class="block text-base font-extrabold text-[var(--text-strong)]">${{ service.price }}</span>
                        <span class="block text-[9px] font-bold text-[var(--text-faint)]">{{ $t('booking.payInStore') }}</span>
                    </div>
                </div>

                <div class="mb-3 text-[11px] font-bold uppercase tracking-wider text-[var(--text-faint)]">
                    {{ $t('booking.selectDay') }}
                </div>
                <div class="mb-5 rounded-2xl border border-[var(--surface-mute)] bg-[var(--surface-alt)] p-4">
                    <div class="mb-3 flex items-center justify-between">
                        <button
                            type="button"
                            class="flex h-7 w-7 items-center justify-center rounded-full bg-[var(--surface-mute)]"
                            @click="previousMonth"
                        >
                            <ChevronLeft :size="14" class="text-[var(--text-mute)]" />
                        </button>
                        <span class="text-[13px] font-extrabold capitalize text-[var(--text-strong)]">{{ monthLabel }}</span>
                        <button
                            type="button"
                            class="flex h-7 w-7 items-center justify-center rounded-full bg-[var(--surface-mute)]"
                            @click="nextMonth"
                        >
                            <ChevronRight :size="14" class="text-[var(--text-mute)]" />
                        </button>
                    </div>
                    <div class="mb-1.5 grid grid-cols-7 gap-0.5">
                        <span
                            v-for="wd in weekdayLabels"
                            :key="wd"
                            class="text-center text-[9px] font-extrabold uppercase text-[var(--text-faint)]"
                            >{{ wd }}</span
                        >
                    </div>
                    <div class="grid grid-cols-7 gap-0.5">
                        <template v-for="(cell, index) in calendarCells" :key="index">
                            <span
                                v-if="!cell.day"
                                class="flex h-8 items-center justify-center text-xs font-semibold text-[var(--border-strong)]"
                            />
                            <button
                                v-else
                                type="button"
                                :disabled="cell.isPast"
                                class="flex h-8 items-center justify-center rounded-[10px] text-xs font-bold"
                                :class="[
                                    cell.isPast && 'cursor-not-allowed font-semibold text-[var(--text-faint)]',
                                    !cell.isPast && selectedDay !== cell.day && !cell.isToday && 'text-[var(--text-body)]',
                                    !cell.isPast && cell.isToday && selectedDay !== cell.day &&
                                        'border border-[var(--green-border)] font-extrabold text-[var(--green-text)]',
                                    selectedDay === cell.day && 'bg-[var(--chip-bg)] font-extrabold text-[var(--chip-fg)]',
                                ]"
                                @click="selectedDay = cell.day"
                            >
                                {{ cell.day }}
                            </button>
                        </template>
                    </div>
                    <div class="mt-2.5 flex gap-3.5 border-t border-[var(--surface-mute)] pt-2.5">
                        <span class="flex items-center gap-1.5 text-[10px] font-bold text-[var(--text-faint)]"
                            ><span class="h-2 w-2 rounded-[3px] bg-[var(--chip-bg)]" />{{ $t('booking.legendSelected') }}</span
                        >
                        <span class="flex items-center gap-1.5 text-[10px] font-bold text-[var(--text-faint)]"
                            ><span class="h-2 w-2 rounded-[3px] border border-[var(--green-border)]" />{{
                                $t('booking.legendToday')
                            }}</span
                        >
                        <span class="flex items-center gap-1.5 text-[10px] font-bold text-[var(--text-faint)]"
                            ><span class="h-2 w-2 rounded-[3px] bg-[var(--surface-mute)]" />{{
                                $t('booking.legendUnavailable')
                            }}</span
                        >
                    </div>
                </div>

                <div class="mb-3 text-[11px] font-bold uppercase tracking-wider text-[var(--text-faint)]">
                    {{ $t('booking.selectTime') }}
                </div>
                <div class="relative mb-6">
                    <select
                        v-model="selectedSlotIndex"
                        class="w-full appearance-none rounded-xl border-[1.5px] border-[var(--border-strong)] bg-[var(--surface-alt)] px-4 py-3.5 pr-10 text-sm font-bold text-[var(--text-strong)] focus:border-[var(--green-border)] focus:outline-none"
                    >
                        <option v-for="(slot, index) in slots" :key="index" :value="index">
                            {{ formatTime(slot.h, slot.m) }}
                        </option>
                    </select>
                    <ChevronDown
                        :size="16"
                        class="pointer-events-none absolute right-3.5 top-1/2 -translate-y-1/2 text-[var(--text-faint)]"
                    />
                </div>

                <button
                    type="button"
                    class="flex w-full items-center justify-center gap-1.5 rounded-2xl bg-[var(--btn-bg)] py-4 text-sm font-bold text-white hover:bg-[var(--btn-hover)]"
                    @click="step = 'details'"
                >
                    {{ $t('booking.continue') }}
                    <ChevronRight :size="16" />
                </button>
            </template>

            <!-- Step: customer details -->
            <template v-else-if="step === 'details'">
                <div class="mb-4 flex items-center justify-between">
                    <div>
                        <span class="block text-[10px] font-bold uppercase tracking-[2px] text-[var(--text-faint)]">{{
                            $t('booking.stepOf', { current: 2, total: 2 })
                        }}</span>
                        <div class="text-lg font-extrabold text-[var(--text-strong)]">{{ $t('booking.title') }}</div>
                    </div>
                    <button
                        type="button"
                        class="flex h-8 w-8 items-center justify-center rounded-full bg-[var(--surface-mute)]"
                        @click="closeToProfile"
                    >
                        <X :size="16" class="text-[var(--text-body)]" />
                    </button>
                </div>

                <div class="mb-5 flex items-center justify-between rounded-2xl border border-[var(--surface-mute)] bg-[var(--surface-alt)] p-4">
                    <div>
                        <span class="block text-[10px] font-bold uppercase tracking-wider text-[var(--text-faint)]">{{
                            provider.name
                        }}</span>
                        <span class="mt-0.5 block text-sm font-extrabold text-[var(--text-strong)]">{{
                            service.name
                        }}</span>
                    </div>
                    <div class="text-right">
                        <span class="block text-base font-extrabold text-[var(--text-strong)]">${{ service.price }}</span>
                        <span class="block text-[9px] font-bold text-[var(--text-faint)]">{{ $t('booking.payInStore') }}</span>
                    </div>
                </div>

                <div class="mb-4">
                    <div class="mb-2 text-[11px] font-bold uppercase tracking-wider text-[var(--text-faint)]">
                        {{ $t('booking.fullName') }}
                    </div>
                    <input
                        v-model="details.fullName"
                        type="text"
                        :placeholder="$t('booking.fullNamePlaceholder')"
                        class="w-full rounded-xl border border-[var(--border-strong)] bg-[var(--surface-alt)] px-4 py-3.5 text-sm font-bold text-[var(--text-strong)] placeholder:font-medium placeholder:text-[var(--text-faint)] focus:outline-none"
                    />
                </div>
                <div class="mb-5">
                    <div class="mb-2 text-[11px] font-bold uppercase tracking-wider text-[var(--text-faint)]">
                        {{ $t('booking.phoneNumber') }}
                    </div>
                    <div
                        class="flex items-center gap-2.5 rounded-xl border border-[var(--border-strong)] bg-[var(--surface-alt)] px-4 py-3.5 focus-within:border-[var(--green-border)]"
                    >
                        <span
                            class="flex shrink-0 items-center gap-1.5 border-r border-[var(--border-strong)] pr-2.5 text-sm font-bold text-[var(--text-strong)]"
                        >
                            <span class="text-base leading-none">🇺🇸</span>
                            +1
                        </span>
                        <input
                            :value="details.phone"
                            type="tel"
                            inputmode="numeric"
                            autocomplete="tel-national"
                            :placeholder="$t('booking.phonePlaceholder')"
                            class="w-full bg-transparent text-sm font-bold text-[var(--text-strong)] placeholder:font-medium placeholder:text-[var(--text-faint)] focus:outline-none"
                            @input="onPhoneInput"
                        />
                    </div>
                </div>

                <div class="mb-6 rounded-2xl border border-[var(--surface-mute)] bg-[var(--surface-alt)] p-4 text-xs leading-relaxed text-[var(--text-mute)]">
                    <div class="mb-1.5 flex items-center gap-1.5 font-extrabold text-[var(--text-heading)]">
                        <Calendar :size="16" class="text-[var(--text-mute)]" />
                        {{ $t('booking.summary') }}
                    </div>
                    <span class="block text-[var(--text-body)]"
                        >{{ $t('booking.summaryDate') }}:
                        <span class="font-extrabold text-[var(--text-heading)]">{{ selectedDateLabel }}, {{ selectedTimeLabel }}</span></span
                    >
                    <span class="block text-[var(--text-body)]"
                        >{{ $t('booking.summaryProvider') }}:
                        <span class="font-extrabold text-[var(--text-heading)]">{{ provider.name }}</span></span
                    >
                </div>

                <div class="flex gap-3">
                    <button
                        type="button"
                        class="w-1/3 rounded-xl bg-[var(--surface-mute)] py-3.5 text-sm font-bold text-[var(--text-heading)] hover:bg-[var(--border-strong)]"
                        @click="step = 'datetime'"
                    >
                        {{ $t('booking.back') }}
                    </button>
                    <button
                        type="button"
                        class="flex w-2/3 items-center justify-center gap-1.5 rounded-xl bg-[var(--btn-green)] py-3.5 text-sm font-bold text-white hover:bg-[var(--btn-green-hover)]"
                        @click="step = 'confirmation'"
                    >
                        <Check :size="16" />
                        {{ $t('booking.submit') }}
                    </button>
                </div>
            </template>

            <!-- Step: confirmation -->
            <template v-else>
                <div class="py-8 text-center">
                    <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-[var(--green-border)]">
                        <Info :size="32" class="text-[var(--green-text)]" />
                    </div>
                    <div class="text-xl font-extrabold text-[var(--text-strong)]">{{ $t('booking.submittedTitle') }}</div>
                    <p class="mx-auto mt-2 max-w-[280px] text-xs font-semibold leading-relaxed text-[var(--text-mute)]">
                        {{ $t('booking.submittedBody') }}
                        <span class="font-extrabold text-[var(--text-heading)]">{{ service.name }}</span>
                        {{ $t('booking.submittedWith') }}
                        <span class="font-extrabold text-[var(--text-heading)]">{{ provider.name }}</span>
                        {{ $t('booking.submittedPending') }}
                    </p>
                    <div class="mx-auto my-6 max-w-[320px] rounded-2xl border border-[var(--green-border)] bg-[var(--green-soft)] p-4 text-left">
                        <div class="flex gap-2.5">
                            <Info :size="16" class="mt-0.5 shrink-0 text-[var(--green-deep)]" />
                            <div class="text-xs leading-relaxed text-[var(--green-deep)]">
                                <div class="font-extrabold">{{ $t('booking.nextTitle') }}</div>
                                <div class="mt-0.5 font-medium opacity-90">{{ $t('booking.nextBody') }}</div>
                            </div>
                        </div>
                    </div>
                    <Link
                        :href="`/p/${provider.slug}`"
                        class="block w-full rounded-xl bg-[var(--btn-bg)] py-3.5 text-center text-sm font-bold text-white hover:bg-[var(--btn-hover)]"
                        >{{ $t('booking.done') }}</Link
                    >
                </div>
            </template>
        </div>
    </div>
</template>
