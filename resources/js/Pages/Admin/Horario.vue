<script setup>
import { computed, ref, watch } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import { Clock4, UtensilsCrossed, Timer, Info, CalendarOff, Plus, Trash2, OctagonX, TriangleAlert, X } from '@lucide/vue';
import AdminLayout from '../../Layouts/AdminLayout.vue';
import Select from '../../Components/ui/Select.vue';
import TimeWheel from '../../Components/ui/TimeWheel.vue';
import Input from '../../Components/ui/Input.vue';
import { useI18n } from 'vue-i18n';
import { useFormat } from '../../composables/useFormat';
import { usePreferences } from '../../composables/usePreferences';
import { useOnboardingReturn } from '../../composables/useOnboardingReturn';

const props = defineProps({
    providerName: { type: String, default: 'Pati' },
    schedule: { type: Object, required: true },
    // { lunchStart, lunchEnd, bufferMinutes, days: [{ weekday, isOpen, workStart, workEnd }] }
    timeOff: { type: Array, default: () => [] },
    // [{ id, startsOn, endsOn, reason }]
});

const { formatTime, formatDuration } = useFormat();
const { t } = useI18n();
const { locale } = usePreferences();
const { returnToInicio } = useOnboardingReturn();

const form = useForm({
    lunchStart: props.schedule.lunchStart,
    lunchEnd: props.schedule.lunchEnd,
    bufferMinutes: props.schedule.bufferMinutes,
    days: props.schedule.days.map((day) => ({ ...day })),
});

/* ====================================================================
 * Una semana no son siete problemas: es uno con excepciones.
 *
 * Nadie piensa «el lunes de 10 a 9, el martes de 10 a 9, el miércoles de 10 a
 * 9…». Piensa «trabajo de 10 a 9, menos los domingos». La pantalla vieja pedía
 * llenar catorce ruedas de precisión, una por hora y por día, y con eso
 * Patricia guardó su primera semana con DOS de siete días mal: el miércoles
 * hasta las 11:45 de la noche y el domingo empezando a las 8:45. No fue
 * torpeza suya — fueron catorce oportunidades de que el dedo resbalara.
 *
 * Ahora hay una sola rueda para el horario de siempre, siete cuadritos para
 * los días, y una lista aparte para el día que de verdad sea distinto.
 *
 * **Por debajo no cambia nada.** `form.days` sigue siendo los siete días con
 * sus horas, y se manda igual que antes. El servidor, el bot y las reservas no
 * se enteran de este cambio: es sólo la manera de llenarlo.
 * ==================================================================== */

// Días que llevan un horario propio. Se deducen al abrir: si un día abierto no
// coincide con el horario más repetido, es que alguien se lo puso a mano.
const excepciones = ref(new Set());

function horarioMasRepetido(days) {
    const cuenta = new Map();

    for (const d of days) {
        if (!d.isOpen) continue;
        const k = d.workStart + '-' + d.workEnd;
        cuenta.set(k, (cuenta.get(k) ?? 0) + 1);
    }

    if (cuenta.size === 0) return { start: 10 * 60, end: 21 * 60 };

    const [mejor] = [...cuenta.entries()].sort((a, b) => b[1] - a[1])[0];
    const [start, end] = mejor.split('-').map(Number);

    return { start, end };
}

const base = ref(horarioMasRepetido(props.schedule.days));

for (const d of props.schedule.days) {
    if (d.isOpen && (d.workStart !== base.value.start || d.workEnd !== base.value.end)) {
        excepciones.value.add(d.weekday);
    }
}

function dia(weekday) {
    return form.days.find((d) => d.weekday === weekday);
}

// Cambiar el horario de siempre mueve todos los días que lo siguen. Los que
// tienen horario propio se quedan como están: para eso son excepciones.
function aplicarBase() {
    for (const d of form.days) {
        if (d.isOpen && !excepciones.value.has(d.weekday)) {
            d.workStart = base.value.start;
            d.workEnd = base.value.end;
        }
    }
}

watch(() => [base.value.start, base.value.end], aplicarBase);

