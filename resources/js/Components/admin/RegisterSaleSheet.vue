<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Contact, X } from '@lucide/vue';
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

/**
 * The select's escape hatch: the walk-in nobody added to the book yet. Picking
 * it opens a name field and the sale is filed under that name with no link to
 * a card — the ledger snapshots names anyway, so nothing is lost.
 */
const OTHER_CLIENT = 'other';

const form = useForm({
    clientId: null,
    clientName: '',
    clientPhone: '',
    amount: '',
    tip: 0,
    paymentMethod: null,
});

// Same live US mask as ClientFormSheet and the appointment forms.
watch(() => form.clientPhone, (value) => {
    const digits = (value || '').replace(/\D/g, '').replace(/^1(?=\d{10})/, '').slice(0, 10);
    let out = digits;
    if (digits.length > 6) out = `(${digits.slice(0, 3)}) ${digits.slice(3, 6)}-${digits.slice(6)}`;
    else if (digits.length > 3) out = `(${digits.slice(0, 3)}) ${digits.slice(3)}`;
    if (out !== value) form.clientPhone = out;
});

// Contact Picker API: same feature-detected pattern as Clientas — the button
// simply is not there on browsers that lack it (iPhone/desktop today).
const pickerSupported = ref(false);

onMounted(() => {
    pickerSupported.value = 'contacts' in navigator && 'select' in navigator.contacts;
});

async function pickContact() {
    let picked;
    try {
        picked = await navigator.contacts.select(['name', 'tel'], { multiple: false });
    } catch {
        return; // cancelado o denegado — nada que reportar
    }
    const contact = (picked ?? [])[0];
    if (!contact) return;
    form.clientName = (contact.name?.[0] ?? '').trim();
    form.clientPhone = contact.tel?.[0] ?? '';
}

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
    { value: OTHER_CLIENT, label: t('admin.saleClientOther') },
]);

const isOtherClient = computed(() => form.clientId === OTHER_CLIENT);

const methodKey = {
    cash: 'admin.pmCash', card: 'admin.pmCard', zelle: 'admin.pmZelle', cashapp: 'admin.pmCashapp',
    venmo: 'admin.pmVenmo', paypal: 'admin.pmPaypal', check: 'admin.pmCheck', other: 'admin.pmOther',
};

// "Otra persona" without a name is just "Sin clienta" with extra steps, so the
// button waits for it.
const incomplete = computed(() => amountNumber.value <= 0
    || !form.paymentMethod
    || (isOtherClient.value && form.clientName.trim() === ''));

function submit() {
    if (incomplete.value || form.processing) return;

    form.transform((data) => ({
        ...data,
        clientId: isOtherClient.value ? null : data.clientId,
        clientName: isOtherClient.value ? data.clientName.trim() : '',
        clientPhone: isOtherClient.value ? data.clientPhone : '',
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

        <template v-if="isOtherClient">
            <div class="mb-5 flex items-end gap-2">
                <OutlinedInput
                    id="sale-client-name"
                    v-model="form.clientName"
                    class="min-w-0 flex-1"
                    :label="$t('admin.saleClientOtherName')"
                    :error="form.errors.clientName"
                    clearable
                />
                <button
                    v-if="pickerSupported"
                    type="button"
                    :aria-label="$t('admin.saleClientFromContacts')"
                    class="flex h-13.5 w-13.5 shrink-0 items-center justify-center rounded-xl border border-[var(--border-strong)] hover:bg-[var(--surface-mute)]"
                    @click="pickContact"
                >
                    <Contact :size="19" class="text-[var(--text-strong)]" />
                </button>
            </div>

            <OutlinedInput
                id="sale-client-phone"
                v-model="form.clientPhone"
                class="mb-5"
                type="tel"
                inputmode="tel"
                :label="$t('admin.appointmentPhone')"
                :error="form.errors.clientPhone"
            />
            <p class="-mt-4 mb-5 text-[12px] font-normal text-[var(--text-faint)]">{{ $t('admin.saleClientOtherPhoneHint') }}</p>
        </template>

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
