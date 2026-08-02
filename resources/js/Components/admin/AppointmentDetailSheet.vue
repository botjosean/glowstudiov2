<script setup>
import { Phone, X, Check, Ban } from '@lucide/vue';
import BottomSheet from '../ui/BottomSheet.vue';
import Badge from '../ui/Badge.vue';

const props = defineProps({
    appointment: { type: Object, default: null },
});

const open = defineModel({ type: Boolean, default: false });
const emit = defineEmits(['confirm', 'reject', 'cancel']);

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
                <div class="text-lg font-extrabold text-[var(--text-strong)]">{{ $t('admin.appointmentDetail') }}</div>
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
                    <div class="text-base font-extrabold text-[var(--text-strong)]">{{ appointment.clientName }}</div>
                    <div class="mt-1 flex items-center gap-1.5 text-xs font-semibold text-[var(--text-mute)]">
                        <Phone :size="13" class="text-[var(--text-faint)]" />
                        {{ appointment.clientPhone }}
                    </div>
                </div>
                <Badge :variant="badgeVariant[appointment.status]" uppercase>{{ $t(statusKey[appointment.status]) }}</Badge>
            </div>

            <div class="mb-4 rounded-2xl border border-[var(--surface-mute)] bg-[var(--surface-alt)] px-4">
                <div class="flex items-center justify-between border-b border-[var(--surface-mute)] py-3">
                    <span class="text-xs font-bold text-[var(--text-faint)]">{{ $t('admin.service') }}</span>
                    <span class="text-[13px] font-extrabold text-[var(--text-strong)]">{{ appointment.service }}</span>
                </div>
                <div class="flex items-center justify-between border-b border-[var(--surface-mute)] py-3">
                    <span class="text-xs font-bold text-[var(--text-faint)]">{{ $t('admin.provider') }}</span>
                    <span class="text-[13px] font-extrabold text-[var(--text-strong)]">{{ appointment.provider }}</span>
                </div>
                <div class="flex items-center justify-between border-b border-[var(--surface-mute)] py-3">
                    <span class="text-xs font-bold text-[var(--text-faint)]">{{ $t('admin.dateTime') }}</span>
                    <span class="text-[13px] font-extrabold text-[var(--text-strong)]">{{ appointment.dateLabel }}</span>
                </div>
                <div class="flex items-center justify-between border-b border-[var(--surface-mute)] py-3">
                    <span class="text-xs font-bold text-[var(--text-faint)]">{{ $t('admin.duration') }}</span>
                    <span class="text-[13px] font-extrabold text-[var(--text-strong)]">{{ appointment.duration }}</span>
                </div>
                <div class="flex items-center justify-between py-3">
                    <span class="text-xs font-bold text-[var(--text-faint)]">{{ $t('admin.price') }}</span>
                    <span class="text-[13px] font-extrabold text-[var(--green-text)]"
                        >${{ appointment.price }} · {{ $t('booking.payInStore') }}</span
                    >
                </div>
            </div>

            <div class="flex flex-col gap-2.5">
                <div v-if="appointment.status === 'pending'" class="flex gap-2.5">
                    <button
                        type="button"
                        class="flex flex-1 items-center justify-center gap-1.5 rounded-xl bg-[var(--surface-mute)] py-3.5 text-[13px] font-bold text-[var(--text-body)] hover:bg-[var(--border-strong)]"
                        @click="emit('reject')"
                    >
                        <X :size="15" />
                        {{ $t('admin.reject') }}
                    </button>
                    <button
                        type="button"
                        class="flex flex-1 items-center justify-center gap-1.5 rounded-xl bg-[var(--btn-green)] py-3.5 text-[13px] font-bold text-white hover:bg-[var(--btn-green-hover)]"
                        @click="emit('confirm')"
                    >
                        <Check :size="15" />
                        {{ $t('admin.confirm') }}
                    </button>
                </div>
                <button
                    v-if="appointment.status !== 'cancelled'"
                    type="button"
                    class="flex items-center justify-center gap-1.5 rounded-xl border border-[var(--danger-border)] py-3.5 text-[13px] font-bold text-[var(--danger)] hover:bg-[var(--danger-hover)]"
                    @click="emit('cancel')"
                >
                    <Ban :size="15" />
                    {{ $t('admin.cancelAppointment') }}
                </button>
            </div>
        </template>
    </BottomSheet>
</template>
