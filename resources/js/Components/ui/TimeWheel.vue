<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import { ChevronUp, ChevronDown } from '@lucide/vue';
import { useFormat } from '../../composables/useFormat';

/**
 * A scrolling time picker, Booksy's.
 *
 * A dropdown of ninety-six options is technically the same choice and nothing
 * like the same gesture: you hunt for a row in a list. Here the hours move
 * under your thumb, the one you land on sits in a box, and its neighbours fade
 * — so "a bit later" is a flick rather than a search. It is the piece of
 * Booksy's schedule the owner singled out ("mira cómo es la selección de hora,
 * súper intuitiva").
 *
 * Built on CSS scroll snapping rather than a drag library: the browser already
 * does the physics, and this way it keeps working with a keyboard, with a
 * mouse wheel, and for anyone who scrolls by dragging the scrollbar.
 */
const props = defineProps({
    // Minutes from midnight, the same unit the schedule stores.
    //
    // Fifteen, not Booksy's five: UpdateScheduleRequest rejects anything that
    // is not a multiple of fifteen, and the availability engine lays its slots
    // on that same grid. A finer wheel here would just produce values the
    // server refuses — and moving the grid is a change to how every
    // appointment is offered, not a change to a picker.
    step: { type: Number, default: 15 },
    min: { type: Number, default: 0 },
    // The same range the dropdown this replaced offered (00:00–23:45). 24:00
    // is deliberately out: the twelve-hour formatter renders it as "12:00 PM",
    // which reads as midday.
    max: { type: Number, default: 24 * 60 - 15 },
    label: { type: String, default: '' },
});

const model = defineModel({ type: Number, required: true });

const { formatTime } = useFormat();

const options = computed(() => {
    const list = [];
    for (let minute = props.min; minute <= props.max; minute += props.step) {
        list.push(minute);
    }
    return list;
});

// Not `label`: that name is already the prop, and a function shadowing it
// would make the template read one and the script the other.
function timeLabel(minute) {
    return formatTime(Math.floor(minute / 60), minute % 60);
}

const track = ref(null);

// Item height in px. Fixed rather than measured so the maths below cannot
// disagree with the layout; it matches the h-11 on each row.
const ITEM = 44;

const selectedIndex = computed(() => {
    const index = options.value.indexOf(model.value);
    if (index !== -1) {
        return index;
    }
    // A stored value that is not on the grid (an older schedule saved on a
    // different step) still has to show *something* sensible: the nearest one.
    return options.value.reduce(
        (best, minute, i) => (Math.abs(minute - model.value) < Math.abs(options.value[best] - model.value) ? i : best),
        0,
    );
});

function scrollToSelected(behavior = 'auto') {
    if (track.value) {
        track.value.scrollTo({ top: selectedIndex.value * ITEM, behavior });
    }
}

// Nothing is written back until the wheel has finished placing itself.
//
// Without this, a value that is not on the grid — an end time of 24:00 saved
// before this existed, say — would be silently rewritten to the nearest one
// the moment the page rendered, and she would find her Sunday shortened by a
// screen she only looked at. A picker may not change a schedule by being
// opened.
const ready = ref(false);

onMounted(() => nextTick(() => {
    scrollToSelected();
    window.setTimeout(() => {
        ready.value = true;
    }, 150);
}));

// Follows the value when something else changes it — "aplicar a todos los
// días" writes every wheel at once.
watch(model, () => {
    if (track.value && Math.round(track.value.scrollTop / ITEM) !== selectedIndex.value) {
        nextTick(() => scrollToSelected('smooth'));
    }
});

// Reading the value off the scroll position rather than tracking gestures:
// whatever moved it — thumb, wheel, keyboard — lands here the same way.
function onScroll() {
    if (!track.value || !ready.value) {
        return;
    }

    const index = Math.round(track.value.scrollTop / ITEM);
    const minute = options.value[Math.max(0, Math.min(options.value.length - 1, index))];

    if (minute !== undefined && minute !== model.value) {
        model.value = minute;
    }
}

function step(direction) {
    const next = options.value[selectedIndex.value + direction];

    if (next !== undefined) {
        model.value = next;
        nextTick(() => scrollToSelected('smooth'));
    }
}
</script>

<template>
    <div class="flex flex-col items-center">
        <span v-if="label" class="mb-1 text-[12px] font-medium text-[var(--text-mute)]">{{ label }}</span>

        <button
            type="button"
            :aria-label="$t('admin.timeEarlier')"
            class="flex h-6 w-full items-center justify-center text-[var(--text-faint)] hover:text-[var(--text-mute)]"
            @click="step(-1)"
        >
            <ChevronUp :size="16" />
        </button>

        <div class="relative w-full">
            <!-- The box the chosen hour sits in. Behind the track and not
                 clickable, so it never eats a scroll gesture. -->
            <div
                class="pointer-events-none absolute inset-x-0 top-1/2 z-0 h-11 -translate-y-1/2 rounded-xl border border-[var(--text-strong)]"
            />

            <div
                ref="track"
                class="relative z-10 h-[220px] snap-y snap-mandatory overflow-y-auto scroll-smooth [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
                role="listbox"
                :aria-label="label"
                @scroll.passive="onScroll"
            >
                <!-- Half the track's height of padding top and bottom, so the
                     first and last hours can reach the middle box. -->
                <div class="h-[88px]" />

                <div
                    v-for="(minute, index) in options"
                    :key="minute"
                    class="flex h-11 snap-center items-center justify-center text-[15px] tabular-nums transition-colors"
                    :class="index === selectedIndex
                        ? 'font-bold text-[var(--text-strong)]'
                        : (Math.abs(index - selectedIndex) === 1
                            ? 'font-normal text-[var(--text-mute)]'
                            : 'font-normal text-[var(--text-faint)] opacity-50')"
                    role="option"
                    :aria-selected="index === selectedIndex"
                >
                    {{ timeLabel(minute) }}
                </div>

                <div class="h-[88px]" />
            </div>
        </div>

        <button
            type="button"
            :aria-label="$t('admin.timeLater')"
            class="flex h-6 w-full items-center justify-center text-[var(--text-faint)] hover:text-[var(--text-mute)]"
            @click="step(1)"
        >
            <ChevronDown :size="16" />
        </button>
    </div>
</template>
