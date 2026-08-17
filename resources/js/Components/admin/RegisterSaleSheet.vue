<script setup>
import { computed, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { X } from '@lucide/vue';
import BottomSheet from '../ui/BottomSheet.vue';
import OutlinedInput from '../ui/OutlinedInput.vue';
import OutlinedSelect from '../ui/OutlinedSelect.vue';
import Chip from '../ui/Chip.vue';
import { useI18n } from 'vue-i18n';
import { useHaptics } from '../../composables/useHaptics';

/**
 * Record a charge: who (optional, from the client book), how much, the tip
 * and how she paid. Mirrors Booksy's checkout minus the processor — this
 * writes the ledger, the money already moved at the counter.
 */
const props = defineProps({
    clients: { type: Array, required: true },
    // [{ id, name }]
    methods: { type: Array, required: true },
    // Accepted payment method slugs, e.g. ['cash', 'zelle'].
    prefill: { type: Object, default: null },
    // { clientId, amount } | null — from a confirmed appointment's "Registrar venta".
});

const open = defineModel({ type: Boolean, default: false });

const { t } = useI18n();
const haptics = useHaptics();

const form = useForm({
    clientId: null,
    amount: '',
    tip: 0,
    paymentMethod: null,
});

// Percentages, not fixed dollars: a $40 haircut and a $200 balayage deserve
// different suggestions and the math is the register's job, not hers.
const TIP_CHOICES = [0, 10, 15, 20];
const tipChoice = ref(0);
const customTip = ref('');

watch(open, (isOpen) => {
    if (!isOpen) return;
    form.reset();
    form.clearErrors();
    form.paymentMethod = props.methods[0] ?? null;
    tipChoice.value = 0;
    customTip.value = '';
    if (props.prefill) {
        form.clientId = props.prefill.clientId ?? null;
        form.amount = props.prefill.amount !== null ? String(props.prefill.amount) : '';
    }
});

const amountNumber = computed(() => {
    const value = parseFloat(String(form.amount).replace(',', '.'));
    return Number.isFinite(value) && value > 0 ? value : 0;
});

const tipNumber = computed(() => {
    if (tipChoice.value === 'custom') {
        const value = parseFloat(String(customTip.value).replace(',', '.'));
        return Number.isFinite(value) && value > 0 ? Math.round(value * 100) / 100 : 0;
    }
    return Math.round(amountNumber.value * tipChoice.value) / 100;
});

const total = computed(() => Math.round((amountNumber.value + tipNumber.value) * 100) / 100);

const clientOptions = computed(() => [
    { value: null, label: t('admin.saleClientNone') },
    ...props.clients.map((client) => ({ value: client.id, label: client.name })),
]);

const methodKey = {
    cash: 'admin.pmCash', card: 'admin.pmCard', zelle: 'admin.pmZelle', cashapp: 'admin.pmCashapp',
    venmo: 'admin.pmVenmo', paypal: 'admin.pmPaypal', check: 'admin.pmCheck', other: 'admin.pmOther',
};

const incomplete = computed(() => amountNumber.value <= 0 || !form.paymentMethod);

function submit() {
    if (incomplete.value || form.processing) return;

    form.transform((data) => ({
        ...data,
        amount: amountNumber.value,
        tip: tipNumber.value,
    })).post('/admin/ventas', {
        preserveScroll: true,
        onSuccess: () => {
            haptics.success();
            open.value = false;
        },
        onError: () => haptics.error(),
    });
}
</script>

<template>
    <BottomSheet v-model="open">
        <div class="mb-6 flex items-center justify-between">
            <div class="text-[20px] font-bold leading-tight tracking-tight text-[var(--text-strong)]">
                {{ $t('admin.registerSale') }}
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
            id="sale-client"
            v-model="form.clientId"
            class="mb-5"
            :label="$t('admin.saleClient')"
            :options="clientOptions"
            :error="form.errors.clientId"
        />

        <OutlinedInput
            id="sale-amount"
            v-model="form.amount"
            class="mb-5"
            type="text"
            inputmode="decimal"
            :label="$t('admin.saleAmount')"
            :error="form.errors.amount"
        />

        <div class="mb-5">
            <span class="mb-2 block text-[13px] font-medium text-[var(--text-mute)]">{{ $t('admin.saleTip') }}</span>
            <div class="flex flex-wrap gap-2">
                <Chip :active="tipChoice === 0" class="justify-center" @click="tipChoice = 0">
                    {{ $t('admin.saleTipNone') }}
                </Chip>
                <Chip
                    v-for="pct in TIP_CHOICES.slice(1)"
                    :key="pct"
                    :active="tipChoice === pct"
                    class="justify-center"
                    @click="tipChoice = pct"
                >
                    {{ pct }}%
                </Chip>
                <Chip :active="tipChoice === 'custom'" class="justify-center" @click="tipChoice = 'custom'">
                    {{ $t('admin.saleTipCustom') }}
                </Chip>
            </div>
            <div v-if="tipChoice === 'custom'" class="mt-3">
                <OutlinedInput
                    id="sale-tip-custom"
                    v-model="customTip"
                    type="text"
                    inputmode="decimal"
                    :label="`${$t('admin.saleTip')} ($)`"
                />
            </div>
            <p v-if="form.errors.tip" class="mt-1.5 text-[13px] font-normal text-[var(--danger)]">{{ form.errors.tip }}</p>
        </div>

        <div class="mb-2">
            <span class="mb-2 block text-[13px] font-medium text-[var(--text-mute)]">{{ $t('admin.saleMethod') }}</span>
            <div class="flex flex-wrap gap-2">
                <Chip
                    v-for="method in methods"
                    :key="method"
                    :active="form.paymentMethod === method"
                    class="justify-center"
                    @click="form.paymentMethod = method"
                >
                    {{ $t(methodKey[method] ?? 'admin.pmOther') }}
                </Chip>
            </div>
            <p v-if="form.errors.paymentMethod" class="mt-1.5 text-[13px] font-normal text-[var(--danger)]">
                {{ form.errors.paymentMethod }}
            </p>
        </div>

        <div class="mt-6">
            <button
                type="button"
                :disabled="incomplete || form.processing"
                class="w-full rounded-xl bg-[var(--btn-bg)] py-3.5 text-[15px] font-semibold text-white hover:bg-[var(--btn-hover)] disabled:cursor-not-allowed disabled:opacity-60"
                @click="submit"
            >
                {{ form.processing ? $t('common.saving') : `$${total.toFixed(2)} · ${$t('admin.registerSale')}` }}
            </button>
        </div>
    </BottomSheet>
</template>
