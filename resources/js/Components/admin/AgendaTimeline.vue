<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { usePreferences } from '../../composables/usePreferences';
import { useFormat } from '../../composables/useFormat';

/**
 * The day as a continuous vertical canvas — the agenda's whole point. Fifteen
 * minutes per row, diagonal hatching wherever the professional does not take
 * appointments (before opening, after closing, lunch), and each appointment as
 * a block whose height IS its duration, so gaps and tight days read at a
 * glance instead of being inferred from a list.
 *
 * All times are minutes since local midnight of the selected day. The blocks
 * come in with a `startMin`/`endMin` already derived from their UTC timestamp
 * by the parent, which is also who decides what "the selected day" means.
 */
const props = defineProps({
    // [{ id, clientName, service, status, startMin, endMin, timeLabel }]
    appointments: { type: Array, required: true },
    // { start, end } in minutes, or null when the day takes no appointments.
    window: { type: Object, default: null },
    // { start, end } in minutes, or null when there is no lunch break.
    lunch: { type: Object, default: null },
    // Shown centered on a closed day (time-off reason, e.g. "Vacaciones").
    closedReason: { type: String, default: '' },
    isToday: { type: Boolean, default: false },
});

const emit = defineEmits(['select', 'create']);

const { timeFormat } = usePreferences();
const { formatTime } = useFormat();

// 16px per quarter hour → 64px per hour. Tall enough that a 30-minute
// appointment still fits a readable line of text, short enough that a
// 12-hour day scrolls in about three screens.
const QUARTER_PX = 16;
const AXIS_PX = 56;

const hasBlocks = computed(() => props.appointments.length > 0);
const isClosed = computed(() => props.window === null);

// Closed day with nothing booked: no grid to show, just the hatched rest.
const restOnly = computed(() => isClosed.value && !hasBlocks.value);

// The visible range: the working window padded to full hours, stretched to
// cover any appointment that lives outside it (a legacy booking, an manual
// exception) so nothing ever renders off-canvas.
const range = computed(() => {
    let start = props.window ? props.window.start : 9 * 60;
    let end = props.window ? props.window.end : 20 * 60;

    for (const appt of props.appointments) {
        start = Math.min(start, appt.startMin);
        end = Math.max(end, appt.endMin);
    }

    return {
        start: Math.floor(start / 60) * 60,
        end: Math.ceil(end / 60) * 60,
    };
});

const canvasHeight = computed(() => ((range.value.end - range.value.start) / 15) * QUARTER_PX);

function yFor(minute) {
    return ((minute - range.value.start) / 15) * QUARTER_PX;
}

function hourLabel(hour) {
    if (timeFormat.value === '24') return `${String(hour).padStart(2, '0')}:00`;
    const hour12 = hour % 12 === 0 ? 12 : hour % 12;
    return `${hour12} ${hour >= 12 ? 'p.m.' : 'a.m.'}`;
}

const hourMarks = computed(() => {
    const marks = [];
    for (let minute = range.value.start; minute <= range.value.end; minute += 60) {
        marks.push({ minute, label: hourLabel(minute / 60), top: yFor(minute) });
    }
    return marks;
});

const quarterMarks = computed(() => {
    const marks = [];
    for (let minute = range.value.start; minute < range.value.end; minute += 15) {
        if (minute % 60 !== 0) marks.push({ minute, top: yFor(minute) });
    }
    return marks;
});

// Everything outside the working window plus lunch, clipped to the visible
// range: rendered as diagonal hatching, Booksy-style.
const restBands = computed(() => {
    const bands = [];
    const { start, end } = range.value;

    if (isClosed.value) {
        bands.push({ from: start, to: end });
    } else {
        if (props.window.start > start) bands.push({ from: start, to: props.window.start });
        if (props.window.end < end) bands.push({ from: props.window.end, to: end });
        if (props.lunch && props.lunch.end > props.lunch.start) {
            bands.push({
                from: Math.max(props.lunch.start, start),
                to: Math.min(props.lunch.end, end),
            });
        }
    }

    return bands
        .filter((band) => band.to > band.from)
        .map((band) => ({ top: yFor(band.from), height: yFor(band.to) - yFor(band.from) }));
});

