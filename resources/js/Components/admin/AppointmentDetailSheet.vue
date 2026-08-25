<script setup>
import { Link } from '@inertiajs/vue3';
import { Phone, X, Check, Ban, Receipt, CalendarClock } from '@lucide/vue';
import BottomSheet from '../ui/BottomSheet.vue';
import Badge from '../ui/Badge.vue';

const props = defineProps({
    appointment: { type: Object, default: null },
    processing: { type: Boolean, default: false },
});

const open = defineModel({ type: Boolean, default: false });
const emit = defineEmits(['confirm', 'reject', 'cancel', 'reschedule']);

const badgeVariant = {
    confirmed: 'confirmed',
    pending: 'pending',
    cancelled: 'cancelled',
    closed: 'closed',
};

const statusKey = {
    confirmed: 'admin.statusConfirmed',
    pending: 'admin.statusPending',
    cancelled: 'admin.statusCancelled',
    closed: 'admin.statusClosed',
};
</script>

<template>
    <BottomSheet v-model="open">
        <template v-if="appointment">
            <div class="mb-4 flex items-center justify-between">
                <div class="text-lg font-bold text-[var(--text-strong)]">{{ $t('admin.appointmentDetail') }}</div>
                <button
                    type="button"
                    class="flex h-8 w-8 items-center justify-center rounded-full bg-[var(--surface-mute)]"
                    @click="open = false"
                >
                    <X :size="16" class="text-[var(--text-mute)]" />
                </button>
            </div>

            <div class="mb-3 flex items-center justify-between rounded-2xl border border-[var(--surface-mute)] bg-[var(--surface-alt)] p-4">
                <div>
                    <div class="text-base font-bold text-[var(--text-strong)]">{{ appointment.clientName }}</div>
                    <div class="mt-1 flex items-center gap-1.5 text-[13px] font-normal text-[var(--text-mute)]">
                        <Phone :size="13" class="text-[var(--text-faint)]" />
                        {{ appointment.clientPhone }}
                    </div>
                </div>
                <Badge :variant="badgeVariant[appointment.status]" uppercase>{{ $t(statusKey[appointment.status]) }}</Badge>
            </div>

            <div class="mb-4 rounded-2xl border border-[var(--surface-mute)] bg-[var(--surface-alt)] px-4">
                <div class="flex items-center justify-between border-b border-[var(--surface-mute)] py-3">
                    <span class="text-[13px] font-medium text-[var(--text-faint)]">{{ $t('admin.service') }}</span>
                    <span class="text-[14px] font-semibold text-[var(--text-strong)]">{{ appointment.service }}</span>
                </div>
                <div class="flex items-center justify-between border-b border-[var(--surface-mute)] py-3">
                    <span class="text-[13px] font-medium text-[var(--text-faint)]">{{ $t('admin.provider') }}</span>
                    <span class="text-[14px] font-semibold text-[var(--text-strong)]">{{ appointment.provider }}</span>
                </div>
                <div class="flex items-center justify-between border-b border-[var(--surface-mute)] py-3">
                    <span class="text-[13px] font-medium text-[var(--text-faint)]">{{ $t('admin.dateTime') }}</span>
                    <span class="text-[14px] font-semibold text-[var(--text-strong)]">{{ appointment.dateLabel }}</span>
                </div>
                <div class="flex items-center justify-between border-b border-[var(--surface-mute)] py-3">
                    <span class="text-[13px] font-medium text-[var(--text-faint)]">{{ $t('admin.duration') }}</span>
                    <span class="text-[14px] font-semibold text-[var(--text-strong)]">{{ appointment.duration }}</span>
                </div>
                <div class="flex items-center justify-between py-3" :class="appointment.atHome && 'border-b border-[var(--surface-mute)]'">
                    <span class="text-[13px] font-medium text-[var(--text-faint)]">{{ $t('admin.price') }}</span>
                    <span class="text-[14px] font-semibold text-[var(--green-text)]"
                        >${{ appointment.price }} · {{ $t('booking.payInStore') }}</span
                    >
                </div>
                <!-- A home visit is the one thing she must not miss when
                     confirming — confirming IS the prior coordination. -->
                <div v-if="appointment.atHome" class="py-3">
                    <span class="block text-[13px] font-bold text-[var(--loc-title)]">{{ $t('admin.atHomeTitle') }}</span>
                    <span class="mt-0.5 block text-[13px] font-normal leading-relaxed text-[var(--text-body)]">
                        {{ appointment.clientAddress || $t('admin.atHomeNoAddress') }}
                    </span>
                </div>
            </div>

            <div class="flex flex-col gap-2.5">
                <div v-if="appointment.status === 'pending'" class="flex gap-2.5">
                    <button
                        type="button"
                        :disabled="processing"
                        class="flex flex-1 items-center justify-center gap-1.5 rounded-xl bg-[var(--surface-mute)] py-3.5 text-[14px] font-semibold text-[var(--text-body)] hover:bg-[var(--border-strong)] disabled:cursor-not-allowed disabled:opacity-60"
                        @click="emit('reject')"
                    >
                        <X :size="15" />
                        {{ $t('admin.reject') }}
                    </button>
                    <button
                        type="button"
                        :disabled="processing"
                        class="flex flex-1 items-center justify-center gap-1.5 rounded-xl bg-[var(--btn-green)] py-3.5 text-[14px] font-semibold text-white hover:bg-[var(--btn-green-hover)] disabled:cursor-not-allowed disabled:opacity-60"
                        @click="emit('confirm')"
                    >
                        <Check :size="15" />
                        {{ $t('admin.confirm') }}
                    </button>
                </div>
                <Link
                    v-if="['confirmed', 'closed'].includes(appointment.status)"
                    :href="`/admin/ventas?clientPhone=${appointment.clientPhoneDigits}&amount=${appointment.price}`"
                    class="flex items-center justify-center gap-1.5 rounded-xl bg-[var(--btn-bg)] py-3.5 text-[14px] font-semibold text-white hover:bg-[var(--btn-hover)]"
                >
                    <Receipt :size="15" />
                    {{ $t('admin.registerSale') }}
                </Link>
                <!--
                    Mover, antes que cancelar. Cancelar y volver a reservar era
                    lo unico que habia hasta hoy, y entre las dos cosas la hora
                    quedaba suelta: otra clienta podia llevarsela.
                -->
                <button
                    v-if="!['cancelled', 'closed'].includes(appointment.status)"
                    type="button"
                    :disabled="processing"
                    class="flex items-center justify-center gap-1.5 rounded-xl border border-[var(--border-strong)] py-3.5 text-[14px] font-semibold text-[var(--text-heading)] hover:bg-[var(--surface-mute)] disabled:cursor-not-allowed disabled:opacity-60"
                    @click="emit('reschedule')"
                >
                    <CalendarClock :size="15" />
                    {{ $t('admin.reschedule') }}
                </button>
                <button
                    v-if="!['cancelled', 'closed'].includes(appointment.status)"
                    type="button"
                    :disabled="processing"
                    class="flex items-center justify-center gap-1.5 rounded-xl border border-[var(--danger-border)] py-3.5 text-[14px] font-semibold text-[var(--danger)] hover:bg-[var(--danger-hover)] disabled:cursor-not-allowed disabled:opacity-60"
                    @click="emit('cancel')"
                >
                    <Ban :size="15" />
                    {{ $t('admin.cancelAppointment') }}
                </button>
            </div>
        </template>
    </BottomSheet>
</template>
