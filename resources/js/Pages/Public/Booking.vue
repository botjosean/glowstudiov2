<script setup>
import { ref, computed } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { X, ChevronLeft, ChevronRight, Calendar, Check, Info } from '@lucide/vue';
import { useFormat } from '../../composables/useFormat';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    provider: { type: Object, required: true }, // { slug, name }
    service: { type: Object, required: true }, // { id, name, price }
    // True only when the provider has a studio AND travels AND this service
    // is marked home-eligible — the server decides, the UI just obeys.
    homeOption: { type: Boolean, default: false },
    selectedDate: { type: String, required: true }, // 'YYYY-MM-DD', "today" in the provider's timezone
    slots: { type: Array, required: true }, // [{ h, m }] free slots for selectedDate
    monthAvailability: { type: Object, required: true }, // { 'YYYY-MM-DD': freeSlotCount }
});

const { t, locale } = useI18n();
const { formatTime } = useFormat();

const step = ref('datetime'); // datetime | details | confirmation

function pad2(n) {
    return String(n).padStart(2, '0');
}

// Anchored to the provider's "today" (from the server) rather than the
// visitor's browser clock — the barber's local time is what determines
// which slots are actually bookable.
const [initialYear, initialMonth, initialDay] = props.selectedDate.split('-').map(Number);
const today = new Date(initialYear, initialMonth - 1, initialDay);
const viewMonth = ref(initialMonth - 1);
const viewYear = ref(initialYear);
const selectedDay = ref(initialDay);

const selectedDateKey = computed(() => `${viewYear.value}-${pad2(viewMonth.value + 1)}-${pad2(selectedDay.value)}`);

const slots = computed(() => props.slots);
const selectedSlotIndex = ref(0);

function selectDay(day) {
    selectedDay.value = day;
    selectedSlotIndex.value = 0;
    router.reload({
        only: ['slots', 'selectedDate'],
        data: { date: `${viewYear.value}-${pad2(viewMonth.value + 1)}-${pad2(day)}` },
        preserveState: true,
        preserveScroll: true,
    });
}

function reloadMonthAvailability() {
    router.reload({
        only: ['monthAvailability'],
        data: { month: `${viewYear.value}-${pad2(viewMonth.value + 1)}` },
        preserveState: true,
        preserveScroll: true,
    });
}

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
        const key = `${viewYear.value}-${pad2(viewMonth.value + 1)}-${pad2(day)}`;
        const isUnavailable = !isPast && (props.monthAvailability[key] ?? 0) === 0;
        cells.push({ day, isPast, isToday, isUnavailable });
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
    reloadMonthAvailability();
}

function nextMonth() {
    if (viewMonth.value === 11) {
        viewMonth.value = 0;
        viewYear.value += 1;
    } else {
        viewMonth.value += 1;
    }
    reloadMonthAvailability();
}

const selectedDateLabel = computed(() =>
    new Date(viewYear.value, viewMonth.value, selectedDay.value).toLocaleDateString(
        locale.value === 'es' ? 'es-ES' : 'en-US',
        { weekday: 'short', month: 'short', day: 'numeric' },
    ),
);

const selectedTimeLabel = computed(() => {
    const slot = slots.value[selectedSlotIndex.value];
    return slot ? formatTime(slot.h, slot.m) : '';
});

const bookingForm = useForm({
    date: '',
    time: '',
    fullName: '',
    phone: '',
    atHome: false,
    address: '',
});

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
    bookingForm.phone = formatUsPhone(event.target.value);
}