function alternarDia(weekday) {
    const d = dia(weekday);
    d.isOpen = !d.isOpen;

    if (!d.isOpen) {
        // Un día cerrado no puede tener horario propio: al volver a abrirlo
        // hereda el de siempre, que es lo que ella espera.
        excepciones.value.delete(weekday);
        return;
    }

    d.workStart = base.value.start;
    d.workEnd = base.value.end;
}

function agregarExcepcion(weekday) {
    excepciones.value.add(weekday);
    eligiendoExcepcion.value = false;
}

function quitarExcepcion(weekday) {
    excepciones.value.delete(weekday);
    const d = dia(weekday);
    d.workStart = base.value.start;
    d.workEnd = base.value.end;
}

const eligiendoExcepcion = ref(false);

const listaExcepciones = computed(() =>
    form.days.filter((d) => d.isOpen && excepciones.value.has(d.weekday)));

// La frase en palabras. Es la que delata un horario raro de un vistazo: leer
// «de 9:00 AM a 11:45 PM» chirría mucho antes que verlo en una rueda.
const resumen = computed(() => {
    const abiertos = openDays.value;
    if (abiertos.length === 0) return t('admin.summaryNone');

    const params = {
        days: t('admin.summaryDays', abiertos.length),
        list: abiertos.map((d) => dayName(d.weekday).toLowerCase()).join(', '),
        from: formatTime(Math.floor(base.value.start / 60), base.value.start % 60),
        to: formatTime(Math.floor(base.value.end / 60), base.value.end % 60),
        n: listaExcepciones.value.length,
    };

    return listaExcepciones.value.length > 0
        ? t('admin.summaryExtra', params)
        : t('admin.summaryLine', params);
});

/**
 * El freno para el dedo resbalado.
 *
 * No bloquea: hay quien de verdad abre doce horas, y una app que le discute a
 * la dueña cuánto trabaja es una app insoportable. Sólo pregunta — y ese
 * aviso solo habría atajado los dos errores de Patricia.
 */
const aviso = computed(() => {
    const largo = (base.value.end - base.value.start) / 60;
    const params = {
        hours: String(Math.round(largo * 10) / 10).replace('.0', ''),
        from: formatTime(Math.floor(base.value.start / 60), base.value.start % 60),
        to: formatTime(Math.floor(base.value.end / 60), base.value.end % 60),
    };

    if (base.value.end <= base.value.start) return t('admin.backwardsWarn');
    if (largo > 12) return t('admin.longDayWarn', params);

    return null;
});

// Lunch is optional: the rest of the stack already treats an empty window
// (lunchStart === lunchEnd) as "no lunch" — validation allows it (gte) and
// GenerateAvailableSlots skips the filter — so the switch only collapses the
// window to 0–0, remembering the last real one to restore on re-enable.
const lunchEnabled = ref(props.schedule.lunchStart < props.schedule.lunchEnd);
const lastLunch = {
    start: lunchEnabled.value ? props.schedule.lunchStart : 13 * 60,
    end: lunchEnabled.value ? props.schedule.lunchEnd : 14 * 60,
};

function toggleLunch() {
    if (lunchEnabled.value) {
        lastLunch.start = form.lunchStart;
        lastLunch.end = form.lunchEnd;
        form.lunchStart = 0;
        form.lunchEnd = 0;
        lunchEnabled.value = false;

        return;
    }

    form.lunchStart = lastLunch.start;
    form.lunchEnd = lastLunch.end;
    lunchEnabled.value = true;
}

function submit() {
    form.put('/admin/horario', { preserveScroll: true, preserveState: true, onSuccess: returnToInicio });
}

/**
 * Day names come from the platform rather than from fourteen translation keys,
 * so they follow whatever language the panel is in without a second list to
 * keep in step. 2026-08-09 is a Sunday, which makes weekday 0 land on Sunday
 * exactly as Carbon numbers it server-side.
 */
function dayName(weekday) {
    return new Date(Date.UTC(2026, 7, 9 + weekday))
        .toLocaleDateString(locale.value === 'es' ? 'es-ES' : 'en-US', { weekday: 'long', timeZone: 'UTC' });
}

const bufferOptions = computed(() =>
    Array.from({ length: 21 }, (_, i) => i * 15).map((minutes) => ({
        value: minutes,
        label: formatDuration(minutes),
    })),
);

const label = (minutes) => formatTime(Math.floor(minutes / 60), minutes % 60);

