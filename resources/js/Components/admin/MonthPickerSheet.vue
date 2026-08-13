<script setup>
import { computed, ref, watch } from 'vue';
import { ChevronLeft, ChevronRight, X } from '@lucide/vue';
import BottomSheet from '../ui/BottomSheet.vue';
import { usePreferences } from '../../composables/usePreferences';

/**
 * The compact month calendar behind the agenda's "Today ∨" header: jump to
 * any date without scrubbing the 14-day strip. Weeks start on Sunday to match
 * the strip and the US salón it serves.
 */
const props = defineProps({
    selected: { type: Date, required: true },
});

const open = defineModel({ type: Boolean, default: false });
const emit = defineEmits(['pick']);

const { locale } = usePreferences();
const jsLocale = computed(() => (locale.value === 'es' ? 'es-ES' : 'en-US'));

// The month being browsed, first of month. Re-anchored to the selection each
// time the sheet opens so it never resumes on a month from a previous visit.
const viewMonth = ref(new Date(props.selected.getFullYear(), props.selected.getMonth(), 1));

watch(open, (isOpen) => {
    if (isOpen) viewMonth.value = new Date(props.selected.getFullYear(), props.selected.getMonth(), 1);
});

// Only the first letter: CSS `capitalize` would title-case every word and
// turn "agosto de 2026" into "Agosto De 2026", which is wrong in Spanish.
const monthLabel = computed(() => {
    const label = viewMonth.value.toLocaleDateString(jsLocale.value, { month: 'long', year: 'numeric' });

    return label.charAt(0).toUpperCase() + label.slice(1);
});

const weekdayLabels = computed(() => {
    // Any known Sunday works as an anchor; 2026-08-02 is one.
    const sunday = new Date(2026, 7, 2);
    return Array.from({ length: 7 }, (_, i) => {
        const day = new Date(sunday);
        day.setDate(sunday.getDate() + i);
        return day.toLocaleDateString(jsLocale.value, { weekday: 'short' }).replace('.', '').slice(0, 3);
    });
});

const todayKey = new Date().toDateString();
const selectedKey = computed(() => props.selected.toDateString());

const cells = computed(() => {
    const year = viewMonth.value.getFullYear();
    const month = viewMonth.value.getMonth();
    const firstWeekday = new Date(year, month, 1).getDay();
    const daysInMonth = new Date(year, month + 1, 0).getDate();

    const list = Array.from({ length: firstWeekday }, () => null);
    for (let dayNum = 1; dayNum <= daysInMonth; dayNum += 1) {
        const date = new Date(year, month, dayNum);
        list.push({
            date,
            num: dayNum,
            key: date.toDateString(),
            isToday: date.toDateString() === todayKey,
        });
    }
    return list;
});

function shiftMonth(delta) {
    viewMonth.value = new Date(viewMonth.value.getFullYear(), viewMonth.value.getMonth() + delta, 1);
}

function pick(cell) {
    emit('pick', cell.date);
    open.value = false;
}
</script>

<template>
    <BottomSheet v-model="open">
        <div class="mb-4 flex items-center justify-between">
            <div class="text-[20px] font-bold leading-tight tracking-tight text-[var(--text-strong)]">
                {{ $t('admin.agendaPickDay') }}
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

        <div class="mb-3 flex items-center justify-between">
            <button
                type="button"
                :aria-label="$t('admin.agendaPrevMonth')"
                class="flex h-9 w-9 items-center justify-center rounded-full hover:bg-[var(--surface-mute)]"
                @click="shiftMonth(-1)"
            >
                <ChevronLeft :size="18" class="text-[var(--text-strong)]" />
            </button>
            <span class="text-[15px] font-semibold text-[var(--text-strong)]">{{ monthLabel }}</span>
            <button
                type="button"
                :aria-label="$t('admin.agendaNextMonth')"
                class="flex h-9 w-9 items-center justify-center rounded-full hover:bg-[var(--surface-mute)]"
                @click="shiftMonth(1)"
            >
                <ChevronRight :size="18" class="text-[var(--text-strong)]" />
            </button>
        </div>

        <div class="grid grid-cols-7 gap-y-1 pb-2">
            <span
                v-for="label in weekdayLabels"
                :key="label"
                class="pb-1 text-center text-[10px] font-medium uppercase text-[var(--text-faint)]"
            >
                {{ label }}
            </span>
            <template v-for="(cell, index) in cells" :key="cell ? cell.key : `empty-${index}`">
                <span v-if="cell === null" />
                <button
                    v-else
                    type="button"
                    class="mx-auto flex h-10 w-10 items-center justify-center rounded-full text-[14px] tabular-nums transition-colors"
                    :class="cell.key === selectedKey
                        ? 'bg-[var(--chip-bg)] font-bold text-[var(--chip-fg)]'
                        : cell.isToday
                            ? 'font-bold text-[var(--danger)] hover:bg-[var(--surface-mute)]'
                            : 'font-medium text-[var(--text-strong)] hover:bg-[var(--surface-mute)]'"
                    @click="pick(cell)"
                >
                    {{ cell.num }}
                </button>
            </template>
        </div>
    </BottomSheet>
</template>
