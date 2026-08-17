<script setup>
import { computed, onMounted, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { Plus, Receipt, SlidersHorizontal, Trash2, X } from '@lucide/vue';
import { useI18n } from 'vue-i18n';
import AdminLayout from '../../Layouts/AdminLayout.vue';
import BottomSheet from '../../Components/ui/BottomSheet.vue';
import ConfirmDialog from '../../Components/ui/ConfirmDialog.vue';
import RegisterSaleSheet from '../../Components/admin/RegisterSaleSheet.vue';
import { useFormat } from '../../composables/useFormat';

/**
 * The register tab: today's total on top, the day-by-day ledger below.
 * Records, never charges — see RegisterSaleSheet.
 */
const props = defineProps({
    providerName: { type: String, default: 'Pati' },
    sales: { type: Array, required: true },
    // [{ id, clientName, clientId, amount, tip, paymentMethod, createdAt }]
    acceptedMethods: { type: Array, required: true },
    allMethods: { type: Array, required: true },
    clients: { type: Array, required: true },
    // { clientId, amount } | null — set by the server from ?clientPhone=&amount=
    // on the "Registrar venta" link on a confirmed appointment.
    prefill: { type: Object, default: null },
});

const { t } = useI18n();
const { formatTime, formatDayLabel } = useFormat();

const methodKey = {
    cash: 'admin.pmCash', card: 'admin.pmCard', zelle: 'admin.pmZelle', cashapp: 'admin.pmCashapp',
    venmo: 'admin.pmVenmo', paypal: 'admin.pmPaypal', check: 'admin.pmCheck', other: 'admin.pmOther',
};

const enriched = computed(() =>
    props.sales.map((sale) => {
        const date = new Date(sale.createdAt);
        return {
            ...sale,
            total: Math.round((sale.amount + sale.tip) * 100) / 100,
            dayKey: date.toDateString(),
            timeLabel: formatTime(date.getHours(), date.getMinutes()),
            methodLabel: t(methodKey[sale.paymentMethod] ?? 'admin.pmOther'),
        };
    }),
);

const todayKey = new Date().toDateString();

const todayTotal = computed(() =>
    enriched.value
        .filter((sale) => sale.dayKey === todayKey)
        .reduce((sum, sale) => sum + sale.total, 0),
);

// Newest day first; the server already ordered rows newest first.
const groups = computed(() => {
    const map = new Map();
    for (const sale of enriched.value) {
        if (!map.has(sale.dayKey)) {
            map.set(sale.dayKey, {
                key: sale.dayKey,
                label: sale.dayKey === todayKey ? t('admin.agendaToday') : formatDayLabel(new Date(sale.createdAt)),
                sales: [],
                total: 0,
            });
        }
        const group = map.get(sale.dayKey);
        group.sales.push(sale);
        group.total = Math.round((group.total + sale.total) * 100) / 100;
    }
    return [...map.values()];
});

const registerOpen = ref(false);

onMounted(() => {
    if (props.prefill) registerOpen.value = true;
});

// ---- Accepted methods ------------------------------------------------------

const methodsOpen = ref(false);
const methodsForm = useForm({ methods: [] });

function openMethods() {
    methodsForm.methods = [...props.acceptedMethods];
    methodsForm.clearErrors();
    methodsOpen.value = true;
}

function toggleMethod(method) {
    methodsForm.methods = methodsForm.methods.includes(method)
        ? methodsForm.methods.filter((item) => item !== method)
        : [...methodsForm.methods, method];
}

function saveMethods() {
    if (methodsForm.methods.length === 0 || methodsForm.processing) return;
    methodsForm.put('/admin/ventas/metodos', {
        preserveScroll: true,
        onSuccess: () => {
            methodsOpen.value = false;
        },
    });
}

// ---- Delete ----------------------------------------------------------------

const deleteOpen = ref(false);
const deleteProcessing = ref(false);
const pendingDelete = ref(null);

function askDelete(sale) {
    pendingDelete.value = sale;
    deleteOpen.value = true;
}

function confirmDelete() {
    if (!pendingDelete.value) return;
    deleteProcessing.value = true;
    router.delete(`/admin/ventas/${pendingDelete.value.id}`, {
        preserveScroll: true,
        onFinish: () => {
            deleteProcessing.value = false;
            deleteOpen.value = false;
            pendingDelete.value = null;
        },
    });
}
</script>

<template>
    <AdminLayout :provider-name="providerName">
        <div class="px-4 pt-4">
            <div class="flex items-center justify-between px-1 pb-3">
                <h1 class="text-[22px] font-bold leading-tight tracking-tight text-[var(--text-strong)]">
                    {{ $t('admin.salesTitle') }}
                </h1>
                <button
                    type="button"
                    :aria-label="$t('admin.saleMethodsTitle')"
                    class="flex h-10 w-10 items-center justify-center rounded-full hover:bg-[var(--surface-mute)]"
                    @click="openMethods"
                >
                    <SlidersHorizontal :size="19" class="text-[var(--text-strong)]" />
                </button>
            </div>

            <div class="rounded-2xl border border-[var(--surface-mute)] bg-[var(--surface)] p-4">
                <div class="text-[13px] font-medium text-[var(--text-mute)]">{{ $t('admin.salesTodayTotal') }}</div>
                <div class="mt-0.5 text-[28px] font-bold tabular-nums tracking-tight text-[var(--text-strong)]">
                    ${{ todayTotal.toFixed(2) }}
                </div>
            </div>
        </div>

        <div v-if="sales.length === 0" class="flex flex-col items-center px-8 pb-6 pt-14 text-center">
            <div class="flex h-14 w-14 items-center justify-center rounded-full bg-[var(--surface-mute)]">
                <Receipt :size="24" class="text-[var(--text-faint)]" />
            </div>
            <p class="mt-4 text-base font-bold text-[var(--text-strong)]">{{ $t('admin.salesEmpty') }}</p>
            <p class="mt-1 text-[13px] font-normal text-[var(--text-mute)]">{{ $t('admin.salesEmptyHint') }}</p>
        </div>

        <div v-else class="flex flex-col px-4 pb-4">
            <template v-for="group in groups" :key="group.key">
                <div class="flex items-baseline justify-between px-1 pb-1 pt-5">
                    <span class="text-[13px] font-semibold capitalize text-[var(--text-mute)]">{{ group.label }}</span>
                    <span class="text-[13px] font-semibold tabular-nums text-[var(--text-mute)]">${{ group.total.toFixed(2) }}</span>
                </div>
                <div
                    v-for="sale in group.sales"
                    :key="sale.id"
                    class="flex items-center gap-3 border-b border-[var(--surface-mute)] px-1 py-3 last:border-b-0"
                >
                    <div class="min-w-0 flex-1">
                        <div class="truncate text-[15px] font-semibold text-[var(--text-strong)]">
                            {{ sale.clientName ?? $t('admin.saleClientNone') }}
                        </div>
                        <div class="mt-0.5 text-[13px] font-normal text-[var(--text-mute)]">
                            {{ sale.timeLabel }} · {{ sale.methodLabel }}<template v-if="sale.tip > 0"> · {{ $t('admin.saleTip') }} ${{ sale.tip.toFixed(2) }}</template>
                        </div>
                    </div>
                    <span class="shrink-0 text-[15px] font-bold tabular-nums text-[var(--text-strong)]">
                        ${{ sale.total.toFixed(2) }}
                    </span>
                    <button
                        type="button"
                        :aria-label="$t('admin.saleDeleteTitle')"
                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full hover:bg-[var(--danger-hover)]"
                        @click="askDelete(sale)"
                    >
                        <Trash2 :size="15" class="text-[var(--text-faint)]" />
                    </button>
                </div>
            </template>
        </div>

        <button
            type="button"
            :aria-label="$t('admin.registerSale')"
            class="fixed bottom-24 right-4 z-20 flex h-13 w-13 items-center justify-center rounded-full bg-[var(--btn-bg)] shadow-[0_8px_20px_rgba(0,0,0,0.25)] hover:bg-[var(--btn-hover)] sm:right-[calc(50vw-224px)]"
            @click="registerOpen = true"
        >
            <Plus :size="22" class="text-white" />
        </button>

        <RegisterSaleSheet v-model="registerOpen" :clients="clients" :methods="acceptedMethods" :prefill="prefill" />

        <BottomSheet v-model="methodsOpen">
            <div class="mb-2 flex items-center justify-between">
                <div class="text-[20px] font-bold leading-tight tracking-tight text-[var(--text-strong)]">
                    {{ $t('admin.saleMethodsTitle') }}
                </div>
                <button
                    type="button"
                    :aria-label="$t('common.close')"
                    class="flex h-8 w-8 items-center justify-center rounded-full bg-[var(--surface-mute)] hover:bg-[var(--border-strong)]"
                    @click="methodsOpen = false"
                >
                    <X :size="16" class="text-[var(--text-mute)]" />
                </button>
            </div>
            <p class="mb-4 text-[13px] font-normal text-[var(--text-mute)]">{{ $t('admin.saleMethodsHint') }}</p>
            <label
                v-for="method in allMethods"
                :key="method"
                class="flex cursor-pointer items-center justify-between border-b border-[var(--surface-mute)] py-3.5 last:border-b-0"
            >
                <span class="text-[15px] font-medium text-[var(--text-strong)]">
                    {{ $t({ cash: 'admin.pmCash', card: 'admin.pmCard', zelle: 'admin.pmZelle', cashapp: 'admin.pmCashapp', venmo: 'admin.pmVenmo', paypal: 'admin.pmPaypal', check: 'admin.pmCheck', other: 'admin.pmOther' }[method]) }}
                </span>
                <input
                    type="checkbox"
                    class="h-5 w-5 accent-[var(--btn-bg)]"
                    :checked="methodsForm.methods.includes(method)"
                    @change="toggleMethod(method)"
                />
            </label>
            <p v-if="methodsForm.errors.methods" class="mt-2 text-[13px] font-normal text-[var(--danger)]">
                {{ methodsForm.errors.methods }}
            </p>
            <button
                type="button"
                :disabled="methodsForm.methods.length === 0 || methodsForm.processing"
                class="mt-5 w-full rounded-xl bg-[var(--btn-bg)] py-3.5 text-[15px] font-semibold text-white hover:bg-[var(--btn-hover)] disabled:cursor-not-allowed disabled:opacity-60"
                @click="saveMethods"
            >
                {{ methodsForm.processing ? $t('common.saving') : $t('common.confirm') }}
            </button>
        </BottomSheet>

        <ConfirmDialog
            v-model="deleteOpen"
            :title="$t('admin.saleDeleteTitle')"
            :body="$t('admin.saleDeleteBody')"
            :confirm-label="$t('admin.saleDeleteTitle')"
            :processing="deleteProcessing"
            variant="danger"
            @confirm="confirmDelete"
        />
    </AdminLayout>
</template>