/**
 * Overlap layout: greedy lane assignment per cluster of transitively
 * overlapping blocks. Confirmed and pending can never overlap (the database
 * exclusion constraint), but a closed appointment no longer blocks its slot,
 * so a rebooked hour would otherwise paint two blocks on top of each other.
 */
const laidOut = computed(() => {
    const sorted = [...props.appointments].sort((a, b) => a.startMin - b.startMin || a.endMin - b.endMin);
    const out = [];
    let cluster = [];
    let clusterEnd = -1;

    const flush = () => {
        if (!cluster.length) return;
        const laneEnds = [];
        for (const block of cluster) {
            let lane = laneEnds.findIndex((endMin) => endMin <= block.startMin);
            if (lane === -1) {
                laneEnds.push(block.endMin);
                lane = laneEnds.length - 1;
            } else {
                laneEnds[lane] = block.endMin;
            }
            block.lane = lane;
        }
        for (const block of cluster) {
            block.laneCount = laneEnds.length;
            out.push(block);
        }
        cluster = [];
    };

    for (const appt of sorted) {
        const block = { ...appt };
        if (cluster.length && block.startMin >= clusterEnd) flush();
        cluster.push(block);
        clusterEnd = Math.max(clusterEnd, block.endMin);
    }
    flush();

    return out.map((block) => {
        const height = Math.max(yFor(block.endMin) - yFor(block.startMin), 22);
        return {
            ...block,
            top: yFor(block.startMin),
            height,
            // Three text lines fit from ~56px; one comfortable line from 32.
            detail: height >= 56 ? 'full' : height >= 32 ? 'row' : 'slim',
        };
    });
});

// The red "now" line, re-anchored every minute while the component lives.
const nowMinutes = ref(new Date().getHours() * 60 + new Date().getMinutes());
let nowTimer = null;

onMounted(() => {
    nowTimer = setInterval(() => {
        const now = new Date();
        nowMinutes.value = now.getHours() * 60 + now.getMinutes();
    }, 60_000);
});

onUnmounted(() => clearInterval(nowTimer));

const nowTop = computed(() => {
    if (!props.isToday) return null;
    if (nowMinutes.value < range.value.start || nowMinutes.value > range.value.end) return null;
    return yFor(nowMinutes.value);
});

// A tap on open canvas proposes that quarter hour for a manual booking.
// Taps on the rest bands and outside the window are ignored — the hatching
// already says "not here".
function handleCanvasClick(event) {
    if (isClosed.value) return;
    const minute = range.value.start + Math.floor(event.offsetY / QUARTER_PX) * 15;
    if (minute < props.window.start || minute >= props.window.end) return;
    if (props.lunch && minute >= props.lunch.start && minute < props.lunch.end) return;
    emit('create', minute);
}
</script>

