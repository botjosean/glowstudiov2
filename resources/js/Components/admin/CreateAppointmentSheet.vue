<script setup>
import { ref, computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { X } from '@lucide/vue';
import { useI18n } from 'vue-i18n';
import BottomSheet from '../ui/BottomSheet.vue';
import OutlinedInput from '../ui/OutlinedInput.vue';
import OutlinedSelect from '../ui/OutlinedSelect.vue';
import Chip from '../ui/Chip.vue';
import { useFormat } from '../../composables/useFormat';

/**
 * The panel's own booking flow: walk-ins and phone bookings the professional
 * takes herself. The hour chips come from the same availability endpoint the
 * whole platform uses, so what she can pick here is exactly what the bot and
 * the public page would offer — never more.
 *
 * The phone is optional on purpose: forcing it invites invented numbers,
 * and an invented number can belong to a real person the WhatsApp assistant
 * would then mistake for this client. The hint under the field says what is
 * actually lost by leaving it empty.
 */
const props = defineProps({
    services: { type: Array, required: true },
    // The day being booked — the sheet inherits it from the agenda's
    // selected day rather than asking again.
    date: { type: Date, required: true },
    // 'HH:MM' the agenda canvas was tapped on. Only honored if that hour is
    // actually free: the availability endpoint stays the sole authority.
    preselectHour: { type: String, default: '' },
    // Filled when the agenda was reached from a client card ("Cita nueva"):
    // the professional already said who this is for.
    prefillName: { type: String, default: '' },
    prefillPhone: { type: String, default: '' },
});

const open = defineModel({ type: Boolean, default: false });
const emit = defineEmits(['created']);

const { t } = useI18n();
const { formatDuration, formatDayLabel } = useFormat();

const form = useForm({
    serviceId: null,
    fecha: '',
    hora: '',
    clientName: '',
    clientPhone: '',
});

const serviceOptions = computed(() => props.services.map((service) => ({
    value: service.id,
    label: `${service.name} · ${formatDuration(service.durationMinutes)} · $${service.price}`,
})));

// Local calendar date, never toISOString(): the provider's day is what the
// agenda shows, and UTC conversion would shift evenings to the next day.
function toYmd(date) {
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}

const slots = ref([]);
const slotsLoading = ref(false);
const slotsError = ref('');

async function loadSlots() {
    if (!form.serviceId) {
        slots.value = [];

        return;
    }

    slotsLoading.value = true;
    slotsError.value = '';
    slots.value = [];
    form.hora = '';

    try {
        const res = await fetch(`/admin/citas/horas?serviceId=${form.serviceId}&fecha=${form.fecha}`, {
            headers: { Accept: 'application/json' },
        });
        if (!res.ok) throw new Error('slots');
        slots.value = (await res.json()).horas;
        if (props.preselectHour && slots.value.some((slot) => slot.value === props.preselectHour)) {
            form.hora = props.preselectHour;
        }
    } catch {
        slotsError.value = t('admin.slotsError');
    } finally {
        slotsLoading.value = false;
    }
}

watch(open, (isOpen) => {
    if (!isOpen) return;
    form.reset();
    form.clearErrors();
    form.serviceId = props.services[0]?.id ?? null;
    form.fecha = toYmd(props.date);
    form.clientName = props.prefillName;
    form.clientPhone = props.prefillPhone;
    loadSlots();
});

watch(() => form.serviceId, () => {
    if (open.value) loadSlots();
});

// Same live US mask as the sign-up form; the guard against reassigning an
// identical value is what keeps the watcher from looping.
watch(() => form.clientPhone, (value) => {
    const digits = (value || '').replace(/\D/g, '').replace(/^1(?=\d{10})/, '').slice(0, 10);
    let out = digits;
    if (digits.length > 6) out = `(${digits.slice(0, 3)}) ${digits.slice(3, 6)}-${digits.slice(6)}`;
    else if (digits.length > 3) out = `(${digits.slice(0, 3)}) ${digits.slice(3)}`;
    if (out !== value) form.clientPhone = out;
});

const incomplete = computed(() => !form.hora || !form.clientName.trim());

const selectedService = computed(() => props.services.find((service) => service.id === form.serviceId));

function submit() {
    if (incomplete.value || form.processing) return;

    // Captured before onSuccess: what the WhatsApp prompt needs to offer
    // notifying the client right away.
    const created = {
        clientName: form.clientName.trim(),
        clientPhone: form.clientPhone,
        phoneDigits: (form.clientPhone || '').replace(/\D/g, '').replace(/^1(?=\d{10})/, ''),
        service: selectedService.value?.name ?? '',
        startsAt: new Date(`${form.fecha}T${form.hora}:00`).toISOString(),
    };

    form.post('/admin/citas', {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            open.value = false;
            emit('created', created);
        },
    });
}
</script>