function submitBooking() {
    const slot = slots.value[selectedSlotIndex.value];
    if (!slot) return;

    bookingForm.date = selectedDateKey.value;
    bookingForm.time = `${pad2(slot.h)}:${pad2(slot.m)}`;

    bookingForm.post(`/reservar/${props.provider.slug}/${props.service.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            step.value = 'confirmation';
        },
    });
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
                        <span class="block text-[12px] font-medium text-[var(--text-mute)]">{{
                            $t('booking.stepOf', { current: 1, total: 2 })
                        }}</span>
                        <div class="text-lg font-bold text-[var(--text-strong)]">{{ $t('booking.title') }}</div>
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
                        <span class="block text-[12px] font-medium text-[var(--text-mute)]">{{
                            provider.name
                        }}</span>
                        <span class="mt-0.5 block text-[15px] font-semibold text-[var(--text-strong)]">{{
                            service.name
                        }}</span>
                    </div>
                    <div class="text-right">
                        <span class="block text-base font-bold text-[var(--text-strong)]">${{ service.price }}</span>
                        <span class="block text-[9px] font-bold text-[var(--text-faint)]">{{ $t('booking.payInStore') }}</span>
                    </div>
                </div>

                <div class="mb-3 text-[13px] font-medium text-[var(--text-mute)]">
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
                        <span class="text-[14px] font-semibold capitalize text-[var(--text-strong)]">{{ monthLabel }}</span>
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
                            class="text-center text-[9px] font-bold uppercase text-[var(--text-faint)]"
                            >{{ wd }}</span
                        >
                    </div>
                    <div class="grid grid-cols-7 gap-0.5">
                        <template v-for="(cell, index) in calendarCells" :key="index">
                            <span
                                v-if="!cell.day"
                                class="flex h-8 items-center justify-center text-[13px] font-normal text-[var(--border-strong)]"
                            />
                            <button
                                v-else
                                type="button"
                                :disabled="cell.isPast || cell.isUnavailable"
                                class="flex h-8 items-center justify-center rounded-[10px] text-[13px] font-medium"
                                :class="[
                                    cell.isPast && 'cursor-not-allowed font-semibold text-[var(--text-faint)]',
                                    !cell.isPast && cell.isUnavailable && selectedDay !== cell.day &&
                                        'cursor-not-allowed bg-[var(--surface-mute)] text-[var(--text-faint)]',
                                    !cell.isPast && !cell.isUnavailable && selectedDay !== cell.day && !cell.isToday && 'text-[var(--text-body)]',
                                    !cell.isPast && !cell.isUnavailable && cell.isToday && selectedDay !== cell.day &&
                                        'border border-[var(--green-border)] font-bold text-[var(--green-text)]',
                                    selectedDay === cell.day && 'bg-[var(--chip-bg)] font-bold text-[var(--chip-fg)]',
                                ]"
                                @click="selectDay(cell.day)"
                            >
                                {{ cell.day }}
                            </button>
                        </template>
                    </div>
                    <div class="mt-2.5 flex gap-3.5 border-t border-[var(--surface-mute)] pt-2.5">
                        <span class="flex items-center gap-1.5 text-[11px] font-medium text-[var(--text-faint)]"
                            ><span class="h-2 w-2 rounded-[3px] bg-[var(--chip-bg)]" />{{ $t('booking.legendSelected') }}</span
                        >
                        <span class="flex items-center gap-1.5 text-[11px] font-medium text-[var(--text-faint)]"
                            ><span class="h-2 w-2 rounded-[3px] border border-[var(--green-border)]" />{{
                                $t('booking.legendToday')
                            }}</span
                        >
                        <span class="flex items-center gap-1.5 text-[11px] font-medium text-[var(--text-faint)]"
                            ><span class="h-2 w-2 rounded-[3px] bg-[var(--surface-mute)]" />{{
                                $t('booking.legendUnavailable')
                            }}</span
                        >
                    </div>
                </div>

                <div class="mb-3 text-[13px] font-medium text-[var(--text-mute)]">
                    {{ $t('booking.selectTime') }}
                </div>
                <div class="mb-6">
                    <p
                        v-if="slots.length === 0"
                        class="rounded-xl border border-[var(--surface-mute)] bg-[var(--surface-alt)] px-4 py-5 text-center text-[13px] font-normal text-[var(--text-mute)]"
                    >
                        {{ $t('booking.noSlots') }}
                    </p>
                    <div v-else class="scrollbar-thin grid max-h-[176px] grid-cols-4 gap-2 overflow-y-auto pr-1">
                        <button
                            v-for="(slot, index) in slots"
                            :key="index"
                            type="button"
                            class="rounded-xl border-[1.5px] py-2.5 text-[14px] font-semibold transition-colors"
                            :class="
                                selectedSlotIndex === index
                                    ? 'border-[var(--chip-bg)] bg-[var(--chip-bg)] text-[var(--chip-fg)]'
                                    : 'border-[var(--border-strong)] bg-[var(--surface-alt)] text-[var(--text-body)] hover:border-[var(--text-faint)]'
                            "
                            @click="selectedSlotIndex = index"
                        >
                            {{ formatTime(slot.h, slot.m) }}
                        </button>
                    </div>
                </div>

                <button
                    type="button"
                    :disabled="slots.length === 0"
                    class="flex w-full items-center justify-center gap-1.5 rounded-2xl bg-[var(--btn-bg)] py-4 text-[15px] font-semibold text-white hover:bg-[var(--btn-hover)] disabled:cursor-not-allowed disabled:opacity-50"
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
                        <span class="block text-[12px] font-medium text-[var(--text-mute)]">{{
                            $t('booking.stepOf', { current: 2, total: 2 })
                        }}</span>
                        <div class="text-lg font-bold text-[var(--text-strong)]">{{ $t('booking.title') }}</div>
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
                        <span class="block text-[12px] font-medium text-[var(--text-mute)]">{{
                            provider.name
                        }}</span>
                        <span class="mt-0.5 block text-[15px] font-semibold text-[var(--text-strong)]">{{
                            service.name
                        }}</span>
                    </div>
                    <div class="text-right">
                        <span class="block text-base font-bold text-[var(--text-strong)]">${{ service.price }}</span>
                        <span class="block text-[9px] font-bold text-[var(--text-faint)]">{{ $t('booking.payInStore') }}</span>
                    </div>
                </div>

                <div v-if="homeOption" class="mb-4">
                    <div class="mb-2 text-[13px] font-medium text-[var(--text-mute)]">
                        {{ $t('booking.whereQuestion') }}
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <button
                            type="button"
                            class="rounded-xl border py-3 text-[14px] font-semibold transition-colors"
                            :class="!bookingForm.atHome
                                ? 'border-transparent bg-[var(--chip-bg)] text-[var(--chip-fg)]'
                                : 'border-[var(--border-strong)] bg-[var(--surface)] text-[var(--text-body)]'"
                            @click="bookingForm.atHome = false"
                        >
                            {{ $t('booking.atStudio') }}
                        </button>
                        <button
                            type="button"
                            class="rounded-xl border py-3 text-[14px] font-semibold transition-colors"
                            :class="bookingForm.atHome
                                ? 'border-transparent bg-[var(--chip-bg)] text-[var(--chip-fg)]'
                                : 'border-[var(--border-strong)] bg-[var(--surface)] text-[var(--text-body)]'"
                            @click="bookingForm.atHome = true"
                        >
                            {{ $t('booking.atHome') }}
                        </button>
                    </div>
                    <template v-if="bookingForm.atHome">
                        <textarea
                            v-model="bookingForm.address"
                            rows="2"
                            :placeholder="$t('booking.homeAddressPlaceholder')"
                            class="mt-3 w-full resize-none rounded-xl border border-[var(--border-strong)] bg-[var(--surface-alt)] px-4 py-3 text-[15px] font-semibold text-[var(--text-strong)] placeholder:font-medium placeholder:text-[var(--text-faint)] focus:outline-none"
                        />
                        <p class="mt-1.5 text-[12px] font-normal leading-relaxed text-[var(--text-faint)]">
                            {{ $t('booking.homeCoordinationNote', { provider: provider.name }) }}
                        </p>
                    </template>
                </div>

                <div class="mb-4">
                    <div class="mb-2 text-[13px] font-medium text-[var(--text-mute)]">
                        {{ $t('booking.fullName') }}
                    </div>
                    <input
                        v-model="bookingForm.fullName"
                        type="text"
                        :placeholder="$t('booking.fullNamePlaceholder')"
                        class="w-full rounded-xl border border-[var(--border-strong)] bg-[var(--surface-alt)] px-4 py-3.5 text-[15px] font-semibold text-[var(--text-strong)] placeholder:font-medium placeholder:text-[var(--text-faint)] focus:outline-none"
                    />
                </div>
                <div class="mb-5">
                    <div class="mb-2 text-[13px] font-medium text-[var(--text-mute)]">
                        {{ $t('booking.phoneNumber') }}
                    </div>
                    <div
                        class="flex items-center gap-2.5 rounded-xl border border-[var(--border-strong)] bg-[var(--surface-alt)] px-4 py-3.5 focus-within:border-[var(--green-border)]"
                    >
                        <span
                            class="flex shrink-0 items-center gap-1.5 border-r border-[var(--border-strong)] pr-2.5 text-[15px] font-semibold text-[var(--text-strong)]"
                        >
                            <span class="text-base leading-none">🇺🇸</span>
                            +1
                        </span>
                        <input
                            :value="bookingForm.phone"
                            type="tel"
                            inputmode="numeric"
                            autocomplete="tel-national"
                            :placeholder="$t('booking.phonePlaceholder')"
                            class="w-full bg-transparent text-[15px] font-semibold text-[var(--text-strong)] placeholder:font-medium placeholder:text-[var(--text-faint)] focus:outline-none"
                            @input="onPhoneInput"
                        />
                    </div>
                </div>

                <div class="mb-6 rounded-2xl border border-[var(--surface-mute)] bg-[var(--surface-alt)] p-4 text-xs leading-relaxed text-[var(--text-mute)]">
                    <div class="mb-1.5 flex items-center gap-1.5 font-bold text-[var(--text-heading)]">
                        <Calendar :size="16" class="text-[var(--text-mute)]" />
                        {{ $t('booking.summary') }}
                    </div>
                    <span class="block text-[var(--text-body)]"
                        >{{ $t('booking.summaryDate') }}:
                        <span class="font-bold text-[var(--text-heading)]">{{ selectedDateLabel }}, {{ selectedTimeLabel }}</span></span
                    >
                    <span class="block text-[var(--text-body)]"
                        >{{ $t('booking.summaryProvider') }}:
                        <span class="font-bold text-[var(--text-heading)]">{{ provider.name }}</span></span
                    >
                </div>

                <p
                    v-if="bookingForm.errors.time || bookingForm.errors.fullName || bookingForm.errors.phone || bookingForm.errors.address || bookingForm.errors.atHome"
                    class="mb-3 text-[13px] font-normal text-[var(--danger)]"
                >
                    {{ bookingForm.errors.time || bookingForm.errors.fullName || bookingForm.errors.phone || bookingForm.errors.address || bookingForm.errors.atHome }}
                </p>

                <div class="flex gap-3">
                    <button
                        type="button"
                        class="w-1/3 rounded-xl bg-[var(--surface-mute)] py-3.5 text-[15px] font-semibold text-[var(--text-heading)] hover:bg-[var(--border-strong)]"
                        @click="step = 'datetime'"
                    >
                        {{ $t('booking.back') }}
                    </button>
                    <button
                        type="button"
                        :disabled="bookingForm.processing"
                        class="flex w-2/3 items-center justify-center gap-1.5 rounded-xl bg-[var(--btn-green)] py-3.5 text-[15px] font-semibold text-white hover:bg-[var(--btn-green-hover)] disabled:opacity-60"
                        @click="submitBooking"
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
                    <div class="text-xl font-bold text-[var(--text-strong)]">{{ $t('booking.submittedTitle') }}</div>
                    <p class="mx-auto mt-2 max-w-[280px] text-[13px] font-normal leading-relaxed text-[var(--text-mute)]">
                        {{ $t('booking.submittedBody') }}
                        <span class="font-bold text-[var(--text-heading)]">{{ service.name }}</span>
                        {{ $t('booking.submittedWith') }}
                        <span class="font-bold text-[var(--text-heading)]">{{ provider.name }}</span>
                        {{ $t('booking.submittedPending') }}
                    </p>
                    <p v-if="bookingForm.atHome" class="mx-auto mt-3 max-w-[280px] text-[13px] font-semibold text-[var(--loc-title)]">
                        {{ $t('booking.confirmedAtHome') }}
                    </p>
                    <div class="mx-auto my-6 max-w-[320px] rounded-2xl border border-[var(--green-border)] bg-[var(--green-soft)] p-4 text-left">
                        <div class="flex gap-2.5">
                            <Info :size="16" class="mt-0.5 shrink-0 text-[var(--green-deep)]" />
                            <div class="text-xs leading-relaxed text-[var(--green-deep)]">
                                <div class="font-bold">{{ $t('booking.nextTitle') }}</div>
                                <div class="mt-0.5 font-medium opacity-90">
                                    {{ $t('booking.nextBody', { provider: provider.name }) }}
                                </div>
                            </div>
                        </div>
                    </div>
                    <Link
                        :href="`/p/${provider.slug}`"
                        class="block w-full rounded-xl bg-[var(--btn-bg)] py-3.5 text-center text-[15px] font-semibold text-white hover:bg-[var(--btn-hover)]"
                        >{{ $t('booking.done') }}</Link
                    >
                </div>
            </template>
        </div>
    </div>
</template>