const openDays = computed(() => form.days.filter((day) => day.isOpen));

const scheduleError = computed(() => {
    const keys = Object.keys(form.errors).filter((key) => key.startsWith('days') || key.startsWith('lunch') || key.startsWith('buffer'));
    return keys.length ? form.errors[keys[0]] : '';
});

// ------------------------------------------------------- parar la agenda
const pausing = ref(false);

/** Las fechas las decide el servidor, con el reloj del salón, no el del navegador. */
function pauseAgenda(days) {
    pausing.value = true;
    router.post('/admin/horario/parar', { days }, {
        preserveScroll: true,
        preserveState: true,
        onFinish: () => { pausing.value = false; },
    });
}

// ------------------------------------------------------------ time off
const timeOffForm = useForm({ startsOn: '', endsOn: '', reason: '' });
const removing = ref(null);

function addTimeOff() {
    timeOffForm.post('/admin/horario/ausencias', {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => timeOffForm.reset(),
    });
}

function removeTimeOff(id) {
    removing.value = id;
    router.delete(`/admin/horario/ausencias/${id}`, {
        preserveScroll: true,
        preserveState: true,
        onFinish: () => { removing.value = null; },
    });
}

function timeOffLabel(off) {
    const format = (iso) => new Date(`${iso}T12:00:00`).toLocaleDateString(
        locale.value === 'es' ? 'es-ES' : 'en-US',
        { day: 'numeric', month: 'short' },
    );

    return off.startsOn === off.endsOn ? format(off.startsOn) : `${format(off.startsOn)} – ${format(off.endsOn)}`;
}
</script>