<template>
    <div
        v-if="restOnly"
        class="agenda-rest flex min-h-[200px] flex-col items-center justify-center gap-1 rounded-2xl border border-[var(--surface-mute)] px-6 py-10 text-center"
    >
        <p class="text-[15px] font-bold text-[var(--text-strong)]">{{ $t('admin.agendaDayOff') }}</p>
        <p class="text-[13px] font-normal text-[var(--text-mute)]">{{ closedReason || $t('admin.dayClosed') }}</p>
    </div>

    <div v-else class="relative select-none" :style="{ height: `${canvasHeight}px` }">
        <!-- Hour axis -->
        <div
            v-for="mark in hourMarks"
            :key="`h-${mark.minute}`"
            class="pointer-events-none absolute left-0 right-0"
            :style="{ top: `${mark.top}px` }"
        >
            <div class="absolute right-0 border-t border-[var(--surface-mute)]" :style="{ left: `${AXIS_PX}px` }" />
            <span class="absolute -top-2 left-0 w-[48px] pr-2 text-right text-[10px] font-medium tabular-nums text-[var(--text-faint)]">
                {{ mark.label }}
            </span>
        </div>
        <div
            v-for="mark in quarterMarks"
            :key="`q-${mark.minute}`"
            class="pointer-events-none absolute right-0 border-t border-dashed border-[var(--surface-mute)] opacity-60"
            :style="{ top: `${mark.top}px`, left: `${AXIS_PX}px` }"
        />

        <!-- Non-working time -->
        <div
            v-for="(band, index) in restBands"
            :key="`rest-${index}`"
            class="agenda-rest pointer-events-none absolute right-0"
            :style="{ top: `${band.top}px`, height: `${band.height}px`, left: `${AXIS_PX}px` }"
        />
        <p
            v-if="isClosed"
            class="pointer-events-none absolute left-1/2 top-6 -translate-x-1/2 rounded-full bg-[var(--surface)] px-3 py-1 text-[12px] font-semibold text-[var(--text-mute)] shadow-[0_1px_3px_rgba(0,0,0,0.08)]"
        >
            {{ closedReason || $t('admin.agendaDayOff') }}
        </p>

        <!-- Tap target for free slots -->
        <button
            v-if="!isClosed"
            type="button"
            class="absolute bottom-0 right-0 top-0 cursor-pointer"
            :style="{ left: `${AXIS_PX}px` }"
            :aria-label="$t('admin.addAppointment')"
            @click="handleCanvasClick"
        />

        <!-- Appointments -->
        <button
            v-for="block in laidOut"
            :key="block.id"
            type="button"
            class="absolute overflow-hidden rounded-lg border px-2 py-1 text-left transition-shadow hover:shadow-[0_2px_8px_rgba(0,0,0,0.12)]"
            :class="{
                'border-[var(--gold-border)] bg-[var(--gold-soft)]': block.status !== 'closed',
                'border-dashed': block.status === 'pending',
                'border-[var(--surface-mute)] bg-[var(--surface-mute)] opacity-80': block.status === 'closed',
            }"
            :style="{
                top: `${block.top}px`,
                height: `${block.height}px`,
                left: `calc(${AXIS_PX}px + ${(block.lane / block.laneCount) * 100}% - ${(AXIS_PX * block.lane) / block.laneCount}px)`,
                width: `calc(${100 / block.laneCount}% - ${AXIS_PX / block.laneCount}px - 4px)`,
            }"
            :aria-label="`${block.timeLabel} · ${block.clientName} · ${block.service}`"
            @click="emit('select', block)"
        >
            <template v-if="block.detail === 'full'">
                <div class="flex items-center gap-1.5 text-[11px] font-semibold tabular-nums" :class="block.status === 'closed' ? 'text-[var(--text-mute)]' : 'text-[var(--gold-text)]'">
                    {{ block.timeLabel }}
                    <span v-if="block.status === 'pending'" class="h-1.5 w-1.5 shrink-0 rounded-full bg-[var(--pending)]" />
                </div>
                <div class="truncate text-[13px] font-semibold leading-tight text-[var(--text-strong)]">{{ block.clientName }}</div>
                <div class="truncate text-[11px] font-normal text-[var(--text-mute)]">{{ block.service }}</div>
            </template>
            <template v-else>
                <div class="flex items-center gap-1.5 truncate leading-tight" :class="block.detail === 'slim' ? 'text-[10px]' : 'text-[12px]'">
                    <span class="font-semibold tabular-nums" :class="block.status === 'closed' ? 'text-[var(--text-mute)]' : 'text-[var(--gold-text)]'">{{ block.timeLabel }}</span>
                    <span v-if="block.status === 'pending'" class="h-1.5 w-1.5 shrink-0 rounded-full bg-[var(--pending)]" />
                    <span class="truncate font-semibold text-[var(--text-strong)]">{{ block.clientName }}</span>
                </div>
            </template>
        </button>

        <!-- Now -->
        <div v-if="nowTop !== null" class="pointer-events-none absolute left-0 right-0 z-10" :style="{ top: `${nowTop}px` }">
            <div class="absolute border-t-2 border-[var(--danger)]" :style="{ left: `${AXIS_PX - 4}px`, right: '0' }" />
            <span class="absolute -top-[3px] h-2 w-2 rounded-full bg-[var(--danger)]" :style="{ left: `${AXIS_PX - 8}px` }" />
        </div>
    </div>
</template>

<style scoped>
/* Booksy's diagonal hatching for non-working time, drawn from the theme's own
   muted surface so it holds up in dark mode without a second definition. */
.agenda-rest {
    background-image: repeating-linear-gradient(
        135deg,
        transparent,
        transparent 6px,
        var(--surface-mute) 6px,
        var(--surface-mute) 7px
    );
}
</style>