<template>
    <BottomSheet v-model="open">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <div class="text-[20px] font-bold leading-tight tracking-tight text-[var(--text-strong)]">
                    {{ $t('admin.addAppointment') }}
                </div>
                <div class="mt-0.5 text-[13px] font-normal capitalize text-[var(--text-mute)]">{{ formatDayLabel(date) }}</div>
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

        <OutlinedSelect
            id="appointment-service"
            v-model="form.serviceId"
            class="mb-5"
            :label="$t('admin.service')"
            :options="serviceOptions"
            :error="form.errors.serviceId"
        />

        <div class="mb-5">
            <span class="mb-2 block text-[13px] font-medium text-[var(--text-mute)]">{{ $t('admin.pickHour') }}</span>
            <p v-if="slotsLoading" class="text-[13px] font-normal text-[var(--text-faint)]">{{ $t('admin.slotsLoading') }}</p>
            <p v-else-if="slotsError" class="text-[13px] font-normal text-[var(--danger)]">{{ slotsError }}</p>
            <p v-else-if="slots.length === 0" class="text-[13px] font-normal text-[var(--text-mute)]">{{ $t('admin.slotsEmpty') }}</p>
            <div v-else class="grid max-h-[190px] grid-cols-3 gap-2 overflow-y-auto pr-1">
                <Chip
                    v-for="slot in slots"
                    :key="slot.value"
                    :active="form.hora === slot.value"
                    class="justify-center"
                    @click="form.hora = slot.value"
                >
                    {{ slot.label }}
                </Chip>
            </div>
            <p v-if="form.errors.hora || form.errors.time" class="mt-1.5 text-[13px] font-normal text-[var(--danger)]">
                {{ form.errors.hora || form.errors.time }}
            </p>
        </div>

        <OutlinedInput
            id="appointment-client"
            v-model="form.clientName"
            class="mb-5"
            :label="$t('admin.appointmentClientName')"
            :error="form.errors.clientName"
            clearable
        />

        <div class="mb-2">
            <OutlinedInput
                id="appointment-phone"
                v-model="form.clientPhone"
                type="tel"
                inputmode="tel"
                :label="$t('admin.appointmentPhone')"
                :error="form.errors.clientPhone"
            />
            <p class="mt-1.5 text-[12px] font-normal text-[var(--text-faint)]">{{ $t('admin.phoneOptionalHint') }}</p>
        </div>

        <div class="mt-6">
            <button
                type="button"
                :disabled="incomplete || form.processing"
                class="w-full rounded-xl bg-[var(--btn-bg)] py-3.5 text-[15px] font-semibold text-white hover:bg-[var(--btn-hover)] disabled:cursor-not-allowed disabled:opacity-60"
                @click="submit"
            >
                {{ form.processing ? $t('common.saving') : $t('admin.addAppointment') }}
            </button>
            <p v-if="incomplete" class="mt-2 text-center text-[12px] font-normal text-[var(--text-faint)]">
                {{ $t('admin.addAppointmentMissing') }}
            </p>
        </div>
    </BottomSheet>
</template>