<template>
    <AdminLayout :provider-name="providerName">
        <div class="flex flex-col gap-4 p-4 pb-6">
            <div class="px-1 pt-2">
                <h1 class="text-[22px] font-bold leading-tight tracking-tight text-[var(--text-strong)]">
                    {{ $t('admin.scheduleTitle') }}
                </h1>
                <p class="mt-1 text-[13px] font-medium leading-relaxed text-[var(--text-mute)]">
                    {{ $t('admin.scheduleSubtitle') }}
                </p>
            </div>

            <!--
                Deliberately the first thing on the page: when this is needed
                it is needed in seconds, from a phone, probably while something
                has already gone wrong.
            -->
            <div class="rounded-2xl border border-[var(--danger-border)] bg-[var(--danger-hover)] p-4">
                <div class="mb-3 flex items-center gap-2.5">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-[10px] bg-[var(--danger-border)]">
                        <OctagonX :size="16" class="text-[var(--danger)]" />
                    </div>
                    <div class="min-w-0">
                        <div class="text-[15px] font-semibold text-[var(--text-strong)]">{{ $t('admin.agendaPauseTitle') }}</div>
                        <div class="text-[12px] font-normal leading-relaxed text-[var(--text-mute)]">{{ $t('admin.agendaPauseHint') }}</div>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <button
                        type="button"
                        :disabled="pausing"
                        class="rounded-xl border border-[var(--danger-border)] bg-[var(--surface)] py-3 text-[14px] font-semibold text-[var(--danger)] disabled:opacity-50"
                        @click="pauseAgenda(1)"
                    >
                        {{ $t('admin.agendaPauseToday') }}
                    </button>
                    <button
                        type="button"
                        :disabled="pausing"
                        class="rounded-xl border border-[var(--danger-border)] bg-[var(--surface)] py-3 text-[14px] font-semibold text-[var(--danger)] disabled:opacity-50"
                        @click="pauseAgenda(2)"
                    >
                        {{ $t('admin.agendaPauseTomorrow') }}
                    </button>
                </div>
            </div>

            <div class="rounded-2xl border border-[var(--surface-mute)] bg-[var(--surface-alt)] p-4">
                <div class="mb-3 flex items-center gap-2.5">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-[10px] bg-[var(--chip-bg)]">
                        <Clock4 :size="16" class="text-[var(--chip-fg)]" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="text-[15px] font-semibold text-[var(--text-strong)]">{{ $t('admin.usualHours') }}</div>
                        <div class="text-[12px] font-normal text-[var(--text-faint)]">{{ $t('admin.usualHoursHint') }}</div>
                    </div>
                </div>

                <!--
                    Una sola rueda, no catorce. La rueda se queda porque se
                    siente mejor que dos listas de noventa y seis opciones; el
                    problema nunca fue la rueda, fue tener catorce.
                -->
                <div class="grid grid-cols-2 gap-3">
                    <TimeWheel v-model="base.start" :label="$t('admin.iStart')" />
                    <TimeWheel v-model="base.end" :label="$t('admin.iEnd')" />
                </div>

                <div
                    v-if="aviso"
                    class="mt-3 flex items-start gap-2 rounded-xl border border-[var(--amber-border)] bg-[var(--amber-soft)] px-3 py-2.5"
                >
                    <TriangleAlert :size="15" class="mt-0.5 shrink-0 text-[var(--amber-text)]" />
                    <span class="text-[13px] font-medium leading-snug text-[var(--amber-text)]">{{ aviso }}</span>
                </div>

                <!-- Los siete días en una línea: la semana entera de un
                     vistazo, que es justo lo que no había. -->
                <div class="mt-5 flex gap-1.5">
                    <button
                        v-for="day in form.days"
                        :key="day.weekday"
                        type="button"
                        role="switch"
                        :aria-checked="day.isOpen"
                        :aria-label="dayName(day.weekday)"
                        class="flex aspect-square flex-1 items-center justify-center rounded-xl border text-[14px] font-bold uppercase transition-colors"
                        :class="day.isOpen
                            ? 'border-[var(--chip-bg)] bg-[var(--chip-bg)] text-[var(--chip-fg)]'
                            : 'border-[var(--border-strong)] bg-[var(--surface)] text-[var(--text-faint)]'"
                        @click="alternarDia(day.weekday)"
                    >{{ dayName(day.weekday).charAt(0) }}</button>
                </div>

                <!-- Y escrito en palabras. Leer «de 9:00 AM a 11:45 PM»
                     chirría mucho antes que verlo en una rueda. -->
                <p class="mt-3 text-[13px] font-normal leading-relaxed text-[var(--text-mute)]">{{ resumen }}</p>
            </div>

            <!-- ============ DÍAS DISTINTOS ============ -->
            <div class="rounded-2xl border border-[var(--surface-mute)] bg-[var(--surface-alt)] p-4">
                <div class="mb-3 flex items-center gap-2.5">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-[10px] bg-[var(--gold-soft)]">
                        <Clock4 :size="17" class="text-[var(--gold-text)]" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="text-[15px] font-semibold text-[var(--text-strong)]">{{ $t('admin.differentDay') }}</div>
                        <div class="text-[12px] font-normal text-[var(--text-faint)]">{{ $t('admin.differentDayHint') }}</div>
                    </div>
                </div>

                <div v-for="day in listaExcepciones" :key="day.weekday" class="mb-3 rounded-xl border border-[var(--surface-mute)] bg-[var(--surface)] p-3">
                    <div class="mb-2 flex items-center justify-between gap-2">
                        <span class="text-[14px] font-semibold capitalize text-[var(--text-strong)]">{{ dayName(day.weekday) }}</span>
                        <button
                            type="button"
                            :aria-label="$t('common.clear')"
                            class="flex h-7 w-7 items-center justify-center rounded-full bg-[var(--surface-mute)] hover:bg-[var(--border-strong)]"
                            @click="quitarExcepcion(day.weekday)"
                        >
                            <X :size="13" class="text-[var(--text-mute)]" />
                        </button>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <TimeWheel v-model="day.workStart" :label="$t('admin.iStart')" />
                        <TimeWheel v-model="day.workEnd" :label="$t('admin.iEnd')" />
                    </div>
                </div>

                <button
                    v-if="!eligiendoExcepcion"
                    type="button"
                    class="w-full rounded-xl border border-dashed border-[var(--border-strong)] py-2.5 text-[13px] font-semibold text-[var(--text-mute)] hover:bg-[var(--surface-mute)]"
                    @click="eligiendoExcepcion = true"
                >
                    {{ $t('admin.addDifferentDay') }}
                </button>

                <div v-else class="flex gap-1.5">
                    <button
                        v-for="day in form.days"
                        :key="day.weekday"
                        type="button"
                        :aria-label="dayName(day.weekday)"
                        :disabled="!day.isOpen || excepciones.has(day.weekday)"
                        class="flex-1 rounded-xl border border-[var(--border-strong)] bg-[var(--surface)] py-2 text-[13px] font-bold uppercase text-[var(--text-strong)] disabled:opacity-30"
                        @click="agregarExcepcion(day.weekday)"
                    >{{ dayName(day.weekday).charAt(0) }}</button>
                </div>
            </div>

            <div class="rounded-2xl border border-[var(--surface-mute)] bg-[var(--surface-alt)] p-4">
                <div class="mb-3 flex items-center gap-2.5">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-[10px] bg-[var(--loc-chip)]">
                        <UtensilsCrossed :size="16" class="text-[var(--loc-text)]" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="text-[15px] font-semibold text-[var(--text-strong)]">{{ $t('admin.lunch') }}</div>
                        <div class="text-[12px] font-normal text-[var(--text-faint)]">{{ $t('admin.lunchHint') }}</div>
                    </div>
                    <button
                        type="button"
                        role="switch"
                        :aria-checked="lunchEnabled"
                        :aria-label="$t('admin.lunch')"
                        class="relative h-7 w-12 shrink-0 rounded-full transition-colors"
                        :class="lunchEnabled ? 'bg-[var(--btn-green)]' : 'bg-[var(--border-strong)]'"
                        @click="toggleLunch"
                    >
                        <span
                            class="absolute top-1 h-5 w-5 rounded-full bg-white shadow transition-all"
                            :class="lunchEnabled ? 'left-6' : 'left-1'"
                        />
                    </button>
                </div>
                <div v-if="lunchEnabled" class="grid grid-cols-2 gap-3">
                    <TimeWheel v-model="form.lunchStart" :label="$t('admin.startTime')" />
                    <TimeWheel v-model="form.lunchEnd" :label="$t('admin.endTime')" />
                </div>
                <div v-else class="text-[13px] font-normal text-[var(--text-faint)]">
                    {{ $t('admin.lunchOff') }}
                </div>
            </div>

            <div class="rounded-2xl border border-[var(--surface-mute)] bg-[var(--surface-alt)] p-4">
                <div class="mb-3 flex items-center gap-2.5">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-[10px] bg-[var(--blue-soft)]">
                        <Timer :size="16" class="text-[var(--blue-text)]" />
                    </div>
                    <div>
                        <div class="text-[15px] font-semibold text-[var(--text-strong)]">{{ $t('admin.buffer') }}</div>
                        <div class="text-[12px] font-normal text-[var(--text-faint)]">{{ $t('admin.bufferHint') }}</div>
                    </div>
                </div>
                <Select v-model="form.bufferMinutes" :options="bufferOptions" />
            </div>

            <div class="flex items-start gap-2.5 rounded-2xl border border-[var(--green-border)] bg-[var(--green-soft)] p-4">
                <Info :size="16" class="mt-0.5 shrink-0 text-[var(--green-text)]" />
                <div class="text-[13px] font-normal leading-relaxed text-[var(--green-deep)]">
                    <template v-if="openDays.length === 0">{{ $t('admin.scheduleSummaryClosed') }}</template>
                    <template v-else>
                        {{ $t('admin.scheduleSummaryDays', { days: openDays.length }) }},
                        <template v-if="lunchEnabled">
                            {{ $t('admin.scheduleSummaryLunch') }}
                            <span class="font-bold">{{ label(form.lunchStart) }} {{ $t('admin.to') }} {{ label(form.lunchEnd) }}</span>
                        </template>
                        <span v-else class="font-bold">{{ $t('admin.scheduleSummaryNoLunch') }}</span>
                        {{ $t('admin.scheduleSummaryAnd') }}
                        <span class="font-bold">{{ formatDuration(form.bufferMinutes) }}</span>
                        {{ $t('admin.scheduleSummaryBetween') }}
                    </template>
                </div>
            </div>

            <p v-if="scheduleError" class="text-[13px] font-normal text-[var(--danger)]">{{ scheduleError }}</p>

            <button
                type="button"
                :disabled="form.processing"
                class="w-full rounded-xl bg-[var(--btn-bg)] py-3.5 text-[15px] font-semibold text-white hover:bg-[var(--btn-hover)] disabled:cursor-not-allowed disabled:opacity-60"
                @click="submit"
            >
                {{ form.processing ? $t('common.saving') : $t('admin.saveChanges') }}
            </button>

            <div class="rounded-2xl border border-[var(--surface-mute)] bg-[var(--surface-alt)] p-4">
                <div class="mb-3 flex items-center gap-2.5">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-[10px] bg-[var(--amber-soft)]">
                        <CalendarOff :size="16" class="text-[var(--amber-text)]" />
                    </div>
                    <div>
                        <div class="text-[15px] font-semibold text-[var(--text-strong)]">{{ $t('admin.timeOffTitle') }}</div>
                        <div class="text-[12px] font-normal text-[var(--text-faint)]">{{ $t('admin.timeOffHint') }}</div>
                    </div>
                </div>

                <div v-if="timeOff.length" class="mb-3 flex flex-col gap-2">
                    <div
                        v-for="off in timeOff"
                        :key="off.id"
                        class="flex items-center justify-between gap-3 rounded-xl border border-[var(--surface-mute)] bg-[var(--surface)] px-3 py-2.5"
                    >
                        <div class="min-w-0">
                            <div class="text-[14px] font-semibold text-[var(--text-strong)]">{{ timeOffLabel(off) }}</div>
                            <div v-if="off.reason" class="truncate text-[12px] font-normal text-[var(--text-mute)]">{{ off.reason }}</div>
                        </div>
                        <button
                            type="button"
                            :disabled="removing === off.id"
                            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-[10px] bg-[var(--surface-mute)] disabled:opacity-50"
                            :aria-label="$t('admin.timeOffRemove')"
                            @click="removeTimeOff(off.id)"
                        >
                            <Trash2 :size="14" class="text-[var(--danger)]" />
                        </button>
                    </div>
                </div>
                <p v-else class="mb-3 text-[13px] font-normal text-[var(--text-faint)]">{{ $t('admin.timeOffEmpty') }}</p>

                <div class="grid grid-cols-2 gap-3">
                    <label class="flex flex-col gap-2">
                        <span class="text-[13px] font-medium text-[var(--text-mute)]">{{ $t('admin.timeOffFrom') }}</span>
                        <input
                            v-model="timeOffForm.startsOn"
                            type="date"
                            class="w-full rounded-xl border border-[var(--border-strong)] bg-[var(--surface)] px-3 py-3 text-[15px] text-[var(--text-strong)] focus:border-[var(--text-strong)] focus:outline-none"
                        />
                    </label>
                    <label class="flex flex-col gap-2">
                        <span class="text-[13px] font-medium text-[var(--text-mute)]">{{ $t('admin.timeOffTo') }}</span>
                        <input
                            v-model="timeOffForm.endsOn"
                            type="date"
                            class="w-full rounded-xl border border-[var(--border-strong)] bg-[var(--surface)] px-3 py-3 text-[15px] text-[var(--text-strong)] focus:border-[var(--text-strong)] focus:outline-none"
                        />
                    </label>
                </div>
                <div class="mt-3">
                    <Input v-model="timeOffForm.reason" :label="$t('admin.timeOffReason')" :placeholder="$t('admin.timeOffReasonPlaceholder')" />
                </div>

                <p v-if="timeOffForm.errors.startsOn || timeOffForm.errors.endsOn" class="mt-2 text-[13px] font-normal text-[var(--danger)]">
                    {{ timeOffForm.errors.startsOn || timeOffForm.errors.endsOn }}
                </p>

                <button
                    type="button"
                    :disabled="timeOffForm.processing || !timeOffForm.startsOn || !timeOffForm.endsOn"
                    class="mt-3 flex w-full items-center justify-center gap-1.5 rounded-xl border border-[var(--border-strong)] py-3 text-[14px] font-semibold text-[var(--text-body)] hover:bg-[var(--surface-mute)] disabled:cursor-not-allowed disabled:opacity-50"
                    @click="addTimeOff"
                >
                    <Plus :size="15" />
                    {{ timeOffForm.processing ? $t('common.saving') : $t('admin.timeOffAdd') }}
                </button>
            </div>
        </div>
    </AdminLayout>
</template>
